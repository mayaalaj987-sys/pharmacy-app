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
 * Paying a wholesaler by card asks for the card.
 *
 * The till already demanded ten digits from a customer paying that way; buying
 * from a supplier accepted the word "card" and nothing else. Same rule now, and
 * the number is discarded the moment it is validated — no column anywhere
 * stores it, which is the only defensible way for this application to handle
 * one.
 */
class PurchaseCardNumberTest extends SecurityTestCase
{
    public function test_checking_out_by_card_without_the_number_is_refused(): void
    {
        [$owner, $pharmacy] = $this->buyer('card-missing');
        $this->inCart($pharmacy, $this->offer('card-missing'), 5);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/purchase-cart/checkout', [
            'payment_method' => 'card',
        ], $this->at($pharmacy))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('card_number');

        $this->assertSame(0, Order::count());
        $this->assertSame(1, PurchaseCartItem::count());
    }

    public function test_a_card_number_that_is_not_ten_digits_is_refused(): void
    {
        [$owner, $pharmacy] = $this->buyer('card-short');
        $this->inCart($pharmacy, $this->offer('card-short'), 5);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        foreach (['123', '12345678901', 'abcdefghij'] as $number) {
            $this->postJson('/api/purchase-cart/checkout', [
                'payment_method' => 'card',
                'card_number' => $number,
            ], $this->at($pharmacy))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('card_number');
        }

        $this->assertSame(0, Order::count());
    }

    public function test_ten_digits_goes_through(): void
    {
        [$owner, $pharmacy] = $this->buyer('card-ok');
        $this->inCart($pharmacy, $this->offer('card-ok'), 5);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/purchase-cart/checkout', [
            'payment_method' => 'card',
            'card_number' => '1234567890',
        ], $this->at($pharmacy))->assertCreated();

        $this->assertDatabaseHas('orders', [
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'card',
        ]);
    }

    public function test_the_number_is_never_written_down(): void
    {
        // A pharmacy management system has no business keeping one.
        [$owner, $pharmacy] = $this->buyer('card-forget');
        $this->inCart($pharmacy, $this->offer('card-forget'), 5);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/purchase-cart/checkout', [
            'payment_method' => 'card',
            'card_number' => '9876543210',
        ], $this->at($pharmacy))->assertCreated();

        $this->assertStringNotContainsString(
            '9876543210',
            json_encode(Order::sole()->toArray()),
        );
    }

    public function test_paying_cash_asks_for_no_card(): void
    {
        [$owner, $pharmacy] = $this->buyer('card-cash');
        $this->inCart($pharmacy, $this->offer('card-cash'), 5);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/purchase-cart/checkout', [
            'payment_method' => 'cash',
        ], $this->at($pharmacy))->assertCreated();
    }

    public function test_the_single_order_endpoint_applies_the_same_rule(): void
    {
        // Two ways to place an order, one rule about paying for it.
        [$owner, $pharmacy] = $this->buyer('card-single');
        $offer = $this->offer('card-single');

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/orders', [
            'supplier_id' => $offer->supplier_id,
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'card',
            'items' => [['medicine_id' => $offer->id, 'quantity' => 2]],
        ], $this->at($pharmacy))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('card_number');

        $this->assertSame(0, Order::count());
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

    private function inCart(Pharmacy $pharmacy, Medicine $offer, int $quantity): void
    {
        PurchaseCartItem::create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $offer->id,
            'quantity' => $quantity,
            'added_by' => PurchaseCartItem::ADDED_BY_PHARMACIST,
        ]);
    }

    private function offer(string $suffix): Medicine
    {
        $supplier = Supplier::create([
            'name' => 'Barada '.$suffix,
            'phone' => '0930111222',
            'email' => $suffix.'@example.demo',
            'address' => 'Damascus',
        ]);

        return Medicine::create([
            'pharmacy_id' => null,
            'supplier_id' => $supplier->id,
            'name' => 'Amoxicillin 500mg',
            'category_medicine' => 'Antibiotics',
            'cost_price' => 8000,
            'selling_price' => 12500,
            'manufacturer' => 'Qasioun Labs',
            'quantity' => 500,
            'reorder_level' => 10,
            'expire_date' => now()->addYear()->toDateString(),
        ]);
    }
}
