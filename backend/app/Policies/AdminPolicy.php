<?php

namespace App\Policies;

use App\Models\Admin;

class AdminPolicy
{
    public function viewAny(Admin $actor): bool
    {
        return $actor->is_active && $actor->isSuperAdmin();
    }

    public function create(Admin $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function manage(Admin $actor, Admin $target): bool
    {
        return $this->viewAny($actor);
    }
}
