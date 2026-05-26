<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class InitialAdminSetupService
{
    public const DEFAULT_ADMIN_EMAIL = 'admin@example.com';

    private const DEFAULT_ADMIN_PASSWORD = 'password';

    public function defaultAdmin(): ?User
    {
        if (! Schema::hasTable('users')) {
            return null;
        }

        return User::query()
            ->where('email', self::DEFAULT_ADMIN_EMAIL)
            ->first();
    }

    public function isRequired(): bool
    {
        $admin = $this->defaultAdmin();

        if (! $admin) {
            return false;
        }

        return blank($admin->username) && Hash::check(self::DEFAULT_ADMIN_PASSWORD, $admin->password);
    }
}
