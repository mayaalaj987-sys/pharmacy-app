<?php

namespace Tests\Feature\Auth;

use App\Models\Employee;
use App\Models\Medicine;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ProfileAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_exposes_phone_and_a_configured_profile_image_url_for_canonical_and_legacy_paths(): void
    {
        Storage::fake('public');
        config(['filesystems.disks.public.url' => 'https://media.example.test/storage']);
        [$pharmacist, $pharmacy, $token] = $this->authenticatedOwner('session');
        Storage::disk('public')->put('profiles/legacy.jpg', 'image');
        $pharmacist->forceFill([
            'phone' => '0999000000',
            'profile_image' => json_encode(['profiles/legacy.jpg']),
        ])->save();

        $this->withToken($token)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->getJson('https://api.example.test/api/me')
            ->assertOk()
            ->assertJsonPath('data.session.actor.phone', '0999000000')
            ->assertJsonPath('data.session.actor.profile_image_url', 'https://media.example.test/storage/profiles/legacy.jpg');

        $pharmacist->forceFill(['profile_image' => 'profiles/canonical.jpg'])->save();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->getJson('https://api.example.test/api/me')
            ->assertOk()
            ->assertJsonPath('data.session.actor.profile_image_url', 'https://media.example.test/storage/profiles/canonical.jpg');
    }

    public function test_profile_update_changes_only_name_and_phone_and_returns_a_refreshed_session(): void
    {
        [$pharmacist, $pharmacy, $token] = $this->authenticatedOwner('profile');

        $this->withToken($token)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->postJson('/api/profile/update', [
                'name' => 'Updated Owner',
                'phone' => '0988000000',
            ])
            ->assertOk()
            ->assertJsonPath('code', 'profile_updated')
            ->assertJsonPath('data.session.actor.name', 'Updated Owner')
            ->assertJsonPath('data.session.actor.phone', '0988000000')
            ->assertJsonPath('data.session.active_pharmacy.id', $pharmacy->id);

        $this->assertDatabaseHas('pharmacists', [
            'id' => $pharmacist->id,
            'name' => 'Updated Owner',
            'phone' => '0988000000',
        ]);
    }

    public function test_profile_replacement_uses_canonical_storage_and_removes_the_old_owned_image_only_after_success(): void
    {
        Storage::fake('public');
        [$pharmacist, $pharmacy, $token] = $this->authenticatedOwner('replace');
        Storage::disk('public')->put('profiles/old.jpg', 'old');
        $pharmacist->forceFill(['profile_image' => json_encode(['profiles/old.jpg'])])->save();

        $response = $this->withToken($token)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->post('/api/profile/update', [
                'profile_image' => $this->png('client-name.png'),
            ], ['Accept' => 'application/json']);

        $response->assertOk();
        $stored = $pharmacist->fresh()->profile_image;
        $this->assertMatchesRegularExpression('/^profiles\/[0-9a-f-]+\.png$/', $stored);
        Storage::disk('public')->assertExists($stored);
        Storage::disk('public')->assertMissing('profiles/old.jpg');
        $this->assertStringNotContainsString('client-name', $stored);
    }

    public function test_invalid_spoofed_and_oversized_profile_images_are_rejected(): void
    {
        Storage::fake('public');
        [, $pharmacy, $token] = $this->authenticatedOwner('invalid-images');

        $files = [
            UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'),
            $this->spoofedImage(),
            $this->png('large.png', 2100),
        ];

        foreach ($files as $file) {
            $this->withToken($token)
                ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
                ->post('/api/profile/update', ['profile_image' => $file], ['Accept' => 'application/json'])
                ->assertUnprocessable()
                ->assertJsonPath('code', 'validation_failed')
                ->assertJsonValidationErrors('profile_image');
        }

        $this->assertSame([], Storage::disk('public')->allFiles('profiles'));
    }

    public function test_unsafe_legacy_paths_are_neither_exposed_nor_deleted(): void
    {
        Storage::fake('public');
        [$pharmacist, $pharmacy, $token] = $this->authenticatedOwner('unsafe');
        Storage::disk('public')->put('outside.jpg', 'outside');
        $pharmacist->forceFill(['profile_image' => '../outside.jpg'])->save();

        $this->withToken($token)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.session.actor.profile_image_url', null);

        $this->withToken($token)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->post('/api/profile/update', [
                'profile_image' => $this->png('new.png'),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        Storage::disk('public')->assertExists('outside.jpg');
    }

    public function test_failed_database_profile_update_removes_the_new_image_and_preserves_the_old_image(): void
    {
        Storage::fake('public');
        [$pharmacist, $pharmacy, $token] = $this->authenticatedOwner('rollback');
        Storage::disk('public')->put('profiles/old.jpg', 'old');
        $pharmacist->forceFill(['profile_image' => 'profiles/old.jpg'])->save();

        Pharmacist::saving(function (Pharmacist $saving): void {
            if ($saving->name === 'Force failure') {
                throw new RuntimeException('forced save failure');
            }
        });

        $this->withToken($token)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->post('/api/profile/update', [
                'name' => 'Force failure',
                'profile_image' => $this->png('replacement.png'),
            ], ['Accept' => 'application/json'])
            ->assertServerError();

        Storage::disk('public')->assertExists('profiles/old.jpg');
        $this->assertSame(['profiles/old.jpg'], Storage::disk('public')->allFiles('profiles'));
        $this->assertSame('profiles/old.jpg', $pharmacist->fresh()->profile_image);
    }

    public function test_profile_update_prohibits_identity_credentials_status_and_token_fields(): void
    {
        [$pharmacist, $pharmacy, $token] = $this->authenticatedOwner('prohibited');
        $oldPassword = $pharmacist->password;

        $this->withToken($token)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->postJson('/api/profile/update', [
                'email' => 'changed@example.test',
                'password' => 'changed-password',
                'pharmacist_id' => 999,
                'deactivated_at' => now()->toISOString(),
                'abilities' => ['*'],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors(['email', 'password', 'pharmacist_id', 'deactivated_at', 'abilities']);

        $fresh = $pharmacist->fresh();
        $this->assertSame($pharmacist->email, $fresh->email);
        $this->assertSame($oldPassword, $fresh->password);
        $this->assertNull($fresh->deactivated_at);
    }

    public function test_status_token_and_another_actor_id_cannot_update_a_profile(): void
    {
        [$pharmacist, $pharmacy] = $this->authenticatedOwner('status-token');
        $statusToken = $pharmacist->createToken('status', ['registration-status'])->plainTextToken;

        $this->withToken($statusToken)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->postJson('/api/profile/update', ['name' => 'Not allowed'])
            ->assertForbidden();

        $other = $this->owner('other-profile');
        $appToken = $pharmacist->createToken('app', ['app'])->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($appToken)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->postJson('/api/profile/update', ['actor_id' => $other->id, 'name' => 'Not allowed'])
            ->assertUnprocessable();

        $this->assertNotSame('Not allowed', $pharmacist->fresh()->name);
        $this->assertNotSame('Not allowed', $other->fresh()->name);
    }

    public function test_password_change_requires_and_verifies_current_password_and_confirmation(): void
    {
        [, $pharmacy, $token] = $this->authenticatedOwner('password-validation');

        $this->withToken($token)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->postJson('/api/password/change', [
                'new_password' => 'new-password',
                'new_password_confirmation' => 'new-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->withToken($token)
            ->postJson('/api/password/change', [
                'current_password' => 'wrong-password',
                'new_password' => 'new-password',
                'new_password_confirmation' => 'new-password',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'current_password_incorrect');

        $this->withToken($token)
            ->postJson('/api/password/change', [
                'current_password' => 'password',
                'new_password' => 'new-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('new_password');
    }

    public function test_password_change_hashes_the_password_keeps_current_token_and_revokes_all_others(): void
    {
        [$pharmacist, $pharmacy, $current] = $this->authenticatedOwner('password-change');
        $pharmacist->createToken('other-app', ['app']);
        $pharmacist->createToken('registration', ['registration-status']);

        $this->withToken($current)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->postJson('/api/password/change', [
                'current_password' => 'password',
                'new_password' => 'new-password',
                'new_password_confirmation' => 'new-password',
            ])
            ->assertOk()
            ->assertJsonPath('code', 'password_changed');

        $this->assertTrue(Hash::check('new-password', $pharmacist->fresh()->password));
        $this->assertFalse(Hash::check('password', $pharmacist->fresh()->password));
        $this->assertSame(1, PersonalAccessToken::count());

        $this->app['auth']->forgetGuards();
        $this->withToken($current)->getJson('/api/me')->assertOk();
        $this->postJson('/api/login', ['email' => $pharmacist->email, 'password' => 'password'])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'invalid_credentials');
        $this->postJson('/api/login', ['email' => $pharmacist->email, 'password' => 'new-password'])
            ->assertOk();
    }

    public function test_deactivation_requires_the_correct_password_revokes_all_tokens_and_preserves_domain_rows(): void
    {
        [$pharmacist, $pharmacy, $current] = $this->authenticatedOwner('deactivate');
        $medicine = Medicine::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => 'Historical medicine',
            'category_medicine' => 'Vitamins',
            'cost_price' => 10,
            'selling_price' => 20,
            'quantity' => 3,
        ]);
        $pharmacist->createToken('other', ['app']);

        $this->withToken($current)
            ->postJson('/api/account/deactivate', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->withToken($current)
            ->postJson('/api/account/deactivate', ['current_password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'current_password_incorrect');

        $this->withToken($current)
            ->postJson('/api/account/deactivate', ['current_password' => 'password'])
            ->assertOk()
            ->assertJsonPath('code', 'account_deactivated');

        $this->assertNotNull($pharmacist->fresh()->deactivated_at);
        $this->assertDatabaseHas('pharmacists', ['id' => $pharmacist->id]);
        $this->assertDatabaseHas('pharmacies', ['id' => $pharmacy->id]);
        $this->assertDatabaseHas('medicines', ['id' => $medicine->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->app['auth']->forgetGuards();
        $this->withToken($current)->getJson('/api/me')->assertUnauthorized();
        $this->postJson('/api/login', ['email' => $pharmacist->email, 'password' => 'wrong'])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'invalid_credentials');
        $this->postJson('/api/login', ['email' => $pharmacist->email, 'password' => 'password'])
            ->assertForbidden()
            ->assertJsonPath('code', 'account_deactivated')
            ->assertJsonPath('message', 'This account has been deactivated.');
    }

    public function test_deactivated_account_gate_denies_an_existing_app_token_and_old_delete_route_is_absent(): void
    {
        [$pharmacist, $pharmacy, $token] = $this->authenticatedOwner('active-gate');
        $pharmacist->forceFill(['deactivated_at' => now()])->save();

        $this->withToken($token)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->getJson('/api/me')
            ->assertForbidden()
            ->assertJsonPath('code', 'account_deactivated');

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->deleteJson('/api/delete-account')->assertNotFound();
        $this->assertDatabaseHas('pharmacists', ['id' => $pharmacist->id]);
        $this->assertDatabaseHas('pharmacies', ['id' => $pharmacy->id]);
    }

    public function test_new_password_minimum_length_boundary(): void
    {
        [, , $token] = $this->authenticatedOwner('length-boundary');

        $this->withToken($token)
            ->postJson('/api/password/change', [
                'current_password' => 'password',
                'new_password' => '1234567',
                'new_password_confirmation' => '1234567',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('new_password');

        $this->withToken($token)
            ->postJson('/api/password/change', [
                'current_password' => 'password',
                'new_password' => '12345678',
                'new_password_confirmation' => '12345678',
            ])
            ->assertOk()
            ->assertJsonPath('code', 'password_changed');
    }

    public function test_password_change_and_deactivate_are_rate_limited_per_account(): void
    {
        [, , $token] = $this->authenticatedOwner('throttle');

        for ($i = 0; $i < 5; $i++) {
            $this->withToken($token)
                ->postJson('/api/password/change', [
                    'current_password' => 'wrong',
                    'new_password' => 'new-password',
                    'new_password_confirmation' => 'new-password',
                ])
                ->assertUnprocessable();
        }

        $this->withToken($token)
            ->postJson('/api/password/change', [
                'current_password' => 'wrong',
                'new_password' => 'new-password',
                'new_password_confirmation' => 'new-password',
            ])
            ->assertStatus(429);

        // Both routes share the account-security bucket, so the exhausted limit
        // also blocks deactivation for the same account.
        $this->withToken($token)
            ->postJson('/api/account/deactivate', ['current_password' => 'wrong'])
            ->assertStatus(429);
    }

    public function test_deactivate_is_rate_limited_independently_of_password_change(): void
    {
        [, , $token] = $this->authenticatedOwner('throttle-deactivate');

        for ($i = 0; $i < 5; $i++) {
            $this->withToken($token)
                ->postJson('/api/account/deactivate', ['current_password' => 'wrong'])
                ->assertUnprocessable();
        }

        $this->withToken($token)
            ->postJson('/api/account/deactivate', ['current_password' => 'wrong'])
            ->assertStatus(429);
    }

    public function test_employee_token_is_rejected_on_profile_password_and_deactivate_routes(): void
    {
        [, $pharmacy] = $this->authenticatedOwner('employee-reject');
        $employee = Employee::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => 'Employee',
            'phone' => '0999000000',
            'email' => 'employee-reject@example.test',
            'password' => Hash::make('password'),
            'cv' => 'cv.pdf',
            'role' => 'employee',
            'status' => 'approved',
            'first_login' => false,
        ]);
        $employeeToken = $employee->createToken('employee', ['app'])->plainTextToken;

        $this->app['auth']->forgetGuards();
        $this->withToken($employeeToken)
            ->postJson('/api/profile/update', ['name' => 'Denied'])
            ->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $this->withToken($employeeToken)
            ->postJson('/api/password/change', [
                'current_password' => 'password',
                'new_password' => 'new-password',
                'new_password_confirmation' => 'new-password',
            ])
            ->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $this->withToken($employeeToken)
            ->postJson('/api/account/deactivate', ['current_password' => 'password'])
            ->assertUnauthorized();
    }

    private function authenticatedOwner(string $suffix): array
    {
        $pharmacist = $this->owner($suffix);
        $pharmacy = Pharmacy::create([
            'pharmacist_id' => $pharmacist->id,
            'pharmacy_name' => 'Pharmacy '.$suffix,
            'pharmacy_address' => 'Address '.$suffix,
            'certificate' => 'certificates/private.pdf',
            'license' => 'licenses/private.pdf',
            'status' => 'approved',
        ]);
        $token = $pharmacist->createToken('current', ['app'])->plainTextToken;

        return [$pharmacist, $pharmacy, $token];
    }

    private function owner(string $suffix): Pharmacist
    {
        return Pharmacist::create([
            'name' => 'Pharmacist '.$suffix,
            'email' => 'pharmacist-'.$suffix.'@example.test',
            'password' => Hash::make('password'),
        ]);
    }

    private function png(string $name, int $minimumKilobytes = 0): UploadedFile
    {
        $content = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );

        if ($minimumKilobytes > 0) {
            $content .= str_repeat("\0", ($minimumKilobytes * 1024) - strlen($content));
        }

        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function spoofedImage(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'spoofed-image-');
        file_put_contents($path, 'not an image');

        return new UploadedFile($path, 'spoofed.jpg', 'image/jpeg', UPLOAD_ERR_OK, true);
    }
}
