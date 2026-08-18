<?php

namespace Tests\Feature\Documents;

use App\Exceptions\DocumentUploadException;
use App\Models\Admin;
use App\Models\Employee;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use App\Models\PharmacyDocumentVersion;
use App\Services\DocumentVersionService;
use App\Services\PrivateDocumentService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PrivateDocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('documents');
    }

    public function test_pdf_jpeg_and_png_are_verified_and_stored_only_on_the_private_disk(): void
    {
        [, $pharmacy, $token] = $this->authenticatedOwner('formats');
        $files = [
            $this->validPdfUpload('certificate.pdf'),
            $this->jpegUpload('certificate.jpeg'),
            $this->pngUpload('certificate.png'),
        ];

        foreach ($files as $index => $file) {
            $response = $this->withToken($token)
                ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
                ->post('/api/pharmacy/documents/certificate', ['document' => $file], ['Accept' => 'application/json'])
                ->assertCreated()
                ->assertJsonPath('code', 'document_uploaded')
                ->assertJsonPath('data.version', $index + 1);

            foreach (['storage_key', 'path', 'disk', 'sha256', 'filename'] as $unsafe) {
                $this->assertStringNotContainsString($unsafe, $response->getContent());
            }
        }

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertCount(3, Storage::disk('documents')->allFiles('pharmacy-documents'));
        $this->assertNull(config('filesystems.disks.documents.url'));
        $this->assertFalse(config('filesystems.disks.documents.serve'));
        $this->assertTrue(config('filesystems.disks.documents.throw'));
    }

    public function test_document_limit_accepts_five_mib_and_rejects_larger_uploads(): void
    {
        [, $pharmacy, $token] = $this->authenticatedOwner('sizes');

        $this->withToken($token)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->post('/api/pharmacy/documents/license', [
                'document' => UploadedFile::fake()->createWithContent('license.pdf', $this->sizedPdf(5 * 1024 * 1024)),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $this->withToken($token)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->post('/api/pharmacy/documents/license', [
                'document' => UploadedFile::fake()->create('too-large.pdf', 5121, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors('document');
    }

    public function test_spoofed_malformed_active_and_unsupported_documents_are_rejected(): void
    {
        [, $pharmacy, $token] = $this->authenticatedOwner('invalid');
        $invalidFiles = [
            UploadedFile::fake()->createWithContent('renamed.jpg', $this->validPdfContent()),
            UploadedFile::fake()->createWithContent('broken.pdf', "%PDF-1.4\nnot complete"),
            UploadedFile::fake()->createWithContent('active.pdf', $this->activePdf()),
            UploadedFile::fake()->createWithContent('polyglot.pdf', $this->validPdfContent()."PK\x03\x04archive"),
            UploadedFile::fake()->createWithContent('vector.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script/></svg>'),
            UploadedFile::fake()->createWithContent('archive.zip', "PK\x03\x04malicious"),
            UploadedFile::fake()->createWithContent('program.exe', "MZ\x90\x00malicious"),
            UploadedFile::fake()->createWithContent('broken.png', "\x89PNG\r\n\x1a\ninvalid"),
        ];

        foreach ($invalidFiles as $file) {
            $this->withToken($token)
                ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
                ->post('/api/pharmacy/documents/certificate', ['document' => $file], ['Accept' => 'application/json'])
                ->assertUnprocessable();
        }

        $this->assertDatabaseCount('pharmacy_document_versions', 0);
        $this->assertSame([], Storage::disk('documents')->allFiles());
    }

    public function test_replacement_is_a_new_pending_version_and_the_approved_original_remains_effective(): void
    {
        [$owner, $pharmacy, $token] = $this->authenticatedOwner('replacement');
        $first = $this->uploadPharmacyDocument($pharmacy, $token, 'certificate', $this->validPdfUpload('first.pdf'));
        $first->update([
            'review_status' => PharmacyDocumentVersion::STATUS_APPROVED,
            'reviewed_at' => now(),
            'effective_at' => now(),
        ]);

        $second = $this->uploadPharmacyDocument($pharmacy, $token, 'certificate', $this->pngUpload('replacement.png'));
        $this->assertSame(2, $second->version_number);
        $this->assertSame(PharmacyDocumentVersion::STATUS_PENDING, $second->review_status);
        $this->assertSame(PharmacyDocumentVersion::STATUS_APPROVED, $first->fresh()->review_status);
        $this->assertNotNull($first->fresh()->effective_at);
        $this->assertSame('approved', $pharmacy->fresh()->status);
        Storage::disk('documents')->assertExists($first->storage_key);
        Storage::disk('documents')->assertExists($second->storage_key);

        Storage::disk('documents')->delete($second->storage_key);
        $this->withToken($token)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.session.active_pharmacy.certificate_on_file', true);
        Storage::disk('documents')->put($second->storage_key, $this->pngBytes());

        app(DocumentVersionService::class)->approvePharmacyDocument($second, $owner);
        $this->assertSame(PharmacyDocumentVersion::STATUS_SUPERSEDED, $first->fresh()->review_status);
        $this->assertNotNull($first->fresh()->superseded_at);
        $this->assertSame(PharmacyDocumentVersion::STATUS_APPROVED, $second->fresh()->review_status);
    }

    public function test_rejected_replacement_never_becomes_effective(): void
    {
        [$owner, $pharmacy, $token] = $this->authenticatedOwner('reject');
        $first = $this->uploadPharmacyDocument($pharmacy, $token, 'license', $this->validPdfUpload('first.pdf'));
        $first->update(['review_status' => 'approved', 'effective_at' => now()]);
        $replacement = $this->uploadPharmacyDocument($pharmacy, $token, 'license', $this->validPdfUpload('replacement.pdf'));

        app(DocumentVersionService::class)->rejectPharmacyDocument($replacement, $owner, 'The document is unreadable.');

        $this->assertSame('approved', $first->fresh()->review_status);
        $this->assertNotNull($first->fresh()->effective_at);
        $this->assertSame('rejected', $replacement->fresh()->review_status);
        $this->assertNull($replacement->fresh()->effective_at);
    }

    public function test_legal_original_identity_and_integrity_metadata_are_immutable(): void
    {
        [, $pharmacy, $token] = $this->authenticatedOwner('immutable');
        $document = $this->uploadPharmacyDocument($pharmacy, $token, 'certificate', $this->validPdfUpload());

        $this->expectException(\LogicException::class);
        $document->update(['storage_key' => 'pharmacy-documents/certificate/replaced.pdf']);
    }

    public function test_pharmacy_metadata_and_download_are_owner_only_and_tenant_scoped(): void
    {
        [, $pharmacy, $token] = $this->authenticatedOwner('owner');
        $document = $this->uploadPharmacyDocument($pharmacy, $token, 'certificate', $this->validPdfUpload());

        $this->withToken($token)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->getJson('/api/pharmacy/documents')
            ->assertOk()
            ->assertJsonPath('data.0.id', $document->id)
            ->assertJsonMissingPath('data.0.storage_key')
            ->assertJsonMissingPath('data.0.sha256');

        $download = $this->withToken($token)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->get('/api/pharmacy/documents/'.$document->id.'/download')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('private', (string) $download->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $download->headers->get('Cache-Control'));
        $this->assertStringContainsString('attachment;', (string) $download->headers->get('Content-Disposition'));

        [, $foreignPharmacy, $foreignToken] = $this->authenticatedOwner('foreign');
        $this->app['auth']->forgetGuards();
        $this->withToken($foreignToken)
            ->withHeader('X-Pharmacy-Id', (string) $foreignPharmacy->id)
            ->getJson('/api/pharmacy/documents/'.$document->id.'/download')
            ->assertForbidden();

        $publicAttempt = $this->get('/storage/'.$document->storage_key);
        $this->assertContains($publicAttempt->status(), [403, 404]);
    }

    public function test_employee_documents_are_employee_self_access_only(): void
    {
        $employee = $this->employee('self');
        $token = $employee->createToken('app', ['app'])->plainTextToken;
        $response = $this->withToken($token)
            ->post('/api/employee/documents/cv', ['document' => $this->validPdfUpload('cv.pdf')], ['Accept' => 'application/json'])
            ->assertCreated();
        $documentId = $response->json('data.id');

        $this->withToken($token)->get('/api/employee/documents/'.$documentId.'/download')->assertOk();

        $other = $this->employee('other');
        $otherToken = $other->createToken('app', ['app'])->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken)
            ->getJson('/api/employee/documents/'.$documentId.'/download')
            ->assertForbidden();

        [, $pharmacy, $ownerToken] = $this->authenticatedOwner('recruiter');
        $this->app['auth']->forgetGuards();
        $this->withToken($ownerToken)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->getJson('/api/employee/documents/'.$documentId.'/download')
            ->assertUnauthorized();
    }

    public function test_employee_replacement_versions_are_retained_until_a_retention_policy_exists(): void
    {
        $employee = $this->employee('retention');
        $token = $employee->createToken('app', ['app'])->plainTextToken;
        $firstId = $this->withToken($token)
            ->post('/api/employee/documents/cv', ['document' => $this->validPdfUpload('first.pdf')], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data.id');
        $secondId = $this->withToken($token)
            ->post('/api/employee/documents/cv', ['document' => $this->pngUpload('second.png')], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data.id');

        $first = $employee->documentVersions()->findOrFail($firstId);
        $second = $employee->documentVersions()->findOrFail($secondId);
        $this->assertNotNull($first->superseded_at);
        $this->assertNull($second->superseded_at);
        Storage::disk('documents')->assertExists($first->storage_key);
        Storage::disk('documents')->assertExists($second->storage_key);
    }

    public function test_employee_with_recruitment_documents_is_not_hard_deleted_by_dismissal(): void
    {
        [, $pharmacy, $ownerToken] = $this->authenticatedOwner('retained-dismissal');
        $employee = $this->employee('dismissal');
        $employeeToken = $employee->createToken('app', ['app'])->plainTextToken;
        $this->withToken($employeeToken)
            ->post('/api/employee/documents/cv', ['document' => $this->validPdfUpload('cv.pdf')], ['Accept' => 'application/json'])
            ->assertCreated();
        $employee->forceFill([
            'pharmacy_id' => $pharmacy->id,
            'shift' => Employee::SHIFT_MORNING,
            'status' => Employee::STATUS_APPROVED,
        ])->save();

        $this->app['auth']->forgetGuards();
        $this->withToken($ownerToken)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->deleteJson('/api/employees/'.$employee->id.'/dismiss')
            ->assertStatus(409)
            ->assertJsonPath('code', 'employee_document_retention_required');

        $this->assertDatabaseHas('employees', ['id' => $employee->id]);
        $this->assertDatabaseHas('employee_document_versions', ['employee_id' => $employee->id]);
        $this->assertCount(1, Storage::disk('documents')->allFiles('employee-documents'));
    }

    public function test_pharmacy_employee_lists_never_expose_recruitment_document_references(): void
    {
        [, $pharmacy, $token] = $this->authenticatedOwner('safe-employee-list');
        Employee::create([
            'name' => 'Applicant',
            'phone' => '0999000011',
            'email' => 'applicant@example.test',
            'password' => Hash::make('password'),
            'cv' => 'cvs/confidential-original.pdf',
            'experience_proof' => 'experience/confidential-proof.pdf',
            'role' => 'employee',
            'status' => 'pending',
            'first_login' => true,
        ]);

        $response = $this->withToken($token)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->getJson('/api/employees/pending')
            ->assertOk();
        $this->assertStringNotContainsString('confidential-original', $response->getContent());
        $this->assertStringNotContainsString('confidential-proof', $response->getContent());
        $this->assertStringNotContainsString('experience_proof', $response->getContent());
        $this->assertStringNotContainsString('"cv"', $response->getContent());
    }

    public function test_database_failure_removes_the_new_file_and_preserves_the_old_version(): void
    {
        [, $pharmacy, $token] = $this->authenticatedOwner('rollback');
        $old = $this->uploadPharmacyDocument($pharmacy, $token, 'certificate', $this->validPdfUpload('old.pdf'));
        $oldKey = $old->storage_key;
        PharmacyDocumentVersion::creating(function (): void {
            throw new RuntimeException('forced database failure');
        });

        $this->withToken($token)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->post('/api/pharmacy/documents/certificate', ['document' => $this->pngUpload('new.png')], ['Accept' => 'application/json'])
            ->assertServerError();

        $this->assertDatabaseCount('pharmacy_document_versions', 1);
        Storage::disk('documents')->assertExists($oldKey);
        $this->assertSame([$oldKey], Storage::disk('documents')->allFiles('pharmacy-documents'));
    }

    public function test_storage_failure_creates_no_database_record(): void
    {
        [, $pharmacy, $token] = $this->authenticatedOwner('storage-failure');
        $rootFile = tempnam(sys_get_temp_dir(), 'document-disk-root-');
        Storage::forgetDisk('documents');
        config([
            'filesystems.disks.documents.root' => $rootFile,
            'filesystems.disks.documents.throw' => true,
        ]);

        try {
            $this->withToken($token)
                ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
                ->post('/api/pharmacy/documents/certificate', ['document' => $this->validPdfUpload()], ['Accept' => 'application/json'])
                ->assertServerError()
                ->assertJsonPath('code', 'document_storage_failed');
            $this->assertDatabaseCount('pharmacy_document_versions', 0);
        } finally {
            @unlink($rootFile);
        }
    }

    public function test_storage_returning_false_is_reported_and_leaves_no_file(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('put')->once()->andReturn(false);
        $disk->shouldReceive('delete')->once()->andReturn(true);
        Storage::shouldReceive('disk')->with('documents')->andReturn($disk);

        try {
            app(PrivateDocumentService::class)->storeUpload(
                $this->validPdfUpload(),
                'pharmacy-documents',
                'certificate',
            );
            $this->fail('A false storage result must fail closed.');
        } catch (DocumentUploadException $exception) {
            $this->assertSame('document_storage_verification_failed', $exception->errorCode);
            $this->assertSame(500, $exception->status);
        }
    }

    public function test_registration_partial_upload_failures_leave_no_orphans_or_database_rows(): void
    {
        $this->post('/api/register', [
            'name' => 'Failed Owner',
            'email' => 'failed-owner@example.test',
            'password' => 'password',
            'pharmacy_name' => 'Failed Pharmacy',
            'pharmacy_address' => 'Address',
            'profile_image' => $this->pngUpload('profile.png'),
            'certificate' => $this->validPdfUpload('certificate.pdf'),
            'license' => UploadedFile::fake()->createWithContent('license.pdf', "%PDF-1.4\nbroken"),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->assertDatabaseCount('pharmacists', 0);
        $this->assertDatabaseCount('pharmacies', 0);
        $this->assertDatabaseCount('pharmacy_document_versions', 0);
        $this->assertSame([], Storage::disk('documents')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles('profiles'));
    }

    public function test_add_pharmacy_and_employee_registration_partial_failures_leave_no_orphans(): void
    {
        [$owner, $active, $token] = $this->authenticatedOwner('partial');
        $this->withToken($token)
            ->post('/api/pharmacy/add', [
                'pharmacy_name' => 'Second',
                'pharmacy_address' => 'Address',
                'certificate' => $this->validPdfUpload('certificate.pdf'),
                'license' => UploadedFile::fake()->createWithContent('license.pdf', 'broken'),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable();
        $this->assertSame(1, $owner->pharmacies()->count());
        $this->assertDatabaseHas('pharmacies', ['id' => $active->id]);
        $this->assertSame([], Storage::disk('documents')->allFiles());

        $this->post('/api/employee/register', [
            'name' => 'Failed Employee',
            'phone' => '0999000022',
            'email' => 'failed-employee@example.test',
            'password' => 'password',
            'role' => 'employee',
            'cv' => $this->validPdfUpload('cv.pdf'),
            'experience_proof' => UploadedFile::fake()->createWithContent('experience.pdf', "%PDF-1.4\nbroken"),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertDatabaseCount('employees', 0);
        $this->assertSame([], Storage::disk('documents')->allFiles());
    }

    public function test_initial_admin_approval_requires_and_activates_valid_document_versions_without_exposing_paths(): void
    {
        $response = $this->post('/api/register', [
            'name' => 'Owner',
            'email' => 'new-owner@example.test',
            'password' => 'password',
            'pharmacy_name' => 'New Pharmacy',
            'pharmacy_address' => 'New Address',
            'certificate' => $this->validPdfUpload('certificate.pdf'),
            'license' => $this->pngUpload('license.png'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $pharmacyId = $response->json('data.pharmacy.id');

        $admin = new Admin;
        $admin->forceFill([
            'name' => 'Document Reviewer',
            'email' => 'document-reviewer@example.test',
            'password' => 'Strong!Password123',
            'role' => Admin::ROLE_PHARMACY_REVIEWER,
            'is_active' => true,
            'auth_version' => 1,
            'password_changed_at' => now(),
        ])->save();

        $adminResponse = $this->actingAs($admin, 'admin')
            ->withSession(['admin_auth_version' => 1])
            ->postJson('/api/admin/review/applications/'.$pharmacyId.'/approve', ['review_version' => 0])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseCount('pharmacy_document_versions', 2);
        $this->assertSame(2, PharmacyDocumentVersion::where('review_status', 'approved')->whereNotNull('effective_at')->count());
        foreach (['storage_key', 'sha256', 'certificate":', 'license":'] as $unsafe) {
            $this->assertStringNotContainsString($unsafe, $adminResponse->getContent());
        }

        $undocumented = $this->pharmacy($this->owner('undocumented'), 'undocumented', 'pending');
        $this->actingAs($admin, 'admin')
            ->withSession(['admin_auth_version' => 1])
            ->postJson('/api/admin/review/applications/'.$undocumented->id.'/approve', ['review_version' => 0])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'legal_documents_required');
    }

    public function test_missing_or_malformed_legacy_values_do_not_set_document_flags(): void
    {
        [, $pharmacy, $token] = $this->authenticatedOwner('flags');
        $pharmacy->forceFill([
            'certificate' => json_encode(['../confidential.pdf']),
            'license' => json_encode(['licenses/missing.pdf']),
        ])->save();

        $this->withToken($token)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.session.active_pharmacy.certificate_on_file', false)
            ->assertJsonPath('data.session.active_pharmacy.license_on_file', false);
    }

    private function uploadPharmacyDocument(
        Pharmacy $pharmacy,
        string $token,
        string $type,
        UploadedFile $file,
    ): PharmacyDocumentVersion {
        $id = $this->withToken($token)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->post('/api/pharmacy/documents/'.$type, ['document' => $file], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data.id');

        return PharmacyDocumentVersion::findOrFail($id);
    }

    private function authenticatedOwner(string $suffix): array
    {
        $owner = $this->owner($suffix);
        $pharmacy = $this->pharmacy($owner, $suffix, 'approved');
        $token = $owner->createToken('app', ['app'])->plainTextToken;

        return [$owner, $pharmacy, $token];
    }

    private function owner(string $suffix): Pharmacist
    {
        return Pharmacist::create([
            'name' => 'Owner '.$suffix,
            'email' => 'owner-'.$suffix.'@example.test',
            'password' => Hash::make('password'),
        ]);
    }

    private function pharmacy(Pharmacist $owner, string $suffix, string $status): Pharmacy
    {
        return Pharmacy::create([
            'pharmacist_id' => $owner->id,
            'pharmacy_name' => 'Pharmacy '.$suffix,
            'pharmacy_address' => 'Address '.$suffix,
            'certificate' => '',
            'license' => '',
            'status' => $status,
        ]);
    }

    private function employee(string $suffix): Employee
    {
        return Employee::create([
            'name' => 'Employee '.$suffix,
            'phone' => '0999000000',
            'email' => 'employee-'.$suffix.'@example.test',
            'password' => Hash::make('password'),
            'cv' => '',
            'role' => 'employee',
            'status' => 'pending',
            'first_login' => true,
        ]);
    }

    private function pngUpload(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $this->pngBytes());
    }

    private function pngBytes(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
    }

    private function jpegUpload(string $name): UploadedFile
    {
        $image = imagecreatetruecolor(2, 2);
        imagefill($image, 0, 0, imagecolorallocate($image, 20, 80, 140));
        ob_start();
        imagejpeg($image, null, 90);
        $contents = ob_get_clean();
        imagedestroy($image);

        return UploadedFile::fake()->createWithContent($name, $contents);
    }

    private function activePdf(): string
    {
        $prefix = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /OpenAction 2 0 R >>\nendobj\n";
        $offset = strlen($prefix);

        return $prefix."xref\n0 1\n0000000000 65535 f \ntrailer\n<< /Size 1 >>\nstartxref\n{$offset}\n%%EOF\n";
    }

    private function sizedPdf(int $targetBytes): string
    {
        $prefix = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%";
        $fillerLength = $targetBytes - strlen($prefix) - 100;
        do {
            $xrefOffset = strlen($prefix) + max(0, $fillerLength) + 1;
            $suffix = "\nxref\n0 2\n0000000000 65535 f \n0000000009 00000 n \ntrailer\n<< /Size 2 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";
            $newLength = $targetBytes - strlen($prefix) - strlen($suffix);
            $changed = $newLength !== $fillerLength;
            $fillerLength = $newLength;
        } while ($changed);

        return $prefix.str_repeat('A', $fillerLength).$suffix;
    }
}
