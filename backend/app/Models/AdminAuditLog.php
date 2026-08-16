<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected $hidden = ['ip_address', 'user_agent'];

    protected function casts(): array
    {
        return [
            'ip_address' => 'encrypted',
            'before_state' => 'array',
            'after_state' => 'array',
            'logged_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Administrator audit records are append-only.'));
        static::deleting(fn () => throw new \LogicException('Administrator audit records are append-only.'));
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }
}
