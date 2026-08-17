<?php

namespace Tests\Feature\Integration;

use App\Models\Medicine;
use App\Models\Order;
use App\Models\Pharmacy;
use App\Models\Sale;
use App\Models\Supplier;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Security\SecurityTestCase;

/**
 * Two stock rules the pharmacy depends on.
 *
 * 1. Expired stock can never be sold. The app blocks it too, but a client-side
 *    check is a convenience, not a guarantee — this is the guarantee.
 * 2. A purchase order cannot exceed what the supplier actually has, and the
 *    units it takes are released again if the order is cancelled.
 */
class StockSafetyRulesTest extends SecurityTestCase
{
    public function test_an_expired_medicine_cannot_be_sold(): void
    {
        $owner = $this->pharmacist('expired-sale');
        $pharmacy = $this->pharmacy($owner, 'expired-sale');
        $expired = $this->stock($pharmacy, 'Azithromycin 250mg', expiresInDays: -1);
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->postJson('/api/sale/create', [
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'cash',
            'items' => [['medicine_id' => $expired->id, 'quantity' => 1]],
        ], ['X-Pharmacy-Id' => $pharmacy->id])
            ->assertStatus(400)
            ->assertJsonPath('code', 'medicine_expired')
            ->assertJsonPath('medicine.name', 'Azithromycin 250mg');

        // Nothing moved: no sale, no stock change.
        $this->assertSame(0, Sale::where('pharmacy_id', $pharmacy->id)->count());
        $this->assertSame(30, $expired->fresh()->quantity);
    }

