<?php

namespace Tests\Feature\Purchasing;

use App\Models\Medicine;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use App\Models\Supplier;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Security\SecurityTestCase;

/**
 * What a delivery does to the price of the stock it joins.
 *
 * Two numbers meet here and neither used to be set honestly. Cost decides
 * profit, and was frozen at whatever the first delivery cost. Shelf price
 * decides revenue, and was copied from whatever the supplier suggested.
 */
class ReceivingPricesTest extends SecurityTestCase
{
    public function test_a_dearer_delivery_raises_the_recorded_cost(): void
    {
        // Left alone, every profit report drifts further from reality as
        // prices rise: sold at 8,000 against a cost last true a year ago.
        [$owner, $pharmacy] = $this->buyer('rc-rise');
        $shelf = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 0, cost: 5000, selling: 9000);
        $order = $this->order($pharmacy, 'Amoxicillin 500mg', quantity: 10, paid: 7000);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/orders/'.$order->id.'/receive', [], $this->at($pharmacy))->assertOk();

        // Nothing was left of the old stock, so the new price is simply the price.
        $this->assertSame(7000.0, (float) $shelf->fresh()->cost_price);
    }

    public function test_the_cost_is_blended_across_what_is_already_on_the_shelf(): void
    {
        // Overwriting with the newest price would revalue 100 cheaply bought
        // boxes because 10 dear ones turned up. A weighted average is the only
        // figure true of the mixed pile.
        [$owner, $pharmacy] = $this->buyer('rc-blend');
        $shelf = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 100, cost: 5000, selling: 9000);
        $order = $this->order($pharmacy, 'Amoxicillin 500mg', quantity: 10, paid: 7000);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/orders/'.$order->id.'/receive', [], $this->at($pharmacy))->assertOk();

        // (100 x 5000 + 10 x 7000) / 110
        $this->assertSame(5181.82, (float) $shelf->fresh()->cost_price);
        $this->assertSame(110, $shelf->fresh()->quantity);
    }

    public function test_a_restock_leaves_the_shelf_price_the_pharmacy_set(): void
    {
        // Their margin, decided once. A delivery is not a reason to revisit it.
        [$owner, $pharmacy] = $this->buyer('rc-keep');
        $shelf = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 40, cost: 5000, selling: 11111);
        $order = $this->order($pharmacy, 'Amoxicillin 500mg', quantity: 10, paid: 7000);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/orders/'.$order->id.'/receive', [], $this->at($pharmacy))->assertOk();

        $this->assertSame(11111.0, (float) $shelf->fresh()->selling_price);
    }

    public function test_the_pharmacist_sets_the_shelf_price_of_a_new_drug(): void
    {
        [$owner, $pharmacy] = $this->buyer('rc-new');
        $order = $this->order($pharmacy, 'Cefixime 400mg', quantity: 10, paid: 15000, suggestedRetail: 22000);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/orders/'.$order->id.'/receive', [
            'selling_prices' => [OrderItem::sole()->medicine_id => 25000],
        ], $this->at($pharmacy))->assertOk();

        $stock = Medicine::where('pharmacy_id', $pharmacy->id)->sole();
        $this->assertSame(25000.0, (float) $stock->selling_price);
        $this->assertSame(15000.0, (float) $stock->cost_price);
    }

    public function test_a_new_drug_falls_back_to_the_suppliers_suggestion(): void
    {
        // The old behaviour, kept as the default so an unattended receive still
        // produces a sellable price rather than a zero.
        [$owner, $pharmacy] = $this->buyer('rc-fallback');
        $order = $this->order($pharmacy, 'Cefixime 400mg', quantity: 10, paid: 15000, suggestedRetail: 22000);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/orders/'.$order->id.'/receive', [], $this->at($pharmacy))->assertOk();

        $this->assertSame(22000.0, (float) Medicine::where('pharmacy_id', $pharmacy->id)->sole()->selling_price);
    }

    public function test_a_price_can_be_set_on_a_restock_too(): void
    {
        [$owner, $pharmacy] = $this->buyer('rc-reprice');
        $shelf = $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 40, cost: 5000, selling: 9000);
        $order = $this->order($pharmacy, 'Amoxicillin 500mg', quantity: 10, paid: 7000);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/orders/'.$order->id.'/receive', [
            'selling_prices' => [OrderItem::sole()->medicine_id => 12000],
        ], $this->at($pharmacy))->assertOk();

        $this->assertSame(12000.0, (float) $shelf->fresh()->selling_price);
    }

    public function test_the_receiving_plan_says_which_drugs_are_new(): void
    {
        [$owner, $pharmacy] = $this->buyer('rc-plan');
        $this->shelf($pharmacy, 'Amoxicillin 500mg', quantity: 40, cost: 5000, selling: 9000);

        $supplier = $this->supplier('Barada');
        $order = Order::create([
            'supplier_id' => $supplier->id,
            'pharmacy_id' => $pharmacy->id,
            'date' => now()->toDateString(),
            'total_price' => 220000,
            'payment_method' => 'cash',
            'status' => 'pending',
        ]);
        $known = $this->offer($supplier, 'Amoxicillin 500mg', cost: 7000, retail: 12500);
        $fresh = $this->offer($supplier, 'Cefixime 400mg', cost: 15000, retail: 22000);
        $this->line($order, $known, 10, 7000);
        $this->line($order, $fresh, 10, 15000);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $plan = $this->getJson('/api/orders/'.$order->id.'/receiving-plan', $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('order.supplier_name', 'Barada');

        $items = collect($plan->json('items'))->keyBy('name');

        $this->assertFalse($items['Amoxicillin 500mg']['is_new']);
        $this->assertSame(9000.0, (float) $items['Amoxicillin 500mg']['current_selling_price']);
        // Nothing to decide: their own price is the suggestion.
        $this->assertSame(9000.0, (float) $items['Amoxicillin 500mg']['suggested_selling_price']);

        $this->assertTrue($items['Cefixime 400mg']['is_new']);
        $this->assertNull($items['Cefixime 400mg']['current_selling_price']);
        $this->assertSame(22000.0, (float) $items['Cefixime 400mg']['suggested_selling_price']);
        // The price agreed on the order, not the catalogue's price today.
        $this->assertSame(15000.0, (float) $items['Cefixime 400mg']['unit_cost']);
    }

    public function test_there_is_nothing_to_plan_for_an_order_already_received(): void
    {
        [$owner, $pharmacy] = $this->buyer('rc-done');
        $order = $this->order($pharmacy, 'Cefixime 400mg', quantity: 10, paid: 15000);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/orders/'.$order->id.'/receive', [], $this->at($pharmacy))->assertOk();

        $this->getJson('/api/orders/'.$order->id.'/receiving-plan', $this->at($pharmacy))
            ->assertStatus(409)
            ->assertJsonPath('code', 'order_not_pending');
    }

    public function test_another_pharmacy_cannot_read_the_plan(): void
    {
        [, $theirPharmacy] = $this->buyer('rc-theirs');
        [$mine, $myPharmacy] = $this->buyer('rc-mine');
        $order = $this->order($theirPharmacy, 'Cefixime 400mg', quantity: 10, paid: 15000);

        Sanctum::actingAs($mine, ['*'], 'pharmacist');
        $this->getJson('/api/orders/'.$order->id.'/receiving-plan', $this->at($myPharmacy))
            ->assertForbidden();
    }

    public function test_a_negative_shelf_price_is_refused(): void
    {
        [$owner, $pharmacy] = $this->buyer('rc-negative');
        $order = $this->order($pharmacy, 'Cefixime 400mg', quantity: 10, paid: 15000);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/orders/'.$order->id.'/receive', [
            'selling_prices' => [OrderItem::sole()->medicine_id => -1],
        ], $this->at($pharmacy))->assertUnprocessable();

        $this->assertSame('pending', $order->fresh()->status);
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

    /** A pending single-line order for the pharmacy. */
    private function order(
        Pharmacy $pharmacy,
        string $name,
        int $quantity,
        int $paid,
        int $suggestedRetail = 20000,
    ): Order {
        $supplier = $this->supplier('Barada-'.$name);
        $order = Order::create([
            'supplier_id' => $supplier->id,
            'pharmacy_id' => $pharmacy->id,
            'date' => now()->toDateString(),
            'total_price' => $paid * $quantity,
            'payment_method' => 'cash',
            'status' => 'pending',
        ]);

        $this->line($order, $this->offer($supplier, $name, $paid, $suggestedRetail), $quantity, $paid);

        return $order;
    }

    private function line(Order $order, Medicine $offer, int $quantity, int $paid): OrderItem
    {
        return OrderItem::create([
            'order_id' => $order->id,
            'medicine_id' => $offer->id,
            'quantity' => $quantity,
            'price' => $paid,
        ]);
    }

    private function shelf(Pharmacy $pharmacy, string $name, int $quantity, int $cost, int $selling): Medicine
    {
        return Medicine::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => $name,
            'category_medicine' => 'Antibiotics',
            'cost_price' => $cost,
            'selling_price' => $selling,
            'manufacturer' => 'Qasioun Labs',
            'quantity' => $quantity,
            'reorder_level' => 10,
            'expire_date' => now()->addMonths(18)->toDateString(),
        ]);
    }

    private function supplier(string $name): Supplier
    {
        return Supplier::create([
            'name' => str_contains($name, '-') ? explode('-', $name)[0] : $name,
            'phone' => '09'.substr((string) (crc32($name) % 100000000), 0, 8),
            'email' => md5($name).'@example.demo',
            'address' => 'Damascus',
        ]);
    }

    private function offer(Supplier $supplier, string $name, int $cost, int $retail): Medicine
    {
        return Medicine::create([
            'pharmacy_id' => null,
            'supplier_id' => $supplier->id,
            'name' => $name,
            'category_medicine' => 'Antibiotics',
            'cost_price' => $cost,
            'selling_price' => $retail,
            'manufacturer' => 'Qasioun Labs',
            'quantity' => 500,
            'reorder_level' => 10,
            'expire_date' => now()->addMonths(18)->toDateString(),
        ]);
    }
}
