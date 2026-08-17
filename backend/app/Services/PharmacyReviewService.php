<?php

namespace App\Services;

use App\Exceptions\AdminWorkflowException;
use App\Models\Admin;
use App\Models\Notification;
use App\Models\Pharmacy;
use App\Models\PharmacyDocumentVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PharmacyReviewService
{
    public function __construct(
        private readonly PrivateDocumentService $documents,
        private readonly AdminAuditLogger $audit,
    ) {}

    /** @return array{pharmacy:Pharmacy,idempotent:bool} */
    public function approve(Pharmacy $pharmacy, Admin $admin, int $expectedVersion, Request $request): array
    {
        return $this->decide($pharmacy, $admin, 'approved', $expectedVersion, null, $request);
    }

    /** @return array{pharmacy:Pharmacy,idempotent:bool} */
    public function reject(Pharmacy $pharmacy, Admin $admin, int $expectedVersion, string $reason, Request $request): array
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($reason));
        if (! is_string($normalized) || mb_strlen($normalized) < 5 || mb_strlen($normalized) > 500) {
            throw new AdminWorkflowException('A rejection reason between 5 and 500 characters is required.', 'rejection_reason_invalid', 422);
        }

        return $this->decide($pharmacy, $admin, 'rejected', $expectedVersion, $normalized, $request);
    }

    /** @return array{pharmacy:Pharmacy,idempotent:bool} */
    private function decide(
        Pharmacy $pharmacy,
        Admin $admin,
        string $decision,
        int $expectedVersion,
        ?string $reason,
        Request $request,
    ): array {
        return DB::transaction(function () use ($pharmacy, $admin, $decision, $expectedVersion, $reason, $request): array {
            $locked = Pharmacy::query()->lockForUpdate()->findOrFail($pharmacy->id);
            if ($locked->status !== 'pending') {
                if ($locked->status === $decision) {
                    $this->audit->record($request, $admin, 'pharmacy.review.duplicate', 'success', 'pharmacy', $locked->id, reason: 'already_'.$decision);

                    return ['pharmacy' => $locked->load(['pharmacist', 'documentVersions']), 'idempotent' => true];
                }

                throw new AdminWorkflowException('The pharmacy application has already been finalized.', 'review_already_finalized');
            }
            if ((int) $locked->review_version !== $expectedVersion) {
                throw new AdminWorkflowException('The pharmacy application changed before this decision was applied.', 'review_version_conflict');
            }

            $now = now();
            if ($decision === 'approved') {
                $pending = collect();
                foreach (PharmacyDocumentVersion::TYPES as $type) {
                    $version = $locked->documentVersions()
                        ->where('document_type', $type)
                        ->where('review_status', PharmacyDocumentVersion::STATUS_PENDING)
                        ->latest('version_number')
                        ->lockForUpdate()
                        ->first();
                    if (! $version || $this->documents->verifiedPharmacyDocumentContents($version) === null) {
                        throw new AdminWorkflowException(
                            'Valid pending legal documents are required before approval.',
                            'legal_documents_required',
                            422,
                        );
                    }
                    $pending->push($version);
                }

                foreach ($pending as $version) {
                    $locked->documentVersions()
                        ->where('document_type', $version->document_type)
                        ->where('review_status', PharmacyDocumentVersion::STATUS_APPROVED)
                        ->whereKeyNot($version->id)
                        ->update([
                            'review_status' => PharmacyDocumentVersion::STATUS_SUPERSEDED,
                            'superseded_at' => $now,
                        ]);
                    $version->forceFill([
                        'review_status' => PharmacyDocumentVersion::STATUS_APPROVED,
                        'reviewed_by_type' => $admin->getMorphClass(),
                        'reviewed_by_id' => $admin->id,
                        'decision_reason' => null,
                        'reviewed_at' => $now,
                        'effective_at' => $now,
                        'superseded_at' => null,
                    ])->save();
                }
            } else {
                $locked->documentVersions()
                    ->where('review_status', PharmacyDocumentVersion::STATUS_PENDING)
                    ->lockForUpdate()
                    ->get()
                    ->each(function (PharmacyDocumentVersion $version) use ($admin, $reason, $now): void {
                        $version->forceFill([
                            'review_status' => PharmacyDocumentVersion::STATUS_REJECTED,
                            'reviewed_by_type' => $admin->getMorphClass(),
                            'reviewed_by_id' => $admin->id,
                            'decision_reason' => $reason,
                            'reviewed_at' => $now,
                            'effective_at' => null,
                        ])->save();
                    });
            }

            $before = ['status' => 'pending', 'review_version' => (int) $locked->review_version];
            $locked->forceFill([
                'status' => $decision,
                'reviewed_by_admin_id' => $admin->id,
                'reviewed_at' => $now,
                'rejection_reason' => $reason,
                'review_version' => $locked->review_version + 1,
            ])->save();
            $this->audit->record(
                $request,
                $admin,
                $decision === 'approved' ? 'pharmacy.review.approved' : 'pharmacy.review.rejected',
                'success',
                'pharmacy',
                $locked->id,
                $reason,
                $before,
                ['status' => $decision, 'review_version' => (int) $locked->review_version],
            );

            // In-app notification for the pharmacy owner. Recipient is derived
            // from the locked pharmacy row, never from client input.
            Notification::create([
                'pharmacy_id' => $locked->id,
                'title' => $decision === 'approved' ? 'Pharmacy approved' : 'Pharmacy rejected',
                'message' => $decision === 'approved'
                    ? 'Your pharmacy registration has been approved.'
                    : 'Your pharmacy registration was rejected.'.($reason ? ' Reason: '.$reason : ''),
                'type' => $decision === 'approved' ? 'pharmacy_approved' : 'pharmacy_rejected',
                'is_read' => false,
                'date' => now(),
            ]);

            return ['pharmacy' => $locked->load(['pharmacist', 'documentVersions']), 'idempotent' => false];
        });
    }
}
