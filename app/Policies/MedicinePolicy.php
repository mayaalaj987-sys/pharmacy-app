<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\Medicine;
use App\Models\Pharmacist;

class MedicinePolicy
{
    public function view(Pharmacist|Employee $user, Medicine $medicine): bool
    {
        return $medicine->pharmacy !== null
            && $medicine->pharmacy->status === 'approved'
            && ($user instanceof Pharmacist
                ? (int) $medicine->pharmacy->pharmacist_id === (int) $user->id
                : $user->status === 'approved' && (int) $medicine->pharmacy_id === (int) $user->pharmacy_id);
    }

    public function update(Pharmacist|Employee $user, Medicine $medicine): bool
    {
        return $user instanceof Pharmacist && $this->view($user, $medicine);
    }
}
