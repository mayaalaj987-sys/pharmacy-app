<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A pharmacy's standing invitation to one person, for one shift.
 *
 * There is no "on hold" or "superseded" state. When someone is hired the other
 * offers they hold are left exactly as they are, and whether one can be acted
 * on is worked out at read time from the world as it stands — see
 * {@see self::isAcceptableFor()}. That is what lets an offer still be waiting
 * months later if the job they took does not work out.
 */
class JobOffer extends Model
{
    use HasFactory;

    /** Sent, and waiting on the applicant. */
    public const STATUS_PENDING = 'pending';

    /** The applicant took this one. */
    public const STATUS_ACCEPTED = 'accepted';

    /** The pharmacy pulled it. */
    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $fillable = [
        'pharmacy_id', 'employee_id', 'created_by_pharmacist_id',
        'shift', 'salary', 'status', 'offered_at', 'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'salary' => 'float',
            'offered_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Whether the applicant could act on this offer right now.
     *
     * Four things have to be true at once, and none of them is stored on this
     * row: three belong to the world around it. A pending offer from a
     * suspended pharmacy, or for a shift somebody else has since taken, is
     * still a real record — it just cannot be accepted today.
     */
    public function isAcceptableFor(Employee $employee): bool
    {
        return $this->unacceptableReasonFor($employee) === null;
    }

    /**
     * Why this offer cannot be accepted, or null when it can.
     *
     * Returned to the client so the button can explain itself instead of
     * failing on tap.
     */
    public function unacceptableReasonFor(Employee $employee): ?string
    {
        if (! $this->isPending()) {
            // Named separately because these read very differently to the
            // person holding them. An accepted offer is their current job, not
            // a failure — reporting it as "no longer open" made the one good
            // outcome in the list look like the broken one.
            return match ($this->status) {
                self::STATUS_WITHDRAWN => 'offer_withdrawn',
                self::STATUS_ACCEPTED => 'offer_accepted',
                default => 'offer_not_pending',
            };
        }

        if ($employee->isEmployed()) {
            return 'already_employed';
        }

        $pharmacy = $this->pharmacy;

        if ($pharmacy === null || ! $pharmacy->isOperational()) {
            return 'pharmacy_unavailable';
        }

        if (! in_array($this->shift, $pharmacy->freeShifts(), true)) {
            return 'shift_taken';
        }

        return null;
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(Pharmacist::class, 'created_by_pharmacist_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
