<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A pharmacy owner's verdict on the application itself.
 *
 * One per owner, revisable. A rating you cannot change is a snapshot of one bad
 * afternoon kept forever, and the note is where the reason lives — a star on
 * its own says somebody was unhappy without saying why, which is the one thing
 * feedback has to do.
 */
class Rating extends Model
{
    use HasFactory;

    protected $fillable = ['pharmacist_id', 'stars', 'note', 'date'];

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
