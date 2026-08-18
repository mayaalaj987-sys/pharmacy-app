<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One recruiter opening one applicant's document, once.
 *
 * Recruiters may read any in-pool CV without asking, so this is the whole of
 * the accountability: who looked, at whom, and when.
 */
class RecruitmentDocumentAccess extends Model
{
    use HasFactory;

    public const ACTION_PREVIEWED = 'previewed';

    public const ACTION_DOWNLOADED = 'downloaded';

    /** One moment matters, so there is no created/updated pair to keep in step. */
    public $timestamps = false;

    protected $fillable = [
        'pharmacist_id', 'pharmacy_id', 'employee_id',
        'employee_document_version_id', 'action', 'ip_address', 'user_agent', 'accessed_at',
    ];

    protected function casts(): array
    {
        return ['accessed_at' => 'datetime'];
    }

    public function pharmacist()
    {
        return $this->belongsTo(Pharmacist::class);
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function documentVersion()
    {
        return $this->belongsTo(EmployeeDocumentVersion::class, 'employee_document_version_id');
    }
}
