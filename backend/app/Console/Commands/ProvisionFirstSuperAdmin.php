<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\InteractsWithAdminSecrets;
use App\Models\Admin;
use App\Services\AdminAccountService;
use Illuminate\Console\Command;

class ProvisionFirstSuperAdmin extends Command
{
    use InteractsWithAdminSecrets;

    protected $signature = 'admin:provision-super {--email=} {--name=} {--force : Confirm an intentional production operation}';

    protected $description = 'Provision the first individual super administrator without exposing a password.';

    public function handle(AdminAccountService $accounts): int
    {
        $email = Admin::normalizeEmail((string) ($this->option('email') ?: $this->ask('Administrator email')));
        $existing = Admin::query()->where('email', $email)->first();
        if ($existing?->is_active && $existing->isSuperAdmin()) {
            $this->info('The first super administrator is already provisioned. No change was made.');

            return self::SUCCESS;
        }
        if (Admin::query()->exists()) {
            $this->error('Bootstrap provisioning is closed because administrator accounts already exist.');

            return self::FAILURE;
        }
        if (! $this->confirmProductionOperation('provision the first super administrator')) {
            $this->warn('Cancelled. No change was made.');

            return self::FAILURE;
        }

        $name = trim((string) ($this->option('name') ?: $this->ask('Administrator name')));
        if (! $this->validIdentity($name, $email)) {
            return self::FAILURE;
        }
        $password = $this->newPassword();
        if ($password === null) {
            return self::FAILURE;
        }

        try {
            $accounts->create(null, $name, $email, $password, Admin::ROLE_SUPER_ADMIN);
        } catch (\Throwable) {
            $this->error('Provisioning failed. No credentials were logged.');

            return self::FAILURE;
        } finally {
            $password = '';
        }

        $this->info('The first super administrator was provisioned.');

        return self::SUCCESS;
    }
}
