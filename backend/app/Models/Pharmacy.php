<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pharmacy extends Model
{
    use HasFactory;

    protected $fillable = [
        'pharmacist_id', 'pharmacy_name', 'pharmacy_address',
        'certificate', 'license', 'status', 'latitude', 'longitude', 'created_at', 'updated_at',
    ];

    protected $hidden = ['certificate', 'license'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'reviewed_at' => 'datetime',
            'blocked_at' => 'datetime',
        ];
    }

    /**
     * Suspended by an administrator.
     *
     * Independent of [status]: a suspended pharmacy keeps its approved review
     * decision and regains it in full when the suspension is lifted.
     */
    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    /** Approved and not currently suspended. */
    public function isOperational(): bool
    {
        return $this->status === 'approved' && ! $this->isBlocked();
    }

    /**
     * Shifts nobody is working here yet.
     *
     * This is the pharmacy's capacity: there is no separate headcount limit,
     * because a seat is a shift and a shift can only be held once. Reads the
     * loaded `employees` relation when it is already there, so a pool listing
     * can report free shifts for many pharmacies without a query each.
     *
     * @return list<string>
     */
    public function freeShifts(): array
    {
        $taken = $this->relationLoaded('employees')
            ? $this->employees->pluck('shift')
            : $this->employees()->whereNotNull('shift')->pluck('shift');

        return array_values(array_diff(Employee::SHIFTS, $taken->filter()->all()));
    }

    public function jobOffers()
    {
        return $this->hasMany(JobOffer::class);
    }

    public function blockedByAdmin()
    {
        return $this->belongsTo(Admin::class, 'blocked_by_admin_id');
    }

    public function pharmacist()
    {
        return $this->belongsTo(Pharmacist::class);
    }

    public function documentVersions()
    {
        return $this->hasMany(PharmacyDocumentVersion::class);
    }

    public function reviewedByAdmin()
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function medicines()
    {
        return $this->hasMany(Medicine::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }
}
