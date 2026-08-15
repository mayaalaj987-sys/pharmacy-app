<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $guarded = [];

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
