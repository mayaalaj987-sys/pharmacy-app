<?php

namespace Tests\Feature\Purchasing;

use App\Models\Medicine;
use App\Models\Order;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use App\Models\PurchaseCartItem;
use App\Models\Supplier;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Security\SecurityTestCase;

/**
 * Expired stock must not be bought.
 *
 * The POS already refuses to sell an expired box. Without a matching rule at
 * the buying end, the money goes out, the delivery arrives, and the pharmacy
 * discovers it owns something it can never sell — a loss that shows up nowhere
 * until somebody reaches for the shelf.
 *
 * The catalogue ages on its own and nothing refreshes it, so every offer
 * eventually crosses this line. It is a dated fault, not a hypothetical one.
 */
class ExpiredStockPurchaseTest extends SecurityTestCase
{
    public function test_an_expired_offer_cannot_be_bought(): void
    {
        [$owner, $pharmacy] = $this->buyer('exp-buy');
        $offer = $this->offer($this->supplier('Barada'), 'Amoxicillin 500mg', expiresInDays: -1);
        $this->inCart($pharmacy, $offer, 10);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/purchase-cart/checkout', ['payment_method' => 'cash'], $this->at($pharmacy))
            ->assertStatus(409)
            // The same code the POS answers with, so the client has one rule to
            // recognise rather than two.
            ->assertJsonPath('code', 'medicine_expired')
            ->assertJsonPath('medicine.name', 'Amoxicillin 500mg');

        $this->assertSame(0, Order::count());
        $this->assertSame(1, PurchaseCartItem::count());
        // Nothing reserved either: the whole checkout rolled back.
        $this->assertSame(500, $offer->fresh()->quantity);
    }

    public function test_one_expired_line_stops_the_whole_cart(): void
    {
        [$owner, $pharmacy] = $this->buyer('exp-all');
        $good = $this->offer($this->supplier('Barada'), 'Aspirin 100mg', expiresInDays: 400);
        $stale = $this->offer($this->supplier('Al-Shahba'), 'Amoxicillin 500mg', expiresInDays: -30);

        $this->inCart($pharmacy, $good, 5);
        $this->inCart($pharmacy, $stale, 5);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/purchase-cart/checkout', ['payment_method' => 'cash'], $this->at($pharmacy))
            ->assertStatus(409)
            ->assertJsonPath('code', 'medicine_expired');

        $this->assertSame(0, Order::count());
        $this->assertSame(500, $good->fresh()->quantity);
    }

