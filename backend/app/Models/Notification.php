<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'notifications';

    /** A pharmacy read your CV. */
    public const TYPE_CV_VIEWED = 'cv_viewed';

    /** A pharmacy wants to hire you. */
    public const TYPE_OFFER_RECEIVED = 'offer_received';

    /** A pharmacy pulled an offer you were holding. */
    public const TYPE_OFFER_WITHDRAWN = 'offer_withdrawn';

    /** You no longer work at that pharmacy. */
    public const TYPE_EMPLOYMENT_ENDED = 'employment_ended';

    /** A pharmacy vouched that your training is finished. */
    public const TYPE_ROLE_PROMOTED = 'role_promoted';

    /** The pharmacist's business: money, stock, suppliers, staffing. */
    public const AUDIENCE_OWNER = 'owner';

    /** Anything the people working the counter should see. */
    public const AUDIENCE_STAFF = 'staff';

    protected $fillable = [
        'pharmacy_id', 'employee_id', 'title', 'message', 'type', 'audience', 'is_read', 'date',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'date' => 'date',
        ];
    }

    /** True when this is addressed to a person rather than to a pharmacy. */
    public function isPersonal(): bool
    {
        return $this->employee_id !== null;
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    // helper to create + could be extended to push via broadcasting later
    public static function notify(
        int $pharmacyId,
        string $title,
        string $message,
        string $type,
        string $audience = self::AUDIENCE_OWNER,
    ): self {
        return self::create([
            'pharmacy_id' => $pharmacyId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'audience' => $audience,
            'is_read' => false,
            'date' => now()->toDateString(),
        ]);
    }

    /**
     * Address one person directly.
     *
     * Carries no pharmacy: the recipient may not have one, which is the whole
     * reason this exists. Leaving `pharmacy_id` null is also what keeps these
     * rows out of every pharmacy-scoped query without a new guard.
     */
    public static function notifyEmployee(int $employeeId, string $title, string $message, string $type): self
    {
        return self::create([
            'pharmacy_id' => null,
            'employee_id' => $employeeId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'is_read' => false,
            'date' => now()->toDateString(),
        ]);
    }
}
