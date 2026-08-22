<?php

namespace Tests\Feature\Purchasing;

use App\Models\Medicine;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use App\Models\PurchaseCartItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Security\SecurityTestCase;

/**
 * A delivery is its own batch, and the oldest one sells first.
 *
 * Merging deliveries into one row forced a single expiry date onto stock that
 * physically has two, and every choice of date was wrong: keep the old one and
 * a fresh delivery is blocked from sale, take the new one and stock past its
 * date becomes sellable. Separate rows are what the shelf actually looks like.
 *
 * First-expired-first-out is the pharmacy rule and the only allocation that
 * does not manufacture waste — selling the long-dated boxes first guarantees
 * the short-dated ones are still there when they expire.
 */
class BatchAndFefoTest extends SecurityTestCase
{
    public function test_a_delivery_with_a_different_date_becomes_its_own_batch(): void
    {
        [$owner, $pharmacy] = $this->buyer('batch-new');
        $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 5, expiresInDays: 20);
        $order = $this->order($pharmacy, 'Amoxicillin 500mg', quantity: 200, expiresInDays: 900);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/orders/'.$order->id.'/receive', [], $this->at($pharmacy))->assertOk();

        $batches = Medicine::where('pharmacy_id', $pharmacy->id)->orderBy('expire_date')->get();
        $this->assertCount(2, $batches);
        $this->assertSame(5, $batches->first()->quantity);
        $this->assertSame(200, $batches->last()->quantity);
    }

    public function test_a_delivery_of_the_same_batch_still_merges(): void
    {
        // Same drug, same date, same physical pile. Splitting that would be
        // clutter with no meaning behind it.
        [$owner, $pharmacy] = $this->buyer('batch-merge');
        $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 40, expiresInDays: 500);
        $order = $this->order($pharmacy, 'Amoxicillin 500mg', quantity: 60, expiresInDays: 500);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/orders/'.$order->id.'/receive', [], $this->at($pharmacy))->assertOk();

        $this->assertSame(1, Medicine::where('pharmacy_id', $pharmacy->id)->count());
        $this->assertSame(100, Medicine::where('pharmacy_id', $pharmacy->id)->sole()->quantity);
    }

    public function test_a_new_batch_keeps_the_price_the_pharmacy_already_charges(): void
    {
        // Their margin, decided once. A second batch arriving is not a reason
        // to quietly revert to whatever the supplier suggests.
        [$owner, $pharmacy] = $this->buyer('batch-price');
        $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 5, expiresInDays: 20, selling: 11111);
        $order = $this->order($pharmacy, 'Amoxicillin 500mg', quantity: 100, expiresInDays: 900, retail: 12500);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/orders/'.$order->id.'/receive', [], $this->at($pharmacy))->assertOk();

        $fresh = Medicine::where('pharmacy_id', $pharmacy->id)->orderByDesc('expire_date')->first();
        $this->assertSame(11111.0, (float) $fresh->selling_price);
    }

    public function test_a_sale_takes_from_the_batch_that_expires_first(): void
    {
        [$owner, $pharmacy] = $this->buyer('fefo-order');
        $old = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 10, expiresInDays: 20);
        $fresh = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 100, expiresInDays: 900);

        $this->sell($owner, $pharmacy, $fresh, 4);

        // Asked against the fresh batch, drawn from the old one anyway.
        $this->assertSame(6, $old->fresh()->quantity);
        $this->assertSame(100, $fresh->fresh()->quantity);
    }

    public function test_a_line_spills_into_the_next_batch_when_the_first_runs_out(): void
    {
        [$owner, $pharmacy] = $this->buyer('fefo-spill');
        $old = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 10, expiresInDays: 20);
        $fresh = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 100, expiresInDays: 900);

        $this->sell($owner, $pharmacy, $old, 25);

        $this->assertSame(0, $old->fresh()->quantity);
        $this->assertSame(85, $fresh->fresh()->quantity);

        // One line, two sale rows — which is what makes the recorded cost the
        // cost of the boxes that actually left.
        $this->assertSame(2, SaleItem::count());
    }

    public function test_duplicate_lines_cannot_claim_the_same_stock_twice(): void
    {
        [$owner, $pharmacy] = $this->buyer('fefo-duplicate');
        $batch = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 10, expiresInDays: 200);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/sale/create', [
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'cash',
            'items' => [
                ['medicine_id' => $batch->id, 'quantity' => 8],
                ['medicine_id' => $batch->id, 'quantity' => 8],
            ],
        ], $this->at($pharmacy))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.1.medicine_id');

        $this->assertSame(10, $batch->fresh()->quantity);
        $this->assertSame(0, Sale::count());
    }

    public function test_two_batch_ids_cannot_oversell_the_combined_shelf(): void
    {
        [$owner, $pharmacy] = $this->buyer('fefo-two-lines');
        $old = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 5, expiresInDays: 20);
        $fresh = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 5, expiresInDays: 200);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/sale/create', [
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'cash',
            'items' => [
                ['medicine_id' => $old->id, 'quantity' => 8],
                ['medicine_id' => $fresh->id, 'quantity' => 8],
            ],
        ], $this->at($pharmacy))
            ->assertStatus(400)
            ->assertJsonPath('code', 'insufficient_stock')
            ->assertJsonPath('medicine.available_quantity', 2);

        // The first reservation is rolled back with the failed basket.
        $this->assertSame(5, $old->fresh()->quantity);
        $this->assertSame(5, $fresh->fresh()->quantity);
        $this->assertSame(0, Sale::count());
    }

    public function test_equal_expiry_batches_use_the_older_row_first(): void
    {
        [$owner, $pharmacy] = $this->buyer('fefo-tie');
        $first = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 10, expiresInDays: 200);
        $second = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 10, expiresInDays: 200);

        $this->sell($owner, $pharmacy, $second, 4);

        $this->assertSame(6, $first->fresh()->quantity);
        $this->assertSame(10, $second->fresh()->quantity);
    }

    public function test_each_sale_line_records_the_cost_of_its_own_batch(): void
    {
        // Profit is revenue minus this. Reading it back off the medicine later
        // would reprice a finished sale every time fresh stock arrives.
        [$owner, $pharmacy] = $this->buyer('fefo-cost');
        $old = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 10, expiresInDays: 20, cost: 5000);
        $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 100, expiresInDays: 900, cost: 9000);

        $this->sell($owner, $pharmacy, $old, 25);

        $costs = SaleItem::orderBy('id')->pluck('cost_price')->map(fn ($c) => (float) $c);
        $this->assertSame([5000.0, 9000.0], $costs->all());
    }

    public function test_both_batches_ring_up_at_one_price(): void
    {
        // Charging two prices for the same box inside one transaction is
        // indefensible at the counter, whatever the accounting says.
        [$owner, $pharmacy] = $this->buyer('fefo-price');
        $old = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 10, expiresInDays: 20, selling: 9000);
        $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 100, expiresInDays: 900, selling: 14000);

        $this->sell($owner, $pharmacy, $old, 25);

        $this->assertSame([9000.0, 9000.0], SaleItem::orderBy('id')->pluck('price')->map(fn ($p) => (float) $p)->all());
        // 25 x 9,000 — the price the till displayed for the batch it sells first.
        $this->assertSame(225000.0, (float) Sale::sole()->total_price);
    }

    public function test_an_expired_batch_is_skipped_and_the_good_one_used(): void
    {
        // The whole reason batches matter: 200 sound boxes must not be blocked
        // by five that went out of date beside them.
        [$owner, $pharmacy] = $this->buyer('fefo-skip');
        $expired = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 5, expiresInDays: -10);
        $good = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 200, expiresInDays: 900);

        $this->sell($owner, $pharmacy, $expired, 3);

        $this->assertSame(5, $expired->fresh()->quantity);
        $this->assertSame(197, $good->fresh()->quantity);
    }

    public function test_a_drug_whose_every_batch_expired_cannot_be_sold(): void
    {
        [$owner, $pharmacy] = $this->buyer('fefo-alldead');
        $expired = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 40, expiresInDays: -10);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/sale/create', [
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'cash',
            'items' => [['medicine_id' => $expired->id, 'quantity' => 1]],
        ], $this->at($pharmacy))
            ->assertStatus(400)
            ->assertJsonPath('code', 'medicine_expired')
            ->assertJsonPath('medicine.name', 'Amoxicillin 500mg');

        $this->assertSame(40, $expired->fresh()->quantity);
        $this->assertSame(0, Sale::count());
    }

    public function test_a_shortage_is_measured_across_every_batch(): void
    {
        // "Only 12 left" has to mean the shelf, not whichever row the till
        // pointed at, or the pharmacist goes looking for boxes that are there.
        [$owner, $pharmacy] = $this->buyer('fefo-short');
        $old = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 5, expiresInDays: 20);
        $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 7, expiresInDays: 900);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/sale/create', [
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'cash',
            'items' => [['medicine_id' => $old->id, 'quantity' => 20]],
        ], $this->at($pharmacy))
            ->assertStatus(400)
            ->assertJsonPath('code', 'insufficient_stock')
            ->assertJsonPath('medicine.available_quantity', 12);

        $this->assertSame(0, Sale::count());
    }

    public function test_undated_stock_is_kept_for_last(): void
    {
        // A batch with no expiry has no claim to being urgent, and putting it
        // ahead of a dated one is how the dated one gets thrown away.
        [$owner, $pharmacy] = $this->buyer('fefo-undated');
        $undated = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 50, expiresInDays: null);
        $dated = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 50, expiresInDays: 400);

        $this->sell($owner, $pharmacy, $undated, 10);

        $this->assertSame(40, $dated->fresh()->quantity);
        $this->assertSame(50, $undated->fresh()->quantity);
    }

    public function test_running_low_is_judged_on_the_whole_shelf(): void
    {
        // A pharmacy holding 200 boxes in a fresh batch is not low because the
        // older batch beside it is down to three.
        [$owner, $pharmacy] = $this->buyer('fefo-low');
        $old = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 6, expiresInDays: 20, reorder: 10);
        $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 200, expiresInDays: 900, reorder: 10);

        $this->sell($owner, $pharmacy, $old, 6);

        // The old batch is empty, the shelf is not.
        $this->assertSame(0, $old->fresh()->quantity);
        $this->assertSame(0, PurchaseCartItem::count());
        $this->assertSame(0, Notification::whereIn('type', ['low_stock', 'out_of_stock'])->count());
    }

    public function test_one_pharmacys_batches_are_never_drawn_on_by_another(): void
    {
        [$owner, $pharmacy] = $this->buyer('fefo-mine');
        [, $other] = $this->buyer('fefo-theirs');
        $mine = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 20, expiresInDays: 400);
        $theirs = $this->shelf($other, 'Amoxicillin 500mg', quantity: 500, expiresInDays: 30);

        $this->sell($owner, $pharmacy, $mine, 5);

        $this->assertSame(15, $mine->fresh()->quantity);
        $this->assertSame(500, $theirs->fresh()->quantity);
    }

    private function sell(Pharmacist $owner, Pharmacy $pharmacy, Medicine $batch, int $quantity): void
    {
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->postJson('/api/sale/create', [
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'cash',
            'items' => [['medicine_id' => $batch->id, 'quantity' => $quantity]],
        ], $this->at($pharmacy))->assertCreated();
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

    private function shelf(
        Pharmacy $pharmacy,
        string $name,
        int $quantity,
        ?int $expiresInDays,
        int $cost = 8000,
        int $selling = 12500,
        int $reorder = 5,
    ): Medicine {
        return Medicine::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => $name,
            'category_medicine' => 'Antibiotics',
            'cost_price' => $cost,
            'selling_price' => $selling,
            'manufacturer' => 'Qasioun Labs',
            'quantity' => $quantity,
            'reorder_level' => $reorder,
            'expire_date' => $expiresInDays === null
                ? null
                : now()->addDays($expiresInDays)->toDateString(),
        ]);
    }

    private function order(
        Pharmacy $pharmacy,
        string $name,
        int $quantity,
        int $expiresInDays,
        int $retail = 12500,
    ): Order {
        $supplier = Supplier::create([
            'name' => 'Barada '.$name.$expiresInDays,
            'phone' => '0930111222',
            'email' => md5($name.$expiresInDays).'@example.demo',
            'address' => 'Damascus',
        ]);

        $offer = Medicine::create([
            'pharmacy_id' => null,
            'supplier_id' => $supplier->id,
            'name' => $name,
            'category_medicine' => 'Antibiotics',
            'cost_price' => 8000,
            'selling_price' => $retail,
            'manufacturer' => 'Qasioun Labs',
            'quantity' => 900,
            'reorder_level' => 10,
            'expire_date' => now()->addDays($expiresInDays)->toDateString(),
        ]);

        $order = Order::create([
            'supplier_id' => $supplier->id,
            'pharmacy_id' => $pharmacy->id,
            'date' => now()->toDateString(),
            'total_price' => 8000 * $quantity,
            'payment_method' => 'cash',
            'status' => 'pending',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'medicine_id' => $offer->id,
            'quantity' => $quantity,
            'price' => 8000,
        ]);

        return $order;
    }
}
