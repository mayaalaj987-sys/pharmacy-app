<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Stock removed from the shelf without being sold, and what it cost.
 *
 * The drug's name and unit cost are copied onto the row rather than joined.
 * Both move — a drug can be repriced, and receiving blends the recorded cost —
 * and a loss booked last March must still read as it did in March.
 */
class StockWriteOff extends Model
{
    use HasFactory;

    /** Past its date. The till already refuses it; this is the paperwork. */
    public const REASON_EXPIRED = 'expired';

    /** Broken, spoiled, or a cold chain that lapsed. */
    public const REASON_DAMAGED = 'damaged';

    /** Unaccounted for at a stock count. */
    public const REASON_LOST = 'lost';

    /** Sent back to the supplier, usually a recall or a wrong delivery. */
    public const REASON_RETURNED = 'returned_to_supplier';

    public const REASONS = [
        self::REASON_EXPIRED,
        self::REASON_DAMAGED,
        self::REASON_LOST,
        self::REASON_RETURNED,
    ];

    protected $fillable = [
        'pharmacy_id', 'medicine_id', 'medicine_name', 'unit_cost',
        'quantity', 'reason', 'note', 'pharmacist_id', 'employee_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'decimal:2',
        ];
    }

    /** What this loss cost the pharmacy. */
    public function value(): float
    {
        return (float) $this->unit_cost * $this->quantity;
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    /**
     * Losses that belong in a profit figure.
     *
     * Stock sent back to the supplier is not a loss — it is either replaced or
     * refunded, and counting it would show a pharmacy losing money for
     * returning something it never wanted.
     */
    public function scopeCounted($query)
    {
        return $query->where('reason', '!=', self::REASON_RETURNED);
    }
}
