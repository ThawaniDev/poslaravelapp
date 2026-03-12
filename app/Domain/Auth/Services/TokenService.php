<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Models\User;

class TokenService
{
    /**
     * Default token abilities for each role.
     */
    private const ROLE_ABILITIES = [
        'owner' => ['*'],
        'chain_manager' => ['*'],
        'branch_manager' => [
            'pos:*', 'catalog:*', 'inventory:*', 'orders:*',
            'customers:*', 'staff:read', 'staff:write', 'reports:read',
            'settings:read', 'settings:write',
        ],
        'cashier' => [
            'pos:*', 'catalog:read', 'orders:read', 'orders:write',
            'customers:read', 'customers:write',
        ],
        'inventory_clerk' => [
            'catalog:*', 'inventory:*', 'orders:read',
        ],
        'accountant' => [
            'reports:*', 'orders:read', 'payments:read',
            'catalog:read', 'inventory:read',
        ],
        'kitchen_staff' => [
            'orders:read', 'catalog:read',
        ],
    ];

    /**
     * Create a Sanctum token with role-based abilities.
     */
    public function createToken(User $user, string $tokenName = 'api-token'): string
    {
        $abilities = $this->getAbilitiesForUser($user);

        $token = $user->createToken(
            name: $tokenName,
            abilities: $abilities,
            expiresAt: now()->addDays(30),
        );

        return $token->plainTextToken;
    }

    /**
     * Refresh — revoke current and issue new token.
     */
    public function refreshToken(User $user): string
    {
        $currentToken = $user->currentAccessToken();
        $tokenName = $currentToken?->name ?? 'refreshed';

        $currentToken?->delete();

        return $this->createToken($user, $tokenName);
    }

    /**
     * Get Sanctum abilities array for user based on role.
     */
    private function getAbilitiesForUser(User $user): array
    {
        $roleValue = $user->role?->value ?? 'cashier';

        return self::ROLE_ABILITIES[$roleValue] ?? self::ROLE_ABILITIES['cashier'];
    }
}
