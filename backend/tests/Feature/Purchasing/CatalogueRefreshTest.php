<?php

namespace Tests\Feature\Purchasing;

use App\Models\Medicine;
use App\Models\Supplier;
use Tests\Feature\Security\SecurityTestCase;

/**
 * Keeping the supplier catalogue alive.
 *
 * Nothing else puts stock back: ordering reserves units off the shared
 * catalogue and only a cancellation returns them, so without this the suppliers
 * drain to nothing and never recover.
 *
 * The command is deliberately random, so these assert the rules it must obey
 * rather than the figures it happens to produce.
 */
class CatalogueRefreshTest extends SecurityTestCase
{
    public function test_a_drained_supplier_gets_stock_back(): void
    {
        $offer = $this->offer('Amoxicillin 500mg', quantity: 0, reorder: 20);

        $this->artisan('catalogue:refresh')->assertSuccessful();

        $this->assertGreaterThan(0, $offer->fresh()->quantity);
    }

    public function test_recovery_is_gradual_rather_than_instant(): void
    {
        // A pharmacy that emptied a supplier should still see the consequence
        // tomorrow. Snapping back to full overnight makes the shared catalogue
        // meaningless.
        $offer = $this->offer('Amoxicillin 500mg', quantity: 0, reorder: 20);

        $this->artisan('catalogue:refresh')->assertSuccessful();

        // One day's delivery is a couple of reorder levels, not a full shelf.
        $this->assertLessThan(20 * 8, $offer->fresh()->quantity);
    }

    public function test_stock_never_climbs_past_the_ceiling(): void
    {
        // Otherwise a year of daily runs leaves every supplier holding millions.
        $offer = $this->offer('Amoxicillin 500mg', quantity: 150, reorder: 20);

        $this->artisan('catalogue:refresh --days=90')->assertSuccessful();

        $this->assertLessThanOrEqual(20 * 8, $offer->fresh()->quantity);
    }

    public function test_a_well_stocked_supplier_is_left_alone(): void
    {
        $offer = $this->offer('Amoxicillin 500mg', quantity: 500, reorder: 20);

        $this->artisan('catalogue:refresh')->assertSuccessful();

        $this->assertSame(500, $offer->fresh()->quantity);
    }

    public function test_a_price_stays_a_plausible_wholesale_price(): void
    {
        // A random walk with nothing holding it eventually wanders above what
        // the drug sells for. Anchoring the band to retail is what stops that.
        $offer = $this->offer('Amoxicillin 500mg', quantity: 200, reorder: 20, cost: 8000, retail: 12500);

        $this->artisan('catalogue:refresh --days=90')->assertSuccessful();

        $cost = (float) $offer->fresh()->cost_price;
        $this->assertGreaterThanOrEqual(12500 * 0.55, $cost);
        $this->assertLessThanOrEqual(12500 * 0.80, $cost);
    }

    public function test_prices_actually_move(): void
    {
        // The whole point: a cheapest-supplier comparison whose answer never
        // changes is one nobody needs to make twice. Across many offers at
        // least one must have shifted.
        for ($i = 1; $i <= 25; $i++) {
            $this->offer('Drug '.$i, quantity: 200, reorder: 20, cost: 8000, retail: 12500);
        }

        $before = Medicine::whereNull('pharmacy_id')->pluck('cost_price', 'id');
        $this->artisan('catalogue:refresh --days=5')->assertSuccessful();
        $after = Medicine::whereNull('pharmacy_id')->pluck('cost_price', 'id');

        $this->assertNotEquals($before->all(), $after->all());
    }

    public function test_a_batch_running_out_of_shelf_life_is_replaced(): void
    {
        // Purchasing refuses expired stock, correctly. Without renewal the
        // whole catalogue ages past its dates and becomes unbuyable.
        $offer = $this->offer('Amoxicillin 500mg', quantity: 200, reorder: 20, expiresInMonths: 2);

        $this->artisan('catalogue:refresh')->assertSuccessful();

        $this->assertTrue($offer->fresh()->expire_date->isAfter(now()->addMonths(11)));
    }

    public function test_an_already_expired_offer_is_brought_back(): void
    {
        $offer = $this->offer('Amoxicillin 500mg', quantity: 200, reorder: 20, expiresInMonths: -3);

        $this->artisan('catalogue:refresh')->assertSuccessful();

        $this->assertTrue($offer->fresh()->expire_date->isFuture());
    }

    public function test_a_batch_with_plenty_of_life_left_keeps_its_date(): void
    {
        $offer = $this->offer('Amoxicillin 500mg', quantity: 200, reorder: 20, expiresInMonths: 24);
        $original = $offer->expire_date->toDateString();

        $this->artisan('catalogue:refresh')->assertSuccessful();

        $this->assertSame($original, $offer->fresh()->expire_date->toDateString());
    }

    public function test_a_pharmacys_own_shelf_is_never_touched(): void
    {
        // The single most important rule here. This command exists to simulate a
        // wholesaler; a pharmacy's stock, cost and expiry are real records of
        // what they bought and must never be invented.
        $owner = $this->pharmacist('refresh-shelf');
        $pharmacy = $this->pharmacy($owner, 'refresh-shelf');

        $shelf = Medicine::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => 'Amoxicillin 500mg',
            'category_medicine' => 'Antibiotics',
            'cost_price' => 5000,
            'selling_price' => 9000,
            'quantity' => 3,
            'reorder_level' => 20,
            'expire_date' => now()->addMonth()->toDateString(),
        ]);
        $this->offer('Amoxicillin 500mg', quantity: 0, reorder: 20);

        $this->artisan('catalogue:refresh')->assertSuccessful();

        $after = $shelf->fresh();
        $this->assertSame(3, $after->quantity);
        $this->assertSame(5000.0, (float) $after->cost_price);
        $this->assertSame(
            now()->addMonth()->toDateString(),
            $after->expire_date->toDateString(),
        );
        $this->assertTrue($pharmacy->exists);
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $offer = $this->offer('Amoxicillin 500mg', quantity: 0, reorder: 20, expiresInMonths: 1);

        $this->artisan('catalogue:refresh --dry-run')->assertSuccessful();

        $this->assertSame(0, $offer->fresh()->quantity);
    }

    public function test_an_empty_catalogue_is_reported_not_crashed(): void
    {
        $this->artisan('catalogue:refresh')
            ->expectsOutputToContain('The supplier catalogue is empty.')
            ->assertSuccessful();
    }

    private function offer(
        string $name,
        int $quantity,
        int $reorder,
        int $cost = 8000,
        int $retail = 12500,
        int $expiresInMonths = 18,
    ): Medicine {
        return Medicine::create([
            'pharmacy_id' => null,
            'supplier_id' => $this->supplier()->id,
            'name' => $name,
            'category_medicine' => 'Antibiotics',
            'cost_price' => $cost,
            'selling_price' => $retail,
            'manufacturer' => 'Qasioun Labs',
            'quantity' => $quantity,
            'reorder_level' => $reorder,
            'expire_date' => now()->addMonths($expiresInMonths)->toDateString(),
        ]);
    }

    private function supplier(): Supplier
    {
        return Supplier::firstOrCreate(
            ['name' => 'Barada Pharma Distribution'],
            [
                'phone' => '0930111222',
                'email' => 'orders@barada-pharma.demo',
                'address' => 'Al-Mazzeh, Damascus',
            ],
        );
    }
}
