<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Pharmacist extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'profile_image', 'deactivated_at', 'created_at', 'updated_at',
    ];

    protected $hidden = [
        'password',
        'profile_image',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'deactivated_at' => 'datetime',
        ];
    }

    /**
     * How many branches one owner may run.
     *
     * Two. A pharmacist can plausibly stand behind two counters; past that the
     * app would be pretending someone is running a chain from a phone, and
     * every screen here — one active pharmacy at a time, two shifts, one
     * purchase cart — is built for a shop, not a head office.
     */
    public const MAX_PHARMACIES = 2;

    public function pharmacies()
    {
        return $this->hasMany(Pharmacy::class);
    }

    /**
     * Branches counted against the limit.
     *
     * A rejected application is not a branch — it was refused and the owner
     * should be free to try again. Pending ones do count, because they are
     * waiting to become real and letting someone queue five of them would make
     * the limit meaningless the moment an admin worked through the list.
     */
    public function pharmacyCount(): int
    {
        return $this->pharmacies()->where('status', '!=', 'rejected')->count();
    }

    public function hasRoomForAnotherPharmacy(): bool
    {
        return $this->pharmacyCount() < self::MAX_PHARMACIES;
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }

    public function uploadedPharmacyDocuments()
    {
        return $this->morphMany(PharmacyDocumentVersion::class, 'uploaded_by');
    }

    // pharmacist can login only if they have at least one approved pharmacy
    public function hasApprovedPharmacy(): bool
    {
        return $this->pharmacies()->where('status', 'approved')->exists();
    }
}
