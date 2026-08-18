<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One line in a pharmacy's purchase cart.
 *
 * A line names a supplier's offer, not a drug: the same drug from two suppliers
 * is two offers and may legitimately sit in the cart twice, at two prices. That
 * is what makes switching to a cheaper supplier a change of `medicine_id` and
 * nothing else.
 *
 * Prices are never copied here. The catalogue is shared and moves; a price
 * frozen at the moment something was added would quietly become a lie, and the
 * only price that matters is the one charged at checkout.
 */
class PurchaseCartItem extends Model
{
    use HasFactory;

    /** The pharmacist put this line here deliberately. */
    public const ADDED_BY_PHARMACIST = 'pharmacist';

    /** The app queued it because stock ran low. Awaiting a yes or a no. */
    public const ADDED_BY_APP = 'app';

    protected $fillable = ['pharmacy_id', 'medicine_id', 'quantity', 'added_by'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    /** The supplier's catalogue offer this line buys. */
    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    /** What this line costs at the catalogue's current price. */
    public function subtotal(): float
    {
        return (float) $this->medicine->cost_price * $this->quantity;
    }

    /** Whether the supplier can still fill this line in full. */
    public function isAvailable(): bool
    {
        return $this->medicine->quantity >= $this->quantity;
    }
}
