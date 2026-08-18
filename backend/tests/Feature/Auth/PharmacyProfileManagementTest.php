<?php

namespace Tests\Feature\Auth;

use App\Models\Employee;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PharmacyProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_nullable_numeric_coordinates_and_safe_document_flags_without_paths(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('certificates/private.pdf', $this->validPdfContent());
        Storage::disk('public')->put('licenses/private.pdf', $this->validPdfContent());
        [$owner, $pharmacy, $token] = $this->authenticatedOwner('session', 33.5138, 36.2765);

        $response = $this->withToken($token)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.session.active_pharmacy.latitude', fn ($value) => is_float($value) && $value === 33.5138)
            ->assertJsonPath('data.session.active_pharmacy.longitude', fn ($value) => is_float($value) && $value === 36.2765)
            ->assertJsonPath('data.session.active_pharmacy.certificate_on_file', true)
            ->assertJsonPath('data.session.active_pharmacy.license_on_file', true);

        $this->assertStringNotContainsString('certificates/private.pdf', $response->getContent());
        $this->assertStringNotContainsString('licenses/private.pdf', $response->getContent());

        $pharmacy->forceFill(['latitude' => null, 'longitude' => null])->save();
        $this->withToken($token)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.session.active_pharmacy.latitude', null)
            ->assertJsonPath('data.session.active_pharmacy.longitude', null);
    }

    public function test_active_owner_updates_name_address_and_valid_coordinates_and_receives_normalized_session(): void
    {
        [, $pharmacy, $token] = $this->authenticatedOwner('update');

        $this->withToken($token)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->postJson('/api/pharmacy/profile/update', [
                'pharmacy_name' => 'Updated Pharmacy',
                'pharmacy_address' => 'Updated Address',
                'latitude' => 33.5102,
                'longitude' => 36.2913,
            ])
            ->assertOk()
            ->assertJsonPath('code', 'pharmacy_profile_updated')
            ->assertJsonPath('data.session.active_pharmacy.name', 'Updated Pharmacy')
            ->assertJsonPath('data.session.active_pharmacy.address', 'Updated Address')
            ->assertJsonPath('data.session.active_pharmacy.latitude', 33.5102)
            ->assertJsonPath('data.session.active_pharmacy.longitude', 36.2913);

        $this->assertDatabaseHas('pharmacies', [
            'id' => $pharmacy->id,
            'pharmacy_name' => 'Updated Pharmacy',
            'pharmacy_address' => 'Updated Address',
        ]);
    }

    public function test_coordinate_boundaries_are_accepted(): void
    {
        [, $pharmacy, $token] = $this->authenticatedOwner('boundaries');

        foreach ([[-90, -180], [90, 180]] as [$latitude, $longitude]) {
            $this->withToken($token)
                ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
                ->postJson('/api/pharmacy/profile/update', compact('latitude', 'longitude'))
                ->assertOk();
        }

        $this->assertSame(90.0, $pharmacy->fresh()->latitude);
        $this->assertSame(180.0, $pharmacy->fresh()->longitude);
    }

    public function test_invalid_incomplete_and_non_numeric_coordinates_are_rejected(): void
    {
        [, $pharmacy, $token] = $this->authenticatedOwner('invalid-coordinates', 1, 2);
        $invalidPayloads = [
            ['latitude' => -90.0000001, 'longitude' => 0],
            ['latitude' => 0, 'longitude' => 180.0000001],
            ['latitude' => 10],
            ['longitude' => 10],
            ['latitude' => 'north', 'longitude' => 'east'],
            ['latitude' => null, 'longitude' => 10],
        ];

        foreach ($invalidPayloads as $payload) {
            $this->withToken($token)
                ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
                ->postJson('/api/pharmacy/profile/update', $payload)
                ->assertUnprocessable()
                ->assertJsonPath('code', 'validation_failed');
        }

        $this->assertSame(1.0, $pharmacy->fresh()->latitude);
        $this->assertSame(2.0, $pharmacy->fresh()->longitude);
    }

    public function test_both_null_clear_coordinates_while_omission_preserves_them(): void
    {
        [, $pharmacy, $token] = $this->authenticatedOwner('clear', 10, 20);

        $this->withToken($token)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->postJson('/api/pharmacy/profile/update', ['pharmacy_address' => 'Still located'])
            ->assertOk();
        $this->assertSame(10.0, $pharmacy->fresh()->latitude);
        $this->assertSame(20.0, $pharmacy->fresh()->longitude);

        $this->withToken($token)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->postJson('/api/pharmacy/profile/update', ['latitude' => null, 'longitude' => null])
            ->assertOk();
        $this->assertNull($pharmacy->fresh()->latitude);
        $this->assertNull($pharmacy->fresh()->longitude);
    }

    public function test_cross_owner_and_mismatched_same_owner_active_pharmacy_updates_are_rejected(): void
    {
        [$owner, $active, $token] = $this->authenticatedOwner('tenant');
        $sameOwnerOther = $this->pharmacy($owner, 'same-owner-other');
        $foreignOwner = $this->owner('foreign');
        $foreign = $this->pharmacy($foreignOwner, 'foreign');

        $this->withToken($token)
            ->withHeader('X-Pharmacy-Id', (string) $foreign->id)
            ->postJson('/api/pharmacy/profile/update', ['pharmacy_name' => 'Hacked'])
            ->assertForbidden()
            ->assertJsonPath('code', 'active_pharmacy_forbidden');

        $this->withToken($token)
            ->withHeader('X-Pharmacy-Id', (string) $active->id)
            ->postJson('/api/pharmacy/profile/update', [
                'pharmacy_id' => $sameOwnerOther->id,
                'pharmacy_name' => 'Hacked',
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'pharmacy_context_conflict');

        $this->assertNotSame('Hacked', $active->fresh()->pharmacy_name);
        $this->assertNotSame('Hacked', $sameOwnerOther->fresh()->pharmacy_name);
        $this->assertNotSame('Hacked', $foreign->fresh()->pharmacy_name);
    }

    public function test_active_context_is_required_when_owner_has_multiple_approved_pharmacies(): void
    {
        [$owner, , $token] = $this->authenticatedOwner('context-required');
        $this->pharmacy($owner, 'second');

        $this->withToken($token)
            ->postJson('/api/pharmacy/profile/update', ['pharmacy_name' => 'No context'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'active_pharmacy_required');
    }

    public function test_owner_status_approval_and_document_fields_are_prohibited_and_status_remains_unchanged(): void
    {
        [, $pharmacy, $token] = $this->authenticatedOwner('prohibited');

        $this->withToken($token)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->postJson('/api/pharmacy/profile/update', [
                'id' => 999,
                'pharmacy_id' => $pharmacy->id,
                'pharmacist_id' => 999,
                'owner_id' => 999,
                'status' => 'rejected',
                'approval_status' => 'rejected',
                'approved' => false,
                'certificate' => 'replacement.pdf',
                'license' => 'replacement.pdf',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors([
                'id', 'pharmacy_id', 'pharmacist_id', 'owner_id', 'status', 'approval_status', 'approved', 'certificate', 'license',
            ]);

        $fresh = $pharmacy->fresh();
        $this->assertSame('approved', $fresh->status);
        $this->assertSame(json_encode(['certificates/private.pdf']), $fresh->certificate);
        $this->assertSame(json_encode(['licenses/private.pdf']), $fresh->license);
    }

    public function test_app_ability_and_pharmacist_actor_are_required(): void
    {
        [$owner, $pharmacy] = $this->authenticatedOwner('ability');
        $statusToken = $owner->createToken('status', ['registration-status'])->plainTextToken;

        $this->withToken($statusToken)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->postJson('/api/pharmacy/profile/update', ['pharmacy_name' => 'Denied'])
            ->assertForbidden();

        $employee = Employee::create([
            'pharmacy_id' => $pharmacy->id,
            'shift' => Employee::SHIFT_MORNING,
            'name' => 'Employee',
            'phone' => '0999000000',
            'email' => 'employee@example.test',
            'password' => Hash::make('password'),
            'cv' => 'cv.pdf',
            'role' => 'employee',
            'status' => 'approved',
            'first_login' => false,
        ]);
        $employeeToken = $employee->createToken('employee', ['app'])->plainTextToken;

        $this->app['auth']->forgetGuards();
        $this->withToken($employeeToken)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->postJson('/api/pharmacy/profile/update', ['pharmacy_name' => 'Denied'])
            ->assertUnauthorized();

        $this->assertNotSame('Denied', $pharmacy->fresh()->pharmacy_name);
    }

    private function authenticatedOwner(
        string $suffix,
        ?float $latitude = null,
        ?float $longitude = null,
    ): array {
        $owner = $this->owner($suffix);
        $pharmacy = $this->pharmacy($owner, $suffix, $latitude, $longitude);
        $token = $owner->createToken('current', ['app'])->plainTextToken;

        return [$owner, $pharmacy, $token];
    }

    private function owner(string $suffix): Pharmacist
    {
        return Pharmacist::create([
            'name' => 'Owner '.$suffix,
            'email' => 'owner-'.$suffix.'@example.test',
            'password' => Hash::make('password'),
        ]);
    }

    private function pharmacy(
        Pharmacist $owner,
        string $suffix,
        ?float $latitude = null,
        ?float $longitude = null,
    ): Pharmacy {
        return Pharmacy::create([
            'pharmacist_id' => $owner->id,
            'pharmacy_name' => 'Pharmacy '.$suffix,
            'pharmacy_address' => 'Address '.$suffix,
            'certificate' => json_encode(['certificates/private.pdf']),
            'license' => json_encode(['licenses/private.pdf']),
            'status' => 'approved',
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }
}
