<?php

namespace Tests\Feature\Admin;

use App\Exceptions\AdminWorkflowException;
use App\Models\Admin;
use App\Services\AdminAccountService;
use App\Services\AdminAuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminAuditAndCommandsTest extends AdminTestCase
{
    public function test_audit_records_are_redacted_and_immutable_through_models_and_database_guards(): void
    {
        $admin = $this->admin('audit');
        $request = Request::create('/api/admin/test', 'POST', server: [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => str_repeat('A', 700),
            'HTTP_AUTHORIZATION' => 'Bearer should-never-be-read',
        ]);
        $entry = app(AdminAuditLogger::class)->record(
            $request,
            $admin,
            'admin.test.audit',
            'success',
            'admin',
            $admin->id,
            before: ['role' => 'super_admin', 'password' => 'redact-me', 'storage_path' => 'redact-me'],
            after: ['is_active' => true, 'session_token' => 'redact-me'],
        );
        $raw = DB::table('admin_audit_logs')->where('id', $entry->id)->first();
        $this->assertStringNotContainsString('redact-me', (string) $raw->before_state.(string) $raw->after_state);
        $this->assertLessThanOrEqual(512, strlen($entry->user_agent));

        try {
            $entry->forceFill(['reason' => 'changed'])->save();
            $this->fail('Model updates must be rejected.');
        } catch (\LogicException) {
            $this->assertTrue(true);
        }
        try {
            DB::table('admin_audit_logs')->where('id', $entry->id)->update(['reason' => 'changed']);
            $this->fail('Database updates must be rejected.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
        try {
            $entry->delete();
            $this->fail('Model deletes must be rejected.');
        } catch (\LogicException) {
            $this->assertTrue(true);
        }
        try {
            DB::table('admin_audit_logs')->where('id', $entry->id)->delete();
            $this->fail('Database deletes must be rejected.');
        } catch (QueryException) {
            $this->assertDatabaseHas('admin_audit_logs', ['id' => $entry->id]);
        }
    }

    public function test_create_reset_and_status_commands_require_an_individual_super_admin_and_hidden_secrets(): void
    {
        $actor = $this->admin('cli-actor');

        $this->artisan('admin:create', [
            '--actor' => $actor->email,
            '--email' => 'cli-reviewer@example.test',
            '--name' => 'CLI Reviewer',
            '--role' => Admin::ROLE_PHARMACY_REVIEWER,
        ])->expectsQuestion('Authorizing super-admin password', 'Strong!Password123')
            ->expectsQuestion('New administrator password', 'Created!Strong123')
            ->expectsQuestion('Confirm new administrator password', 'Created!Strong123')
            ->assertExitCode(0);
        $target = Admin::where('email', 'cli-reviewer@example.test')->firstOrFail();

        $this->artisan('admin:reset-password', ['email' => $target->email, '--actor' => $actor->email])
            ->expectsQuestion('Authorizing super-admin password', 'Strong!Password123')
            ->expectsQuestion('New administrator password', 'Reset!Password123')
            ->expectsQuestion('Confirm new administrator password', 'Reset!Password123')
            ->assertExitCode(0);
        $this->assertTrue(Hash::check('Reset!Password123', $target->fresh()->password));

        $this->artisan('admin:set-status', ['email' => $target->email, 'status' => 'disabled', '--actor' => $actor->email])
            ->expectsQuestion('Authorizing super-admin password', 'Strong!Password123')
            ->assertExitCode(0);
        $this->assertFalse($target->fresh()->is_active);
        $this->assertStringNotContainsString('Strong!Password123', Artisan::output());
        $this->assertStringNotContainsString('Reset!Password123', Artisan::output());
    }

    public function test_first_super_admin_provisioning_is_interactive_secret_safe_and_idempotent(): void
    {
        $this->artisan('admin:provision-super', [
            '--email' => 'FIRST@EXAMPLE.TEST',
            '--name' => 'First Owner',
        ])->expectsQuestion('New administrator password', 'Bootstrap!Strong123')
            ->expectsQuestion('Confirm new administrator password', 'Bootstrap!Strong123')
            ->expectsOutputToContain('was provisioned')
            ->assertExitCode(0);
        $this->assertDatabaseHas('admins', ['email' => 'first@example.test', 'role' => Admin::ROLE_SUPER_ADMIN, 'is_active' => true]);
        $this->assertStringNotContainsString('Bootstrap!Strong123', Artisan::output());

        $this->artisan('admin:provision-super', ['--email' => 'first@example.test', '--name' => 'Ignored'])
            ->expectsOutputToContain('already provisioned')
            ->assertExitCode(0);
        $this->assertDatabaseCount('admins', 1);
    }

    public function test_reset_and_status_services_invalidate_sessions_and_protect_the_last_super_admin(): void
    {
        $actor = $this->admin('operator');
        $target = $this->admin('target', Admin::ROLE_PHARMACY_REVIEWER);
        $service = app(AdminAccountService::class);
        $oldVersion = $target->auth_version;
        $service->resetPassword($actor, $target, 'Replacement!Strong123');
        $this->assertTrue(Hash::check('Replacement!Strong123', $target->fresh()->password));
        $this->assertGreaterThan($oldVersion, $target->fresh()->auth_version);

        $service->setActive($actor, $target, false);
        $this->assertFalse($target->fresh()->is_active);
        $service->setActive($actor, $target, true);
        $this->assertTrue($target->fresh()->is_active);

        $this->expectException(AdminWorkflowException::class);
        $service->setActive($actor, $actor, false);
    }
}
