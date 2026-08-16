<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\EmployeeDocumentVersion;
use App\Models\Pharmacy;
use App\Models\PharmacyDocumentVersion;
use App\Services\DocumentVersionService;
use App\Services\LegacyDocumentService;
use App\Services\PrivateDocumentService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class MigrateLegacyDocuments extends Command
{
    protected $signature = 'documents:migrate-legacy
        {--dry-run : Explicitly inspect and report without changing database or storage}
        {--execute : Copy and register verified documents; omitted means dry run}
        {--batch=100 : Maximum owners to inspect per owner type}
        {--after=0 : Inspect owner IDs greater than this value}
        {--retry : Retry rows previously reported by an external run manifest}
        {--quarantine : Copy unknown files to private quarantine after known copies are verified}';

    protected $description = 'Dry-run or resumably copy verified legacy documents into private versioned storage.';

    private array $manifest = [];

    public function handle(
        LegacyDocumentService $legacy,
        PrivateDocumentService $documents,
        DocumentVersionService $versions,
    ): int {
        if ($this->option('dry-run') && $this->option('execute')) {
            $this->error('The --dry-run and --execute options are mutually exclusive. No action was taken.');

            return self::INVALID;
        }

        $execute = (bool) $this->option('execute');
        $batch = min(1000, max(1, (int) $this->option('batch')));
        $after = max(0, (int) $this->option('after'));
        $counts = ['would_copy' => 0, 'copied' => 0, 'skipped' => 0, 'issues' => 0, 'failed' => 0];

        $this->info($execute
            ? 'EXECUTE mode: verified files will be copied; public originals will remain.'
            : 'DRY RUN: no files, database rows, manifests, or quarantine entries will be changed.');
        if ($this->option('retry')) {
            $this->info('Retry mode: unresolved rows in this bounded ID window will be evaluated again.');
        }

        Pharmacy::query()->where('id', '>', $after)->orderBy('id')->limit($batch)->get()
            ->each(function (Pharmacy $pharmacy) use ($legacy, $documents, $versions, $execute, &$counts): void {
                foreach (['certificate', 'license'] as $type) {
                    $this->process($pharmacy, $type, true, $legacy, $documents, $versions, $execute, $counts);
                }
            });

        Employee::query()->where('id', '>', $after)->orderBy('id')->limit($batch)->get()
            ->each(function (Employee $employee) use ($legacy, $documents, $versions, $execute, &$counts): void {
                foreach (['cv', 'experience_proof'] as $type) {
                    if ($type === 'experience_proof' && (! is_string($employee->experience_proof) || trim($employee->experience_proof) === '')) {
                        continue;
                    }
                    $this->process($employee, $type, false, $legacy, $documents, $versions, $execute, $counts);
                }
            });

        if ($execute) {
            $this->writeManifest();
            if ($this->option('quarantine')) {
                $this->copyUnknownFilesToQuarantine($counts);
            }
        }

        $this->table(array_keys($counts), [array_values($counts)]);
        $this->info('Migration pass complete. No storage paths, filenames, or hashes were emitted.');

        return $counts['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function process(
        Model $owner,
        string $type,
        bool $jsonWrapped,
        LegacyDocumentService $legacy,
        PrivateDocumentService $documents,
        DocumentVersionService $versions,
        bool $execute,
        array &$counts,
    ): void {
        $legacyValue = $owner->{$type};
        $result = $legacy->inspect($legacyValue, $type, $jsonWrapped);
        $ownerKind = $owner instanceof Pharmacy ? 'pharmacy' : 'employee';
        if ($result['status'] !== 'valid') {
            $counts['issues']++;
            $this->line(sprintf('%s row=%d type=%s issue=%s', $ownerKind, $owner->getKey(), $type, $result['issue']));

            return;
        }

        $locatorHash = $legacy->locatorHash($ownerKind, (int) $owner->getKey(), $type, $result['path']);
        $model = $owner instanceof Pharmacy ? PharmacyDocumentVersion::class : EmployeeDocumentVersion::class;
        $existing = $model::query()->where('legacy_locator_hash', $locatorHash)->first();
        if ($existing !== null) {
            $contentMatches = hash_equals($existing->sha256, $result['metadata']['sha256']);
            try {
                $privateMatches = Storage::disk(PrivateDocumentService::DISK)->exists($existing->storage_key)
                    && hash_equals($existing->sha256, hash('sha256', Storage::disk(PrivateDocumentService::DISK)->get($existing->storage_key)));
            } catch (Throwable) {
                $privateMatches = false;
            }
            if (! $contentMatches || ! $privateMatches) {
                $counts['issues']++;
                $this->line(sprintf('%s row=%d type=%s issue=hash_mismatch', $ownerKind, $owner->getKey(), $type));

                return;
            }
            $counts['skipped']++;

            return;
        }

        if (! $execute) {
            $counts['would_copy']++;

            return;
        }

        $stored = null;
        try {
            $source = Storage::disk('public')->path($result['path']);
            $scope = $owner instanceof Pharmacy ? 'pharmacy-documents' : 'employee-documents';
            $stored = $documents->storePath($source, $scope, $type);
            $version = DB::transaction(function () use ($owner, $type, $stored, $versions, $locatorHash) {
                $locked = $owner::query()->lockForUpdate()->findOrFail($owner->getKey());
                if ($owner instanceof Pharmacy) {
                    $version = $versions->createPharmacyVersion($locked, $type, $stored, null, $locatorHash);
                    if ($locked->status === 'approved') {
                        $version->update([
                            'review_status' => PharmacyDocumentVersion::STATUS_APPROVED,
                            'reviewed_at' => now(),
                            'effective_at' => now(),
                        ]);
                    }

                    return $version;
                }

                return $versions->createEmployeeVersion($locked, $type, $stored, null, $locatorHash);
            });
            $this->manifest[] = [
                'owner_type' => $ownerKind,
                'owner_id' => $owner->getKey(),
                'document_type' => $type,
                'version_id' => $version->id,
                'locator_hash' => $locatorHash,
                'content_hash' => $stored->sha256,
                'result' => 'copied',
            ];
            $counts['copied']++;
        } catch (Throwable $exception) {
            if ($stored !== null) {
                $documents->delete($stored->storageKey);
            }
            logger()->warning('Legacy document migration failed.', [
                'owner_type' => $ownerKind,
                'owner_id' => $owner->getKey(),
                'document_type' => $type,
                'exception_class' => $exception::class,
            ]);
            $this->manifest[] = [
                'owner_type' => $ownerKind,
                'owner_id' => $owner->getKey(),
                'document_type' => $type,
                'result' => 'failed',
                'issue' => 'migration_failed',
                'exception_class' => $exception::class,
            ];
            $counts['failed']++;
            $this->line(sprintf('%s row=%d type=%s issue=migration_failed', $ownerKind, $owner->getKey(), $type));
        }
    }

    private function writeManifest(): void
    {
        if ($this->manifest === []) {
            return;
        }

        $payload = collect($this->manifest)->map(fn (array $row) => json_encode($row, JSON_THROW_ON_ERROR))->implode("\n")."\n";
        Storage::disk(PrivateDocumentService::DISK)->put(
            'migration-manifests/'.now()->format('YmdHis').'-'.Str::uuid().'.jsonl',
            $payload,
        );
    }

    private function copyUnknownFilesToQuarantine(array &$counts): void
    {
        $documentPrefixes = ['certificates/', 'licenses/', 'cvs/', 'experience/'];
        $referenced = collect();
        Pharmacy::query()->get(['certificate', 'license'])->each(function (Pharmacy $pharmacy) use ($referenced): void {
            foreach ([$pharmacy->certificate, $pharmacy->license] as $value) {
                $decoded = is_string($value) ? json_decode($value, true) : null;
                if (is_array($decoded) && count($decoded) === 1 && is_string($decoded[0])) {
                    $referenced->put($decoded[0], true);
                }
            }
        });
        Employee::query()->get(['cv', 'experience_proof'])->each(function (Employee $employee) use ($referenced): void {
            foreach ([$employee->cv, $employee->experience_proof] as $value) {
                if (is_string($value) && trim($value) !== '') {
                    $referenced->put(trim($value), true);
                }
            }
        });

        foreach (Storage::disk('public')->allFiles() as $candidate) {
            if (! collect($documentPrefixes)->contains(fn (string $prefix) => str_starts_with($candidate, $prefix))) {
                continue;
            }
            if ($referenced->has($candidate)) {
                continue;
            }
            if (str_contains($candidate, '..') || str_contains($candidate, '%') || str_contains($candidate, '\\')) {
                continue;
            }

            $source = Storage::disk('public')->path($candidate);
            $root = realpath(Storage::disk('public')->path(''));
            $real = realpath($source);
            if (! is_file($source) || is_link($source) || $root === false || $real === false
                || ! str_starts_with(
                    str_replace('\\', '/', $real),
                    rtrim(str_replace('\\', '/', $root), '/').'/',
                )) {
                continue;
            }
            $contents = file_get_contents($source);
            if ($contents === false) {
                $counts['failed']++;

                continue;
            }
            Storage::disk(PrivateDocumentService::DISK)->put('quarantine/'.Str::uuid().'.bin', $contents);
        }
    }
}
