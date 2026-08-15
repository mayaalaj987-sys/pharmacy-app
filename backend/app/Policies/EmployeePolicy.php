<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\Pharmacist;

class EmployeePolicy
{
    public function view(Pharmacist|Employee $user, Employee $employee): bool
    {
        return $user instanceof Pharmacist
            && $employee->pharmacy !== null
            && $employee->pharmacy->status === 'approved'
            && (int) $employee->pharmacy->pharmacist_id === (int) $user->id;
    }

    public function update(Pharmacist|Employee $user, Employee $employee): bool
    {
        return $this->view($user, $employee);
    }

    public function delete(Pharmacist|Employee $user, Employee $employee): bool
    {
        return $this->view($user, $employee);
    }
}