    public function test_a_medicine_expiring_today_is_still_sellable(): void
    {
        $owner = $this->pharmacist('expires-today');
        $pharmacy = $this->pharmacy($owner, 'expires-today');
        $medicine = $this->stock($pharmacy, 'Paracetamol 500mg', expiresInDays: 0);
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        // The cut-off is the start of today, so the last day of validity counts.
        $this->postJson('/api/sale/create', [
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'cash',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 1]],
        ], ['X-Pharmacy-Id' => $pharmacy->id])->assertCreated();
    }

    public function test_one_expired_line_rejects_the_whole_basket(): void
    {
        $owner = $this->pharmacist('expired-basket');
        $pharmacy = $this->pharmacy($owner, 'expired-basket');
        $good = $this->stock($pharmacy, 'Ibuprofen 400mg', expiresInDays: 200);
        $expired = $this->stock($pharmacy, 'Aspirin 100mg', expiresInDays: -30);
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->postJson('/api/sale/create', [
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'cash',
            'items' => [
                ['medicine_id' => $good->id, 'quantity' => 2],
                ['medicine_id' => $expired->id, 'quantity' => 1],
            ],
        ], ['X-Pharmacy-Id' => $pharmacy->id])
            ->assertStatus(400)
            ->assertJsonPath('code', 'medicine_expired');

        // The valid line was rolled back with the rest.
        $this->assertSame(30, $good->fresh()->quantity);
    }

    public function test_ordering_more_than_the_supplier_has_is_rejected_with_the_real_figure(): void
    {
        $owner = $this->pharmacist('over-order');
        $pharmacy = $this->pharmacy($owner, 'over-order');
        $supplier = $this->supplier();
        $catalogue = $this->catalogue($supplier->id, available: 40);
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->postJson('/api/orders', [
            'supplier_id' => $supplier->id,
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'cash',
            'items' => [['medicine_id' => $catalogue->id, 'quantity' => 41]],
        ], ['X-Pharmacy-Id' => $pharmacy->id])
            ->assertStatus(400)
            ->assertJsonPath('code', 'supplier_stock_insufficient')
            ->assertJsonPath('medicine.available_quantity', 40)
            ->assertJsonPath('medicine.requested_quantity', 41);

        $this->assertSame(0, Order::count());
        $this->assertSame(40, $catalogue->fresh()->quantity);
    }

    public function test_ordering_exactly_what_is_available_succeeds_and_empties_the_catalogue_row(): void
    {
        $owner = $this->pharmacist('exact-order');
        $pharmacy = $this->pharmacy($owner, 'exact-order');
        $supplier = $this->supplier();
        $catalogue = $this->catalogue($supplier->id, available: 40);
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->postJson('/api/orders', [
            'supplier_id' => $supplier->id,
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'cash',
            'items' => [['medicine_id' => $catalogue->id, 'quantity' => 40]],
        ], ['X-Pharmacy-Id' => $pharmacy->id])->assertCreated();

        $this->assertSame(0, $catalogue->fresh()->quantity);
    }

    public function test_a_second_pharmacy_cannot_take_units_already_reserved(): void
    {
        $supplier = $this->supplier();
        $catalogue = $this->catalogue($supplier->id, available: 50);

        $first = $this->pharmacist('race-a');
        $firstPharmacy = $this->pharmacy($first, 'race-a');
        Sanctum::actingAs($first, ['*'], 'pharmacist');
        $this->postJson('/api/orders', [
            'supplier_id' => $supplier->id,
            'pharmacy_id' => $firstPharmacy->id,
            'payment_method' => 'cash',
            'items' => [['medicine_id' => $catalogue->id, 'quantity' => 45]],
        ], ['X-Pharmacy-Id' => $firstPharmacy->id])->assertCreated();

        // Only 5 left for everyone else.
        $second = $this->pharmacist('race-b');
        $secondPharmacy = $this->pharmacy($second, 'race-b');
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($second, ['*'], 'pharmacist');

        $this->postJson('/api/orders', [
            'supplier_id' => $supplier->id,
            'pharmacy_id' => $secondPharmacy->id,
            'payment_method' => 'cash',
            'items' => [['medicine_id' => $catalogue->id, 'quantity' => 10]],
        ], ['X-Pharmacy-Id' => $secondPharmacy->id])
            ->assertStatus(400)
            ->assertJsonPath('medicine.available_quantity', 5);

        $this->assertSame(5, $catalogue->fresh()->quantity);
    }

    public function test_cancelling_an_order_releases_the_reserved_units(): void
    {
        $owner = $this->pharmacist('cancel-release');
        $pharmacy = $this->pharmacy($owner, 'cancel-release');
        $supplier = $this->supplier();
        $catalogue = $this->catalogue($supplier->id, available: 60);
        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $headers = ['X-Pharmacy-Id' => $pharmacy->id];

        $orderId = $this->postJson('/api/orders', [
            'supplier_id' => $supplier->id,
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'cash',
            'items' => [['medicine_id' => $catalogue->id, 'quantity' => 25]],
        ], $headers)->assertCreated()->json('order_id');

        $this->assertSame(35, $catalogue->fresh()->quantity);

        $this->postJson('/api/orders/'.$orderId.'/cancel', [], $headers)->assertOk();

        $this->assertSame(60, $catalogue->fresh()->quantity);
        $this->assertSame('cancelled', Order::find($orderId)->status);
    }

    public function test_receiving_an_order_keeps_the_units_spent(): void
    {
        $owner = $this->pharmacist('receive-keep');
        $pharmacy = $this->pharmacy($owner, 'receive-keep');
        $supplier = $this->supplier();
        $catalogue = $this->catalogue($supplier->id, available: 60);
        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $headers = ['X-Pharmacy-Id' => $pharmacy->id];

        $orderId = $this->postJson('/api/orders', [
            'supplier_id' => $supplier->id,
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'cash',
            'items' => [['medicine_id' => $catalogue->id, 'quantity' => 25]],
        ], $headers)->assertCreated()->json('order_id');

        $this->postJson('/api/orders/'.$orderId.'/receive', [], $headers)->assertOk();

        // Received goods stay with the pharmacy; the supplier does not get them back.
        $this->assertSame(35, $catalogue->fresh()->quantity);
        $this->assertSame(25, Medicine::where('pharmacy_id', $pharmacy->id)->sole()->quantity);

        // And a received order can no longer be cancelled into a refund.
        $this->postJson('/api/orders/'.$orderId.'/cancel', [], $headers)->assertStatus(400);
        $this->assertSame(35, $catalogue->fresh()->quantity);
    }

    public function test_a_medicine_can_be_added_without_a_barcode(): void
    {
        $owner = $this->pharmacist('no-barcode');
        $pharmacy = $this->pharmacy($owner, 'no-barcode');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        // Barcodes were removed from the app; the field must no longer be required.
        $this->postJson('/api/medicines/add', [
            'pharmacy_id' => $pharmacy->id,
            'name' => 'Cetirizine 10mg',
            'category_medicine' => 'Respiratory',
            'cost_price' => 4000,
            'selling_price' => 7500,
            'quantity' => 25,
            'reorder_level' => 5,
            'manufacturer' => 'Qasioun Labs',
            'expire_date' => now()->addYear()->toDateString(),
        ], ['X-Pharmacy-Id' => $pharmacy->id])->assertCreated();

        $this->assertDatabaseHas('medicines', [
            'pharmacy_id' => $pharmacy->id,
            'name' => 'Cetirizine 10mg',
            'qr_code' => null,
        ]);
    }

    private function supplier(): Supplier
    {
        return Supplier::create([
            'name' => 'Barada Pharma Distribution (Demo)',
            'phone' => '0930111222',
            'email' => 'orders@barada-pharma.demo',
            'address' => 'Al-Mazzeh, Damascus',
        ]);
    }

    private function catalogue(int $supplierId, int $available): Medicine
    {
        return Medicine::create([
            'pharmacy_id' => null,
            'supplier_id' => $supplierId,
            'name' => 'Amoxicillin 500mg',
            'category_medicine' => 'Antibiotics',
            'cost_price' => 8000,
            'selling_price' => 12500,
            'manufacturer' => 'Qasioun Labs',
            'quantity' => $available,
            'reorder_level' => 10,
            'expire_date' => now()->addMonths(18)->toDateString(),
        ]);
    }

    private function stock(Pharmacy $pharmacy, string $name, int $expiresInDays): Medicine
    {
        return Medicine::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => $name,
            'category_medicine' => 'Painkillers',
            'cost_price' => 3000,
            'selling_price' => 6000,
            'manufacturer' => 'Orontes Labs',
            'quantity' => 30,
            'reorder_level' => 5,
            'expire_date' => now()->addDays($expiresInDays)->toDateString(),
        ]);
    }
}
