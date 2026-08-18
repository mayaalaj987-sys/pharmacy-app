<?php

namespace Tests\Feature\Recruitment;

use App\Models\Employee;
use App\Models\Employment;
use App\Models\EmploymentRating;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Security\SecurityTestCase;

/**
 * Each side's verdict on a job that is over.
 *
 * Attached to an employment rather than to a pharmacy or a person: you can only
 * rate a job you actually held, the period being judged is known, and neither
 * side can rate a stranger.
 */
class EmploymentRatingTest extends SecurityTestCase
{
    public function test_both_sides_can_rate_the_job_once_it_has_ended(): void
    {
        [$owner, $pharmacy, $employee] = $this->finishedJob('rate-both');

        $employment = Employment::sole();

        Sanctum::actingAs($employee->fresh(), ['*'], 'employee');
        $this->postJson('/api/employee/employments/'.$employment->id.'/rate', ['stars' => 4])
            ->assertOk()
            ->assertJsonPath('code', 'rating_recorded');

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/employees/employments/'.$employment->id.'/rate', ['stars' => 5])
            ->assertOk();

        // The same job carries both verdicts, told apart by direction.
        $this->assertSame(2, EmploymentRating::count());
        $this->assertSame(4.0, $pharmacy->fresh()->staffRating()['average']);
        $this->assertSame(5.0, $employee->fresh()->workRating()['average']);
    }

    public function test_a_running_job_cannot_be_rated_yet(): void
    {
        // Rating an employer while still working for them is not a free
        // judgement, and the period being rated has to be a finished one.
        [$owner, , $employee] = $this->runningJob('rate-running');
        $employment = Employment::sole();

        Sanctum::actingAs($employee->fresh(), ['*'], 'employee');
        $this->postJson('/api/employee/employments/'.$employment->id.'/rate', ['stars' => 1])
            ->assertStatus(409)
            ->assertJsonPath('code', 'employment_still_running');

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/employees/employments/'.$employment->id.'/rate', ['stars' => 5])
            ->assertStatus(409);

        $this->assertSame(0, EmploymentRating::count());
    }

    public function test_rating_again_replaces_rather_than_stacks(): void
    {
        // Otherwise either side could weight the average by simply repeating
        // themselves.
        [, $pharmacy, $employee] = $this->finishedJob('rate-again');
        $employment = Employment::sole();

        Sanctum::actingAs($employee->fresh(), ['*'], 'employee');
        $this->postJson('/api/employee/employments/'.$employment->id.'/rate', ['stars' => 1])->assertOk();
        $this->postJson('/api/employee/employments/'.$employment->id.'/rate', ['stars' => 5])->assertOk();

        $this->assertSame(1, EmploymentRating::count());
        $this->assertSame(5.0, $pharmacy->fresh()->staffRating()['average']);
        $this->assertSame(1, $pharmacy->fresh()->staffRating()['count']);
    }

    public function test_nobody_can_rate_a_job_that_was_not_theirs(): void
    {
        [, , $employee] = $this->finishedJob('rate-mine');
        [$outsider, $outsiderPharmacy] = $this->hiringOwner('rate-outsider');
        $stranger = $this->applicant('rate-stranger');
        $this->assertTrue($outsiderPharmacy->exists);

        $employment = Employment::sole();

        // A pharmacy that never employed them.
        Sanctum::actingAs($outsider, ['*'], 'pharmacist');
        $this->postJson('/api/employees/employments/'.$employment->id.'/rate', ['stars' => 1])
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');

        // Someone who never held the job.
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($stranger, ['*'], 'employee');
        $this->postJson('/api/employee/employments/'.$employment->id.'/rate', ['stars' => 1])
            ->assertNotFound();

        $this->assertSame(0, EmploymentRating::count());
        $this->assertTrue($employee->exists);
    }

    public function test_an_average_never_reveals_who_said_what(): void
    {
        [$owner, $pharmacy, $employee] = $this->finishedJob('rate-anon');
        $employment = Employment::sole();

        Sanctum::actingAs($employee->fresh(), ['*'], 'employee');
        $this->postJson('/api/employee/employments/'.$employment->id.'/rate', ['stars' => 2])->assertOk();

        // The owner sees the number and nothing else. With two staff, a name
        // beside a low rating would make giving one unsurvivable.
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $history = $this->getJson('/api/employees/history')->assertOk();

        $this->assertStringNotContainsString('"stars"', $history->getContent());
        $history->assertJsonPath('employments.0.my_rating', null);

        $summary = $pharmacy->fresh()->staffRating();
        $this->assertSame(2.0, $summary['average']);
        $this->assertSame(1, $summary['count']);
    }

