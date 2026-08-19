<?php

namespace Tests\Feature\Purchasing;

use App\Models\Medicine;
use App\Models\Notification;
use App\Models\Pharmacy;
use Tests\Feature\Security\SecurityTestCase;

/**
 * Telling a pharmacy its stock is running out of shelf life.
 *
 * Low stock has always shouted — it queues the restock and says so. Expiry,
 * which costs money rather than a missed sale, said nothing until the till
 * refused a box, months after the last moment anyone could act.
 *
 * Two warnings and a verdict, because each calls for a different action, and
 * each fires once per batch: a daily repeat of the same sentence is how people
 * learn to ignore the bell.
 */
class ExpiryWarningTest extends SecurityTestCase
{
    public function test_stock_two_months_out_gets_a_first_warning(): void
    {
        $pharmacy = $this->shop('exp-60');
        $this->shelf($pharmacy, 'Azithromycin 250mg', quantity: 41, expiresInDays: 50);

        $this->artisan('stock:expiry-check')->assertSuccessful();

        $notification = Notification::sole();
        $this->assertSame('expiring_60', $notification->type);
        $this->assertStringContainsString('Still time to sell it at the usual price', $notification->message);
    }

    public function test_the_message_says_what_is_at_stake(): void
    {
        // "Some Azithromycin is expiring" is a shrug. A number is a decision.
        $pharmacy = $this->shop('exp-value');
        $this->shelf($pharmacy, 'Azithromycin 250mg', quantity: 41, expiresInDays: 50, cost: 12000);

        $this->artisan('stock:expiry-check')->assertSuccessful();

        $this->assertStringContainsString('41 x Azithromycin 250mg', Notification::sole()->message);
        $this->assertStringContainsString('492000', Notification::sole()->message);
    }

    public function test_two_weeks_out_the_advice_changes_to_discount_it(): void
    {
        $pharmacy = $this->shop('exp-14');
        $this->shelf($pharmacy, 'Azithromycin 250mg', quantity: 41, expiresInDays: 10);

        $this->artisan('stock:expiry-check')->assertSuccessful();

        $this->assertSame('expiring_14', Notification::sole()->type);
        $this->assertStringContainsString('Discount it now', Notification::sole()->message);
    }

    public function test_stock_already_past_its_date_is_told_to_write_it_off(): void
    {
        // Nothing else can be done with it, and until it is booked the loss
        // sits in the valuation pretending to be an asset.
        $pharmacy = $this->shop('exp-dead');
        $this->shelf($pharmacy, 'Azithromycin 250mg', quantity: 41, expiresInDays: -600);

        $this->artisan('stock:expiry-check')->assertSuccessful();

        $this->assertSame('expired_stock', Notification::sole()->type);
        $this->assertStringContainsString('write it off', Notification::sole()->message);
    }

    public function test_a_batch_that_slipped_past_gets_the_verdict_not_the_early_warning(): void
    {
        // Most urgent stage wins. Telling someone in November that stock expired
        // in March "still has time to sell" is worse than saying nothing.
        $pharmacy = $this->shop('exp-slipped');
        $this->shelf($pharmacy, 'Azithromycin 250mg', quantity: 5, expiresInDays: -30);

        $this->artisan('stock:expiry-check')->assertSuccessful();

        $this->assertSame('expired_stock', Notification::sole()->type);
    }

    public function test_each_stage_fires_once_however_often_the_check_runs(): void
    {
        // Scheduled daily. Repeating the same sentence every morning is how a
        // notification bell becomes wallpaper.
        $pharmacy = $this->shop('exp-once');
        $this->shelf($pharmacy, 'Azithromycin 250mg', quantity: 41, expiresInDays: 50);

        $this->artisan('stock:expiry-check')->assertSuccessful();
        $this->artisan('stock:expiry-check')->assertSuccessful();
        $this->artisan('stock:expiry-check')->assertSuccessful();

        $this->assertSame(1, Notification::count());
    }

