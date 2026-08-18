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

            $this->announceOncePerDay($pharmacist, $pharmacy, $document);
        });
    }

    /**
     * One "your CV was viewed" per pharmacy per day.
     *
     * A recruiter previewing a file and then downloading it is two rows in the
     * log and one act of interest. Announcing both would tell the applicant the
     * same thing twice within a minute, and a feed of "somebody looked at you"
     * stops being news very quickly. The log keeps every access; this only
     * decides when to interrupt someone.
     */
    private function announceOncePerDay(
        Pharmacist $pharmacist,
        ?Pharmacy $pharmacy,
        EmployeeDocumentVersion $document,
    ): void {
        $alreadyToldToday = RecruitmentDocumentAccess::query()
            ->where('employee_id', $document->employee_id)
            ->where('pharmacist_id', $pharmacist->id)
            ->whereDate('accessed_at', now()->toDateString())
            // The row for this very access is already written, so anything
            // beyond it means we have announced this pharmacy today.
            ->count() > 1;

        if ($alreadyToldToday) {
            return;
        }

        $name = $pharmacy?->pharmacy_name ?? 'A pharmacy';

        Notification::notifyEmployee(
            $document->employee_id,
            'Your application was viewed',
            $name.' opened your '.$this->label($document->document_type).'.',
            Notification::TYPE_CV_VIEWED,
        );
    }

    private function label(string $type): string
    {
        return $type === EmployeeDocumentVersion::TYPE_CV ? 'CV' : 'training certificate';
    }
}
