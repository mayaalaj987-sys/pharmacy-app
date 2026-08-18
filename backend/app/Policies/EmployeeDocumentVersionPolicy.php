<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\EmployeeDocumentVersion;
use App\Models\Pharmacist;

class EmployeeDocumentVersionPolicy
{
    /**
     * The applicant's own file.
     *
     * Deliberately unchanged and deliberately narrow: a pharmacist presenting a
     * perfectly valid token still fails this, which is what keeps the
     * employee-self routes employee-only. Recruiter access is a separate
     * ability on separate routes, not a widening of this one.
     */
    public function view(mixed $user, EmployeeDocumentVersion $document): bool
    {
        return $user instanceof Employee && (int) $document->employee_id === (int) $user->id;
    }

    /**
     * A pharmacist reading the file of someone they might hire.
     *
     * This is the "recruitment authorization model" the routes file has been
     * waiting for. You cannot offer a stranger a salary on the strength of a
     * name, so a recruiter needs the CV — but only for people actually in the
     * market, and only the version they stand behind now.
     *
     * Three conditions, each closing a distinct hole:
     *
     * - an approved pharmacy, so an unreviewed signup cannot mine the pool;
     * - the applicant is looking, or already works for this pharmacist. Once
     *   somebody is hired elsewhere their file closes to everyone else, and
     *   their own employer keeps the access they hired them on;
     * - the current version only. Uploading a replacement keeps the old row
     *   forever, so without this a recruiter would see every draft the person
     *   ever put up rather than the one CV they are standing behind.
     */
    public function viewAsRecruiter(mixed $user, EmployeeDocumentVersion $document): bool
    {
        if (! $user instanceof Pharmacist) {
            return false;
        }

        $applicant = $document->employee;

        if ($applicant === null || $document->superseded_at !== null) {
            return false;
        }

        if ($applicant->isEmployed()) {
            return (int) $applicant->pharmacy?->pharmacist_id === (int) $user->id;
        }

        return $user->pharmacies()->where('status', 'approved')->exists();
    }
}
