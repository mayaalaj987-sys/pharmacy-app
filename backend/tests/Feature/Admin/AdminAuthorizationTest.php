<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Employee;
use App\Models\Pharmacist;

class AdminAuthorizationTest extends AdminTestCase
{
    public function test_reviewer_can_review_but_cannot_manage_admins(): void
    {
        $reviewer = $this->admin('reviewer', Admin::ROLE_PHARMACY_REVIEWER);
        $this->pendingPharmacy('matrix');

        $this->asAdmin($reviewer)->getJson('/api/admin/review/applications')->assertOk();
        $this->asAdmin($reviewer)->getJson('/api/admin/admins')->assertForbidden()->assertJsonPath('code', 'forbidden');
        $this->assertDatabaseHas('admin_audit_logs', ['admin_id' => $reviewer->id, 'action' => 'admin.accounts.index', 'outcome' => 'denied']);
        $this->asAdmin($reviewer)->postJson('/api/admin/admins', [
            'name' => 'Attempted Admin', 'email' => 'attempt@example.test',
            'password' => 'Strong!Password123', 'password_confirmation' => 'Strong!Password123',
            'role' => Admin::ROLE_SUPER_ADMIN,
        ])->assertForbidden();
    }

    public function test_super_admin_can_manage_supported_accounts_without_mass_assignment_escalation(): void
    {
        $super = $this->admin('super');
        $response = $this->asAdmin($super)->postJson('/api/admin/admins', [
            'name' => 'New Reviewer',
            'email' => 'NEW-REVIEWER@EXAMPLE.TEST',
            'password' => 'Another!Strong123',
            'password_confirmation' => 'Another!Strong123',
            'role' => Admin::ROLE_PHARMACY_REVIEWER,
            'is_active' => false,
            'auth_version' => 999,
        ])->assertCreated()
            ->assertJsonPath('code', 'admin_created')
            ->assertJsonPath('data.role', Admin::ROLE_PHARMACY_REVIEWER)
            ->assertJsonPath('data.is_active', true);

        $created = Admin::where('email', 'new-reviewer@example.test')->firstOrFail();
        $this->assertSame(1, $created->auth_version);
        $response->assertJsonMissingPath('data.password');
        $this->asAdmin($super)->patchJson('/api/admin/admins/'.$created->public_id.'/role', ['role' => Admin::ROLE_SUPER_ADMIN])
            ->assertOk()->assertJsonPath('data.role', Admin::ROLE_SUPER_ADMIN);
    }

    public function test_last_active_super_admin_cannot_be_disabled_or_demoted(): void
    {
        $super = $this->admin('last-super');
        $this->asAdmin($super)->postJson('/api/admin/admins/'.$super->public_id.'/disable')
            ->assertStatus(409)->assertJsonPath('code', 'last_active_super_admin');
        $this->asAdmin($super)->patchJson('/api/admin/admins/'.$super->public_id.'/role', ['role' => Admin::ROLE_PHARMACY_REVIEWER])
            ->assertStatus(409)->assertJsonPath('code', 'last_active_super_admin');
    }

    public function test_admin_guard_is_strictly_separate_from_application_guards(): void
    {
        $admin = $this->admin('separate');
        $this->asAdmin($admin)->getJson('/api/me')->assertUnauthorized();
        $this->asAdmin($admin)->getJson('/api/employee/documents/999/download')->assertUnauthorized();

        $pharmacist = Pharmacist::create([
            'name' => 'Mobile User', 'email' => 'mobile@example.test', 'password' => 'Strong!Password123',
        ]);
        $token = $pharmacist->createToken('app', ['app'])->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/admin/review/applications')->assertUnauthorized();

        $this->assertDatabaseCount((new Employee)->getTable(), 0);
    }
}
