<?php

namespace Tests\Feature\Purchasing;

use App\Models\Medicine;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use App\Models\PurchaseCartItem;
use App\Models\Supplier;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Security\SecurityTestCase;

/**
 * Turning a cart into orders.
 *
 * A cart may span suppliers; an order may not — `orders.supplier_id` is
 * singular, because each supplier ships and invoices on its own. Checkout is
 * where that split happens, and it is the only moment in the whole flow when
 * stock is reserved or money is owed.
 */
class PurchaseCheckoutTest extends SecurityTestCase
{
    public function test_a_cart_spanning_suppliers_becomes_one_order_each(): void
    {
        [$owner, $pharmacy] = $this->buyer('co-split');
        $barada = $this->supplier('Barada');
        $shahba = $this->supplier('Al-Shahba');

        $this->inCart($pharmacy, $this->offer($barada, 'Amoxicillin 500mg', 8000, 100), 10);
        $this->inCart($pharmacy, $this->offer($barada, 'Aspirin 100mg', 2000, 100), 5);
        $this->inCart($pharmacy, $this->offer($shahba, 'Salbutamol Inhaler', 18000, 100), 2);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $response = $this->postJson('/api/purchase-cart/checkout', [
            'payment_method' => 'cash',
        ], $this->at($pharmacy))
            ->assertCreated()
            ->assertJsonPath('code', 'purchase_placed')
            ->assertJsonCount(2, 'orders')
            ->assertJsonPath('total', 126000);

        $orders = collect($response->json('orders'))->keyBy('supplier_name');
        $this->assertSame(90000.0, (float) $orders['Barada']['total_price']);
        $this->assertSame(2, $orders['Barada']['item_count']);
        $this->assertSame(36000.0, (float) $orders['Al-Shahba']['total_price']);

        $this->assertSame(2, Order::count());
        $this->assertSame(3, OrderItem::count());
    }

    public function test_buying_empties_the_cart_and_reserves_the_stock(): void
    {
        [$owner, $pharmacy] = $this->buyer('co-empty');
        $offer = $this->offer($this->supplier('Barada'), 'Amoxicillin 500mg', 8000, 100);
        $this->inCart($pharmacy, $offer, 30);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        // Paying a wholesaler by card asks for the card, exactly as the till
        // does when a customer pays by one.
        $this->postJson('/api/purchase-cart/checkout', [
            'payment_method' => 'card',
            'card_number' => '1234567890',
        ], $this->at($pharmacy))->assertCreated();

        $this->assertSame(0, PurchaseCartItem::count());
        // Reserved against the shared catalogue, so the next pharmacy sees the truth.
        $this->assertSame(70, $offer->fresh()->quantity);
        $this->assertDatabaseHas('orders', [
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'card',
            'status' => 'pending',
        ]);
    }

    public function test_one_supplier_running_short_buys_nothing_at_all(): void
    {
        // A half-bought cart is the hardest state to explain and to undo. Being
        // told nothing happened, and why, is recoverable in one action.
        [$owner, $pharmacy] = $this->buyer('co-short');
        $barada = $this->supplier('Barada');
        $shahba = $this->supplier('Al-Shahba');

        $plenty = $this->offer($barada, 'Amoxicillin 500mg', 8000, 100);
        $scarce = $this->offer($shahba, 'Salbutamol Inhaler', 18000, 100);

        $this->inCart($pharmacy, $plenty, 10);
        $this->inCart($pharmacy, $scarce, 50);

        // Another pharmacy takes them first.
        $scarce->update(['quantity' => 4]);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/purchase-cart/checkout', ['payment_method' => 'cash'], $this->at($pharmacy))
            ->assertStatus(409)
            ->assertJsonPath('code', 'supplier_stock_insufficient')
            ->assertJsonPath('medicine.name', 'Salbutamol Inhaler')
            ->assertJsonPath('medicine.available_quantity', 4)
            ->assertJsonPath('medicine.requested_quantity', 50);

        $this->assertSame(0, Order::count());
        $this->assertSame(0, OrderItem::count());
        // The cart survives, so lowering the one line and retrying is all it takes.
        $this->assertSame(2, PurchaseCartItem::count());
        $this->assertSame(100, $plenty->fresh()->quantity);
    }

    public function test_an_empty_cart_cannot_be_bought(): void
    {
        [$owner, $pharmacy] = $this->buyer('co-nothing');

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/purchase-cart/checkout', ['payment_method' => 'cash'], $this->at($pharmacy))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'cart_empty');

        $this->assertSame(0, Order::count());
    }

    public function test_the_order_freezes_the_price_the_cart_showed(): void
    {
        // The catalogue moves. What this order costs must not move with it —
        // receiving reads the line price back as the cost of arriving stock.
        [$owner, $pharmacy] = $this->buyer('co-freeze');
        $offer = $this->offer($this->supplier('Barada'), 'Amoxicillin 500mg', 8000, 100);
        $this->inCart($pharmacy, $offer, 10);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/purchase-cart/checkout', ['payment_method' => 'cash'], $this->at($pharmacy))
            ->assertCreated();

        $offer->update(['cost_price' => 99000]);

        $this->assertSame(8000.0, (float) OrderItem::sole()->price);
        $this->assertSame(80000.0, (float) Order::sole()->total_price);
    }

