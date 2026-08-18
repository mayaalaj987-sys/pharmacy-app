<?php

namespace Tests\Feature\Purchasing;

use App\Models\Medicine;
use App\Models\Notification;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use App\Models\PurchaseCartItem;
use App\Models\Supplier;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Security\SecurityTestCase;

/**
 * The app queues a restock the moment a sale leaves a drug running low.
 *
 * A suggestion, not a purchase: the cart costs nothing and reserves nothing, so
 * removing the line is all it takes to say no. That is the whole approval flow.
 */
class AutoRestockTest extends SecurityTestCase
{
    public function test_selling_a_drug_below_its_reorder_level_queues_a_restock(): void
    {
        [$owner, $pharmacy] = $this->buyer('auto-low');
        $stock = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 12, reorder: 10);
        $offer = $this->offer($this->supplier('Barada'), 'Amoxicillin 500mg', cost: 8000, stock: 500);

        $this->sell($owner, $pharmacy, $stock, 4);

        $line = PurchaseCartItem::sole();
        $this->assertSame($offer->id, $line->medicine_id);
        $this->assertSame(PurchaseCartItem::ADDED_BY_APP, $line->added_by);

        // Back to twice the reorder level, not just back over the line: buying
        // to the threshold leaves it one sale away from firing again.
        $this->assertSame(12, $line->quantity);
    }

    public function test_the_notification_says_what_was_queued_and_from_whom(): void
    {
        // "You are low" tells the owner something they now have to act on.
        // "You are low and here is the order" leaves them one decision.
        [$owner, $pharmacy] = $this->buyer('auto-says');
        $stock = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 12, reorder: 10);
        $this->offer($this->supplier('Barada'), 'Amoxicillin 500mg', cost: 8000, stock: 500);

        $this->sell($owner, $pharmacy, $stock, 4);

        $notification = Notification::where('type', 'low_stock')->sole();
        $this->assertStringContainsString('Amoxicillin 500mg', $notification->message);
        $this->assertStringContainsString('12 added to your purchase cart', $notification->message);
        $this->assertStringContainsString('Barada', $notification->message);
        $this->assertStringContainsString('Review it before buying', $notification->message);
    }

    public function test_the_cheapest_supplier_who_can_fill_it_is_chosen(): void
    {
        [$owner, $pharmacy] = $this->buyer('auto-cheap');
        $stock = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 12, reorder: 10);
        $this->offer($this->supplier('Barada'), 'Amoxicillin 500mg', cost: 8000, stock: 500);
        $cheap = $this->offer($this->supplier('Al-Shahba'), 'Amoxicillin 500mg', cost: 7000, stock: 500);

        $this->sell($owner, $pharmacy, $stock, 4);

        $this->assertSame($cheap->id, PurchaseCartItem::sole()->medicine_id);
    }

    public function test_a_cheap_supplier_with_almost_nothing_left_loses_to_a_stocked_one(): void
    {
        // A token handful from the cheapest house does not restock a pharmacy.
        [$owner, $pharmacy] = $this->buyer('auto-scraps');
        $stock = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 12, reorder: 10);
        $this->offer($this->supplier('Al-Shahba'), 'Amoxicillin 500mg', cost: 7000, stock: 2);
        $stocked = $this->offer($this->supplier('Barada'), 'Amoxicillin 500mg', cost: 8000, stock: 9);

        $this->sell($owner, $pharmacy, $stock, 4);

        $line = PurchaseCartItem::sole();
        $this->assertSame($stocked->id, $line->medicine_id);
        // Capped at what they actually have.
        $this->assertSame(9, $line->quantity);
    }

    public function test_a_drug_still_above_its_reorder_level_is_left_alone(): void
    {
        [$owner, $pharmacy] = $this->buyer('auto-fine');
        $stock = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 100, reorder: 10);
        $this->offer($this->supplier('Barada'), 'Amoxicillin 500mg', cost: 8000, stock: 500);

        $this->sell($owner, $pharmacy, $stock, 4);

        $this->assertSame(0, PurchaseCartItem::count());
        $this->assertSame(0, Notification::whereIn('type', ['low_stock', 'out_of_stock'])->count());
    }

    public function test_a_second_sale_does_not_queue_the_drug_twice(): void
    {
        // Otherwise every sale of a low drug doubles the order and re-notifies.
        [$owner, $pharmacy] = $this->buyer('auto-once');
        $stock = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 12, reorder: 10);
        $this->offer($this->supplier('Barada'), 'Amoxicillin 500mg', cost: 8000, stock: 500);

        $this->sell($owner, $pharmacy, $stock, 4);
        $this->sell($owner, $pharmacy, $stock->fresh(), 2);

        $this->assertSame(1, PurchaseCartItem::count());
        $this->assertSame(12, PurchaseCartItem::sole()->quantity);
        $this->assertSame(1, Notification::whereIn('type', ['low_stock', 'out_of_stock'])->count());
    }

    public function test_a_line_switched_to_another_supplier_still_counts_as_queued(): void
    {
        // Matched by drug, not by offer: the pharmacist moving the line to a
        // different supplier is still the same intention to restock.
        [$owner, $pharmacy] = $this->buyer('auto-switched');
        $stock = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 12, reorder: 10);
        $this->offer($this->supplier('Barada'), 'Amoxicillin 500mg', cost: 8000, stock: 500);
        $other = $this->offer($this->supplier('Al-Shahba'), 'Amoxicillin 500mg', cost: 9000, stock: 500);

        $this->sell($owner, $pharmacy, $stock, 4);
        PurchaseCartItem::sole()->update(['medicine_id' => $other->id]);

        $this->sell($owner, $pharmacy, $stock->fresh(), 2);

        $this->assertSame(1, PurchaseCartItem::count());
    }

    public function test_a_drug_no_supplier_carries_still_raises_the_plain_warning(): void
    {
        // Nothing can be queued, but the owner still has to know.
        [$owner, $pharmacy] = $this->buyer('auto-nosupplier');
        $stock = $this->shelf($pharmacy, 'House Blend Syrup', quantity: 12, reorder: 10);

        $this->sell($owner, $pharmacy, $stock, 4);

        $this->assertSame(0, PurchaseCartItem::count());
        $this->assertSame(1, Notification::where('type', 'low_stock')->count());
    }

    public function test_a_drug_with_no_reorder_level_is_not_second_guessed(): void
    {
        // Zero is the column default, and it says nothing about how much to buy.
        // Inventing a quantity is worse than leaving the decision alone.
        [$owner, $pharmacy] = $this->buyer('auto-noreorder');
        $stock = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 4, reorder: 0);
        $this->offer($this->supplier('Barada'), 'Amoxicillin 500mg', cost: 8000, stock: 500);

        $this->sell($owner, $pharmacy, $stock, 4);

        $this->assertSame(0, PurchaseCartItem::count());
        $this->assertSame(1, Notification::where('type', 'out_of_stock')->count());
    }

    public function test_selling_out_entirely_queues_a_full_cushion(): void
    {
        [$owner, $pharmacy] = $this->buyer('auto-zero');
        $stock = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 6, reorder: 10);
        $this->offer($this->supplier('Barada'), 'Amoxicillin 500mg', cost: 8000, stock: 500);

        $this->sell($owner, $pharmacy, $stock, 6);

        $this->assertSame(20, PurchaseCartItem::sole()->quantity);
        $this->assertSame(1, Notification::where('type', 'out_of_stock')->count());
    }

    public function test_one_pharmacys_shortage_never_fills_anothers_cart(): void
    {
        [$owner, $pharmacy] = $this->buyer('auto-mine');
        [, $other] = $this->buyer('auto-other');
        $stock = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 12, reorder: 10);
        $this->offer($this->supplier('Barada'), 'Amoxicillin 500mg', cost: 8000, stock: 500);

        $this->sell($owner, $pharmacy, $stock, 4);

        $this->assertSame(1, PurchaseCartItem::where('pharmacy_id', $pharmacy->id)->count());
        $this->assertSame(0, PurchaseCartItem::where('pharmacy_id', $other->id)->count());
    }

    private function sell(Pharmacist $owner, Pharmacy $pharmacy, Medicine $stock, int $quantity): void
    {
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->postJson('/api/sale/create', [
            'customer_name' => 'Walk-in',
            'payment_method' => 'cash',
            'items' => [['medicine_id' => $stock->id, 'quantity' => $quantity]],
        ], ['X-Pharmacy-Id' => $pharmacy->id])->assertCreated();
    }

    /** @return array{0: Pharmacist, 1: Pharmacy} */
    private function buyer(string $suffix): array
    {
        $owner = $this->pharmacist($suffix);

        return [$owner, $this->pharmacy($owner, $suffix)];
    }

    private function shelf(Pharmacy $pharmacy, string $name, int $quantity, int $reorder): Medicine
    {
        return Medicine::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => $name,
            'category_medicine' => 'Antibiotics',
            'cost_price' => 8000,
            'selling_price' => 12500,
            'manufacturer' => 'Qasioun Labs',
            'quantity' => $quantity,
            'reorder_level' => $reorder,
            'expire_date' => now()->addMonths(18)->toDateString(),
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
