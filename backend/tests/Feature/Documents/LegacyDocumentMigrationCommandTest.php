<?php

namespace Tests\Feature\Documents;

use App\Models\Employee;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use App\Services\LegacyDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LegacyDocumentMigrationCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('documents');
    }

    public function test_explicit_dry_run_changes_neither_database_nor_public_or_private_storage(): void
    {
        [$pharmacy, $employee] = $this->legacyOwners();
        Storage::disk('public')->put('certificates/unreferenced.bin', 'unknown bytes');
        $publicFilesBefore = Storage::disk('public')->allFiles();
        $publicHashesBefore = collect($publicFilesBefore)->mapWithKeys(
            fn (string $path) => [$path => hash('sha256', Storage::disk('public')->get($path))],
        )->all();

        Artisan::call('documents:migrate-legacy', [
            '--dry-run' => true,
            '--batch' => 10,
            '--quarantine' => true,
        ]);
        $output = Artisan::output();

        $this->assertStringContainsString('DRY RUN', $output);
        $this->assertStringContainsString('no files, database rows, manifests, or quarantine entries will be changed', $output);
        $this->assertStringNotContainsString('certificate-private-name.pdf', $output);
        $this->assertStringNotContainsString('employee-private-name.pdf', $output);
        $this->assertDatabaseCount('pharmacy_document_versions', 0);
        $this->assertDatabaseCount('employee_document_versions', 0);
        $this->assertSame([], Storage::disk('documents')->allFiles());
        $this->assertSame($publicFilesBefore, Storage::disk('public')->allFiles());
        $this->assertSame($publicHashesBefore, collect(Storage::disk('public')->allFiles())->mapWithKeys(
            fn (string $path) => [$path => hash('sha256', Storage::disk('public')->get($path))],
        )->all());
        Storage::disk('public')->assertExists(json_decode($pharmacy->certificate, true)[0]);
        Storage::disk('public')->assertExists($employee->cv);
    }

    public function test_omitting_both_mode_flags_still_fails_safe_to_dry_run(): void
    {
        Artisan::call('documents:migrate-legacy', ['--batch' => 1]);

        $this->assertStringContainsString('DRY RUN', Artisan::output());
        $this->assertDatabaseCount('pharmacy_document_versions', 0);
        $this->assertDatabaseCount('employee_document_versions', 0);
        $this->assertSame([], Storage::disk('documents')->allFiles());
    }

    public function test_execute_is_resumable_preserves_originals_and_detects_later_hash_mismatch(): void
    {
        [$pharmacy, $employee] = $this->legacyOwners();

        Artisan::call('documents:migrate-legacy', ['--execute' => true, '--batch' => 10]);
        $this->assertDatabaseCount('pharmacy_document_versions', 2);
        $this->assertDatabaseCount('employee_document_versions', 1);
        $this->assertCount(3, array_merge(
            Storage::disk('documents')->allFiles('pharmacy-documents'),
            Storage::disk('documents')->allFiles('employee-documents'),
        ));
        Storage::disk('public')->assertExists(json_decode($pharmacy->certificate, true)[0]);
        Storage::disk('public')->assertExists($employee->cv);

        Artisan::call('documents:migrate-legacy', ['--execute' => true, '--batch' => 10, '--retry' => true]);
        $this->assertDatabaseCount('pharmacy_document_versions', 2);
        $this->assertDatabaseCount('employee_document_versions', 1);

        Storage::disk('public')->put(json_decode($pharmacy->certificate, true)[0], $this->differentValidPdf());
        Artisan::call('documents:migrate-legacy', ['--execute' => true, '--batch' => 10, '--retry' => true]);
        $this->assertStringContainsString('row='.$pharmacy->id.' type=certificate issue=hash_mismatch', Artisan::output());
        $this->assertStringNotContainsString('certificate-private-name.pdf', Artisan::output());
        $this->assertDatabaseCount('pharmacy_document_versions', 2);
    }

    public function test_inventory_reports_only_row_type_and_issue_for_malformed_and_missing_values(): void
    {
        $owner = $this->owner('issues');
        $pharmacy = Pharmacy::create([
            'pharmacist_id' => $owner->id,
            'pharmacy_name' => 'Issues',
            'pharmacy_address' => 'Address',
            'certificate' => json_encode(['../secret.pdf']),
            'license' => json_encode(['licenses/missing-private-file.pdf']),
            'status' => 'pending',
        ]);

        Artisan::call('documents:legacy-inventory', ['--batch' => 10]);
        $output = Artisan::output();
        $this->assertStringContainsString('pharmacy row='.$pharmacy->id.' type=certificate issue=legacy_path_invalid', $output);
        $this->assertStringContainsString('pharmacy row='.$pharmacy->id.' type=license issue=legacy_file_missing', $output);
        $this->assertStringNotContainsString('../secret.pdf', $output);
        $this->assertStringNotContainsString('missing-private-file.pdf', $output);
    }

    public function test_legacy_service_rejects_encoded_paths_absolute_paths_and_symlinks(): void
    {
        $legacy = app(LegacyDocumentService::class);
        foreach (['certificates/%2e%2e/secret.pdf', 'C:/secret.pdf', '/secret.pdf'] as $unsafe) {
            $result = $legacy->inspect(json_encode([$unsafe]), 'certificate', true);
            $this->assertSame('invalid', $result['status']);
            $this->assertNull($result['path']);
        }

        Storage::disk('public')->makeDirectory('certificates');
        $outside = tempnam(sys_get_temp_dir(), 'legacy-outside-');
        file_put_contents($outside, $this->validPdfContent());
        $link = Storage::disk('public')->path('certificates/linked.pdf');
        if (! @symlink($outside, $link)) {
            @unlink($outside);
            $this->markTestSkipped('File symlink creation is unavailable on this Windows runtime.');
        }

        try {
            $result = $legacy->inspect(json_encode(['certificates/linked.pdf']), 'certificate', true);
            $this->assertSame('invalid', $result['status']);
            $this->assertSame('legacy_file_unsafe', $result['issue']);
            $this->assertNull($result['path']);
        } finally {
            @unlink($link);
            @unlink($outside);
        }
    }

    public function test_quarantine_is_copy_only_and_never_deletes_an_unknown_public_file(): void
    {
        Storage::disk('public')->put('certificates/unclassified.bin', 'unknown bytes');

        Artisan::call('documents:migrate-legacy', [
            '--execute' => true,
            '--batch' => 1,
            '--quarantine' => true,
        ]);

        Storage::disk('public')->assertExists('certificates/unclassified.bin');
        $this->assertCount(1, Storage::disk('documents')->allFiles('quarantine'));
        $this->assertStringNotContainsString('unclassified.bin', Artisan::output());
    }

    private function legacyOwners(): array
    {
        $certificate = 'certificates/certificate-private-name.pdf';
        $license = 'licenses/license-private-name.pdf';
        $cv = 'cvs/employee-private-name.pdf';
        Storage::disk('public')->put($certificate, $this->validPdfContent());
        Storage::disk('public')->put($license, $this->validPdfContent());
        Storage::disk('public')->put($cv, $this->validPdfContent());
        $owner = $this->owner('legacy');
        $pharmacy = Pharmacy::create([
            'pharmacist_id' => $owner->id,
            'pharmacy_name' => 'Legacy',
            'pharmacy_address' => 'Address',
            'certificate' => json_encode([$certificate]),
            'license' => json_encode([$license]),
            'status' => 'approved',
        ]);
        $employee = Employee::create([
            'name' => 'Legacy Employee',
            'phone' => '0999000000',
            'email' => 'legacy-employee@example.test',
            'password' => Hash::make('password'),
            'cv' => $cv,
            'role' => 'trainee',
            'status' => 'pending',
            'first_login' => true,
        ]);

        return [$pharmacy, $employee];
    }

    private function owner(string $suffix): Pharmacist
    {
        return Pharmacist::create([
            'name' => 'Owner',
            'email' => 'legacy-'.$suffix.'@example.test',
            'password' => Hash::make('password'),
        ]);
    }

    private function differentValidPdf(): string
    {
        $prefix = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $offset = strlen($prefix);

        return $prefix."xref\n0 1\n0000000000 65535 f \ntrailer\n<< /Size 1 >>\nstartxref\n{$offset}\n%%EOF\n";
    }
}
