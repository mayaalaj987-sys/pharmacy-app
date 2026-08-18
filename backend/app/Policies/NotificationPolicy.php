<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\Notification;
use App\Models\Pharmacist;

class NotificationPolicy
{
    public function view(Pharmacist|Employee $user, Notification $notification): bool
    {
        // Addressed to a person: pure self-access, with no pharmacy and no
        // status involved, because the recipient may have neither. A pharmacist
        // can never reach one of these, whatever they own.
        if ($notification->isPersonal()) {
            return $user instanceof Employee
                && (int) $notification->employee_id === (int) $user->id;
        }

        if (! $notification->pharmacy || $notification->pharmacy->status !== 'approved') {
            return false;
        }

        return $user instanceof Pharmacist
            ? (int) $notification->pharmacy->pharmacist_id === (int) $user->id
            : $user->status === Employee::STATUS_APPROVED && (int) $notification->pharmacy_id === (int) $user->pharmacy_id;
    }

    public function update(Pharmacist|Employee $user, Notification $notification): bool
    {
        return $this->view($user, $notification);
    }

    public function delete(Pharmacist|Employee $user, Notification $notification): bool
    {
        return $user instanceof Pharmacist && $this->view($user, $notification);
    }
}
