<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\JobOffer;
use App\Models\Notification;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use Illuminate\Support\Facades\DB;

/**
 * Every write that changes who works where.
 *
 * Kept in one place so the transaction boundaries and the notifications that
 * belong to them cannot drift apart: an offer and the message announcing it are
 * the same act, and a client must never see one without the other.
 */
class RecruitmentService
{
    /**
     * Send or re-send an offer.
     *
     * `unique(pharmacy_id, employee_id)` means a pharmacy holds at most one
     * offer per person, so changing your mind about the shift or the salary
     * edits the existing row rather than stacking a competing one. That also
     * makes it impossible to spam an applicant by sending repeatedly.
     */
    public function sendOffer(
        Pharmacy $pharmacy,
        Employee $employee,
        Pharmacist $sender,
        string $shift,
        ?float $salary,
    ): JobOffer {
        return DB::transaction(function () use ($pharmacy, $employee, $sender, $shift, $salary) {
            $offer = JobOffer::updateOrCreate(
                ['pharmacy_id' => $pharmacy->id, 'employee_id' => $employee->id],
                [
                    'created_by_pharmacist_id' => $sender->id,
                    'shift' => $shift,
                    'salary' => $salary,
                    'status' => JobOffer::STATUS_PENDING,
                    // Refreshed on every send: an offer reopened today should
                    // sort as today's, not as the date it was first made.
                    'offered_at' => now(),
                    'responded_at' => null,
                ],
            );

            Notification::notifyEmployee(
                $employee->id,
                'You have a job offer',
                $pharmacy->pharmacy_name.' offered you the '.$shift.' shift'
                    .($salary === null ? '' : ' at '.rtrim(rtrim(number_format($salary, 2, '.', ''), '0'), '.'))
                    .'.',
                Notification::TYPE_OFFER_RECEIVED,
            );

            return $offer;
        });
    }

    /** Pull an offer that has not been accepted. */
    public function withdrawOffer(JobOffer $offer): JobOffer
    {
        return DB::transaction(function () use ($offer) {
            $offer->forceFill([
                'status' => JobOffer::STATUS_WITHDRAWN,
                'responded_at' => now(),
            ])->save();

            Notification::notifyEmployee(
                $offer->employee_id,
                'An offer was withdrawn',
                ($offer->pharmacy?->pharmacy_name ?? 'A pharmacy')
                    .' withdrew its offer for the '.$offer->shift.' shift.',
                Notification::TYPE_OFFER_WITHDRAWN,
            );

            return $offer;
        });
    }
}
