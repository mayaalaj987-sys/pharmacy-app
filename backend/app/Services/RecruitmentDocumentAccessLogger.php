<?php

namespace App\Services;

use App\Models\EmployeeDocumentVersion;
use App\Models\Notification;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use App\Models\RecruitmentDocumentAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Records that a recruiter opened an applicant's file, and tells the applicant.
 *
 * There is no quota on reading CVs — that was decided deliberately. So this is
 * the whole of the control: every open is attributable, and the person whose
 * file it is finds out. A privacy log nobody reads is a liability rather than a
 * protection, which is why the notification is part of the same act.
 */
class RecruitmentDocumentAccessLogger
{
    public function record(
        Request $request,
        Pharmacist $pharmacist,
        ?Pharmacy $pharmacy,
        EmployeeDocumentVersion $document,
        string $action,
    ): void {
        DB::transaction(function () use ($request, $pharmacist, $pharmacy, $document, $action): void {
            RecruitmentDocumentAccess::create([
                'pharmacist_id' => $pharmacist->id,
                'pharmacy_id' => $pharmacy?->id,
                'employee_id' => $document->employee_id,
                'employee_document_version_id' => $document->id,
                'action' => $action,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 512),
                'accessed_at' => now(),
            ]);

            $this->announceOncePerDay($document);
        });
    }

    /**
     * One anonymous "your document was viewed" per day, across every pharmacy.
     *
     * Naming the pharmacy on every open used to be the whole notice — but a
     * recruiter may read any in-pool CV without asking, so being told exactly
     * who looked, every time, reads as surveillance rather than a service. The
     * count of distinct pharmacies is still exact and still theirs to see
     * ({@see \App\Http\Resources\EmployeeDocumentVersionResource}); this ping is
     * only a nudge to go look at it, so one per day is enough regardless of how
     * many pharmacies opened the file or how many times.
     */
    private function announceOncePerDay(EmployeeDocumentVersion $document): void
    {
        $alreadyToldToday = RecruitmentDocumentAccess::query()
            ->where('employee_id', $document->employee_id)
            ->whereDate('accessed_at', now()->toDateString())
            // The row for this very access is already written, so anything
            // beyond it means today has already been announced.
            ->count() > 1;

        if ($alreadyToldToday) {
            return;
        }

        Notification::notifyEmployee(
            $document->employee_id,
            'Your application was viewed',
            'Your '.$this->label($document->document_type).' was viewed today.',
            Notification::TYPE_CV_VIEWED,
        );
    }

    private function label(string $type): string
    {
        return $type === EmployeeDocumentVersion::TYPE_CV ? 'CV' : 'training certificate';
    }
}