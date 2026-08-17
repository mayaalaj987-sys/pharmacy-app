<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A support request raised from the mobile app.
 *
 * The sender is always a foreign key resolved from the authenticated token;
 * nothing here is client-supplied identity.
 */
class SupportTicket extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'pharmacist_id', 'employee_id', 'pharmacy_id',
        'subject', 'message', 'status',
        'admin_response', 'responded_by_admin_id', 'responded_at',
    ];

    protected function casts(): array
    {
        return ['responded_at' => 'datetime'];
    }

    public function pharmacist()
    {
        return $this->belongsTo(Pharmacist::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function responder()
    {
        return $this->belongsTo(Admin::class, 'responded_by_admin_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /** Display name of whoever raised the ticket. */
    public function senderName(): string
    {
        return $this->pharmacist?->name ?? $this->employee?->name ?? 'Unknown';
    }

    public function senderRole(): string
    {
        return $this->pharmacist_id !== null ? 'pharmacist' : 'employee';
    }
}
