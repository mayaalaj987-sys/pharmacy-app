<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One job somebody held, running or finished.
 *
 * A record of what happened, not a statement of what is true now: current
 * employment still lives on the employee row. That separation is what keeps
 * this table free to accumulate without every policy in the app having to
 * consult it.
 */
class Employment extends Model
{
    use HasFactory;

    /** They resigned. */
    public const ENDED_BY_EMPLOYEE = 'employee';

    /** The pharmacy let them go. */
    public const ENDED_BY_PHARMACY = 'pharmacy';

    protected $fillable = [
        'employee_id', 'pharmacy_id', 'shift', 'salary',
        'started_at', 'ended_at', 'ended_by',
    ];

    protected function casts(): array
    {
        return [
            'salary' => 'float',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function isRunning(): bool
    {
        return $this->ended_at === null;
    }

    /** Whole days worked, or days so far if they are still there. */
    public function days(): int
    {
        return (int) $this->started_at->diffInDays($this->ended_at ?? now());
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function ratings()
    {
        return $this->hasMany(EmploymentRating::class);
    }

    /**
     * Whether this job can be judged yet.
     *
     * Only once it is over. Rating an employer while still working for them is
     * not a free judgement, and the period being rated has to be a finished one
     * for the verdict to mean anything.
     */
    public function isRateable(): bool
    {
        return $this->ended_at !== null;
    }

    public function scopeFinished($query)
    {
        return $query->whereNotNull('ended_at');
    }

    public function scopeRunning($query)
    {
        return $query->whereNull('ended_at');
    }
}
