<?php

namespace Tests\Feature\Auth;

use App\Models\Pharmacist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class RegistrationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_pharmacist_and_initial_pharmacy_are_registered_together(): void
    {
        Storage::fake('public');

        $this->post('/api/register', $this->registrationPayload())
            ->assertCreated()
            ->assertJsonStructure(['data' => ['registration_status_token']])
            ->assertJsonMissingPath('data.token')
            ->assertJsonMissingPath('data.session')
            ->assertJsonPath('data.actor.type', 'pharmacist')
            ->assertJsonPath('data.pharmacy.status', 'pending')
            ->assertJsonPath('data.registration.status', 'pending')
            ->assertJsonPath('data.registration.code', 'pharmacy_review_required');

        $this->assertDatabaseHas('pharmacists', ['email' => 'owner@example.test']);
        $this->assertDatabaseHas('pharmacies', [
            'pharmacy_name' => 'Central Pharmacy',
            'status' => 'pending',
        ]);
        $this->assertSame(
            ['registration-status'],
            PersonalAccessToken::sole()->abilities,
        );
    }

    public function test_invalid_initial_pharmacy_creates_no_pharmacist(): void
    {
        Storage::fake('public');
        $payload = $this->registrationPayload();
        unset($payload['license']);

        $this->post('/api/register', $payload)->assertSessionHasErrors('license');

        $this->assertDatabaseCount('pharmacists', 0);
        $this->assertDatabaseCount('pharmacies', 0);
    }

    public function test_registration_prohibits_arbitrary_pharmacist_ownership(): void
    {
        Storage::fake('public');
        $existing = Pharmacist::create([
            'name' => 'Existing',
            'email' => 'existing@example.test',
            'password' => 'password',
        ]);
        $payload = $this->registrationPayload();
        $payload['pharmacist_id'] = $existing->id;

        $this->post('/api/register', $payload)->assertSessionHasErrors('pharmacist_id');
        $this->assertDatabaseCount('pharmacies', 0);
    }

    public function test_registration_rejects_the_obsolete_profile_field_name(): void
    {
        Storage::fake('public');
        $payload = $this->registrationPayload();
        $payload['profile'] = UploadedFile::fake()->create('profile.jpg', 10, 'image/jpeg');

        $this->post('/api/register', $payload)->assertSessionHasErrors('profile');
        $this->assertDatabaseCount('pharmacists', 0);
    }

    public function test_public_second_registration_step_is_not_available(): void
    {
        $this->postJson('/api/register/pharmacy', ['pharmacist_id' => 1])
            ->assertNotFound();
    }

    public function test_trainee_registration_uses_the_aligned_employee_contract(): void
    {
        Storage::fake('public');

        $this->post('/api/employee/register', [
            'name' => 'New Trainee',
            'phone' => '0999000000',
            'email' => 'trainee@example.test',
            'password' => 'password',
            'role' => 'trainee',
            'cv' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf'),
        ])->assertCreated()
            ->assertJsonPath('data.actor.role', 'trainee')
            ->assertJsonPath('data.actor.status', 'pending');

        $this->assertDatabaseHas('employees', [
            'email' => 'trainee@example.test',
            'pharmacy_id' => null,
            'role' => 'trainee',
            'status' => 'pending',
        ]);
    }

    public function test_employee_registration_requires_experience_proof(): void
    {
        Storage::fake('public');

        $this->post('/api/employee/register', [
            'name' => 'New Employee',
            'phone' => '0999000001',
            'email' => 'employee@example.test',
            'password' => 'password',
            'role' => 'employee',
            'cv' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf'),
        ])->assertStatus(400)
            ->assertJsonPath('code', 'experience_proof_required');

        $this->assertDatabaseCount('employees', 0);
    }

    private function registrationPayload(): array
    {
        return [
            'name' => 'Owner',
            'email' => 'owner@example.test',
            'password' => 'password',
            'pharmacy_name' => 'Central Pharmacy',
            'pharmacy_address' => 'Main Street',
            'certificate' => UploadedFile::fake()->create('certificate.pdf', 10, 'application/pdf'),
            'license' => UploadedFile::fake()->create('license.pdf', 10, 'application/pdf'),
        ];
    }
}
