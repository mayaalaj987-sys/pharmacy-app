<?php

namespace App\Services;

use App\Exceptions\AdminWorkflowException;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

class AdminCommandAuthenticator
{
    public function authenticateSuperAdmin(string $email, string $password): Admin
    {
        $admin = Admin::query()->where('email', Admin::normalizeEmail($email))->first();
        if (! $admin || ! $admin->is_active || ! $admin->isSuperAdmin() || ! Hash::check($password, $admin->password)) {
            throw new AdminWorkflowException('The authorizing administrator credentials are invalid.', 'invalid_admin_authorization', 403);
        }

        return $admin;
    }
}
