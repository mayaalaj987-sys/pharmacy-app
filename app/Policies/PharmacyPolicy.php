<?php

namespace App\Policies;

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

    public function operate(Pharmacist|Employee $user, Pharmacy $pharmacy): bool
    {
        return $pharmacy->status === 'approved' && $this->view($user, $pharmacy);
    }

    public function update(Pharmacist|Employee $user, Pharmacy $pharmacy): bool
    {
        return $user instanceof Pharmacist
            && (int) $pharmacy->pharmacist_id === (int) $user->id;
    }
}
