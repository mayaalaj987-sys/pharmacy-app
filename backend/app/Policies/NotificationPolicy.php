<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\Notification;
use App\Models\Pharmacist;

class NotificationPolicy
{
    public function view(Pharmacist|Employee $user, Notification $notification): bool
    {
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
