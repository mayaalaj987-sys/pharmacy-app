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

    public function pharmacies()
    {
        return $this->hasMany(Pharmacy::class);
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
