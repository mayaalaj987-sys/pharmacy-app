<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\PharmacyDocumentVersion;
use App\Services\PrivateDocumentService;
use Illuminate\Support\Facades\Storage;

class AdminReviewWorkflowTest extends AdminTestCase
{
    public function test_review_list_and_detail_are_safe_and_role_authorized(): void
    {
        $reviewer = $this->admin('safe-list', Admin::ROLE_PHARMACY_REVIEWER);
        $pharmacy = $this->pendingPharmacy('safe-list');

        $list = $this->asAdmin($reviewer)->getJson('/api/admin/review/applications')
            ->assertOk()
            ->assertJsonPath('data.0.id', $pharmacy->id)
            ->assertJsonPath('data.0.review_version', 0)
            ->assertJsonStructure(['data' => [['documents' => [['id', 'type', 'review_status', 'mime_category', 'size_bytes', 'preview_url', 'download_url']]]], 'meta']);
        $detail = $this->asAdmin($reviewer)->getJson('/api/admin/review/applications/'.$pharmacy->id)->assertOk();

        foreach (['storage_key', 'sha256', 'legacy_locator_hash', 'filename', 'disk', 'certificate":', 'license":'] as $unsafe) {
            $this->assertStringNotContainsString($unsafe, $list->getContent());
            $this->assertStringNotContainsString($unsafe, $detail->getContent());
        }
    }

