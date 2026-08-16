<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\InteractsWithAdminSecrets;
use App\Models\Admin;
use App\Services\AdminAccountService;
use App\Services\AdminCommandAuthenticator;
use Illuminate\Console\Command;

class CreateAdminAccount extends Command
{
    use InteractsWithAdminSecrets;

    protected $signature = 'admin:create {--actor=} {--email=} {--name=} {--role=} {--force : Confirm an intentional production operation}';

    protected $description = 'Create an individual administrator account under an active super administrator.';

    public function handle(AdminCommandAuthenticator $authenticator, AdminAccountService $accounts): int
    {
        if (! $this->confirmProductionOperation('create an administrator account')) {
            return self::FAILURE;
        }
        $actor = $this->authorizingAdmin($authenticator);
        if ($actor === null) {
            return self::FAILURE;
        }
        $email = Admin::normalizeEmail((string) ($this->option('email') ?: $this->ask('New administrator email')));
        $name = trim((string) ($this->option('name') ?: $this->ask('New administrator name')));
        if (! $this->validIdentity($name, $email)) {
            return self::FAILURE;
        }
        $role = trim((string) ($this->option('role') ?: $this->choice('Role', Admin::ROLES)));
        if (! in_array($role, Admin::ROLES, true)) {
            $this->error('Unsupported role. No change was made.');

            return self::FAILURE;
        }
        $password = $this->newPassword();
        if ($password === null) {
            return self::FAILURE;
        }

        try {
            $accounts->create($actor, $name, $email, $password, $role);
        } catch (\Throwable) {
            $this->error('Account creation failed. No credentials were logged.');

            return self::FAILURE;
        } finally {
            $password = '';
        }
        $this->info('The administrator account was created.');

        return self::SUCCESS;
    }
}
