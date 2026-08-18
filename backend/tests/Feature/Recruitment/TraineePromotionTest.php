<?php

namespace Tests\Feature\Recruitment;

use App\Models\Employee;
use App\Models\Notification;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Security\SecurityTestCase;

/**
 * A trainee becoming an employee.
 *
 * Somebody who trains for a year and gains real experience should not be
 * listed forever as a trainee. Only a pharmacy that employed them can say so:
 * the administrator never watched them work, and `role` is prohibited on
 * profile updates precisely so nobody can promote themselves.
 */
class TraineePromotionTest extends SecurityTestCase
{
    public function test_a_pharmacy_can_confirm_the_training_of_someone_it_employed(): void
    {
        [$owner, $pharmacy] = $this->hiringOwner('promote-basic');
        $trainee = $this->applicant('promote-basic', Employee::ROLE_TRAINEE);
        $this->hire($owner, $pharmacy, $trainee)->assertOk();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->postJson('/api/employees/'.$trainee->id.'/promote')
            ->assertOk()
            ->assertJsonPath('code', 'employee_promoted')
            ->assertJsonPath('employee.role', 'employee');

        $this->assertSame(Employee::ROLE_EMPLOYEE, $trainee->fresh()->role);
        $this->assertSame(1, Notification::where('employee_id', $trainee->id)
            ->where('type', Notification::TYPE_ROLE_PROMOTED)->count());
    }

    public function test_a_past_employer_can_still_vouch_for_them(): void
    {
        // The person who finishes a placement and moves on is exactly who has
        // earned this. Refusing once the job ended would make the training
        // itself worthless.
        [$owner, $pharmacy] = $this->hiringOwner('promote-past');
        $trainee = $this->applicant('promote-past', Employee::ROLE_TRAINEE);
        $this->hire($owner, $pharmacy, $trainee)->assertOk();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($trainee->fresh(), ['*'], 'employee');
        $this->postJson('/api/employee/resign')->assertOk();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/employees/'.$trainee->id.'/promote')->assertOk();

        $this->assertSame(Employee::ROLE_EMPLOYEE, $trainee->fresh()->role);
    }

    public function test_a_pharmacy_that_never_employed_them_cannot(): void
    {
        [, $pharmacy] = $this->hiringOwner('promote-stranger-host');
        $trainee = $this->applicant('promote-stranger', Employee::ROLE_TRAINEE);
        [$outsider] = $this->hiringOwner('promote-stranger-outsider');
        $this->assertTrue($pharmacy->exists);

        Sanctum::actingAs($outsider, ['*'], 'pharmacist');
        $this->postJson('/api/employees/'.$trainee->id.'/promote')
            ->assertNotFound()
            ->assertJsonPath('code', 'employee_not_found');

        $this->assertSame(Employee::ROLE_TRAINEE, $trainee->fresh()->role);
    }

    public function test_promoting_an_employee_is_refused(): void
    {
        [$owner, $pharmacy] = $this->hiringOwner('promote-twice');
        $employee = $this->applicant('promote-twice', Employee::ROLE_EMPLOYEE);
        $this->hire($owner, $pharmacy, $employee)->assertOk();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/employees/'.$employee->id.'/promote')
            ->assertStatus(409)
            ->assertJsonPath('code', 'already_an_employee');
    }

    public function test_a_trainee_cannot_promote_themselves(): void
    {
        // The reason 'role' is prohibited on the profile route, restated here
        // so a future relaxation of that rule fails loudly.
        $trainee = $this->applicant('promote-self', Employee::ROLE_TRAINEE);
        Sanctum::actingAs($trainee, ['*'], 'employee');

        $this->postJson('/api/employee/profile/update', [
            'name' => 'Still A Trainee',
            'role' => Employee::ROLE_EMPLOYEE,
        ])->assertUnprocessable()->assertJsonValidationErrors('role');

        $this->postJson('/api/employees/'.$trainee->id.'/promote')->assertUnauthorized();
        $this->assertSame(Employee::ROLE_TRAINEE, $trainee->fresh()->role);
    }

    public function test_a_promoted_trainee_appears_as_an_employee_in_the_pool(): void
    {
        [$owner, $pharmacy] = $this->hiringOwner('promote-pool');
        $trainee = $this->applicant('promote-pool', Employee::ROLE_TRAINEE);
        $this->hire($owner, $pharmacy, $trainee)->assertOk();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/employees/'.$trainee->id.'/promote')->assertOk();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($trainee->fresh(), ['*'], 'employee');
        $this->postJson('/api/employee/resign')->assertOk();

        // Back in the pool, now presented as somebody with experience.
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->getJson('/api/employees/pending')
            ->assertOk()
            ->assertJsonPath('employees.0.role', 'employee');
    }

    /** @return array{0: Pharmacist, 1: Pharmacy} */
    private function hiringOwner(string $suffix): array
    {
        $owner = $this->pharmacist($suffix);

        return [$owner, $this->pharmacy($owner, $suffix)];
    }

    private function applicant(string $suffix, string $role): Employee
    {
        return Employee::create([
            'pharmacy_id' => null,
            'name' => 'Applicant '.$suffix,
            'phone' => '0966'.substr(md5($suffix), 0, 6),
            'email' => 'applicant-'.$suffix.'@example.test',
            'password' => Hash::make('password123'),
            'cv' => '',
            'role' => $role,
            'status' => Employee::STATUS_PENDING,
            'first_login' => true,
        ]);
    }
}
