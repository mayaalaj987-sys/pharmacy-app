<?php

namespace Tests\Feature\Recruitment;

use App\Models\Employee;
use App\Models\Employment;
use App\Models\JobOffer;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Security\SecurityTestCase;

/**
 * The record that a job happened.
 *
 * Nothing kept one. Somebody who worked three years and left went back to
 * `status = pending`, indistinguishable in the data from a graduate who
 * registered this morning — no start, no end, and no record of who ended it.
 */
class EmploymentHistoryTest extends SecurityTestCase
{
    public function test_accepting_opens_an_employment_and_leaving_closes_it(): void
    {
        [$owner, $pharmacy] = $this->hiringOwner('hist-basic');
        $employee = $this->applicant('hist-basic');

        $this->hire($owner, $pharmacy, $employee, Employee::SHIFT_EVENING, 400000)->assertOk();

        $running = Employment::where('employee_id', $employee->id)->sole();
        $this->assertTrue($running->isRunning());
        $this->assertSame('evening', $running->shift);
        $this->assertSame(400000.0, $running->salary);
        $this->assertSame($pharmacy->id, (int) $running->pharmacy_id);

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($employee->fresh(), ['*'], 'employee');
        $this->postJson('/api/employee/resign')->assertOk();

        $finished = $running->fresh();
        $this->assertFalse($finished->isRunning());
        $this->assertNotNull($finished->ended_at);
        $this->assertSame(Employment::ENDED_BY_EMPLOYEE, $finished->ended_by);

        // The terms survive even though the employee row was cleared.
        $this->assertSame('evening', $finished->shift);
        $this->assertSame(400000.0, $finished->salary);
        $this->assertNull($employee->fresh()->shift);
    }

    public function test_a_dismissal_records_who_ended_it(): void
    {
        [$owner, $pharmacy] = $this->hiringOwner('hist-dismiss');
        $employee = $this->applicant('hist-dismiss');
        $this->hire($owner, $pharmacy, $employee)->assertOk();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->deleteJson('/api/employees/'.$employee->id.'/dismiss')->assertOk();

        // Resigned or let go is the difference between two very different
        // histories, and the notification that said which was gone by morning.
        $this->assertSame(
            Employment::ENDED_BY_PHARMACY,
            Employment::where('employee_id', $employee->id)->sole()->ended_by,
        );
    }

    public function test_a_pharmacy_can_hire_someone_back(): void
    {
        // This was impossible. The accepted offer survives the job ending, and
        // sendOffer refused whenever one existed — so a pharmacy could never
        // re-hire anyone who had worked there, however they left.
        [$owner, $pharmacy] = $this->hiringOwner('hist-rehire');
        $employee = $this->applicant('hist-rehire');

        $this->hire($owner, $pharmacy, $employee, Employee::SHIFT_MORNING)->assertOk();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($employee->fresh(), ['*'], 'employee');
        $this->postJson('/api/employee/resign')->assertOk();

        $this->app['auth']->forgetGuards();
        $this->hire($owner, $pharmacy, $employee->fresh(), Employee::SHIFT_EVENING)->assertOk();

        $this->assertSame($pharmacy->id, (int) $employee->fresh()->pharmacy_id);
        $this->assertSame('evening', $employee->fresh()->shift);

        // Two separate jobs, not one row overwritten: the first is closed and
        // keeps the shift it was actually worked on.
        $history = Employment::where('employee_id', $employee->id)->orderBy('id')->get();
        $this->assertCount(2, $history);
        $this->assertSame('morning', $history->first()->shift);
        $this->assertNotNull($history->first()->ended_at);
        $this->assertSame('evening', $history->last()->shift);
        $this->assertTrue($history->last()->isRunning());

        // The offer row was reused rather than duplicated.
        $this->assertSame(1, JobOffer::where('employee_id', $employee->id)->count());
    }

    public function test_someone_who_still_works_here_cannot_be_offered_again(): void
    {
        [$owner, $pharmacy] = $this->hiringOwner('hist-current');
        $employee = $this->applicant('hist-current');
        $this->hire($owner, $pharmacy, $employee)->assertOk();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->postJson('/api/recruitment/offers', [
            'employee_id' => $employee->id,
            'shift' => Employee::SHIFT_EVENING,
        ])
            ->assertStatus(409)
            // Refused because they are already here, not because of a stale row.
            ->assertJsonPath('code', 'employee_not_available');
    }

    public function test_history_survives_moving_between_pharmacies(): void
    {
        [$firstOwner, $firstPharmacy] = $this->hiringOwner('hist-move-a');
        [$secondOwner, $secondPharmacy] = $this->hiringOwner('hist-move-b');
        $employee = $this->applicant('hist-move');

        $this->hire($firstOwner, $firstPharmacy, $employee, Employee::SHIFT_MORNING)->assertOk();
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($employee->fresh(), ['*'], 'employee');
        $this->postJson('/api/employee/resign')->assertOk();

        $this->app['auth']->forgetGuards();
        $this->hire($secondOwner, $secondPharmacy, $employee->fresh(), Employee::SHIFT_EVENING)->assertOk();

        $history = Employment::where('employee_id', $employee->id)->orderBy('id')->get();
        $this->assertCount(2, $history);
        $this->assertSame($firstPharmacy->id, (int) $history->first()->pharmacy_id);
        $this->assertSame($secondPharmacy->id, (int) $history->last()->pharmacy_id);
    }

    public function test_ending_one_job_does_not_close_another_persons(): void
    {
        [$owner, $pharmacy] = $this->hiringOwner('hist-colleague');
        $leaver = $this->applicant('hist-colleague-a');
        $stayer = $this->applicant('hist-colleague-b');

        $this->hire($owner, $pharmacy, $leaver, Employee::SHIFT_MORNING)->assertOk();
        $this->app['auth']->forgetGuards();
        $this->hire($owner, $pharmacy, $stayer, Employee::SHIFT_EVENING)->assertOk();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($leaver->fresh(), ['*'], 'employee');
        $this->postJson('/api/employee/resign')->assertOk();

        $this->assertNotNull(Employment::where('employee_id', $leaver->id)->sole()->ended_at);
        $this->assertNull(Employment::where('employee_id', $stayer->id)->sole()->ended_at);
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
            'phone' => '0977'.substr(md5($suffix), 0, 6),
            'email' => 'applicant-'.$suffix.'@example.test',
            'password' => Hash::make('password123'),
            'cv' => '',
            'role' => Employee::ROLE_EMPLOYEE,
            'status' => Employee::STATUS_PENDING,
            'first_login' => true,
        ]);
    }
}
