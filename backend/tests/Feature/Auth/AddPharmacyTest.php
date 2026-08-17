<?php

namespace Tests\Feature\Auth;

use App\Models\Pharmacy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Security\SecurityTestCase;

/**
 * Contract for `POST /pharmacy/add`, the endpoint an owner uses to register a
 * second pharmacy from the app.
 *
 * The two guarantees the client depends on are that the new pharmacy is always
 * created `pending`, and that ownership can never be chosen by the caller.
 */
class AddPharmacyTest extends SecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
    }

    public function test_authenticated_pharmacist_can_add_a_second_pharmacy(): void
    {
        $owner = $this->pharmacist('add-second');
        $first = $this->pharmacy($owner, 'add-second');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->postJson('/api/pharmacy/add', [
            'pharmacy_name' => 'Barada Branch',
            'pharmacy_address' => 'Al-Mazzeh, Damascus',
            'certificate' => $this->validPdfUpload('certificate.pdf'),
            'license' => $this->validPdfUpload('license.pdf'),
        ])
            ->assertCreated()
            ->assertJsonPath('pharmacy.pharmacy_name', 'Barada Branch')
            ->assertJsonPath('pharmacy.status', 'pending')
            ->assertJsonPath('pharmacy.certificate_on_file', true)
            ->assertJsonPath('pharmacy.license_on_file', true);

        $this->assertSame(2, $owner->pharmacies()->count());

        // The pre-existing pharmacy is untouched and stays the only approved one.
        $this->assertSame('approved', $first->fresh()->status);
        $this->assertSame(1, $owner->pharmacies()->where('status', 'approved')->count());
    }

    public function test_the_new_pharmacy_is_created_pending_and_is_not_operational_yet(): void
    {
        $owner = $this->pharmacist('add-pending');
        $this->pharmacy($owner, 'add-pending');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $id = $this->postJson('/api/pharmacy/add', [
            'pharmacy_name' => 'Pending Branch',
            'pharmacy_address' => 'Jaramana, Rif Dimashq',
            'certificate' => $this->validPdfUpload('certificate.pdf'),
            'license' => $this->validPdfUpload('license.pdf'),
        ])->assertCreated()->json('pharmacy.id');

        $this->assertDatabaseHas('pharmacies', ['id' => $id, 'status' => 'pending']);

        // A pending pharmacy cannot be used as an operational context.
        $this->withHeader('X-Pharmacy-Id', (string) $id)
            ->getJson('/api/medicines')
            ->assertForbidden();
    }

    public function test_the_owner_is_taken_from_the_token_and_cannot_be_client_supplied(): void
    {
        $owner = $this->pharmacist('add-owner');
        $this->pharmacy($owner, 'add-owner');
        $victim = $this->pharmacist('add-owner-victim');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->postJson('/api/pharmacy/add', [
            'pharmacy_name' => 'Hijacked',
            'pharmacy_address' => 'Aleppo',
            'certificate' => $this->validPdfUpload('certificate.pdf'),
            'license' => $this->validPdfUpload('license.pdf'),
            'pharmacist_id' => $victim->id,
            'owner_id' => $victim->id,
            'status' => 'approved',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pharmacist_id', 'owner_id', 'status']);

        $this->assertSame(0, $victim->pharmacies()->count());
        $this->assertSame(1, $owner->pharmacies()->count());
    }

    public function test_a_pharmacy_created_without_prohibited_fields_belongs_to_the_caller(): void
    {
        $owner = $this->pharmacist('add-attrib');
        $this->pharmacy($owner, 'add-attrib');
        $other = $this->pharmacist('add-attrib-other');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $id = $this->postJson('/api/pharmacy/add', [
            'pharmacy_name' => 'Owned Branch',
            'pharmacy_address' => 'Homs',
            'certificate' => $this->validPdfUpload('certificate.pdf'),
            'license' => $this->validPdfUpload('license.pdf'),
        ])->assertCreated()->json('pharmacy.id');

        $this->assertSame($owner->id, (int) Pharmacy::find($id)->pharmacist_id);
        $this->assertSame(0, $other->pharmacies()->count());
    }

    public function test_missing_certificate_or_license_is_rejected(): void
    {
        $owner = $this->pharmacist('add-missing');
        $this->pharmacy($owner, 'add-missing');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->postJson('/api/pharmacy/add', [
            'pharmacy_name' => 'No Documents',
            'pharmacy_address' => 'Latakia',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['certificate', 'license']);

        $this->postJson('/api/pharmacy/add', [
            'pharmacy_name' => 'Certificate Only',
            'pharmacy_address' => 'Latakia',
            'certificate' => $this->validPdfUpload('certificate.pdf'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('license');

        $this->assertSame(1, $owner->pharmacies()->count());
    }

    public function test_name_and_address_are_required(): void
    {
        $owner = $this->pharmacist('add-fields');
        $this->pharmacy($owner, 'add-fields');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->postJson('/api/pharmacy/add', [
            'certificate' => $this->validPdfUpload('certificate.pdf'),
            'license' => $this->validPdfUpload('license.pdf'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pharmacy_name', 'pharmacy_address']);

        $this->assertSame(1, $owner->pharmacies()->count());
    }

    public function test_an_oversized_document_is_rejected_and_stores_nothing(): void
    {
        $owner = $this->pharmacist('add-oversize');
        $this->pharmacy($owner, 'add-oversize');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->postJson('/api/pharmacy/add', [
            'pharmacy_name' => 'Too Large',
            'pharmacy_address' => 'Tartus',
            'certificate' => UploadedFile::fake()->create('certificate.pdf', 5121, 'application/pdf'),
            'license' => $this->validPdfUpload('license.pdf'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('certificate');

        $this->assertSame(1, $owner->pharmacies()->count());
        $this->assertSame([], Storage::disk('documents')->allFiles());
    }

    public function test_an_employee_cannot_register_a_pharmacy(): void
    {
        $owner = $this->pharmacist('add-employee');
        $pharmacy = $this->pharmacy($owner, 'add-employee');
        $employee = $this->employee($pharmacy, '901');
        Sanctum::actingAs($employee, ['*'], 'employee');

        $this->postJson('/api/pharmacy/add', [
            'pharmacy_name' => 'Employee Branch',
            'pharmacy_address' => 'Hama',
            'certificate' => $this->validPdfUpload('certificate.pdf'),
            'license' => $this->validPdfUpload('license.pdf'),
        ])->assertUnauthorized();

        $this->assertSame(1, Pharmacy::count());
    }

    public function test_the_new_pharmacy_appears_in_the_session_without_changing_the_active_one(): void
    {
        $owner = $this->pharmacist('add-session');
        $active = $this->pharmacy($owner, 'add-session');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->postJson('/api/pharmacy/add', [
            'pharmacy_name' => 'Second Branch',
            'pharmacy_address' => 'Douma, Rif Dimashq',
            'certificate' => $this->validPdfUpload('certificate.pdf'),
            'license' => $this->validPdfUpload('license.pdf'),
        ])->assertCreated();

        // This is exactly what the client re-reads after a successful submit.
        $response = $this->getJson('/api/me')->assertOk();

        $this->assertCount(2, $response->json('data.session.available_pharmacies'));
        $this->assertSame($active->id, $response->json('data.session.active_pharmacy.id'));
        $this->assertTrue($response->json('data.session.access.operational'));
        $this->assertSame('ready', $response->json('data.session.access.code'));
    }
}
