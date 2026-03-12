<?php

namespace App\Domain\Security\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Security\Models\PinOverride;
use App\Domain\StaffManagement\Models\Permission;
use Illuminate\Support\Facades\Hash;

class PinOverrideService
{
    /**
     * Maximum PIN attempts before lockout.
     */
    private const MAX_ATTEMPTS = 5;

    /**
     * Lockout duration in minutes after exceeding max attempts.
     */
    private const LOCKOUT_MINUTES = 15;

    /**
     * Request a PIN override — validates the authorizing user's PIN
     * and records the override for audit purposes.
     *
     * @param string $storeId
     * @param User   $requestingUser  The user who needs elevated permission
     * @param string $authorizingPin  The PIN entered by the authorizing user
     * @param string $permissionCode  The permission being overridden
     * @param array  $context         Optional action context (e.g. transaction ID)
     *
     * @return PinOverride
     * @throws \InvalidArgumentException
     */
    public function authorize(
        string $storeId,
        User $requestingUser,
        string $authorizingPin,
        string $permissionCode,
        array $context = [],
    ): PinOverride {
        // Verify the permission actually requires PIN
        $permission = Permission::where('name', $permissionCode)->first();
        if (!$permission || !$permission->requires_pin) {
            throw new \InvalidArgumentException("Permission '{$permissionCode}' does not require PIN override.");
        }

        // Find authorized users for this store who have this permission and have a PIN set
        $authorizingUser = $this->findAuthorizingUser($storeId, $authorizingPin, $permissionCode);

        if (!$authorizingUser) {
            throw new \InvalidArgumentException('Invalid PIN or no authorized user found.');
        }

        // Record the override
        return PinOverride::create([
            'store_id'             => $storeId,
            'requesting_user_id'   => $requestingUser->id,
            'authorizing_user_id'  => $authorizingUser->id,
            'permission_code'      => $permissionCode,
            'action_context'       => $context,
            'created_at'           => now(),
        ]);
    }

    /**
     * Check if a permission requires PIN override.
     */
    public function requiresPin(string $permissionCode): bool
    {
        $permission = Permission::where('name', $permissionCode)->first();
        return $permission?->requires_pin ?? false;
    }

    /**
     * Get PIN override history for a store.
     */
    public function history(string $storeId, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return PinOverride::where('store_id', $storeId)
            ->with(['requestingUser:id,name,email', 'authorizingUser:id,name,email'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    // ─── Private ─────────────────────────────────────────────────

    /**
     * Find a user in the store whose PIN matches and who has the required permission.
     */
    private function findAuthorizingUser(string $storeId, string $pin, string $permissionCode): ?User
    {
        // Get all users in this store who have a PIN set
        $users = User::where('store_id', $storeId)
            ->whereNotNull('pin_hash')
            ->where('is_active', true)
            ->get();

        foreach ($users as $user) {
            if (Hash::check($pin, $user->pin_hash)) {
                // Check if this user has the permission (via roles or owner status)
                if ($user->role?->value === 'owner' || $this->userHasPermission($user, $storeId, $permissionCode)) {
                    return $user;
                }
            }
        }

        return null;
    }

    /**
     * Check if user has a permission via their assigned roles.
     */
    private function userHasPermission(User $user, string $storeId, string $permissionCode): bool
    {
        return \Illuminate\Support\Facades\DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('model_has_roles.model_id', $user->id)
            ->where('roles.store_id', $storeId)
            ->where('permissions.name', $permissionCode)
            ->exists();
    }
}
