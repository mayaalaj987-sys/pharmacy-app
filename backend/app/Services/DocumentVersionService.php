<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeDocumentVersion;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use App\Models\PharmacyDocumentVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DocumentVersionService
{
    public function __construct(private readonly PrivateDocumentService $documents) {}

    public function uploadPharmacyDocument(
        Pharmacy $pharmacy,
        string $type,
        UploadedFile $file,
        Pharmacist $uploader,
    ): PharmacyDocumentVersion {
        $this->assertType($type, PharmacyDocumentVersion::TYPES);
        $stored = $this->documents->storeUpload($file, 'pharmacy-documents', $type);

        try {
            return DB::transaction(function () use ($pharmacy, $type, $stored, $uploader) {
                $locked = Pharmacy::query()->lockForUpdate()->findOrFail($pharmacy->id);

                return $this->createPharmacyVersion($locked, $type, $stored, $uploader);
            });
        } catch (Throwable $exception) {
            $this->documents->delete($stored->storageKey);
            throw $exception;
        }
    }

    public function createPharmacyVersion(
        Pharmacy $pharmacy,
        string $type,
        StoredDocument $stored,
        ?Model $uploader = null,
        ?string $legacyLocatorHash = null,
    ): PharmacyDocumentVersion {
        $this->assertType($type, PharmacyDocumentVersion::TYPES);
        $nextVersion = ((int) $pharmacy->documentVersions()->where('document_type', $type)->max('version_number')) + 1;

        return $pharmacy->documentVersions()->create([
            'document_type' => $type,
            'version_number' => $nextVersion,
            'storage_key' => $stored->storageKey,
            'verified_mime_type' => $stored->mimeType,
            'byte_size' => $stored->byteSize,
            'sha256' => $stored->sha256,
            'review_status' => PharmacyDocumentVersion::STATUS_PENDING,
            'uploaded_by_type' => $uploader?->getMorphClass(),
            'uploaded_by_id' => $uploader?->getKey(),
            'legacy_locator_hash' => $legacyLocatorHash,
        ]);
    }

    public function uploadEmployeeDocument(
        Employee $employee,
        string $type,
        UploadedFile $file,
    ): EmployeeDocumentVersion {
        $this->assertType($type, EmployeeDocumentVersion::TYPES);
        $stored = $this->documents->storeUpload($file, 'employee-documents', $type);

        try {
            return DB::transaction(function () use ($employee, $type, $stored) {
                $locked = Employee::query()->lockForUpdate()->findOrFail($employee->id);

                return $this->createEmployeeVersion($locked, $type, $stored, $locked);
            });
        } catch (Throwable $exception) {
            $this->documents->delete($stored->storageKey);
            throw $exception;
        }
    }

    public function createEmployeeVersion(
        Employee $employee,
        string $type,
        StoredDocument $stored,
        ?Model $uploader = null,
        ?string $legacyLocatorHash = null,
    ): EmployeeDocumentVersion {
        $this->assertType($type, EmployeeDocumentVersion::TYPES);
        $now = now();
        $employee->documentVersions()
            ->where('document_type', $type)
            ->whereNull('superseded_at')
            ->update(['superseded_at' => $now]);
        $nextVersion = ((int) $employee->documentVersions()->where('document_type', $type)->max('version_number')) + 1;

        return $employee->documentVersions()->create([
            'document_type' => $type,
            'version_number' => $nextVersion,
            'storage_key' => $stored->storageKey,
            'verified_mime_type' => $stored->mimeType,
            'byte_size' => $stored->byteSize,
            'sha256' => $stored->sha256,
            'review_status' => null,
            'uploaded_by_type' => $uploader?->getMorphClass(),
            'uploaded_by_id' => $uploader?->getKey(),
            'effective_at' => $now,
            'legacy_locator_hash' => $legacyLocatorHash,
        ]);
    }

    public function approvePharmacyDocument(
        PharmacyDocumentVersion $document,
        Model $reviewer,
    ): PharmacyDocumentVersion {
        return DB::transaction(function () use ($document, $reviewer) {
            $locked = PharmacyDocumentVersion::query()->lockForUpdate()->findOrFail($document->id);
            if ($locked->review_status !== PharmacyDocumentVersion::STATUS_PENDING) {
                throw new \DomainException('Only a pending document can be approved.');
            }

            if (! $this->storedContentMatches($locked)) {
                throw new \DomainException('The stored document failed integrity verification.');
            }

            $now = now();
            PharmacyDocumentVersion::query()
                ->where('pharmacy_id', $locked->pharmacy_id)
                ->where('document_type', $locked->document_type)
                ->where('review_status', PharmacyDocumentVersion::STATUS_APPROVED)
                ->whereKeyNot($locked->id)
                ->update([
                    'review_status' => PharmacyDocumentVersion::STATUS_SUPERSEDED,
                    'superseded_at' => $now,
                ]);
            $locked->update([
                'review_status' => PharmacyDocumentVersion::STATUS_APPROVED,
                'reviewed_by_type' => $reviewer->getMorphClass(),
                'reviewed_by_id' => $reviewer->getKey(),
                'decision_reason' => null,
                'reviewed_at' => $now,
                'effective_at' => $now,
                'superseded_at' => null,
            ]);

            return $locked->fresh();
        });
    }

    public function rejectPharmacyDocument(
        PharmacyDocumentVersion $document,
        Model $reviewer,
        string $reason,
    ): PharmacyDocumentVersion {
        return DB::transaction(function () use ($document, $reviewer, $reason) {
            $locked = PharmacyDocumentVersion::query()->lockForUpdate()->findOrFail($document->id);
            if ($locked->review_status !== PharmacyDocumentVersion::STATUS_PENDING) {
                throw new \DomainException('Only a pending document can be rejected.');
            }
            $locked->update([
                'review_status' => PharmacyDocumentVersion::STATUS_REJECTED,
                'reviewed_by_type' => $reviewer->getMorphClass(),
                'reviewed_by_id' => $reviewer->getKey(),
                'decision_reason' => $reason,
                'reviewed_at' => now(),
                'effective_at' => null,
            ]);

            return $locked->fresh();
        });
    }

    /** @param array<int,string> $allowed */
    private function assertType(string $type, array $allowed): void
    {
        if (! in_array($type, $allowed, true)) {
            throw new \InvalidArgumentException('Unsupported document type.');
        }
    }

    private function storedContentMatches(PharmacyDocumentVersion $document): bool
    {
        try {
            if (! $this->documents->isOwnedStorageKey($document->storage_key)) {
                return false;
            }
            $disk = Storage::disk(PrivateDocumentService::DISK);

            return $disk->exists($document->storage_key)
                && $disk->size($document->storage_key) === $document->byte_size
                && hash_equals($document->sha256, hash('sha256', $disk->get($document->storage_key)));
        } catch (Throwable) {
            return false;
        }
    }
}
