<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One drug on one sale, drawn from one batch.
 *
 * A line per batch rather than per drug: a sale of 25 boxes that empties the
 * short-dated batch and takes the rest from a fresh one is two rows, so the
 * recorded cost is the cost of the boxes that actually left.
 *
 * Both prices are frozen here. `price` is what the customer paid and `cost_price`
 * is what those boxes cost the pharmacy — read back off the medicine instead and
 * a finished sale would reprice itself every time fresh stock blended the cost.
 */
class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = ['sale_id', 'medicine_id', 'quantity', 'price', 'cost_price'];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }
}
