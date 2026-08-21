<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EmployeeDocumentVersion extends Model
{
    use HasFactory;

    public const TYPE_CV = 'cv';

    public const TYPE_EXPERIENCE = 'experience_proof';

    public const TYPES = [self::TYPE_CV, self::TYPE_EXPERIENCE];

    protected $fillable = [
        'employee_id', 'document_type', 'version_number', 'storage_key',
        'verified_mime_type', 'byte_size', 'sha256', 'review_status',
        'uploaded_by_type', 'uploaded_by_id', 'reviewed_by_type', 'reviewed_by_id',
        'decision_reason', 'reviewed_at', 'effective_at', 'superseded_at',
        'legacy_locator_hash',
    ];

    protected $hidden = [
        'storage_key',
        'sha256',
        'legacy_locator_hash',
        'uploaded_by_type',
        'uploaded_by_id',
        'reviewed_by_type',
        'reviewed_by_id',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'effective_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $document): void {
            $document->public_id ??= (string) Str::uuid();
        });

        static::updating(function (self $document): void {
            if ($document->isDirty([
                'public_id', 'employee_id', 'document_type', 'version_number', 'storage_key',
                'verified_mime_type', 'byte_size', 'sha256', 'legacy_locator_hash',
            ])) {
                throw new \LogicException('Document originals and identity metadata are immutable.');
            }
        });
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function documentAccesses()
    {
        return $this->hasMany(RecruitmentDocumentAccess::class, 'employee_document_version_id');
    }

    public function uploadedBy()
    {
        return $this->morphTo();
    }

    public function reviewedBy()
    {
        return $this->morphTo();
    }
}