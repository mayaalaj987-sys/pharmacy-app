<?php

namespace App\Console\Commands;

use App\Models\Medicine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the supplier catalogue alive between deliveries.
 *
 * Nothing else ever puts stock back. Placing an order reserves units off the
 * shared catalogue and only a cancellation returns them, so left alone the
 * suppliers drain to nothing and the platform becomes unable to sell anything —
 * with no way to recover. Prices have the mirror problem: frozen at whatever
 * they were seeded as, so the cheapest supplier is the same supplier forever
 * and the weighted-average cost at receiving has nothing to average.
 *
 * This stands in for the wholesaler telling us what they have. It is not a real
 * feed and does not pretend to be; it is what keeps the numbers moving until
 * one exists.
 *
 * Everything is scaled by `--days`, so a week of missed runs caught up in one
 * go lands in roughly the same place as seven daily runs. Only catalogue rows
 * are touched — a pharmacy's own shelf is never written to from here.
 */
class RefreshSupplierCatalogue extends Command
{
    protected $signature = 'catalogue:refresh
        {--days=1 : How many days of activity to apply, for catching up after missed runs}
        {--dry-run : Report what would change without writing anything}';

    protected $description = 'Restock supplier offers, drift their prices and renew batches close to expiry.';

    /** A day's delivery, as a multiple of the offer's reorder level. */
    private const RESTOCK_PER_DAY = 2;

    /** No supplier holds more than this multiple of the reorder level. */
    private const STOCK_CEILING = 8;

    /** How far a price may move in one day, and in one run however long. */
    private const DRIFT_PER_DAY_PERCENT = 2;

    private const MAX_DRIFT_PERCENT = 15;

    /**
     * The band a wholesale price stays inside, as a fraction of shelf price.
     *
     * A random walk with nothing holding it would eventually wander above what
     * the drug sells for. Anchoring to retail keeps every offer a plausible
     * wholesale price without having to remember what it started at.
     */
    private const MIN_MARGIN = 0.55;

    private const MAX_MARGIN = 0.80;

    /** Batches expiring inside this window are replaced by fresh ones. */
    private const EXPIRY_HORIZON_MONTHS = 6;

    public function handle(): int
    {
        $days = max(1, min(90, (int) $this->option('days')));
        $dryRun = (bool) $this->option('dry-run');

        $offers = Medicine::whereNull('pharmacy_id')->get();

        if ($offers->isEmpty()) {
            $this->warn('The supplier catalogue is empty. Seed it first.');

            return self::SUCCESS;
        }

        $restocked = 0;
        $repriced = 0;
        $renewed = 0;

        DB::transaction(function () use ($offers, $days, $dryRun, &$restocked, &$repriced, &$renewed) {
            foreach ($offers as $offer) {
                $changes = [];

                if (($quantity = $this->restock($offer, $days)) !== null) {
                    $changes['quantity'] = $quantity;
                    $restocked++;
                }

                if (($cost = $this->drift($offer, $days)) !== null) {
                    $changes['cost_price'] = $cost;
                    $repriced++;
                }

                if (($expiry = $this->renewBatch($offer)) !== null) {
                    $changes['expire_date'] = $expiry;
                    $renewed++;
                }

                if ($changes !== [] && ! $dryRun) {
                    $offer->update($changes);
                }
            }
        });

        $this->info(sprintf(
            '%s %d offers over %d day(s): %d restocked, %d repriced, %d batches renewed.',
            $dryRun ? 'Would refresh' : 'Refreshed',
            $offers->count(),
            $days,
            $restocked,
            $repriced,
            $renewed,
        ));

        return self::SUCCESS;
    }

    /**
     * A delivery to the supplier, or null when they are already well stocked.
     *
     * Deliberately gradual: an offer that ran dry recovers over several days
     * rather than snapping back to full overnight, so a pharmacy that emptied a
     * supplier still sees the consequence for a while.
     */
    private function restock(Medicine $offer, int $days): ?int
    {
        $reorder = max(1, $offer->reorder_level);
        $ceiling = $reorder * self::STOCK_CEILING;

        if ($offer->quantity >= $ceiling) {
            return null;
        }

        $delivery = $reorder * self::RESTOCK_PER_DAY * $days;
        // Not every supplier restocks every day.
        $delivery = (int) round($delivery * mt_rand(50, 150) / 100);

        return min($ceiling, $offer->quantity + max(1, $delivery));
    }

    /**
     * The offer's new wholesale price, or null if it has not moved.
     *
     * A small random walk rather than a trend. What matters is that the
     * cheapest supplier for a given drug changes over time — a comparison whose
     * answer never changes is one nobody needs to make twice.
     */
    private function drift(Medicine $offer, int $days): ?float
    {
        $retail = (float) $offer->selling_price;

        if ($retail <= 0) {
            return null;
        }

        $swing = min(self::MAX_DRIFT_PERCENT, self::DRIFT_PER_DAY_PERCENT * $days);
        $percent = mt_rand(-$swing * 100, $swing * 100) / 10000;

        $moved = (float) $offer->cost_price * (1 + $percent);
        $clamped = max(
            $retail * self::MIN_MARGIN,
            min($retail * self::MAX_MARGIN, $moved),
        );

        // To the nearest 100 SYP, as the catalogue is priced throughout.
        $rounded = round($clamped / 100) * 100;

        return $rounded === (float) $offer->cost_price ? null : $rounded;
    }

    /**
     * A fresh batch date for an offer running out of shelf life, else null.
     *
     * Without this the whole catalogue ages past its expiry and becomes
     * unbuyable — purchasing refuses expired stock, correctly. A wholesaler's
     * stock turns over; the dates on it move forward, and so must these.
     */
    private function renewBatch(Medicine $offer): ?string
    {
        if ($offer->expire_date !== null
            && $offer->expire_date->isAfter(now()->addMonths(self::EXPIRY_HORIZON_MONTHS))) {
            return null;
        }

        return now()->addMonths(mt_rand(12, 30))->toDateString();
    }
}
