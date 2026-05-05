<?php

namespace App\Domain\Security\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Security\Models\PinOverride;
use App\Domain\StaffManagement\Models\Permission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class PinOverrideService
{
    /**
     * Maximum PIN attempts before lockout.
     */
    public const MAX_ATTEMPTS = 5;

    /**
     * Lockout duration in minutes after exceeding max attempts.
     */
    public const LOCKOUT_MINUTES = 15;

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
        // Check for lockout before verifying PIN to prevent timing attacks
        $lockoutKey = $this->lockoutCacheKey($storeId, $requestingUser->id);
        if (Cache::has($lockoutKey)) {
            $remaining = (int) Cache::get($lockoutKey . ':remaining', self::LOCKOUT_MINUTES);
            throw new \InvalidArgumentException(
                "PIN override locked out. Try again in {$remaining} minutes."
            );
        }

        // Verify the permission actually requires PIN
        $permission = Permission::where('name', $permissionCode)->first();
        if (!$permission || !$permission->requires_pin) {
            throw new \InvalidArgumentException("Permission '{$permissionCode}' does not require PIN override.");
        }

        // Find authorized users for this store who have this permission and have a PIN set
        $authorizingUser = $this->findAuthorizingUser($storeId, $authorizingPin, $permissionCode, $requestingUser->id);

        if (!$authorizingUser) {
            // Track failed attempt
            $this->recordFailedAttempt($lockoutKey);
            throw new \InvalidArgumentException('Invalid PIN or no authorized user found.');
        }

        // Successful — clear failed attempt counter
        $this->clearFailedAttempts($lockoutKey);

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
     * A user cannot authorize their own restricted action (PIN override segregation).
     */
    private function findAuthorizingUser(
        string $storeId,
        string $pin,
        string $permissionCode,
        string $requestingUserId,
    ): ?User {
        // Get all users in this store who have a PIN set (excluding the requesting user)
        $users = User::where('store_id', $storeId)
            ->whereNotNull('pin_hash')
            ->where('is_active', true)
            ->where('id', '!=', $requestingUserId)   // cannot authorize own action
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

    /**
     * Cache key for failed PIN attempts lockout.
     */
    private function lockoutCacheKey(string $storeId, string $userId): string
    {
        return "pin_override_lockout:{$storeId}:{$userId}";
    }

    /**
     * Record a failed PIN override attempt; lock out after MAX_ATTEMPTS.
     */
    private function recordFailedAttempt(string $lockoutKey): void
    {
        $attemptsKey = $lockoutKey . ':attempts';
        $attempts = (int) Cache::get($attemptsKey, 0) + 1;

        if ($attempts >= self::MAX_ATTEMPTS) {
            // Set lockout marker
            Cache::put($lockoutKey, true, now()->addMinutes(self::LOCKOUT_MINUTES));
            Cache::put($lockoutKey . ':remaining', self::LOCKOUT_MINUTES, now()->addMinutes(self::LOCKOUT_MINUTES));
            Cache::forget($attemptsKey);
        } else {
            // Store attempt count (expires in lockout window)
            Cache::put($attemptsKey, $attempts, now()->addMinutes(self::LOCKOUT_MINUTES));
        }
    }

    /**
     * Clear failed attempt counter on successful PIN verification.
     */
    private function clearFailedAttempts(string $lockoutKey): void
    {
        Cache::forget($lockoutKey . ':attempts');
        Cache::forget($lockoutKey);
        Cache::forget($lockoutKey . ':remaining');
    }
}
