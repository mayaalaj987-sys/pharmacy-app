<?php

namespace Tests\Feature\Auth;

use App\Models\Employee;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthenticationSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_pharmacist_login_returns_one_approved_pharmacy_as_active(): void
    {
        $pharmacist = $this->pharmacist('one');
        $approved = $this->pharmacy($pharmacist, 'approved', 'approved');

        $this->postJson('/api/login', $this->credentials($pharmacist))
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.session.actor.type', 'pharmacist')
            ->assertJsonPath('data.session.active_pharmacy.id', $approved->id)
            ->assertJsonPath('data.session.access.code', 'ready')
            ->assertJsonPath('data.session.access.operational', true);
    }

    public function test_pharmacist_login_with_multiple_approved_pharmacies_requires_selection(): void
    {
        $pharmacist = $this->pharmacist('multiple');
        $this->pharmacy($pharmacist, 'one', 'approved');
        $this->pharmacy($pharmacist, 'two', 'approved');

        $this->postJson('/api/login', $this->credentials($pharmacist))
            ->assertOk()
            ->assertJsonCount(2, 'data.session.available_pharmacies')
            ->assertJsonPath('data.session.active_pharmacy', null)
            ->assertJsonPath('data.session.access.code', 'active_pharmacy_required')
            ->assertJsonPath('data.session.access.requires_active_pharmacy', true);
    }

    public function test_rejected_pharmacy_does_not_block_an_approved_pharmacy(): void
    {
        $pharmacist = $this->pharmacist('mixed');
        $this->pharmacy($pharmacist, 'rejected', 'rejected');
        $approved = $this->pharmacy($pharmacist, 'approved', 'approved');
        $this->pharmacy($pharmacist, 'pending', 'pending');

        $this->postJson('/api/login', $this->credentials($pharmacist))
            ->assertOk()
            ->assertJsonCount(3, 'data.session.available_pharmacies')
            ->assertJsonPath('data.session.active_pharmacy.id', $approved->id)
            ->assertJsonPath('data.session.access.code', 'ready');
    }

    public function test_owner_without_approved_pharmacy_is_denied_before_token_creation(): void
    {
        $pharmacist = $this->pharmacist('review');
        $this->pharmacy($pharmacist, 'pending', 'pending');
        $this->pharmacy($pharmacist, 'rejected', 'rejected');

        $this->postJson('/api/login', $this->credentials($pharmacist))
            ->assertForbidden()
            ->assertJsonPath('code', 'pharmacy_review_required');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_employee_and_trainee_login_return_normalized_sessions(): void
    {
        $owner = $this->pharmacist('employee-owner');
        $pharmacy = $this->pharmacy($owner, 'employee', 'approved');
        $employee = $this->employee($pharmacy, 'employee', 'employee', 'approved');
        $trainee = $this->employee($pharmacy, 'trainee', 'trainee', 'approved');

        $this->postJson('/api/employee/login', $this->credentials($employee))
            ->assertOk()
            ->assertJsonPath('data.session.actor.type', 'employee')
            ->assertJsonPath('data.session.actor.role', 'employee')
            ->assertJsonPath('data.session.active_pharmacy.id', $pharmacy->id);

        $this->postJson('/api/employee/login', $this->credentials($trainee))
            ->assertOk()
            ->assertJsonPath('data.session.actor.role', 'trainee')
            ->assertJsonPath('data.session.access.code', 'ready');
    }

    public function test_pending_employee_receives_authenticated_non_operational_session(): void
    {
        $employee = Employee::create([
            'name' => 'Pending Employee',
            'phone' => '0999000000',
            'email' => 'pending@example.test',
            'password' => Hash::make('password'),
            'cv' => 'cv.pdf',
            'role' => 'employee',
            'status' => 'pending',
            'first_login' => true,
        ]);

        $this->postJson('/api/employee/login', $this->credentials($employee))
            ->assertOk()
            ->assertJsonPath('data.session.actor.status', 'pending')
            ->assertJsonPath('data.session.access.code', 'account_pending')
            ->assertJsonPath('data.session.access.operational', false);

        $this->assertTrue($employee->fresh()->first_login);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }

    public function test_authenticated_me_returns_session_and_valid_selected_pharmacy(): void
    {
        $pharmacist = $this->pharmacist('me');
        $this->pharmacy($pharmacist, 'one', 'approved');
        $selected = $this->pharmacy($pharmacist, 'two', 'approved');
        $token = $pharmacist->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->withHeader('X-Pharmacy-Id', (string) $selected->id)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.session.active_pharmacy.id', $selected->id)
            ->assertJsonPath('data.session.access.code', 'ready');
    }

    public function test_me_recovers_from_owned_rejected_or_missing_active_pharmacy(): void
    {
        $pharmacist = $this->pharmacist('stale');
        $this->pharmacy($pharmacist, 'approved-one', 'approved');
        $this->pharmacy($pharmacist, 'approved-two', 'approved');
        $rejected = $this->pharmacy($pharmacist, 'rejected', 'rejected');
        $token = $pharmacist->createToken('test')->plainTextToken;

        foreach ([$rejected->id, 999999] as $staleId) {
            $this->withToken($token)
                ->withHeader('X-Pharmacy-Id', (string) $staleId)
                ->getJson('/api/me')
                ->assertOk()
                ->assertJsonPath('data.session.active_pharmacy', null)
                ->assertJsonPath('data.session.access.code', 'stale_active_pharmacy');
        }
    }

    public function test_me_strictly_rejects_another_owners_pharmacy(): void
    {
        $pharmacist = $this->pharmacist('owner');
        $this->pharmacy($pharmacist, 'owner', 'approved');
        $other = $this->pharmacist('other');
        $foreign = $this->pharmacy($other, 'foreign', 'approved');
        $token = $pharmacist->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->withHeader('X-Pharmacy-Id', (string) $foreign->id)
            ->getJson('/api/me')
            ->assertForbidden()
            ->assertJsonPath('code', 'active_pharmacy_forbidden');
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $pharmacist = $this->pharmacist('logout');
        $this->pharmacy($pharmacist, 'logout', 'approved');
        $current = $pharmacist->createToken('current')->plainTextToken;
        $other = $pharmacist->createToken('other')->plainTextToken;

        $this->assertSame(2, PersonalAccessToken::count());

        $this->withToken($current)
            ->postJson('/api/logout')
            ->assertOk();

        $this->assertSame(1, PersonalAccessToken::count());
        $this->app['auth']->forgetGuards();
        $this->withToken($current)->getJson('/api/me')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($other)->getJson('/api/me')->assertOk();
    }

    public function test_employee_can_use_the_shared_logout_route(): void
    {
        $owner = $this->pharmacist('employee-logout-owner');
        $pharmacy = $this->pharmacy($owner, 'employee-logout', 'approved');
        $employee = $this->employee($pharmacy, 'logout', 'employee', 'approved');
        $token = $employee->createToken('employee-current')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    private function pharmacist(string $suffix): Pharmacist
    {
        return Pharmacist::create([
            'name' => 'Pharmacist '.$suffix,
            'email' => 'pharmacist-'.$suffix.'@example.test',
            'password' => Hash::make('password'),
        ]);
    }

    private function pharmacy(Pharmacist $pharmacist, string $suffix, string $status): Pharmacy
    {
        return Pharmacy::create([
            'pharmacist_id' => $pharmacist->id,
            'pharmacy_name' => 'Pharmacy '.$suffix,
            'pharmacy_address' => 'Address '.$suffix,
            'certificate' => 'certificate.pdf',
            'license' => 'license.pdf',
            'status' => $status,
        ]);
    }

    private function employee(Pharmacy $pharmacy, string $suffix, string $role, string $status): Employee
    {
        return Employee::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => 'Employee '.$suffix,
            'phone' => '0999'.$pharmacy->id.$suffix,
            'email' => 'employee-'.$suffix.'@example.test',
            'password' => Hash::make('password'),
            'cv' => 'cv.pdf',
            'role' => $role,
            'status' => $status,
            'first_login' => false,
        ]);
    }

    private function credentials(Pharmacist|Employee $actor): array
    {
        return ['email' => $actor->email, 'password' => 'password'];
    }
}
