<?php

namespace Tests\Feature\Auth;

use App\Models\Employee;
use App\Models\Pharmacy;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Security\SecurityTestCase;

class EmployeeAccountTest extends SecurityTestCase
{
    public function test_employee_reads_their_own_safe_profile(): void
    {
        [$pharmacy, $employee] = $this->setUpEmployee('acc-show');
        Sanctum::actingAs($employee, ['*'], 'employee');

        $this->getJson('/api/employee/profile')
            ->assertOk()
            ->assertJsonPath('employee.name', $employee->name)
            ->assertJsonPath('employee.pharmacy_id', $pharmacy->id)
            ->assertJsonMissingPath('employee.password')
            ->assertJsonMissingPath('employee.cv');
    }

    public function test_employee_updates_only_their_name_and_phone(): void
    {
        [, $employee] = $this->setUpEmployee('acc-update');
        Sanctum::actingAs($employee, ['*'], 'employee');

        $this->postJson('/api/employee/profile/update', [
            'name' => 'Updated Employee',
            'phone' => '0999123456',
        ])
            ->assertOk()
            ->assertJsonPath('code', 'employee_profile_updated')
            ->assertJsonPath('employee.name', 'Updated Employee');

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'name' => 'Updated Employee',
            'phone' => '0999123456',
        ]);
    }

    public function test_the_session_reports_the_employees_real_phone_number(): void
    {
        // It used to report null unconditionally, and the account screen seeds
        // its phone field from the session — so opening that screen and pressing
        // save posted a blank and wiped the number. The screen is now the main
        // surface for anyone waiting on a job, which makes this load-bearing.
        [, $employee] = $this->setUpEmployee('acc-session-phone');
        Sanctum::actingAs($employee, ['*'], 'employee');

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.session.actor.phone', $employee->phone);
    }

    public function test_an_omitted_phone_leaves_the_stored_number_alone(): void
    {
        [, $employee] = $this->setUpEmployee('acc-phone-keep');
        $original = $employee->phone;
        Sanctum::actingAs($employee, ['*'], 'employee');

        $this->postJson('/api/employee/profile/update', ['name' => 'Renamed Only'])
            ->assertOk();

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'name' => 'Renamed Only',
            'phone' => $original,
        ]);
    }

    public function test_employee_cannot_modify_tenant_role_or_lifecycle_fields(): void
    {
        [$pharmacy, $employee] = $this->setUpEmployee('acc-prohibited');
        $otherOwner = $this->pharmacist('acc-prohibited-other');
        $otherPharmacy = $this->pharmacy($otherOwner, 'acc-prohibited-other');
        Sanctum::actingAs($employee, ['*'], 'employee');

        $this->postJson('/api/employee/profile/update', [
            'name' => 'Escalate',
            'pharmacy_id' => $otherPharmacy->id,
            'role' => 'trainee',
            'status' => 'approved',
            'salary' => 999999,
            'email' => 'new@example.test',
            'password' => 'hacked-password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors([
                'pharmacy_id', 'role', 'status', 'salary', 'email', 'password',
            ]);

        $fresh = $employee->fresh();
        $this->assertSame($pharmacy->id, $fresh->pharmacy_id);
        $this->assertSame('employee', $fresh->role);
        $this->assertNotSame('Escalate', $fresh->name);
        $this->assertTrue(Hash::check('password', $fresh->password));
    }

    public function test_employee_changes_password_and_other_sessions_are_revoked(): void
    {
        [, $employee] = $this->setUpEmployee('acc-password');
        $employee->createToken('other-device', ['app']);
        $current = $employee->createToken('current', ['app']);

        Sanctum::actingAs($employee, ['*'], 'employee');

        $this->postJson('/api/employee/password/change', [
            'current_password' => 'password',
            'new_password' => 'new-password-1',
            'new_password_confirmation' => 'new-password-1',
        ])
            ->assertOk()
            ->assertJsonPath('code', 'password_changed');

        $this->assertTrue(Hash::check('new-password-1', $employee->fresh()->password));
        // Sanctum::actingAs does not persist a token, so every stored token is revoked.
        $this->assertSame(0, PersonalAccessToken::where('tokenable_id', $employee->id)->count());
        unset($current);
    }

    public function test_password_change_requires_the_correct_current_password(): void
    {
        [, $employee] = $this->setUpEmployee('acc-wrong');
        Sanctum::actingAs($employee, ['*'], 'employee');

        $this->postJson('/api/employee/password/change', [
            'current_password' => 'wrong-password',
            'new_password' => 'new-password-1',
            'new_password_confirmation' => 'new-password-1',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'current_password_incorrect');

        $this->assertTrue(Hash::check('password', $employee->fresh()->password));
    }

    public function test_password_change_validates_confirmation_and_minimum_length(): void
    {
        [, $employee] = $this->setUpEmployee('acc-validation');
        Sanctum::actingAs($employee, ['*'], 'employee');

        $this->postJson('/api/employee/password/change', [
            'current_password' => 'password',
            'new_password' => 'short',
            'new_password_confirmation' => 'short',
        ])->assertUnprocessable()->assertJsonValidationErrors('new_password');

        $this->postJson('/api/employee/password/change', [
            'current_password' => 'password',
            'new_password' => 'new-password-1',
        ])->assertUnprocessable()->assertJsonValidationErrors('new_password');
    }

    public function test_pharmacist_cannot_use_the_employee_account_routes(): void
    {
        $owner = $this->pharmacist('acc-pharmacist');
        $this->pharmacy($owner, 'acc-pharmacist');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->getJson('/api/employee/profile')->assertUnauthorized();
        $this->postJson('/api/employee/profile/update', ['name' => 'X'])->assertUnauthorized();
        $this->postJson('/api/employee/password/change', [
            'current_password' => 'password',
            'new_password' => 'new-password-1',
            'new_password_confirmation' => 'new-password-1',
        ])->assertUnauthorized();
    }

    /** @return array{0:Pharmacy,1:Employee} */
    private function setUpEmployee(string $suffix): array
    {
        $owner = $this->pharmacist($suffix);
        $pharmacy = $this->pharmacy($owner, $suffix);
        $employee = $this->employee($pharmacy, substr(md5($suffix), 0, 3));

        return [$pharmacy, $employee];
    }
}
