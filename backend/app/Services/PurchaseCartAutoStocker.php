<?php

namespace App\Services;

use App\Models\Medicine;
use App\Models\Notification;
use App\Models\Pharmacy;
use App\Models\PurchaseCartItem;

/**
 * Queues a restock when a sale leaves a drug running low.
 *
 * The pharmacist notices a shortage when they reach for a box and it is not
 * there, which is a day or two after the moment to have ordered it. The app
 * knows the instant it happens, so it puts the restock in the cart and says so.
 *
 * Queuing, not buying. Nothing in the cart reserves stock or costs money, so a
 * suggestion the pharmacist disagrees with is removed and nothing has happened
 * — which is why there is no separate approve-or-reject flow to build. The line
 * is marked as the app's until they touch it.
 */
class PurchaseCartAutoStocker
{
    /**
     * How much of a cushion to restock to, as a multiple of the reorder level.
     *
     * Buying back only to the reorder level would leave the drug one sale away
     * from being low again, and this notification firing over and over.
     */
    private const TARGET_MULTIPLE = 2;

    /**
     * Considers a stock row that a sale has just drawn down.
     *
     * Returns whether it has spoken for this drug. A caller that gets false
     * should fall back to a plain low-stock warning; getting true means either
     * a restock was queued and announced, or one is already waiting in the cart
     * and saying so again would be nagging.
     */
    public function consider(Pharmacy $pharmacy, Medicine $stock): bool
    {
        // Across every batch, not just the one the sale drew from. A pharmacy
        // holding two hundred boxes in a fresh batch is not running low because
        // the older batch beside it is down to three.
        $onHand = $this->onHand($pharmacy, $stock->name);

        if ($onHand > $stock->reorder_level) {
            return false;
        }

        // With no reorder level there is no basis for a quantity, and inventing
        // one is worse than leaving the decision alone.
        if ($stock->reorder_level < 1) {
            return false;
        }

        if ($this->alreadyQueued($pharmacy, $stock->name)) {
            return true;
        }

        $wanted = self::TARGET_MULTIPLE * $stock->reorder_level - $onHand;
        $offer = $this->bestOffer($stock->name, $wanted);

        if (! $offer) {
            return false;
        }

        $quantity = min($wanted, $offer->quantity);

        PurchaseCartItem::create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $offer->id,
            'quantity' => $quantity,
            'added_by' => PurchaseCartItem::ADDED_BY_APP,
        ]);

        Notification::notify(
            $pharmacy->id,
            $onHand === 0 ? 'Out of stock' : 'Running low',
            $stock->name.' is '
                .($onHand === 0 ? 'out of stock' : 'down to '.$onHand)
                .'. '.$quantity.' added to your purchase cart from '
                .($offer->supplier?->name ?? 'a supplier')
                .'. Review it before buying.',
            $onHand === 0 ? 'out_of_stock' : 'low_stock',
            Notification::AUDIENCE_OWNER,
            // The cart, not the drug: the action this asks for is a review of
            // what was queued, and sending the pharmacist to the medicine
            // record would leave them to find the cart themselves.
            $stock->id,
        );

        return true;
    }

    /**
     * Every box of this drug the pharmacy holds, across all its batches.
     *
     * A delivery with a different expiry date lands as its own row, so one drug
     * is several rows and no single one of them speaks for the shelf.
     */
    private function onHand(Pharmacy $pharmacy, string $name): int
    {
        return (int) Medicine::where('pharmacy_id', $pharmacy->id)
            ->where('name', $name)
            ->sum('quantity');
    }

    /**
     * Whether this drug is already waiting in the cart, from any supplier.
     *
     * Matched by name rather than by offer, because the pharmacist may have
     * switched the line to a different supplier — it is still the same
     * intention to restock, and queuing it twice would double the order.
     */
    private function alreadyQueued(Pharmacy $pharmacy, string $name): bool
    {
        return PurchaseCartItem::where('purchase_cart_items.pharmacy_id', $pharmacy->id)
            ->join('medicines', 'medicines.id', '=', 'purchase_cart_items.medicine_id')
            ->where('medicines.name', $name)
            ->exists();
    }

    /**
     * Who to buy this drug from.
     *
     * The cheapest supplier who can fill the whole order, because that is the
     * saving worth taking automatically. If nobody can, the one holding the
     * most — a part delivery from a stocked supplier beats a token handful from
     * a cheap one who has almost nothing left.
     *
     * Expired offers are excluded outright. Checkout refuses them anyway, so
     * queuing one would put a line in the cart that can never be bought — and
     * an expired box is often the cheapest, which is exactly how it would win.
     */
    private function bestOffer(string $name, int $wanted): ?Medicine
    {
        $offers = Medicine::whereNull('pharmacy_id')
            ->where('name', $name)
            ->where('quantity', '>', 0)
            ->where(function ($query) {
                $query->whereNull('expire_date')
                    ->orWhereDate('expire_date', '>=', now()->startOfDay());
            })
            ->with('supplier:id,name')
            ->get();

        if ($offers->isEmpty()) {
            return null;
        }

        $canFill = $offers->filter(fn (Medicine $offer) => $offer->quantity >= $wanted);

        if ($canFill->isNotEmpty()) {
            return $canFill->sortBy(fn (Medicine $offer) => (float) $offer->cost_price)->first();
        }

        return $offers
            ->sortBy(fn (Medicine $offer) => [-$offer->quantity, (float) $offer->cost_price])
            ->first();
    }
}
