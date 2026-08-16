<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Pharmacy;
use App\Models\PharmacyDocumentVersion;
use Illuminate\Support\Facades\Storage;

class DocumentAvailabilityService
{
    public function __construct(
        private readonly LegacyDocumentService $legacy,
        private readonly PrivateDocumentService $documents,
    ) {}

    public function pharmacyHas(Pharmacy $pharmacy, string $type): bool
    {
        $versions = $pharmacy->documentVersions()
            ->where('document_type', $type)
            ->whereIn('review_status', [
                PharmacyDocumentVersion::STATUS_PENDING,
                PharmacyDocumentVersion::STATUS_APPROVED,
            ])
            ->latest('version_number')
            ->get();

        if ($versions->contains(fn ($version) => $this->storedVersionExists(
            $version->storage_key,
            $version->byte_size,
            $version->sha256,
        ))) {
            return true;
        }

        $value = $type === PharmacyDocumentVersion::TYPE_CERTIFICATE ? $pharmacy->certificate : $pharmacy->license;

        return $this->legacy->inspect($value, $type, true)['status'] === 'valid';
    }

    public function employeeHas(Employee $employee, string $type): bool
    {
        $version = $employee->documentVersions()
            ->where('document_type', $type)
            ->whereNull('superseded_at')
            ->latest('version_number')
            ->first();

        if ($version && $this->storedVersionExists($version->storage_key, $version->byte_size, $version->sha256)) {
            return true;
        }

        $value = $type === 'cv' ? $employee->cv : $employee->experience_proof;

        return $this->legacy->inspect($value, $type, false)['status'] === 'valid';
    }

    private function storedVersionExists(?string $key, int $expectedSize, string $expectedHash): bool
    {
        try {
            return $this->documents->isOwnedStorageKey($key)
                && Storage::disk(PrivateDocumentService::DISK)->exists($key)
                && Storage::disk(PrivateDocumentService::DISK)->size($key) === $expectedSize
                && hash_equals($expectedHash, hash('sha256', Storage::disk(PrivateDocumentService::DISK)->get($key)));
        } catch (\Throwable) {
            return false;
        }
    }
}
