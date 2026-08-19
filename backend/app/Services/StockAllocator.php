<?php

namespace App\Services;

use App\Exceptions\StockAllocationException;
use App\Models\Medicine;
use App\Models\Pharmacy;
use Illuminate\Support\Collection;

/**
 * Decides which physical stock a sale draws from.
 *
 * A pharmacy holds the same drug in several batches with different expiry
 * dates, and since a delivery now lands as its own row rather than merging into
 * the old one, "Amoxicillin 500mg" is several rows here too. The till knows the
 * drug; this works out the boxes.
 *
 * First-expired, first-out. It is the rule pharmacies actually work by, and the
 * only one that does not manufacture waste: selling the long-dated boxes first
 * guarantees the short-dated ones are still on the shelf when they expire.
 */
class StockAllocator
{
    /**
     * How to fill one line of a sale, oldest batch first.
     *
     * Returns the batches to draw from and how many from each, so the caller
     * can write a sale line per batch — which is what makes the recorded cost
     * of goods the cost of the boxes that actually left.
     *
     * Rows are locked, because two tills selling the last packet at once must
     * not both succeed. On SQLite the lock is a no-op, so the quantity check
     * and the decrement live inside the caller's transaction.
     *
     * @return list<array{batch: Medicine, quantity: int}>
     *
     * @throws StockAllocationException
     */
    public function allocate(Pharmacy $pharmacy, int $medicineId, int $quantity): array
    {
        $batches = $this->batchesFor($pharmacy, $medicineId);
        $drug = $batches->first()->name;

        $sellable = $batches
            ->filter(fn (Medicine $batch) => ! $this->hasExpired($batch))
            ->sortBy([
                // Undated stock goes last: a batch with no expiry has no claim
                // to being the most urgent, and pushing it ahead of a dated one
                // is how the dated one ends up being thrown away.
                fn (Medicine $a, Medicine $b) => ($a->expire_date === null) <=> ($b->expire_date === null),
                fn (Medicine $a, Medicine $b) => ($a->expire_date?->timestamp ?? 0) <=> ($b->expire_date?->timestamp ?? 0),
            ])
            ->values();

        $available = (int) $sellable->sum('quantity');

        if ($available <= 0) {
            // Nothing sellable at all. If there is stock but every batch is out
            // of date, say so — "none left" would send them looking for boxes
            // that are sitting right there.
            $expired = $batches->filter(fn (Medicine $batch) => $this->hasExpired($batch) && $batch->quantity > 0);

            if ($expired->isNotEmpty()) {
                throw StockAllocationException::expired(
                    $drug,
                    $expired->first()->expire_date?->toDateString(),
                );
            }

            throw StockAllocationException::notEnough($drug, 0);
        }

        if ($available < $quantity) {
            throw StockAllocationException::notEnough($drug, $available);
        }

        $plan = [];
        $outstanding = $quantity;

        foreach ($sellable as $batch) {
            if ($outstanding <= 0) {
                break;
            }

            $taken = min($outstanding, (int) $batch->quantity);

            if ($taken <= 0) {
                continue;
            }

            $plan[] = ['batch' => $batch, 'quantity' => $taken];
            $outstanding -= $taken;
        }

        return $plan;
    }

    /**
     * What the customer is charged, whichever batches end up filling the line.
     *
     * The price of the batch about to be sold, applied to the whole line. Two
     * batches of one drug can carry different prices — the pharmacist may have
     * repriced on a later delivery — and ringing the same box up at two prices
     * in one transaction is indefensible at the counter. This is also the price
     * the till displayed, since the screen shows the batch it will sell first.
     */
    public function priceFor(Pharmacy $pharmacy, int $medicineId): float
    {
        $plan = $this->allocate($pharmacy, $medicineId, 1);

        return (float) $plan[0]['batch']->selling_price;
    }

    /**
     * Every batch of the drug the given row belongs to.
     *
     * Resolved by name, because that is what identifies a drug once batches are
     * separate rows. Scoped to the pharmacy so a row id from another tenant
     * finds nothing.
     *
     * @return Collection<int, Medicine>
     */
    private function batchesFor(Pharmacy $pharmacy, int $medicineId): Collection
    {
        $anchor = Medicine::where('pharmacy_id', $pharmacy->id)->find($medicineId);

        if (! $anchor) {
            throw StockAllocationException::notEnough('#'.$medicineId, 0);
        }

        return Medicine::where('pharmacy_id', $pharmacy->id)
            ->where('name', $anchor->name)
            ->lockForUpdate()
            ->get();
    }

    private function hasExpired(Medicine $batch): bool
    {
        return $batch->expire_date !== null
            && $batch->expire_date->isBefore(now()->startOfDay());
    }
}
