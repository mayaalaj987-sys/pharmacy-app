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
        'name', 'email', 'password', 'profile_image',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
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

    // pharmacist can login only if they have at least one approved pharmacy
    public function hasApprovedPharmacy(): bool
    {
        return $this->pharmacies()->where('status', 'approved')->exists();
    }
}