    public function test_only_cash_or_card_is_accepted(): void
    {
        [$owner, $pharmacy] = $this->buyer('co-pay');
        $this->inCart($pharmacy, $this->offer($this->supplier('Barada'), 'Amoxicillin 500mg', 8000, 100), 1);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/purchase-cart/checkout', ['payment_method' => 'credit'], $this->at($pharmacy))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method');

        $this->assertSame(0, Order::count());
    }

    public function test_checking_out_never_touches_another_pharmacys_cart(): void
    {
        [$mine, $myPharmacy] = $this->buyer('co-mine');
        [, $theirPharmacy] = $this->buyer('co-theirs');
        $barada = $this->supplier('Barada');

        $this->inCart($myPharmacy, $this->offer($barada, 'Amoxicillin 500mg', 8000, 100), 3);
        $this->inCart($theirPharmacy, $this->offer($barada, 'Aspirin 100mg', 2000, 100), 4);

        Sanctum::actingAs($mine, ['*'], 'pharmacist');
        $this->postJson('/api/purchase-cart/checkout', ['payment_method' => 'cash'], $this->at($myPharmacy))
            ->assertCreated()
            ->assertJsonCount(1, 'orders');

        $this->assertSame(1, PurchaseCartItem::count());
        $this->assertSame(1, Order::where('pharmacy_id', $myPharmacy->id)->count());
        $this->assertSame(0, Order::where('pharmacy_id', $theirPharmacy->id)->count());
    }

    public function test_switching_to_a_cheaper_supplier_moves_the_line(): void
    {
        [$owner, $pharmacy] = $this->buyer('sw-move');
        $dear = $this->offer($this->supplier('Barada'), 'Amoxicillin 500mg', 8000, 100);
        $cheap = $this->offer($this->supplier('Al-Shahba'), 'Amoxicillin 500mg', 7000, 100);
        $line = $this->inCart($pharmacy, $dear, 10);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/purchase-cart/'.$line->id.'/switch-supplier', [
            'medicine_id' => $cheap->id,
        ], $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('item_count', 1)
            ->assertJsonPath('total', 70000)
            ->assertJsonPath('suppliers.0.supplier.name', 'Al-Shahba')
            ->assertJsonPath('suppliers.0.items.0.cheaper_elsewhere', null);

        $this->assertSame(1, PurchaseCartItem::count());
        $this->assertSame($cheap->id, PurchaseCartItem::sole()->medicine_id);
    }

    public function test_switching_onto_a_line_that_already_exists_merges_them(): void
    {
        // The cart is unique on (pharmacy, offer), and two lines for the same
        // drug at the same price is not what anyone meant.
        [$owner, $pharmacy] = $this->buyer('sw-merge');
        $dear = $this->offer($this->supplier('Barada'), 'Amoxicillin 500mg', 8000, 100);
        $cheap = $this->offer($this->supplier('Al-Shahba'), 'Amoxicillin 500mg', 7000, 100);

        $line = $this->inCart($pharmacy, $dear, 10);
        $this->inCart($pharmacy, $cheap, 5);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/purchase-cart/'.$line->id.'/switch-supplier', [
            'medicine_id' => $cheap->id,
        ], $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('item_count', 1)
            ->assertJsonPath('suppliers.0.items.0.quantity', 15);

        $this->assertSame(1, PurchaseCartItem::count());
    }

    public function test_a_switch_cannot_smuggle_in_a_different_drug(): void
    {
        // This is a change of supplier, not a change of mind about what to buy.
        [$owner, $pharmacy] = $this->buyer('sw-other');
        $line = $this->inCart($pharmacy, $this->offer($this->supplier('Barada'), 'Amoxicillin 500mg', 8000, 100), 10);
        $unrelated = $this->offer($this->supplier('Al-Shahba'), 'Salbutamol Inhaler', 500, 100);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/purchase-cart/'.$line->id.'/switch-supplier', [
            'medicine_id' => $unrelated->id,
        ], $this->at($pharmacy))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'not_the_same_drug');

        $this->assertSame($line->medicine_id, PurchaseCartItem::sole()->medicine_id);
    }

    public function test_a_switch_cannot_reach_another_pharmacys_line(): void
    {
        [$mine, $myPharmacy] = $this->buyer('sw-mine');
        [, $theirPharmacy] = $this->buyer('sw-theirs');
        $dear = $this->offer($this->supplier('Barada'), 'Amoxicillin 500mg', 8000, 100);
        $cheap = $this->offer($this->supplier('Al-Shahba'), 'Amoxicillin 500mg', 7000, 100);
        $theirLine = $this->inCart($theirPharmacy, $dear, 10);

        Sanctum::actingAs($mine, ['*'], 'pharmacist');
        $this->postJson('/api/purchase-cart/'.$theirLine->id.'/switch-supplier', [
            'medicine_id' => $cheap->id,
        ], $this->at($myPharmacy))
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');

        $this->assertSame($dear->id, $theirLine->fresh()->medicine_id);
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

    private function offer(Supplier $supplier, string $name, int $cost, int $stock): Medicine
    {
        return Medicine::create([
            'pharmacy_id' => null,
            'supplier_id' => $supplier->id,
            'name' => $name,
            'category_medicine' => 'Antibiotics',
            'cost_price' => $cost,
            'selling_price' => $cost * 1.5,
            'manufacturer' => 'Qasioun Labs',
            'quantity' => $stock,
            'reorder_level' => 10,
            'expire_date' => now()->addMonths(18)->toDateString(),
        ]);
    }
}
