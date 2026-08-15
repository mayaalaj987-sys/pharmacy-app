<?php

namespace Tests\Feature\Auth;

use App\Models\Medicine;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivePharmacyContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_with_multiple_approved_pharmacies_must_supply_active_context(): void
    {
        $owner = $this->pharmacist('multiple');
        $this->pharmacy($owner, 'one', 'approved');
        $this->pharmacy($owner, 'two', 'approved');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->getJson('/api/medicines')
            ->assertStatus(409)
            ->assertJsonPath('code', 'active_pharmacy_required');
    }

    public function test_operational_request_accepts_owned_approved_header(): void
    {
        $owner = $this->pharmacist('selected');
        $selected = $this->pharmacy($owner, 'selected', 'approved');
        $this->pharmacy($owner, 'other', 'approved');
        $medicine = $this->medicine($selected, 'Selected Medicine');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->withHeader('X-Pharmacy-Id', (string) $selected->id)
            ->getJson('/api/medicines')
            ->assertOk()
            ->assertJsonFragment(['id' => $medicine->id]);
    }

    public function test_operational_request_rejects_foreign_or_rejected_context(): void
    {
        $owner = $this->pharmacist('owner');
        $rejected = $this->pharmacy($owner, 'rejected', 'rejected');
        $other = $this->pharmacist('other');
        $foreign = $this->pharmacy($other, 'foreign', 'approved');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        foreach ([$rejected, $foreign] as $invalid) {
            $this->withHeader('X-Pharmacy-Id', (string) $invalid->id)
                ->getJson('/api/medicines')
                ->assertForbidden();
        }
    }

    public function test_header_and_legacy_body_context_must_not_conflict(): void
    {
        $owner = $this->pharmacist('conflict');
        $one = $this->pharmacy($owner, 'one', 'approved');
        $two = $this->pharmacy($owner, 'two', 'approved');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->withHeader('X-Pharmacy-Id', (string) $one->id)
            ->getJson('/api/medicines?pharmacy_id='.$two->id)
            ->assertStatus(409)
            ->assertJsonPath('code', 'pharmacy_context_conflict');
    }

    public function test_record_operation_must_match_active_pharmacy_even_for_same_owner(): void
    {
        $owner = $this->pharmacist('record');
        $active = $this->pharmacy($owner, 'active', 'approved');
        $other = $this->pharmacy($owner, 'other', 'approved');
        $medicine = $this->medicine($other, 'Other Medicine');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->withHeader('X-Pharmacy-Id', (string) $active->id)
            ->postJson('/api/medicines/edit/'.$medicine->id, ['name' => 'Changed'])
            ->assertForbidden()
            ->assertJsonPath('code', 'active_pharmacy_mismatch');

        $this->assertSame('Other Medicine', $medicine->fresh()->name);
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

    private function medicine(Pharmacy $pharmacy, string $name): Medicine
    {
        return Medicine::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => $name,
            'category_medicine' => 'Vitamins',
            'cost_price' => 10,
            'selling_price' => 20,
            'quantity' => 5,
        ]);
    }
}