    public function test_the_single_order_endpoint_refuses_it_too(): void
    {
        // Two ways to buy, one rule. The service is the only writer, so this is
        // the same check seen from the other door.
        [$owner, $pharmacy] = $this->buyer('exp-single');
        $supplier = $this->supplier('Barada');
        $offer = $this->offer($supplier, 'Amoxicillin 500mg', expiresInDays: -1);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/orders', [
            'supplier_id' => $supplier->id,
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'cash',
            'items' => [['medicine_id' => $offer->id, 'quantity' => 5]],
        ], $this->at($pharmacy))
            ->assertStatus(400)
            ->assertJsonPath('code', 'medicine_expired');

        $this->assertSame(0, Order::count());
    }

    public function test_the_cart_says_a_line_has_expired_before_buy_is_pressed(): void
    {
        // Being refused at checkout with no warning is a worse experience than
        // seeing the problem on the line that caused it.
        [$owner, $pharmacy] = $this->buyer('exp-flag');
        $offer = $this->offer($this->supplier('Barada'), 'Amoxicillin 500mg', expiresInDays: -1);
        $this->inCart($pharmacy, $offer, 10);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->getJson('/api/purchase-cart', $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('expired_count', 1)
            ->assertJsonPath('suppliers.0.items.0.expired', true)
            ->assertJsonPath('suppliers.0.items.0.expiring_soon', false);
    }

    public function test_short_dated_stock_is_flagged_but_still_buyable(): void
    {
        // Sometimes exactly what a pharmacy wants — a fast mover at a discount.
        // That judgement belongs to the pharmacist, not to the app.
        [$owner, $pharmacy] = $this->buyer('exp-soon');
        $offer = $this->offer($this->supplier('Barada'), 'Amoxicillin 500mg', expiresInDays: 40);
        $this->inCart($pharmacy, $offer, 10);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->getJson('/api/purchase-cart', $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('expired_count', 0)
            ->assertJsonPath('expiring_soon_count', 1)
            ->assertJsonPath('suppliers.0.items.0.expiring_soon', true);

        $this->postJson('/api/purchase-cart/checkout', ['payment_method' => 'cash'], $this->at($pharmacy))
            ->assertCreated();

        $this->assertSame(1, Order::count());
    }

    public function test_an_expired_offer_is_never_suggested_as_the_cheaper_one(): void
    {
        // Expired stock is frequently the cheapest, so without this it would win
        // every comparison and the app would be talking pharmacies into losses.
        [$owner, $pharmacy] = $this->buyer('exp-cheaper');
        $good = $this->offer($this->supplier('Barada'), 'Amoxicillin 500mg', expiresInDays: 400, cost: 8000);
        $this->offer($this->supplier('Al-Shahba'), 'Amoxicillin 500mg', expiresInDays: -5, cost: 3000);
        $this->inCart($pharmacy, $good, 10);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->getJson('/api/purchase-cart', $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('suppliers.0.items.0.cheaper_elsewhere', null);
    }

    public function test_the_app_never_queues_an_expired_restock(): void
    {
        // A line that can never be checked out is worse than no line at all.
        [$owner, $pharmacy] = $this->buyer('exp-auto');
        $stock = Medicine::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => 'Amoxicillin 500mg',
            'category_medicine' => 'Antibiotics',
            'cost_price' => 8000,
            'selling_price' => 12500,
            'quantity' => 12,
            'reorder_level' => 10,
            'expire_date' => now()->addYear()->toDateString(),
        ]);
        $this->offer($this->supplier('Barada'), 'Amoxicillin 500mg', expiresInDays: -1, cost: 3000);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/sale/create', [
            'customer_name' => 'Walk-in',
            'payment_method' => 'cash',
            'items' => [['medicine_id' => $stock->id, 'quantity' => 4]],
        ], $this->at($pharmacy))->assertCreated();

        $this->assertSame(0, PurchaseCartItem::count());
        // The owner still hears about the shortage.
        $this->assertDatabaseHas('notifications', [
            'pharmacy_id' => $pharmacy->id,
            'type' => 'low_stock',
        ]);
    }

    public function test_an_offer_with_no_expiry_at_all_is_fine(): void
    {
        // The column is nullable, and a missing date is not an expired one.
        [$owner, $pharmacy] = $this->buyer('exp-null');
        $offer = $this->offer($this->supplier('Barada'), 'Amoxicillin 500mg', expiresInDays: null);
        $this->inCart($pharmacy, $offer, 10);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->getJson('/api/purchase-cart', $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('suppliers.0.items.0.expired', false)
            ->assertJsonPath('suppliers.0.items.0.expiring_soon', false)
            ->assertJsonPath('suppliers.0.items.0.medicine.expire_date', null);

        $this->postJson('/api/purchase-cart/checkout', ['payment_method' => 'cash'], $this->at($pharmacy))
            ->assertCreated();
    }

    /** @return array{0: Pharmacist, 1: Pharmacy} */
    private function buyer(string $suffix): array
    {
        $owner = $this->pharmacist($suffix);

        return [$owner, $this->pharmacy($owner, $suffix)];
    }

    /** @return array<string, int> */
    private function at(Pharmacy $pharmacy): array
    {
        return ['X-Pharmacy-Id' => $pharmacy->id];
    }

    private function inCart(Pharmacy $pharmacy, Medicine $offer, int $quantity): PurchaseCartItem
    {
        return PurchaseCartItem::create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $offer->id,
            'quantity' => $quantity,
            'added_by' => PurchaseCartItem::ADDED_BY_PHARMACIST,
        ]);
    }

    private function supplier(string $name): Supplier
    {
        return Supplier::create([
            'name' => $name,
            'phone' => '09'.substr((string) (crc32($name) % 100000000), 0, 8),
            'email' => strtolower($name).'@example.demo',
            'address' => 'Damascus',
        ]);
    }

    private function offer(
        Supplier $supplier,
        string $name,
        ?int $expiresInDays,
        int $cost = 8000,
    ): Medicine {
        return Medicine::create([
            'pharmacy_id' => null,
            'supplier_id' => $supplier->id,
            'name' => $name,
            'category_medicine' => 'Antibiotics',
            'cost_price' => $cost,
            'selling_price' => $cost * 1.5,
            'manufacturer' => 'Qasioun Labs',
            'quantity' => 500,
            'reorder_level' => 10,
            'expire_date' => $expiresInDays === null
                ? null
                : now()->addDays($expiresInDays)->toDateString(),
        ]);
    }
}
