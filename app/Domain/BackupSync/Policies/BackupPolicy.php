<?php

namespace App\Domain\BackupSync\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use Illuminate\Foundation\Auth\User as Authenticatable;

class BackupPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('backup_view');
    }

    public function create(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('backup_create');
    }

    public function restore(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('backup_restore');
    }

    public function delete(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('backup_delete');
    }
}
