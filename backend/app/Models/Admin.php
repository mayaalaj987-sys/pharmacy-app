<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class Admin extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_PHARMACY_REVIEWER = 'pharmacy_reviewer';

    public const ROLES = [self::ROLE_SUPER_ADMIN, self::ROLE_PHARMACY_REVIEWER];

    protected $guarded = [
        'id', 'public_id', 'role', 'is_active', 'auth_version', 'last_login_at',
        'password_changed_at', 'disabled_at', 'created_at', 'updated_at',
    ];

    protected $hidden = [
        'password', 'auth_version',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $admin): void {
            $admin->public_id ??= (string) Str::uuid();
            $admin->email = self::normalizeEmail((string) $admin->email);
        });

        static::saving(function (self $admin): void {
            $admin->email = self::normalizeEmail((string) $admin->email);
            if (! in_array($admin->role, self::ROLES, true)) {
                throw new \InvalidArgumentException('Unsupported administrator role.');
            }
        });
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function canReviewPharmacies(): bool
    {
        return $this->is_active && in_array($this->role, self::ROLES, true);
    }

    public function auditLogs()
    {
        return $this->hasMany(AdminAuditLog::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
