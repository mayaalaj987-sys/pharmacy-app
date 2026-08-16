<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\AdminAuditLog;
use Illuminate\Database\QueryException;

class AdminAuthenticationTest extends AdminTestCase
{
    public function test_admin_identity_is_normalized_constrained_and_sensitive_fields_are_hidden(): void
    {
        $admin = new Admin;
        $admin->forceFill([
            'name' => 'Normalized Admin',
            'email' => '  NORMALIZED@EXAMPLE.TEST ',
            'password' => 'Strong!Password123',
            'role' => Admin::ROLE_SUPER_ADMIN,
            'is_active' => true,
            'auth_version' => 1,
        ])->save();

        $this->assertSame('normalized@example.test', $admin->email);
        $this->assertNotEmpty($admin->public_id);
        $serialized = json_encode($admin);
        $this->assertStringNotContainsString('password', $serialized);
        $this->assertStringNotContainsString('auth_version', $serialized);

        $this->expectException(QueryException::class);
        $duplicate = new Admin;
        $duplicate->forceFill([
            'name' => 'Duplicate', 'email' => 'NORMALIZED@example.test', 'password' => 'Strong!Password123',
            'role' => Admin::ROLE_PHARMACY_REVIEWER, 'is_active' => true, 'auth_version' => 1,
        ])->save();
    }

    public function test_no_admin_self_registration_route_exists(): void
    {
        $this->postJson('/api/admin/register', [])->assertNotFound();
    }

    public function test_unsupported_admin_roles_fail_closed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $admin = new Admin;
        $admin->forceFill([
            'name' => 'Invalid Role', 'email' => 'invalid-role@example.test', 'password' => 'Strong!Password123',
            'role' => 'owner', 'is_active' => true, 'auth_version' => 1,
        ])->save();
    }

    public function test_login_uses_a_regenerated_server_session_and_returns_no_token(): void
    {
        $admin = $this->admin('login');
        $csrf = $this->getJson('/api/admin/csrf')->assertOk()->assertJsonPath('code', 'csrf_ready');
        $before = $csrf->getCookie(config('session.cookie'))?->getValue();

        $response = $this->postJson('/api/admin/login', [
            'email' => strtoupper($admin->email),
            'password' => 'Strong!Password123',
        ])->assertOk()
            ->assertJsonPath('code', 'admin_authenticated')
            ->assertJsonPath('data.admin.role', Admin::ROLE_SUPER_ADMIN)
            ->assertJsonMissingPath('data.token');

        $after = $response->getCookie(config('session.cookie'))?->getValue();
        $this->assertNotSame($before, $after);
        $sessionCookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));
        $this->assertNotNull($sessionCookie);
        $this->assertTrue($sessionCookie->isHttpOnly());
        $this->getJson('/api/admin/session')->assertOk()->assertJsonPath('data.navigation.manage_admins', true);
        $this->assertDatabaseHas('admin_audit_logs', ['admin_id' => $admin->id, 'action' => 'admin.login.succeeded']);
    }

    public function test_login_failures_are_generic_disabled_accounts_are_denied_and_attempts_are_throttled(): void
    {
        $admin = $this->admin('disabled', Admin::ROLE_PHARMACY_REVIEWER, false);
        foreach ([
            ['email' => 'missing@example.test', 'password' => 'Wrong!Password123'],
            ['email' => $admin->email, 'password' => 'Strong!Password123'],
        ] as $credentials) {
            $this->postJson('/api/admin/login', $credentials)
                ->assertUnauthorized()
                ->assertJsonPath('code', 'invalid_credentials');
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/admin/login', ['email' => 'throttle@example.test', 'password' => 'Wrong!Password123'])->assertUnauthorized();
        }
        $this->postJson('/api/admin/login', ['email' => 'throttle@example.test', 'password' => 'Wrong!Password123'])
            ->assertStatus(429)
            ->assertHeader('X-Request-ID')
            ->assertJsonPath('code', 'too_many_attempts');
        $this->assertGreaterThanOrEqual(2, AdminAuditLog::where('action', 'admin.login.failed')->count());
    }

    public function test_logout_invalidates_the_session_and_disabled_or_rotated_sessions_fail_closed(): void
    {
        $admin = $this->admin('lifecycle');
        $this->postJson('/api/admin/login', ['email' => $admin->email, 'password' => 'Strong!Password123'])->assertOk();
        $this->postJson('/api/admin/logout')->assertOk()->assertJsonPath('code', 'admin_logged_out');
        $this->getJson('/api/admin/session')->assertUnauthorized();

        $this->postJson('/api/admin/login', ['email' => $admin->email, 'password' => 'Strong!Password123'])->assertOk();
        $admin->forceFill(['is_active' => false, 'disabled_at' => now(), 'auth_version' => 2])->save();
        $this->getJson('/api/admin/session')->assertForbidden()->assertJsonPath('code', 'account_disabled');

        $admin->forceFill(['is_active' => true, 'disabled_at' => null])->save();
        $this->postJson('/api/admin/login', ['email' => $admin->email, 'password' => 'Strong!Password123'])->assertOk();
        $admin->forceFill(['auth_version' => 3])->save();
        $this->getJson('/api/admin/session')->assertUnauthorized()->assertJsonPath('code', 'session_expired');
    }

    public function test_state_changing_login_requires_csrf_outside_the_test_environment(): void
    {
        $admin = $this->admin('csrf');
        config([
            'app.env' => 'production',
            'admin.allowed_origins' => ['https://admin.example.test'],
            'cors.allowed_origins' => ['https://admin.example.test'],
        ]);
        $this->app->detectEnvironment(fn () => 'production');

        $this->postJson('/api/admin/login', ['email' => $admin->email, 'password' => 'Strong!Password123'])
            ->assertStatus(419)
            ->assertJsonPath('code', 'csrf_token_mismatch');
        $this->withSession(['_token' => 'known-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'known-csrf-token')
            ->postJson('/api/admin/login', ['email' => $admin->email, 'password' => 'Strong!Password123'])
            ->assertOk();
    }

    public function test_browser_origin_policy_is_explicit_and_production_https_only(): void
    {
        $this->withHeader('Origin', 'http://untrusted.example.test')
            ->getJson('/api/admin/csrf')
            ->assertForbidden()
            ->assertHeader('X-Request-ID')
            ->assertJsonPath('code', 'origin_not_allowed');

        $this->app->detectEnvironment(fn () => 'production');
        config(['app.env' => 'production', 'admin.allowed_origins' => ['http://admin.example.test']]);
        $this->getJson('/api/admin/csrf')
            ->assertStatus(503)
            ->assertJsonPath('code', 'admin_origin_configuration_invalid');
    }
}
