<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\InteractsWithAdminSecrets;
use App\Models\Admin;
use App\Services\AdminAccountService;
use App\Services\AdminCommandAuthenticator;
use Illuminate\Console\Command;

class SetAdminStatus extends Command
{
    use InteractsWithAdminSecrets;

    protected $signature = 'admin:set-status {email?} {status? : active or disabled} {--actor=} {--force : Confirm an intentional production operation}';

    protected $description = 'Disable or reactivate an administrator account safely.';

    public function handle(AdminCommandAuthenticator $authenticator, AdminAccountService $accounts): int
    {
        if (! $this->confirmProductionOperation('change administrator active status')) {
            return self::FAILURE;
        }
        $actor = $this->authorizingAdmin($authenticator);
        if ($actor === null) {
            return self::FAILURE;
        }
        $email = Admin::normalizeEmail((string) ($this->argument('email') ?: $this->ask('Target administrator email')));
        $status = strtolower(trim((string) ($this->argument('status') ?: $this->choice('Status', ['active', 'disabled']))));
        if (! in_array($status, ['active', 'disabled'], true)) {
            $this->error('Unsupported status. No change was made.');

            return self::FAILURE;
        }
        $target = Admin::query()->where('email', $email)->first();
        if (! $target) {
            $this->error('Administrator not found. No change was made.');

            return self::FAILURE;
        }

        try {
            $accounts->setActive($actor, $target, $status === 'active');
        } catch (\Throwable) {
            $this->error('Status change failed. No change was made.');

            return self::FAILURE;
        }
        $this->info('The administrator status was updated.');

        return self::SUCCESS;
    }
}
