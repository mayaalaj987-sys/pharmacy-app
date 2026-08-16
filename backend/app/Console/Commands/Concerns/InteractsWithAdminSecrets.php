<?php

namespace App\Console\Commands\Concerns;

use App\Models\Admin;
use App\Services\AdminCommandAuthenticator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

trait InteractsWithAdminSecrets
{
    private function validIdentity(string $name, string $email): bool
    {
        $validator = Validator::make(
            ['name' => $name, 'email' => $email],
            ['name' => ['required', 'string', 'min:2', 'max:120'], 'email' => ['required', 'string', 'email:rfc', 'max:254']],
        );
        if ($validator->fails()) {
            $this->error('The administrator name or email is invalid. No change was made.');

            return false;
        }

        return true;
    }

    private function confirmProductionOperation(string $operation): bool
    {
        return ! app()->environment('production')
            || (bool) $this->option('force')
            || $this->confirm('Production confirmation required: '.$operation.'. Continue?', false);
    }

    private function authorizingAdmin(AdminCommandAuthenticator $authenticator): ?Admin
    {
        $email = trim((string) ($this->option('actor') ?: $this->ask('Authorizing super-admin email')));
        $password = (string) $this->secret('Authorizing super-admin password');

        try {
            return $authenticator->authenticateSuperAdmin($email, $password);
        } catch (\Throwable) {
            $this->error('Authorization failed. No change was made.');

            return null;
        } finally {
            $password = '';
        }
    }

    private function newPassword(): ?string
    {
        $password = (string) $this->secret('New administrator password');
        $confirmation = (string) $this->secret('Confirm new administrator password');
        $validator = Validator::make(
            ['password' => $password, 'password_confirmation' => $confirmation],
            ['password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()]],
        );
        if ($validator->fails()) {
            $this->error('The password does not meet the required strength or confirmation rules. No change was made.');
            $password = '';
            $confirmation = '';

            return null;
        }
        $confirmation = '';

        return $password;
    }
}
