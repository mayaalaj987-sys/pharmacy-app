<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Part of a sale coming back.
 *
 * An entry of its own rather than an edit to the sale. A sale is a financial
 * record of what left the shop and what was charged for it; rewriting one to
 * make a refund fit would destroy the evidence of what actually happened. The
 * return points at the line it reverses and the two are read together.
 */
class SaleReturn extends Model
{
    use HasFactory;

    /** The customer simply did not want it. Back on the shelf. */
    public const REASON_UNWANTED = 'unwanted';

    /** The wrong box was handed over. Back on the shelf. */
    public const REASON_WRONG_ITEM = 'wrong_item';

    /**
     * It came back damaged.
     *
     * Refunded but never restocked — it is written off instead. Putting a
     * broken box back on the shelf to be sold to the next customer is the one
     * outcome a pharmacy must not allow.
     */
    public const REASON_DAMAGED = 'damaged';

    public const REASONS = [
        self::REASON_UNWANTED,
        self::REASON_WRONG_ITEM,
        self::REASON_DAMAGED,
    ];

    /**
     * How long a customer has to bring something back.
     *
     * Two days. Medicine leaves the pharmacy's control the moment it leaves the
     * counter — nobody can vouch for how it was stored — so the window is short
     * enough that the box has plausibly not left a bag.
     */
    public const WINDOW_HOURS = 48;

    protected $fillable = [
        'pharmacy_id', 'sale_id', 'sale_item_id', 'quantity',
        'refund_amount', 'reason', 'note', 'pharmacist_id', 'employee_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'refund_amount' => 'decimal:2',
        ];
    }

    /** Whether the returned boxes go back on the shelf or into the bin. */
    public function isRestockable(): bool
    {
        return $this->reason !== self::REASON_DAMAGED;
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function saleItem()
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }
}
