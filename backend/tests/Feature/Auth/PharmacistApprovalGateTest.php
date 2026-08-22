<?php

namespace Tests\Feature\Auth;

use App\Models\Employee;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class PharmacistApprovalGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_status_token_refreshes_status_without_creating_an_app_session(): void
    {
        Storage::fake('public');
        Storage::fake('documents');

        $response = $this->post('/api/register', $this->registrationPayload())
            ->assertCreated();

        $token = $response->json('data.registration_status_token');

        $this->withToken($token)
            ->getJson('/api/registration/status')
            ->assertOk()
            ->assertJsonPath('data.registration.status', 'pending')
            ->assertJsonPath('data.registration.code', 'pharmacy_review_required')
            ->assertJsonCount(1, 'data.registration.pharmacies');

        $this->withToken($token)->getJson('/api/me')->assertForbidden();
        $this->withToken($token)->getJson('/api/profile')->assertForbidden();
        $this->withToken($token)->getJson('/api/medicines')->assertForbidden();
    }

    public function test_app_token_cannot_be_used_as_a_registration_status_credential(): void
    {
        $pharmacist = $this->pharmacist('app-status');
        $this->pharmacy($pharmacist, 'approved', 'approved');
        $token = $pharmacist->createToken('app', ['app'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/registration/status')
            ->assertForbidden();
    }

    public function test_registration_status_refresh_uses_only_the_authenticated_token_identity(): void
    {
        $pharmacist = $this->pharmacist('identity');
        $this->pharmacy($pharmacist, 'pending', 'pending');
        $token = $pharmacist->createToken('status', ['registration-status'])->plainTextToken;

        foreach (['email', 'phone', 'pharmacist_id', 'pharmacy_id'] as $field) {
            $this->withToken($token)
                ->getJson('/api/registration/status?'.$field.'=1')
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field);
        }
    }

    public function test_status_refresh_reports_approval_without_issuing_or_promoting_an_app_token(): void
    {
        $pharmacist = $this->pharmacist('refresh-approved');
        $pharmacy = $this->pharmacy($pharmacist, 'pending', 'pending');
        $token = $pharmacist->createToken('status', ['registration-status'])->plainTextToken;
        $pharmacy->update(['status' => 'approved']);

        $this->withToken($token)
            ->getJson('/api/registration/status')
            ->assertOk()
            ->assertJsonPath('data.registration.status', 'approved')
            ->assertJsonPath('data.registration.code', 'pharmacy_approved')
            ->assertJsonPath(
                'data.registration.message',
                'Your pharmacy has been approved. You can now log in.',
            );

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertSame(['registration-status'], PersonalAccessToken::sole()->abilities);
    }

    public function test_rejected_status_includes_the_admin_reason_only_on_the_rejected_pharmacy(): void
    {
        $pharmacist = $this->pharmacist('rejection-reason');
        $pending = $this->pharmacy($pharmacist, 'pending', 'pending');
        $rejected = $this->pharmacy($pharmacist, 'rejected', 'rejected');
        $rejected->forceFill(['rejection_reason' => 'The submitted license has expired.'])->save();
        $token = $pharmacist->createToken('status', ['registration-status'])->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/registration/status')
            ->assertOk()
            ->assertJsonPath('data.registration.status', 'pending');

        $pharmacies = collect($response->json('data.registration.pharmacies'))
            ->keyBy('id');
        $this->assertNull($pharmacies[$pending->id]['rejection_reason']);
        $this->assertSame(
            'The submitted license has expired.',
            $pharmacies[$rejected->id]['rejection_reason'],
        );
    }

    public function test_logout_accepts_status_tokens_and_revokes_only_the_presented_token(): void
    {
        $pharmacist = $this->pharmacist('status-logout');
        $status = $pharmacist->createToken('status', ['registration-status']);
        $other = $pharmacist->createToken('other-status', ['registration-status']);

        $this->withToken($status->plainTextToken)
            ->postJson('/api/logout')
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $status->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $other->accessToken->id]);
    }

    public function test_invalid_credentials_are_401_even_when_pharmacy_is_pending(): void
    {
        $pharmacist = $this->pharmacist('bad-password');
        $this->pharmacy($pharmacist, 'pending', 'pending');

        $this->postJson('/api/login', [
            'email' => $pharmacist->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized()
            ->assertJsonPath('code', 'invalid_credentials');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_pending_rejected_and_missing_pharmacies_are_denied_with_403_and_no_token(): void
    {
        $pending = $this->pharmacist('pending');
        $this->pharmacy($pending, 'pending', 'pending');
        $rejected = $this->pharmacist('rejected');
        $this->pharmacy($rejected, 'rejected', 'rejected');
        $missing = $this->pharmacist('missing');

        foreach ([
            [$pending, 'pharmacy_review_required'],
            [$rejected, 'pharmacy_access_rejected'],
            [$missing, 'no_pharmacy_available'],
        ] as [$pharmacist, $code]) {
            $this->postJson('/api/login', $this->credentials($pharmacist))
                ->assertForbidden()
                ->assertJsonPath('code', $code);
        }

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_any_approved_pharmacy_allows_login_and_issues_only_the_app_ability(): void
    {
        $pharmacist = $this->pharmacist('mixed');
        $this->pharmacy($pharmacist, 'pending', 'pending');
        $approved = $this->pharmacy($pharmacist, 'approved', 'approved');
        $this->pharmacy($pharmacist, 'rejected', 'rejected');

        $response = $this->postJson('/api/login', $this->credentials($pharmacist))
            ->assertOk()
            ->assertJsonPath('data.session.active_pharmacy.id', $approved->id);

        $token = PersonalAccessToken::findToken($response->json('data.token'));
        $this->assertSame(['app'], $token?->abilities);
    }

    public function test_employee_and_trainee_login_tokens_receive_the_app_ability_without_status_changes(): void
    {
        $owner = $this->pharmacist('employee-owner');
        $pharmacy = $this->pharmacy($owner, 'employee', 'approved');

        foreach (['employee', 'trainee'] as $role) {
            $employee = Employee::create([
                'pharmacy_id' => $pharmacy->id,
                'shift' => $pharmacy->fresh()->freeShifts()[0] ?? null,
                'name' => ucfirst($role),
                'phone' => '09990000'.($role === 'employee' ? '01' : '02'),
                'email' => $role.'-ability@example.test',
                'password' => Hash::make('password'),
                'cv' => 'cv.pdf',
                'role' => $role,
                'status' => 'approved',
                'first_login' => false,
            ]);

            $response = $this->postJson('/api/employee/login', $this->credentials($employee))
                ->assertOk()
                ->assertJsonPath('data.session.actor.role', $role);

            $token = PersonalAccessToken::findToken($response->json('data.token'));
            $this->assertSame(['app'], $token?->abilities);
        }
    }

    public function test_presented_legacy_wildcard_token_is_revoked_for_every_no_approved_pharmacy_state(): void
    {
        foreach ([
            ['pending', 'pharmacy_review_required'],
            ['rejected', 'pharmacy_access_rejected'],
            [null, 'no_pharmacy_available'],
        ] as [$status, $code]) {
            $pharmacist = $this->pharmacist('legacy-'.($status ?? 'missing'));
            if ($status !== null) {
                $this->pharmacy($pharmacist, $status, $status);
            }
            $presented = $pharmacist->createToken('legacy-presented');
            $other = $pharmacist->createToken('legacy-other');

            $this->app['auth']->forgetGuards();
            $this->withToken($presented->plainTextToken)
                ->getJson('/api/me')
                ->assertForbidden()
                ->assertJsonPath('code', $code);

            $this->assertDatabaseMissing('personal_access_tokens', ['id' => $presented->accessToken->id]);
            $this->assertDatabaseHas('personal_access_tokens', ['id' => $other->accessToken->id]);
        }
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

    private function credentials(Pharmacist|Employee $actor): array
    {
        return ['email' => $actor->email, 'password' => 'password'];
    }

    private function registrationPayload(): array
    {
        return [
            'name' => 'Owner',
            'email' => 'owner@example.test',
            'password' => 'password',
            'pharmacy_name' => 'Central Pharmacy',
            'pharmacy_address' => 'Main Street',
            'certificate' => $this->validPdfUpload('certificate.pdf'),
            'license' => $this->validPdfUpload('license.pdf'),
        ];
    }
}
