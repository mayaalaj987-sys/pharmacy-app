<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use App\Models\PharmacyDocumentVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class AdminTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
        Storage::fake('public');
    }

    protected function admin(string $suffix, string $role = Admin::ROLE_SUPER_ADMIN, bool $active = true): Admin
    {
        $admin = new Admin;
        $admin->forceFill([
            'name' => 'Admin '.$suffix,
            'email' => 'admin-'.$suffix.'@example.test',
            'password' => 'Strong!Password123',
            'role' => $role,
            'is_active' => $active,
            'auth_version' => 1,
            'password_changed_at' => now(),
            'disabled_at' => $active ? null : now(),
        ])->save();

        return $admin;
    }

    protected function asAdmin(Admin $admin): static
    {
        return $this->actingAs($admin, 'admin')->withSession(['admin_auth_version' => $admin->auth_version]);
    }

    protected function pendingPharmacy(string $suffix, bool $withDocuments = true): Pharmacy
    {
        $owner = Pharmacist::create([
            'name' => 'Owner '.$suffix,
            'email' => 'owner-'.$suffix.'@example.test',
            'password' => 'Strong!Password123',
        ]);
        $pharmacy = Pharmacy::create([
            'pharmacist_id' => $owner->id,
            'pharmacy_name' => 'Pharmacy '.$suffix,
            'pharmacy_address' => 'Address '.$suffix,
            'certificate' => '',
            'license' => '',
            'status' => 'pending',
        ]);
        if ($withDocuments) {
            foreach (PharmacyDocumentVersion::TYPES as $type) {
                $this->pharmacyDocument($pharmacy, $type);
            }
        }

        return $pharmacy;
    }

    protected function pharmacyDocument(Pharmacy $pharmacy, string $type): PharmacyDocumentVersion
    {
        $contents = $this->validPdfContent();
        $key = 'pharmacy-documents/'.$type.'/'.Str::uuid().'.pdf';
        Storage::disk('documents')->put($key, $contents);

        return $pharmacy->documentVersions()->create([
            'document_type' => $type,
            'version_number' => 1,
            'storage_key' => $key,
            'verified_mime_type' => 'application/pdf',
            'byte_size' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'review_status' => PharmacyDocumentVersion::STATUS_PENDING,
        ]);
    }
}
