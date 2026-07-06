<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    use HasFactory;

    protected $fillable = ['pharmacist_id', 'stars', 'date'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function pharmacist()
    {
        return $this->belongsTo(Pharmacist::class);
    }
}
