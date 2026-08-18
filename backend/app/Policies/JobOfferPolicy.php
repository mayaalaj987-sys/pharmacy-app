<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\JobOffer;
use App\Models\Pharmacist;

class JobOfferPolicy
{
    /**
     * Read or withdraw an offer your pharmacy sent.
     *
     * Ownership runs through the pharmacy, not through who typed it: a
     * pharmacy has one owner, and an offer belongs to the business rather than
     * to the person who happened to send it.
     */
    public function manage(Pharmacist $pharmacist, JobOffer $offer): bool
    {
        return $offer->pharmacy !== null
            && (int) $offer->pharmacy->pharmacist_id === (int) $pharmacist->id;
    }

    /**
     * Answer an offer addressed to you.
     *
     * Pure self-access, like EmployeeDocumentVersionPolicy: no pharmacy and no
     * status involved, because the whole point is that this applies to someone
     * who has neither yet.
     */
    public function accept(Employee $employee, JobOffer $offer): bool
    {
        return (int) $offer->employee_id === (int) $employee->id;
    }
}
