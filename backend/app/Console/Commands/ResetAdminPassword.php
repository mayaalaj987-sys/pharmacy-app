<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\InteractsWithAdminSecrets;
use App\Models\Admin;
use App\Services\AdminAccountService;
use App\Services\AdminCommandAuthenticator;
use Illuminate\Console\Command;

class ResetAdminPassword extends Command
{
    use InteractsWithAdminSecrets;

    protected $signature = 'admin:reset-password {email?} {--actor=} {--force : Confirm an intentional production operation}';

    protected $description = 'Securely reset an administrator password and invalidate existing sessions.';

    public function handle(AdminCommandAuthenticator $authenticator, AdminAccountService $accounts): int
    {
        if (! $this->confirmProductionOperation('reset an administrator password')) {
            return self::FAILURE;
        }
        $actor = $this->authorizingAdmin($authenticator);
        if ($actor === null) {
            return self::FAILURE;
        }
        $email = Admin::normalizeEmail((string) ($this->argument('email') ?: $this->ask('Target administrator email')));
        $target = Admin::query()->where('email', $email)->first();
        if (! $target) {
            $this->error('Administrator not found. No change was made.');

            return self::FAILURE;
        }
        $password = $this->newPassword();
        if ($password === null) {
            return self::FAILURE;
        }

        try {
            $accounts->resetPassword($actor, $target, $password);
        } catch (\Throwable) {
            $this->error('Password reset failed. No credentials were logged.');

            return self::FAILURE;
        } finally {
            $password = '';
        }
        $this->info('The password was reset and existing sessions were invalidated.');

        return self::SUCCESS;
    }
}
