<?php

namespace App\Domain\Auth\Policies;

use App\Domain\Auth\Models\User;

class UserPolicy
{
    /**
     * Admin or the user themselves can view.
     */
    public function view(User $authenticatedUser, User $targetUser): bool
    {
        return $authenticatedUser->id === $targetUser->id
            || $authenticatedUser->role?->value === 'owner'
            || $authenticatedUser->role?->value === 'chain_manager';
    }

    /**
     * Only the user can update their own profile.
     */
    public function update(User $authenticatedUser, User $targetUser): bool
    {
        return $authenticatedUser->id === $targetUser->id;
    }

    /**
     * Owner / chain manager can deactivate users in their org.
     */
    public function deactivate(User $authenticatedUser, User $targetUser): bool
    {
        if ($authenticatedUser->id === $targetUser->id) {
            return false; // Can't deactivate yourself
        }

        return $authenticatedUser->organization_id === $targetUser->organization_id
            && in_array($authenticatedUser->role?->value, ['owner', 'chain_manager']);
    }

    /**
     * Owner / chain manager can list users in their org.
     */
    public function viewAny(User $authenticatedUser): bool
    {
        return in_array($authenticatedUser->role?->value, [
            'owner', 'chain_manager', 'branch_manager',
        ]);
    }

    /**
     * Owner / chain manager can create users.
     */
    public function create(User $authenticatedUser): bool
    {
        return in_array($authenticatedUser->role?->value, [
            'owner', 'chain_manager', 'branch_manager',
        ]);
    }

    /**
     * Owner can delete users (soft-delete via deactivation).
     */
    public function delete(User $authenticatedUser, User $targetUser): bool
    {
        return $this->deactivate($authenticatedUser, $targetUser);
    }
}
