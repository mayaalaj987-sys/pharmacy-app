<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Medicine extends Model
{
    use HasFactory;

    protected $fillable = [
        'pharmacy_id', 'supplier_id', 'name', 'category_medicine',
        'cost_price', 'selling_price', 'manufacturer', 'quantity',
        'reorder_level', 'expire_date', 'qr_code',
    ];

    protected function casts(): array
    {
        return [
            'expire_date' => 'date',
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
        ];
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('quantity', '<=', 'reorder_level')->where('quantity', '>', 0);
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('quantity', 0);
    }

    public function scopeExpiringSoon($query)
    {
        return $query->whereNotNull('expire_date')
            ->whereBetween('expire_date', [now(), now()->addMonths(3)]);
    }
}