    public function test_a_job_seeker_carries_their_rating_into_the_pool(): void
    {
        [$owner, , $employee] = $this->finishedJob('rate-pool');
        $employment = Employment::sole();

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/employees/employments/'.$employment->id.'/rate', ['stars' => 5])->assertOk();

        // A name and a CV say what someone claims; this says how it went for
        // the people who found out.
        $this->getJson('/api/employees/pending')
            ->assertOk()
            ->assertJsonPath('employees.0.id', $employee->id)
            // JSON renders a whole-number average as an int, so the assertion
            // matches what the client actually receives.
            ->assertJsonPath('employees.0.rating.average', 5)
            ->assertJsonPath('employees.0.rating.count', 1);
    }

    public function test_an_offer_carries_the_pharmacys_rating(): void
    {
        [$owner, $pharmacy, $employee] = $this->finishedJob('rate-offer');
        $employment = Employment::sole();

        Sanctum::actingAs($employee->fresh(), ['*'], 'employee');
        $this->postJson('/api/employee/employments/'.$employment->id.'/rate', ['stars' => 3])->assertOk();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        // 200, not 201: this pharmacy already holds an offer row for them from
        // the job that just ended, so re-offering edits it.
        $this->postJson('/api/recruitment/offers', [
            'employee_id' => $employee->id,
            'shift' => Employee::SHIFT_EVENING,
        ])->assertOk()->assertJsonPath('code', 'offer_updated');

        // Deciding whether to take a job on the salary alone is what this
        // replaces.
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($employee->fresh(), ['*'], 'employee');
        $this->getJson('/api/employee/offers')
            ->assertOk()
            ->assertJsonPath('offers.0.pharmacy.rating.average', 3);

        $this->assertTrue($pharmacy->exists);
    }

    public function test_an_unrated_party_reports_no_average_rather_than_zero(): void
    {
        // Zero stars and "nobody has said" are different things, and showing a
        // new pharmacy as 0.0 would be a verdict nobody gave.
        [, $pharmacy, $employee] = $this->finishedJob('rate-none');

        $this->assertNull($pharmacy->fresh()->staffRating()['average']);
        $this->assertSame(0, $pharmacy->fresh()->staffRating()['count']);
        $this->assertNull($employee->fresh()->workRating()['average']);
    }

    public function test_stars_outside_one_to_five_are_rejected(): void
    {
        [, , $employee] = $this->finishedJob('rate-range');
        $employment = Employment::sole();

        Sanctum::actingAs($employee->fresh(), ['*'], 'employee');

        foreach ([0, 6, -1] as $stars) {
            $this->postJson('/api/employee/employments/'.$employment->id.'/rate', ['stars' => $stars])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('stars');
        }

        $this->assertSame(0, EmploymentRating::count());
    }

    public function test_the_history_screens_show_each_side_their_own_verdict(): void
    {
        [$owner, , $employee] = $this->finishedJob('rate-history');
        $employment = Employment::sole();

        Sanctum::actingAs($employee->fresh(), ['*'], 'employee');
        $this->postJson('/api/employee/employments/'.$employment->id.'/rate', ['stars' => 4])->assertOk();

        $this->getJson('/api/employee/employments')
            ->assertOk()
            ->assertJsonPath('employments.0.can_rate', true)
            ->assertJsonPath('employments.0.my_rating', 4)
            ->assertJsonPath('employments.0.ended_by', Employment::ENDED_BY_EMPLOYEE);

        // The pharmacy's own screen shows its verdict, not the employee's.
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->getJson('/api/employees/history')
            ->assertOk()
            ->assertJsonPath('employments.0.my_rating', null)
            ->assertJsonPath('employments.0.can_rate', true);
    }

    /** @return array{0: Pharmacist, 1: Pharmacy, 2: Employee} */
    private function finishedJob(string $suffix): array
    {
        [$owner, $pharmacy, $employee] = $this->runningJob($suffix);

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($employee->fresh(), ['*'], 'employee');
        $this->postJson('/api/employee/resign')->assertOk();

        return [$owner, $pharmacy, $employee];
    }

    /** @return array{0: Pharmacist, 1: Pharmacy, 2: Employee} */
    private function runningJob(string $suffix): array
    {
        [$owner, $pharmacy] = $this->hiringOwner($suffix);
        $employee = $this->applicant($suffix);
        $this->hire($owner, $pharmacy, $employee, Employee::SHIFT_MORNING, 400000)->assertOk();

        return [$owner, $pharmacy, $employee];
    }

    /** @return array{0: Pharmacist, 1: Pharmacy} */
    private function hiringOwner(string $suffix): array
    {
        $owner = $this->pharmacist($suffix);

        return [$owner, $this->pharmacy($owner, $suffix)];
    }

    private function applicant(string $suffix): Employee
    {
        return Employee::create([
            'pharmacy_id' => null,
            'name' => 'Applicant '.$suffix,
            'phone' => '0988'.substr(md5($suffix), 0, 6),
            'email' => 'applicant-'.$suffix.'@example.test',
            'password' => Hash::make('password123'),
            'cv' => '',
            'role' => Employee::ROLE_EMPLOYEE,
            'status' => Employee::STATUS_PENDING,
            'first_login' => true,
        ]);
    }
}
