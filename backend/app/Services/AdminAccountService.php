<?php

namespace App\Services;

use App\Exceptions\AdminWorkflowException;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAccountService
{
    public function __construct(private readonly AdminAuditLogger $audit) {}

    public function create(
        ?Admin $actor,
        string $name,
        string $email,
        string $password,
        string $role,
        ?Request $request = null,
    ): Admin {
        if (! in_array($role, Admin::ROLES, true)) {
            throw new AdminWorkflowException('The administrator role is unsupported.', 'unsupported_admin_role', 422);
        }
        if ($actor === null && (Admin::query()->exists() || $role !== Admin::ROLE_SUPER_ADMIN)) {
            throw new AdminWorkflowException('Bootstrap provisioning is no longer available.', 'admin_bootstrap_closed', 409);
        }
        if ($actor !== null && (! $actor->is_active || ! $actor->isSuperAdmin())) {
            throw new AdminWorkflowException('This operation requires an active super administrator.', 'forbidden', 403);
        }

        return DB::transaction(function () use ($actor, $name, $email, $password, $role, $request): Admin {
            $normalizedEmail = Admin::normalizeEmail($email);
            if (Admin::query()->where('email', $normalizedEmail)->exists()) {
                throw new AdminWorkflowException('An administrator with this email already exists.', 'admin_email_exists', 409);
            }

            $admin = new Admin;
            $admin->forceFill([
                'name' => trim($name),
                'email' => $normalizedEmail,
                'password' => $password,
                'role' => $role,
                'is_active' => true,
                'auth_version' => 1,
                'password_changed_at' => now(),
            ])->save();

            $auditActor = $actor ?? $admin;
            $this->audit->record(
                $request,
                $auditActor,
                $actor === null ? 'admin.bootstrap.provisioned' : 'admin.account.created',
                'success',
                'admin',
                $admin->id,
                after: ['role' => $admin->role, 'is_active' => true],
            );

            return $admin;
        });
    }

    public function changeRole(Admin $actor, Admin $target, string $role, ?Request $request = null): Admin
    {
        if (! in_array($role, Admin::ROLES, true)) {
            throw new AdminWorkflowException('The administrator role is unsupported.', 'unsupported_admin_role', 422);
        }

        return DB::transaction(function () use ($actor, $target, $role, $request): Admin {
            $locked = Admin::query()->lockForUpdate()->findOrFail($target->id);
            $this->assertActor($actor);
            if ($locked->role === $role) {
                return $locked;
            }
            if ($locked->is_active && $locked->isSuperAdmin() && $role !== Admin::ROLE_SUPER_ADMIN) {
                $this->assertAnotherActiveSuperAdmin($locked);
            }

            $before = ['role' => $locked->role, 'is_active' => $locked->is_active];
            $locked->forceFill(['role' => $role, 'auth_version' => $locked->auth_version + 1])->save();
            $this->audit->record($request, $actor, 'admin.role.changed', 'success', 'admin', $locked->id, before: $before, after: ['role' => $role, 'is_active' => $locked->is_active]);

            return $locked;
        });
    }

    public function setActive(Admin $actor, Admin $target, bool $active, ?Request $request = null): Admin
    {
        return DB::transaction(function () use ($actor, $target, $active, $request): Admin {
            $locked = Admin::query()->lockForUpdate()->findOrFail($target->id);
            $this->assertActor($actor);
            if ($locked->is_active === $active) {
                return $locked;
            }
            if (! $active && $locked->isSuperAdmin()) {
                $this->assertAnotherActiveSuperAdmin($locked);
            }

            $before = ['role' => $locked->role, 'is_active' => $locked->is_active];
            $locked->forceFill([
                'is_active' => $active,
                'disabled_at' => $active ? null : now(),
                'auth_version' => $locked->auth_version + 1,
            ])->save();
            $this->audit->record(
                $request,
                $actor,
                $active ? 'admin.account.reactivated' : 'admin.account.disabled',
                'success',
                'admin',
                $locked->id,
                before: $before,
                after: ['role' => $locked->role, 'is_active' => $active],
            );

            return $locked;
        });
    }

    public function resetPassword(Admin $actor, Admin $target, string $password, ?Request $request = null): Admin
    {
        return DB::transaction(function () use ($actor, $target, $password, $request): Admin {
            $this->assertActor($actor);
            $locked = Admin::query()->lockForUpdate()->findOrFail($target->id);
            $locked->forceFill([
                'password' => $password,
                'password_changed_at' => now(),
                'auth_version' => $locked->auth_version + 1,
            ])->save();
            $this->audit->record($request, $actor, 'admin.password.reset', 'success', 'admin', $locked->id);

            return $locked;
        });
    }

    private function assertActor(Admin $actor): void
    {
        $fresh = Admin::query()->lockForUpdate()->find($actor->id);
        if (! $fresh?->is_active || ! $fresh->isSuperAdmin()) {
            throw new AdminWorkflowException('This operation requires an active super administrator.', 'forbidden', 403);
        }
    }

    private function assertAnotherActiveSuperAdmin(Admin $target): void
    {
        $others = Admin::query()
            ->where('role', Admin::ROLE_SUPER_ADMIN)
            ->where('is_active', true)
            ->whereKeyNot($target->id)
            ->lockForUpdate()
            ->get(['id'])
            ->count();
        if ($others < 1) {
            throw new AdminWorkflowException('The last active super administrator cannot be disabled or demoted.', 'last_active_super_admin', 409);
        }
    }
}
