<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\EmployeeDocumentVersion;
use App\Models\Pharmacy;
use App\Models\PharmacyDocumentVersion;
use App\Services\LegacyDocumentService;
use App\Services\PrivateDocumentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class InventoryLegacyDocuments extends Command
{
    protected $signature = 'documents:legacy-inventory {--batch=100 : Maximum owners to inspect per owner type} {--after=0 : Inspect owner IDs greater than this value}';

    protected $description = 'Safely inventory legacy document references without revealing their paths.';

    public function handle(LegacyDocumentService $legacy): int
    {
        $batch = min(1000, max(1, (int) $this->option('batch')));
        $after = max(0, (int) $this->option('after'));
        $counts = ['valid' => 0, 'missing' => 0, 'invalid' => 0];

        Pharmacy::query()->where('id', '>', $after)->orderBy('id')->limit($batch)->get()
            ->each(function (Pharmacy $pharmacy) use ($legacy, &$counts): void {
                foreach (['certificate', 'license'] as $type) {
                    $result = $legacy->inspect($pharmacy->{$type}, $type, true);
                    $counts[$result['status']]++;
                    if ($result['status'] !== 'valid') {
                        $this->line(sprintf('pharmacy row=%d type=%s issue=%s', $pharmacy->id, $type, $result['issue']));
                    }
                }
            });

        Employee::query()->where('id', '>', $after)->orderBy('id')->limit($batch)->get()
            ->each(function (Employee $employee) use ($legacy, &$counts): void {
                foreach (['cv', 'experience_proof'] as $type) {
                    $result = $legacy->inspect($employee->{$type}, $type, false);
                    $counts[$result['status']]++;
                    if ($result['status'] !== 'valid' && ! ($type === 'experience_proof' && $result['status'] === 'missing')) {
                        $this->line(sprintf('employee row=%d type=%s issue=%s', $employee->id, $type, $result['issue']));
                    }
                }
            });

        $missingRegistered = PharmacyDocumentVersion::query()
            ->get(['id', 'storage_key'])
            ->filter(fn ($version) => ! $this->privateFileExists($version->storage_key))
            ->count()
            + EmployeeDocumentVersion::query()
                ->get(['id', 'storage_key'])
                ->filter(fn ($version) => ! $this->privateFileExists($version->storage_key))
                ->count();

        $registeredKeys = PharmacyDocumentVersion::pluck('storage_key')
            ->merge(EmployeeDocumentVersion::pluck('storage_key'))
            ->flip();
        $orphanedPrivate = collect(Storage::disk(PrivateDocumentService::DISK)->allFiles())
            ->filter(fn (string $key) => str_starts_with($key, 'pharmacy-documents/') || str_starts_with($key, 'employee-documents/'))
            ->reject(fn (string $key) => $registeredKeys->has($key))
            ->count();

        $this->table(
            ['valid', 'missing', 'invalid', 'registered files missing', 'private orphans'],
            [[$counts['valid'], $counts['missing'], $counts['invalid'], $missingRegistered, $orphanedPrivate]],
        );
        $this->info('Inventory complete. No storage paths or filenames were emitted.');

        return self::SUCCESS;
    }

    private function privateFileExists(?string $key): bool
    {
        try {
            return is_string($key) && Storage::disk(PrivateDocumentService::DISK)->exists($key);
        } catch (\Throwable) {
            return false;
        }
    }
}