    public function test_private_document_preview_and_download_are_scoped_verified_and_audited(): void
    {
        $reviewer = $this->admin('document', Admin::ROLE_PHARMACY_REVIEWER);
        $pharmacy = $this->pendingPharmacy('document');
        $document = $pharmacy->documentVersions()->firstOrFail();

        $preview = $this->asAdmin($reviewer)
            ->get('/api/admin/review/applications/'.$pharmacy->id.'/documents/'.$document->public_id.'/preview')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('inline;', (string) $preview->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', (string) $preview->headers->get('Cache-Control'));

        $this->asAdmin($reviewer)
            ->get('/api/admin/review/applications/'.$pharmacy->id.'/documents/'.$document->public_id.'/download')
            ->assertOk();
        $other = $this->pendingPharmacy('other-document');
        $this->asAdmin($reviewer)
            ->getJson('/api/admin/review/applications/'.$other->id.'/documents/'.$document->public_id.'/preview')
            ->assertNotFound();
        $this->assertDatabaseHas('admin_audit_logs', ['admin_id' => $reviewer->id, 'action' => 'pharmacy.document.previewed']);
        $this->assertDatabaseHas('admin_audit_logs', ['admin_id' => $reviewer->id, 'action' => 'pharmacy.document.downloaded']);

        Storage::disk('documents')->delete($document->storage_key);
        $this->asAdmin($reviewer)
            ->getJson('/api/admin/review/applications/'.$pharmacy->id.'/documents/'.$document->public_id.'/preview')
            ->assertNotFound()->assertJsonPath('code', 'document_unavailable');
    }

    public function test_private_document_routes_reject_unauthenticated_and_unsafe_identifiers(): void
    {
        $pharmacy = $this->pendingPharmacy('unsafe-document');
        $document = $pharmacy->documentVersions()->firstOrFail();
        $this->getJson('/api/admin/review/applications/'.$pharmacy->id.'/documents/'.$document->public_id.'/preview')
            ->assertUnauthorized();
        $reviewer = $this->admin('unsafe-document', Admin::ROLE_PHARMACY_REVIEWER);
        $this->asAdmin($reviewer)->getJson('/api/admin/review/applications/'.$pharmacy->id.'/documents/%2e%2e%2fpreview')
            ->assertNotFound();

        foreach (['../../outside.pdf', 'C:\\private\\outside.pdf', '%2e%2e/outside.pdf'] as $unsafe) {
            $copy = clone $document;
            $copy->forceFill(['storage_key' => $unsafe]);
            $this->assertNull(app(PrivateDocumentService::class)->verifiedPharmacyDocumentContents($copy));
        }
    }

    public function test_approval_is_attributed_transactional_idempotent_and_conflict_safe(): void
    {
        $reviewer = $this->admin('approve', Admin::ROLE_PHARMACY_REVIEWER);
        $pharmacy = $this->pendingPharmacy('approve');

        $this->asAdmin($reviewer)->postJson('/api/admin/review/applications/'.$pharmacy->id.'/approve', ['review_version' => 0])
            ->assertOk()->assertJsonPath('code', 'pharmacy_approved')->assertJsonPath('data.status', 'approved');
        $this->assertSame($reviewer->id, $pharmacy->fresh()->reviewed_by_admin_id);
        $this->assertSame(1, $pharmacy->fresh()->review_version);
        $this->assertSame(2, PharmacyDocumentVersion::where('review_status', 'approved')->where('reviewed_by_id', $reviewer->id)->count());
        $this->assertDatabaseHas('admin_audit_logs', ['admin_id' => $reviewer->id, 'action' => 'pharmacy.review.approved', 'target_id' => (string) $pharmacy->id]);

        $this->asAdmin($reviewer)->postJson('/api/admin/review/applications/'.$pharmacy->id.'/approve', ['review_version' => 0])
            ->assertOk()->assertJsonPath('code', 'review_already_applied');
        $this->asAdmin($reviewer)->postJson('/api/admin/review/applications/'.$pharmacy->id.'/reject', ['review_version' => 1, 'reason' => 'Opposite decision'])
            ->assertStatus(409)->assertJsonPath('code', 'review_already_finalized');
    }

    public function test_rejection_requires_a_bounded_reason_normalizes_it_and_rejects_stale_reviews(): void
    {
        $reviewer = $this->admin('reject', Admin::ROLE_PHARMACY_REVIEWER);
        $pharmacy = $this->pendingPharmacy('reject');

        $this->asAdmin($reviewer)->postJson('/api/admin/review/applications/'.$pharmacy->id.'/reject', [
            'review_version' => 0, 'reason' => 'bad',
        ])->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
        $this->asAdmin($reviewer)->postJson('/api/admin/review/applications/'.$pharmacy->id.'/reject', [
            'review_version' => 99, 'reason' => 'The legal document is incomplete.',
        ])->assertStatus(409)->assertJsonPath('code', 'review_version_conflict');

        $this->asAdmin($reviewer)->postJson('/api/admin/review/applications/'.$pharmacy->id.'/reject', [
            'review_version' => 0, 'reason' => "  The legal   document\n is incomplete.  ",
        ])->assertOk()->assertJsonPath('code', 'pharmacy_rejected');
        $this->assertSame('The legal document is incomplete.', $pharmacy->fresh()->rejection_reason);
        $audit = AdminAuditLog::where('action', 'pharmacy.review.rejected')->firstOrFail();
        $this->assertSame('The legal document is incomplete.', $audit->reason);
    }

    public function test_approval_fails_closed_when_documents_are_missing_or_tampered(): void
    {
        $reviewer = $this->admin('integrity', Admin::ROLE_PHARMACY_REVIEWER);
        $missing = $this->pendingPharmacy('missing', false);
        $this->asAdmin($reviewer)->postJson('/api/admin/review/applications/'.$missing->id.'/approve', ['review_version' => 0])
            ->assertUnprocessable()->assertJsonPath('code', 'legal_documents_required');

        $tampered = $this->pendingPharmacy('tampered');
        $document = $tampered->documentVersions()->firstOrFail();
        Storage::disk('documents')->put($document->storage_key, 'tampered');
        $this->asAdmin($reviewer)->postJson('/api/admin/review/applications/'.$tampered->id.'/approve', ['review_version' => 0])
            ->assertUnprocessable()->assertJsonPath('code', 'legal_documents_required');
        $this->assertSame('pending', $tampered->fresh()->status);
    }
}
