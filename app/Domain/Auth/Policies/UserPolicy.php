<?php

namespace App\Domain\Auth\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Auth\Models\User;
use Illuminate\Foundation\Auth\User as Authenticatable;

class UserPolicy
{
    /**
     * Admin or the user themselves can view.
     */
    public function view(Authenticatable $authenticatedUser, User $targetUser): bool
    {
        if ($authenticatedUser instanceof AdminUser) {
            return true;
        }

        return $authenticatedUser->id === $targetUser->id
            || $authenticatedUser->role?->value === 'owner'
            || $authenticatedUser->role?->value === 'chain_manager';
    }

    /**
     * Only the user can update their own profile (admins can too).
     */
    public function update(Authenticatable $authenticatedUser, User $targetUser): bool
    {
        if ($authenticatedUser instanceof AdminUser) {
            return true;
        }

        return $authenticatedUser->id === $targetUser->id;
    }

    /**
     * Owner / chain manager can deactivate users in their org.
     */
    public function deactivate(Authenticatable $authenticatedUser, User $targetUser): bool
    {
        if ($authenticatedUser instanceof AdminUser) {
            return true;
        }

        if ($authenticatedUser->id === $targetUser->id) {
            return false; // Can't deactivate yourself
        }

        return $authenticatedUser->organization_id === $targetUser->organization_id
            && in_array($authenticatedUser->role?->value, ['owner', 'chain_manager']);
    }

    /**
     * Owner / chain manager can list users in their org.
     */
    public function viewAny(Authenticatable $authenticatedUser): bool
    {
        if ($authenticatedUser instanceof AdminUser) {
            return true;
        }

        return in_array($authenticatedUser->role?->value, [
            'owner', 'chain_manager', 'branch_manager',
        ]);
    }

    /**
     * Owner / chain manager can create users.
     */
    public function create(Authenticatable $authenticatedUser): bool
    {
        if ($authenticatedUser instanceof AdminUser) {
            return true;
        }

        return in_array($authenticatedUser->role?->value, [
            'owner', 'chain_manager', 'branch_manager',
        ]);
    }

    /**
     * Owner can delete users (soft-delete via deactivation).
     */
    public function delete(Authenticatable $authenticatedUser, User $targetUser): bool
    {
        return $this->deactivate($authenticatedUser, $targetUser);
    }
}
