<?php

namespace Tests\Feature\Purchasing;

use App\Models\Medicine;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use App\Models\PurchaseCartItem;
use App\Models\Supplier;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Security\SecurityTestCase;

/**
 * What a pharmacy intends to buy, before it has bought it.
 *
 * The cart spans suppliers on purpose — it is where a pharmacist decides who to
 * buy each drug from, which they cannot do while an order is being placed one
 * supplier at a time.
 */
class PurchaseCartTest extends SecurityTestCase
{
    public function test_an_offer_goes_into_the_cart_and_is_totalled(): void
    {
        [$owner, $pharmacy] = $this->buyer('cart-add');
        $offer = $this->offer($this->supplier('A'), 'Amoxicillin 500mg', cost: 8000, stock: 100);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/purchase-cart', [
            'medicine_id' => $offer->id,
            'quantity' => 10,
        ], $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('item_count', 1)
            ->assertJsonPath('total', 80000)
            ->assertJsonPath('suppliers.0.items.0.quantity', 10)
            ->assertJsonPath('suppliers.0.items.0.subtotal', 80000)
            ->assertJsonPath('suppliers.0.items.0.added_by', PurchaseCartItem::ADDED_BY_PHARMACIST);
    }

    public function test_adding_the_same_offer_twice_raises_the_quantity(): void
    {
        // Otherwise one drug shows on two lines at the same price, and the
        // pharmacist has to add up their own cart.
        [$owner, $pharmacy] = $this->buyer('cart-twice');
        $offer = $this->offer($this->supplier('A'), 'Amoxicillin 500mg', cost: 8000, stock: 100);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/purchase-cart', ['medicine_id' => $offer->id, 'quantity' => 10], $this->at($pharmacy));
        $this->postJson('/api/purchase-cart', ['medicine_id' => $offer->id, 'quantity' => 5], $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('item_count', 1)
            ->assertJsonPath('suppliers.0.items.0.quantity', 15);

        $this->assertSame(1, PurchaseCartItem::count());
    }

    public function test_the_cart_groups_by_supplier_because_that_is_how_it_will_be_bought(): void
    {
        // orders.supplier_id is singular: one cart becomes one order per
        // supplier, and the pharmacist should see that split before paying.
        [$owner, $pharmacy] = $this->buyer('cart-group');
        $barada = $this->supplier('Barada');
        $shahba = $this->supplier('Al-Shahba');

        $first = $this->offer($barada, 'Amoxicillin 500mg', cost: 8000, stock: 100);
        $second = $this->offer($barada, 'Aspirin 100mg', cost: 2000, stock: 100);
        $third = $this->offer($shahba, 'Salbutamol Inhaler', cost: 18000, stock: 100);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        foreach ([$first, $second, $third] as $offer) {
            $this->postJson('/api/purchase-cart', ['medicine_id' => $offer->id, 'quantity' => 2], $this->at($pharmacy))
                ->assertOk();
        }

        $cart = $this->getJson('/api/purchase-cart', $this->at($pharmacy))->assertOk();

        $cart->assertJsonCount(2, 'suppliers')
            ->assertJsonPath('item_count', 3)
            ->assertJsonPath('total', 56000);

        $subtotals = collect($cart->json('suppliers'))
            ->mapWithKeys(fn (array $group) => [$group['supplier']['name'] => $group['subtotal']]);

        $this->assertSame(20000.0, (float) $subtotals['Barada']);
        $this->assertSame(36000.0, (float) $subtotals['Al-Shahba']);
    }

    public function test_the_cart_names_a_cheaper_supplier_for_the_same_drug(): void
    {
        // The whole reason to hold several suppliers in one cart: the same box
        // at two prices, side by side, before any money moves.
        [$owner, $pharmacy] = $this->buyer('cart-cheaper');
        $dear = $this->offer($this->supplier('Barada'), 'Amoxicillin 500mg', cost: 8000, stock: 100);
        $cheap = $this->offer($this->supplier('Al-Shahba'), 'Amoxicillin 500mg', cost: 7000, stock: 100);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/purchase-cart', ['medicine_id' => $dear->id, 'quantity' => 10], $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('suppliers.0.items.0.cheaper_elsewhere.medicine_id', $cheap->id)
            ->assertJsonPath('suppliers.0.items.0.cheaper_elsewhere.supplier_name', 'Al-Shahba')
            ->assertJsonPath('suppliers.0.items.0.cheaper_elsewhere.saving', 10000);
    }

    public function test_a_cheaper_supplier_who_cannot_fill_the_order_is_not_offered(): void
    {
        // Advertising a saving and then refusing the purchase at checkout is
        // worse than not mentioning it.
        [$owner, $pharmacy] = $this->buyer('cart-short');
        $dear = $this->offer($this->supplier('Barada'), 'Amoxicillin 500mg', cost: 8000, stock: 100);
        $this->offer($this->supplier('Al-Shahba'), 'Amoxicillin 500mg', cost: 7000, stock: 3);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/purchase-cart', ['medicine_id' => $dear->id, 'quantity' => 10], $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('suppliers.0.items.0.cheaper_elsewhere', null);
    }

    public function test_the_cheapest_line_is_told_it_is_already_the_cheapest(): void
    {
        [$owner, $pharmacy] = $this->buyer('cart-best');
        $this->offer($this->supplier('Barada'), 'Amoxicillin 500mg', cost: 8000, stock: 100);
        $cheap = $this->offer($this->supplier('Al-Shahba'), 'Amoxicillin 500mg', cost: 7000, stock: 100);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/purchase-cart', ['medicine_id' => $cheap->id, 'quantity' => 10], $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('suppliers.0.items.0.cheaper_elsewhere', null);
    }

    public function test_a_line_the_supplier_can_no_longer_fill_is_flagged_not_hidden(): void
    {
        // The catalogue is shared, so another pharmacy can empty it between
        // adding and paying. Dropping the line silently would lose the intent.
        [$owner, $pharmacy] = $this->buyer('cart-gone');
        $offer = $this->offer($this->supplier('Barada'), 'Amoxicillin 500mg', cost: 8000, stock: 100);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/purchase-cart', ['medicine_id' => $offer->id, 'quantity' => 50], $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('suppliers.0.items.0.available', true)
            ->assertJsonPath('unavailable_count', 0);

        $offer->update(['quantity' => 5]);

        $this->getJson('/api/purchase-cart', $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('item_count', 1)
            ->assertJsonPath('suppliers.0.items.0.available', false)
            ->assertJsonPath('unavailable_count', 1);
    }

    public function test_a_pharmacy_stock_row_cannot_be_bought(): void
    {
        // Pharmacy stock and supplier offers share a table. Buying from your
        // own shelf is not a thing, and the id alone does not say which is which.
        [$owner, $pharmacy] = $this->buyer('cart-own');
        $ownStock = Medicine::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => 'Amoxicillin 500mg',
            'category_medicine' => 'Antibiotics',
            'cost_price' => 8000,
            'selling_price' => 12500,
            'quantity' => 30,
            'reorder_level' => 5,
        ]);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/purchase-cart', ['medicine_id' => $ownStock->id, 'quantity' => 1], $this->at($pharmacy))
            ->assertStatus(422)
            ->assertJsonPath('code', 'not_a_supplier_offer');

        $this->assertSame(0, PurchaseCartItem::count());
    }

    public function test_setting_a_quantity_to_zero_removes_the_line(): void
    {
        [$owner, $pharmacy] = $this->buyer('cart-zero');
        $offer = $this->offer($this->supplier('Barada'), 'Amoxicillin 500mg', cost: 8000, stock: 100);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/purchase-cart', ['medicine_id' => $offer->id, 'quantity' => 10], $this->at($pharmacy));
        $line = PurchaseCartItem::sole();

        $this->patchJson('/api/purchase-cart/'.$line->id, ['quantity' => 0], $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('item_count', 0)
            ->assertJsonPath('total', 0);

        $this->assertSame(0, PurchaseCartItem::count());
    }

    public function test_editing_a_suggested_line_makes_it_the_pharmacists_own(): void
    {
        // A quantity the pharmacist chose must stop being presented as
        // something the app is still waiting for a verdict on.
        [$owner, $pharmacy] = $this->buyer('cart-adopt');
        $offer = $this->offer($this->supplier('Barada'), 'Amoxicillin 500mg', cost: 8000, stock: 100);

        $line = PurchaseCartItem::create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $offer->id,
            'quantity' => 40,
            'added_by' => PurchaseCartItem::ADDED_BY_APP,
        ]);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->getJson('/api/purchase-cart', $this->at($pharmacy))
            ->assertJsonPath('suggested_count', 1);

        $this->patchJson('/api/purchase-cart/'.$line->id, ['quantity' => 60], $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('suggested_count', 0)
            ->assertJsonPath('suppliers.0.items.0.quantity', 60)
            ->assertJsonPath('suppliers.0.items.0.added_by', PurchaseCartItem::ADDED_BY_PHARMACIST);
    }

    public function test_one_pharmacy_cannot_read_or_touch_another_pharmacys_cart(): void
    {
        [$mine, $myPharmacy] = $this->buyer('cart-mine');
        [$theirs, $theirPharmacy] = $this->buyer('cart-theirs');
        $offer = $this->offer($this->supplier('Barada'), 'Amoxicillin 500mg', cost: 8000, stock: 100);

        Sanctum::actingAs($theirs, ['*'], 'pharmacist');
        $this->postJson('/api/purchase-cart', ['medicine_id' => $offer->id, 'quantity' => 7], $this->at($theirPharmacy))
            ->assertOk();
        $theirLine = PurchaseCartItem::sole();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($mine, ['*'], 'pharmacist');

        // Not even the existence of the line is confirmed.
        $this->getJson('/api/purchase-cart', $this->at($myPharmacy))
            ->assertOk()
            ->assertJsonPath('item_count', 0);

        $this->patchJson('/api/purchase-cart/'.$theirLine->id, ['quantity' => 1], $this->at($myPharmacy))
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');

        $this->deleteJson('/api/purchase-cart/'.$theirLine->id, [], $this->at($myPharmacy))
            ->assertNotFound();

        // Clearing my cart leaves theirs alone.
        $this->deleteJson('/api/purchase-cart', [], $this->at($myPharmacy))->assertOk();

        $this->assertSame(7, $theirLine->fresh()->quantity);
    }

    public function test_clearing_empties_the_whole_cart(): void
    {
        [$owner, $pharmacy] = $this->buyer('cart-clear');
        $barada = $this->supplier('Barada');

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        foreach (['Amoxicillin 500mg', 'Aspirin 100mg'] as $name) {
            $offer = $this->offer($barada, $name, cost: 5000, stock: 50);
            $this->postJson('/api/purchase-cart', ['medicine_id' => $offer->id, 'quantity' => 3], $this->at($pharmacy));
        }

        $this->deleteJson('/api/purchase-cart', [], $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('item_count', 0)
            ->assertJsonCount(0, 'suppliers');

        $this->assertSame(0, PurchaseCartItem::count());
    }

    public function test_a_nonsense_quantity_is_refused(): void
    {
        [$owner, $pharmacy] = $this->buyer('cart-bad');
        $offer = $this->offer($this->supplier('Barada'), 'Amoxicillin 500mg', cost: 8000, stock: 100);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        foreach ([0, -5] as $quantity) {
            $this->postJson('/api/purchase-cart', ['medicine_id' => $offer->id, 'quantity' => $quantity], $this->at($pharmacy))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('quantity');
        }

        $this->assertSame(0, PurchaseCartItem::count());
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
