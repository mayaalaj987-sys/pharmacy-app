<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\Pharmacist;
use App\Models\Task;

class TaskPolicy
{
    public function view(Pharmacist|Employee $user, Task $task): bool
    {
        if (! $task->pharmacy || $task->pharmacy->status !== 'approved') {
            return false;
        }

        return $user instanceof Pharmacist
            ? (int) $task->pharmacy->pharmacist_id === (int) $user->id
            : $user->status === Employee::STATUS_APPROVED && (int) $task->employee_id === (int) $user->id;
    }

    public function update(Pharmacist|Employee $user, Task $task): bool
    {
        return $user instanceof Employee && $this->view($user, $task);
    }

    public function delete(Pharmacist|Employee $user, Task $task): bool
    {
        return $user instanceof Pharmacist && $this->view($user, $task);
    }
}
