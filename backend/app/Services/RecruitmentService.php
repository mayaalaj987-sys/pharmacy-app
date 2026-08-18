<?php

namespace App\Services;

use App\Exceptions\RecruitmentException;
use App\Models\Employee;
use App\Models\Employment;
use App\Models\JobOffer;
use App\Models\Notification;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use Illuminate\Database\QueryException;
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

    /**
     * The applicant takes one offer.
     *
     * Locks pharmacy, then employee, then offer — that order everywhere, so
     * accepting and withdrawing cannot deadlock against each other. On SQLite
     * those locks do nothing at all, which is why the unique index on
     * (pharmacy_id, shift) is the real guarantee and a QueryException from it is
     * translated into the same refusal as the pre-check.
     *
     * The other offers this person holds are left exactly as they are. Nothing
     * marks them superseded, so the day they leave this job those offers are
     * simply acceptable again.
     */
    public function acceptOffer(JobOffer $offer, Employee $actor): Employee
    {
        return DB::transaction(function () use ($offer, $actor) {
            $pharmacy = Pharmacy::lockForUpdate()->find($offer->pharmacy_id);

            if ($pharmacy === null || ! $pharmacy->isOperational()) {
                throw new RecruitmentException(
                    'This pharmacy is not operating right now.',
                    'pharmacy_unavailable',
                );
            }

            $employee = Employee::lockForUpdate()->find($actor->id);

            if ($employee === null || $employee->isEmployed()) {
                throw new RecruitmentException(
                    'You already have a job. Leave it before accepting another offer.',
                    'already_employed',
                );
            }

            $locked = JobOffer::lockForUpdate()->find($offer->id);

            if ($locked === null || ! $locked->isPending()) {
                throw new RecruitmentException(
                    'This offer is no longer open.',
                    'offer_not_pending',
                );
            }

            if (! in_array($locked->shift, $pharmacy->freeShifts(), true)) {
                throw new RecruitmentException(
                    'Someone else now covers the '.$locked->shift.' shift.',
                    'shift_taken',
                );
            }

            try {
                $employee->forceFill([
                    'pharmacy_id' => $pharmacy->id,
                    'shift' => $locked->shift,
                    'status' => Employee::STATUS_APPROVED,
                    'salary' => $locked->salary,
                    // Reset per job, so the welcome shown on first sign-in
                    // belongs to this pharmacy rather than a previous one.
                    'first_login' => true,
                ])->save();
            } catch (QueryException) {
                throw new RecruitmentException(
                    'That shift was taken while you were deciding.',
                    'shift_taken',
                );
            }

            $locked->forceFill([
                'status' => JobOffer::STATUS_ACCEPTED,
                'responded_at' => now(),
            ])->save();

            // The job starts here. Recorded separately from the employee row so
            // that leaving does not erase the fact that it happened.
            Employment::create([
                'employee_id' => $employee->id,
                'pharmacy_id' => $pharmacy->id,
                'shift' => $locked->shift,
                'salary' => $locked->salary,
                'started_at' => now(),
            ]);

            $this->announceAcceptance($pharmacy, $employee, $locked);

            return $employee;
        });
    }

    /**
     * Tell the pharmacy that hired them, and every pharmacy that did not.
     *
     * One bulk insert rather than a create per row: the pharmacies still
     * waiting are told in the same statement, inside the same transaction, so
     * nobody is left holding an offer they think is live.
     */
    private function announceAcceptance(Pharmacy $pharmacy, Employee $employee, JobOffer $accepted): void
    {
        $now = now();
        $today = $now->toDateString();

        $rows = [[
            'pharmacy_id' => $pharmacy->id,
            'employee_id' => null,
            'title' => 'Offer accepted',
            'message' => $employee->name.' accepted your offer and now covers the '
                .$accepted->shift.' shift.',
            'type' => 'employee_offer_accepted',
            'is_read' => false,
            'date' => $today,
            'created_at' => $now,
            'updated_at' => $now,
        ]];

        $others = JobOffer::query()
            ->where('employee_id', $employee->id)
            ->where('id', '!=', $accepted->id)
            ->where('status', JobOffer::STATUS_PENDING)
            ->pluck('pharmacy_id')
            ->unique();

        foreach ($others as $pharmacyId) {
            $rows[] = [
                'pharmacy_id' => $pharmacyId,
                'employee_id' => null,
                'title' => 'Applicant hired elsewhere',
                'message' => $employee->name.' took a job at another pharmacy. '
                    .'Your offer stays open in case that changes.',
                'type' => 'employee_hired_elsewhere',
                'is_read' => false,
                'date' => $today,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        Notification::insert($rows);
    }

    /**
     * End someone's employment, whoever decided it.
     *
     * Detaches rather than deletes, which is the whole reason the old dismissal
     * was impossible. Deleting an employee would have cascaded away every task
     * ever assigned to them and stripped their name from every sale they rang
     * up — so the 409 that blocked it was protecting real data, not being
     * fussy. Nothing is destroyed here, so the retention question it was
     * waiting on simply does not arise.
     *
     * The row, the documents and the tokens all survive: the person stays
     * signed in and lands back on the offers screen, where their old offers are
     * live again because nothing was ever written to them.
     */
    public function endEmployment(Employee $employee, string $initiator): Employee
    {
        return DB::transaction(function () use ($employee, $initiator) {
            $pharmacy = $employee->pharmacy;
            $shift = $employee->shift;

            // Close the record before the columns that identify it are cleared.
            Employment::query()
                ->where('employee_id', $employee->id)
                ->where('pharmacy_id', $employee->pharmacy_id)
                ->running()
                ->update([
                    'ended_at' => now(),
                    'ended_by' => $initiator,
                    'updated_at' => now(),
                ]);

            $employee->forceFill([
                'pharmacy_id' => null,
                // Releases the seat, so the pharmacy can hire for it again.
                'shift' => null,
                'salary' => null,
                'status' => Employee::STATUS_PENDING,
            ])->save();

            if ($pharmacy !== null) {
                Notification::notify(
                    $pharmacy->id,
                    $initiator === 'employee' ? 'An employee resigned' : 'Employee dismissed',
                    $employee->name.' no longer covers the '.$shift.' shift.',
                    'employee_left',
                );
            }

            if ($initiator === 'pharmacy') {
                Notification::notifyEmployee(
                    $employee->id,
                    'Your job has ended',
                    ($pharmacy?->pharmacy_name ?? 'Your pharmacy')
                        .' ended your employment. Your offers are open again.',
                    Notification::TYPE_EMPLOYMENT_ENDED,
                );
            }

            return $employee;
        });
    }
}
