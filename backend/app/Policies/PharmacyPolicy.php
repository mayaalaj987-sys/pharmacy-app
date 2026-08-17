<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Employee;
use App\Models\Pharmacist;
use App\Models\Pharmacy;

class PharmacyPolicy
{
    public function view(Pharmacist|Employee $user, Pharmacy $pharmacy): bool
    {
        if ($user instanceof Pharmacist) {
            return (int) $pharmacy->pharmacist_id === (int) $user->id;
        }

        return $user->status === 'approved'
            && (int) $user->pharmacy_id === (int) $pharmacy->id;
    }

    /**
     * A suspended pharmacy cannot trade, even though its approval stands.
     * Every operational request resolves its tenant through this gate.
     */
    public function operate(Pharmacist|Employee $user, Pharmacy $pharmacy): bool
    {
        return $pharmacy->isOperational() && $this->view($user, $pharmacy);
    }

    public function update(Pharmacist|Employee $user, Pharmacy $pharmacy): bool
    {
        return $user instanceof Pharmacist
            && (int) $pharmacy->pharmacist_id === (int) $user->id;
    }

    public function viewAnyReview(Admin $admin): bool
    {
        return $admin->canReviewPharmacies();
    }

    public function review(Admin $admin, Pharmacy $pharmacy): bool
    {
        return $admin->canReviewPharmacies() && $pharmacy->status === 'pending';
    }
}