    public function test_the_same_batch_warns_again_as_it_gets_closer(): void
    {
        // A second warning is not a repeat: two months out and two weeks out
        // call for different things.
        $pharmacy = $this->shop('exp-again');
        $batch = $this->shelf($pharmacy, 'Azithromycin 250mg', quantity: 41, expiresInDays: 50);

        $this->artisan('stock:expiry-check')->assertSuccessful();

        $batch->update(['expire_date' => now()->addDays(9)->toDateString()]);
        $this->artisan('stock:expiry-check')->assertSuccessful();

        $this->assertSame(2, Notification::count());
        $this->assertSame(
            ['expiring_60', 'expiring_14'],
            Notification::orderBy('id')->pluck('type')->all(),
        );
    }

    public function test_two_batches_of_one_drug_are_warned_about_separately(): void
    {
        // They expire on different days and each is its own decision. Matching
        // on the drug name would silence the second one.
        $pharmacy = $this->shop('exp-batches');
        $this->shelf($pharmacy, 'Azithromycin 250mg', quantity: 5, expiresInDays: 10);
        $this->shelf($pharmacy, 'Azithromycin 250mg', quantity: 40, expiresInDays: 55);

        $this->artisan('stock:expiry-check')->assertSuccessful();

        $this->assertSame(2, Notification::count());
    }

    public function test_stock_with_plenty_of_life_left_is_not_mentioned(): void
    {
        $pharmacy = $this->shop('exp-fine');
        $this->shelf($pharmacy, 'Azithromycin 250mg', quantity: 41, expiresInDays: 400);

        $this->artisan('stock:expiry-check')->assertSuccessful();

        $this->assertSame(0, Notification::count());
    }

    public function test_an_empty_batch_is_not_worth_warning_about(): void
    {
        // Nothing is at stake: there are no boxes to lose.
        $pharmacy = $this->shop('exp-empty');
        $this->shelf($pharmacy, 'Azithromycin 250mg', quantity: 0, expiresInDays: -10);

        $this->artisan('stock:expiry-check')->assertSuccessful();

        $this->assertSame(0, Notification::count());
    }

    public function test_the_supplier_catalogue_is_not_a_pharmacys_problem(): void
    {
        // Those rows belong to a wholesaler and are renewed by their own job.
        Medicine::create([
            'pharmacy_id' => null,
            'supplier_id' => null,
            'name' => 'Azithromycin 250mg',
            'category_medicine' => 'Antibiotics',
            'cost_price' => 12000,
            'selling_price' => 18500,
            'quantity' => 500,
            'reorder_level' => 10,
            'expire_date' => now()->subDays(10)->toDateString(),
        ]);

        $this->artisan('stock:expiry-check')->assertSuccessful();

        $this->assertSame(0, Notification::count());
    }

    public function test_a_dry_run_sends_nothing(): void
    {
        $pharmacy = $this->shop('exp-dry');
        $this->shelf($pharmacy, 'Azithromycin 250mg', quantity: 41, expiresInDays: 10);

        $this->artisan('stock:expiry-check --dry-run')->assertSuccessful();

        $this->assertSame(0, Notification::count());
    }

    public function test_each_pharmacy_hears_only_about_its_own_shelf(): void
    {
        $mine = $this->shop('exp-mine');
        $theirs = $this->shop('exp-theirs');
        $this->shelf($mine, 'Azithromycin 250mg', quantity: 5, expiresInDays: 10);
        $this->shelf($theirs, 'Amoxicillin 500mg', quantity: 5, expiresInDays: 10);

        $this->artisan('stock:expiry-check')->assertSuccessful();

        $this->assertSame(1, Notification::where('pharmacy_id', $mine->id)->count());
        $this->assertStringContainsString(
            'Azithromycin 250mg',
            Notification::where('pharmacy_id', $mine->id)->sole()->message,
        );
    }

    private function shop(string $suffix): Pharmacy
    {
        return $this->pharmacy($this->pharmacist($suffix), $suffix);
    }

    private function shelf(
        Pharmacy $pharmacy,
        string $name,
        int $quantity,
        int $expiresInDays,
        int $cost = 12000,
    ): Medicine {
        return Medicine::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => $name,
            'category_medicine' => 'Antibiotics',
            'cost_price' => $cost,
            'selling_price' => $cost * 1.5,
            'manufacturer' => 'Ugarit Pharma',
            'quantity' => $quantity,
            'reorder_level' => 10,
            'expire_date' => now()->addDays($expiresInDays)->toDateString(),
        ]);
    }
}
