<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\Order;
use App\Models\Pharmacist;

class OrderPolicy
{
    public function view(Pharmacist|Employee $user, Order $order): bool
    {
        return $user instanceof Pharmacist
            && $order->pharmacy !== null
            && $order->pharmacy->status === 'approved'
            && (int) $order->pharmacy->pharmacist_id === (int) $user->id;
    }

    public function update(Pharmacist|Employee $user, Order $order): bool
    {
        return $this->view($user, $order);
    }
}
