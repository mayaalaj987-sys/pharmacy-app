<?php

namespace App\Console\Commands;

use App\Models\Medicine;
use App\Models\Notification;
use Illuminate\Console\Command;

/**
 * Warns a pharmacy about stock running out of shelf life.
 *
 * The app already shouts when stock runs low — it queues the restock and says
 * so. Expiry, which costs the pharmacy money rather than a missed sale, said
 * nothing at all. A pharmacist found out by reaching for a box and having the
 * till refuse it, months after the last moment they could have sold it.
 *
 * Two warnings and a verdict, because they call for different actions: at two
 * months there is still time to sell the stock at its normal price, at two
 * weeks the answer is a discount, and once it is out of date the only thing
 * left to do is write it off so the loss lands somewhere.
 *
 * Each stage fires once per batch. A daily repeat of the same sentence is how
 * people learn to ignore a notification bell.
 */
class CheckStockExpiry extends Command
{
    protected $signature = 'stock:expiry-check {--dry-run : Report what would be sent without sending it}';

    protected $description = 'Warn pharmacies about stock approaching or past its expiry date.';

    /** Days out, longest first, paired with the notification type that marks it. */
    private const STAGES = [
        ['days' => 60, 'type' => 'expiring_60'],
        ['days' => 14, 'type' => 'expiring_14'],
        ['days' => 0, 'type' => 'expired_stock'],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $sent = 0;

        $batches = Medicine::query()
            ->whereNotNull('pharmacy_id')
            ->whereNotNull('expire_date')
            ->where('quantity', '>', 0)
            ->whereDate('expire_date', '<=', now()->addDays(self::STAGES[0]['days']))
            ->get();

        foreach ($batches as $batch) {
            $stage = $this->stageFor($batch);

            if ($stage === null || $this->alreadyWarned($batch, $stage['type'])) {
                continue;
            }

            $sent++;

            if (! $dryRun) {
                Notification::create([
                    'pharmacy_id' => $batch->pharmacy_id,
                    'title' => $this->title($stage['days']),
                    'message' => $this->message($batch, $stage['days']),
                    'type' => $stage['type'],
                    'audience' => Notification::AUDIENCE_OWNER,
                    'is_read' => false,
                    'date' => now(),
                ]);
            }
        }

        $this->info(sprintf(
            '%s %d warning(s) across %d batch(es) with a date in view.',
            $dryRun ? 'Would send' : 'Sent',
            $sent,
            $batches->count(),
        ));

        return self::SUCCESS;
    }

    /**
     * The most urgent stage this batch has reached, or null if it is still fine.
     *
     * Most urgent first, so a batch that has sat unnoticed until it expired gets
     * the verdict rather than a two-month warning it can no longer act on.
     *
     * @return array{days: int, type: string}|null
     */
    private function stageFor(Medicine $batch): ?array
    {
        $daysLeft = (int) now()->startOfDay()->diffInDays($batch->expire_date->startOfDay(), false);

        foreach (array_reverse(self::STAGES) as $stage) {
            if ($daysLeft <= $stage['days']) {
                return $stage;
            }
        }

        return null;
    }

    /**
     * Whether this batch has already been through this stage.
     *
     * Keyed on the batch id in the message, which is unique to it — two batches
     * of the same drug expire on different days and each deserves its own
     * warning, so matching on the name alone would silence the second one.
     */
    private function alreadyWarned(Medicine $batch, string $type): bool
    {
        return Notification::where('pharmacy_id', $batch->pharmacy_id)
            ->where('type', $type)
            ->where('message', 'LIKE', '%[batch '.$batch->id.']%')
            ->exists();
    }

    private function title(int $days): string
    {
        return match ($days) {
            60 => 'Expiring in two months',
            14 => 'Expiring in two weeks',
            default => 'Expired stock',
        };
    }

    /**
     * Says what is at stake and what to do about it.
     *
     * The value is the point. "Some Azithromycin is expiring" is a shrug;
     * "492,000 is about to be thrown away" is a decision.
     */
    private function message(Medicine $batch, int $days): string
    {
        $value = (float) $batch->cost_price * $batch->quantity;
        $stake = $batch->quantity.' x '.$batch->name.' ('.$value.') [batch '.$batch->id.']';

        return match ($days) {
            60 => $stake.' expires on '.$batch->expire_date->toDateString()
                .'. Still time to sell it at the usual price.',
            14 => $stake.' expires on '.$batch->expire_date->toDateString()
                .'. Discount it now or it is a loss.',
            default => $stake.' expired on '.$batch->expire_date->toDateString()
                .'. It cannot be sold — write it off so the loss is recorded.',
        };
    }
}
