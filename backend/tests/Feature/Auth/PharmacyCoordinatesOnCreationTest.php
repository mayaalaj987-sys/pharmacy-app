<?php

namespace Tests\Feature\Auth;

use App\Models\Pharmacy;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Security\SecurityTestCase;

/**
 * Coordinates picked on the map at the moment a pharmacy is created.
 *
 * Both creation endpoints accept them, and both treat them as optional: the
 * map needs a location permission and a network round-trip, so requiring it
 * would block registration for anyone who declines or is offline.
 */
class PharmacyCoordinatesOnCreationTest extends SecurityTestCase
{
    private const DAMASCUS = ['latitude' => 33.5138, 'longitude' => 36.2765];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
    }

    public function test_signup_stores_the_point_picked_on_the_map(): void
    {
        $response = $this->postJson('/api/register', $this->registration(self::DAMASCUS))
            ->assertCreated()
            ->assertJsonPath('data.pharmacy.latitude', 33.5138)
            ->assertJsonPath('data.pharmacy.longitude', 36.2765);

        $pharmacy = Pharmacy::find($response->json('data.pharmacy.id'));
        $this->assertSame(33.5138, (float) $pharmacy->latitude);
        $this->assertSame(36.2765, (float) $pharmacy->longitude);
    }

    public function test_signup_without_a_map_pin_still_succeeds(): void
    {
        // Typing the address has to remain enough; the map is a convenience.
        $response = $this->postJson('/api/register', $this->registration())
            ->assertCreated()
            ->assertJsonPath('data.pharmacy.latitude', null);

        $pharmacy = Pharmacy::find($response->json('data.pharmacy.id'));
        $this->assertNull($pharmacy->latitude);
        $this->assertNull($pharmacy->longitude);
        $this->assertSame('Al-Mazzeh, Damascus', $pharmacy->pharmacy_address);
    }

    public function test_adding_a_second_pharmacy_stores_its_own_point(): void
    {
        $owner = $this->pharmacist('coords-add');
        $first = $this->pharmacy($owner, 'coords-add');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $id = $this->postJson('/api/pharmacy/add', [
            'pharmacy_name' => 'Aleppo Branch',
            'pharmacy_address' => 'Al-Furqan, Aleppo',
            'latitude' => 36.2021,
            'longitude' => 37.1343,
            'certificate' => $this->validPdfUpload('certificate.pdf'),
            'license' => $this->validPdfUpload('license.pdf'),
        ])
            ->assertCreated()
            ->assertJsonPath('pharmacy.latitude', 36.2021)
            ->json('pharmacy.id');

        $this->assertSame(36.2021, (float) Pharmacy::find($id)->latitude);

        // A branch has its own location; the first pharmacy is not touched.
        $this->assertNull($first->fresh()->latitude);
    }

    public function test_adding_a_second_pharmacy_without_a_point_still_succeeds(): void
    {
        $owner = $this->pharmacist('coords-add-none');
        $this->pharmacy($owner, 'coords-add-none');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $id = $this->postJson('/api/pharmacy/add', [
            'pharmacy_name' => 'Homs Branch',
            'pharmacy_address' => 'Al-Waer, Homs',
            'certificate' => $this->validPdfUpload('certificate.pdf'),
            'license' => $this->validPdfUpload('license.pdf'),
        ])->assertCreated()->json('pharmacy.id');

        $this->assertNull(Pharmacy::find($id)->latitude);
    }

    public function test_half_a_pair_is_rejected_rather_than_silently_dropped(): void
    {
        // A latitude with no longitude is not a place — it is a client bug.
        $this->postJson('/api/register', $this->registration(['latitude' => 33.5138]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('longitude');

        $this->postJson('/api/register', $this->registration(['longitude' => 36.2765]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('latitude');

        $this->assertSame(0, Pharmacy::count());
    }

    public function test_coordinates_outside_the_globe_are_rejected(): void
    {
        $this->postJson('/api/register', $this->registration([
            'latitude' => 91,
            'longitude' => 181,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);

        $this->assertSame(0, Pharmacy::count());
    }

    public function test_a_non_numeric_coordinate_is_rejected(): void
    {
        $this->postJson('/api/register', $this->registration([
            'latitude' => 'Damascus',
            'longitude' => 'Syria',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_the_saved_point_comes_back_in_the_session(): void
    {
        $owner = $this->pharmacist('coords-session');
        $pharmacy = $this->pharmacy($owner, 'coords-session');
        $pharmacy->forceFill(self::DAMASCUS)->save();
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        // This is what the app reads back to draw the pin after signing in.
        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.session.active_pharmacy.latitude', 33.5138)
            ->assertJsonPath('data.session.active_pharmacy.longitude', 36.2765);
    }

    private function registration(array $coordinates = []): array
    {
        return [
            'name' => 'Maya Alhaj',
            'email' => 'maya-'.uniqid().'@example.test',
            'password' => 'password123',
            'pharmacy_name' => 'Barada Pharmacy',
            'pharmacy_address' => 'Al-Mazzeh, Damascus',
            'certificate' => $this->validPdfUpload('certificate.pdf'),
            'license' => $this->validPdfUpload('license.pdf'),
            ...$coordinates,
        ];
    }
}
