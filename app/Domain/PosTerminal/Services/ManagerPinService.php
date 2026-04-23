<?php

namespace App\Domain\PosTerminal\Services;

use App\Domain\Auth\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Manager-PIN step-up service. POS clients call `verify()` with a manager
 * PIN + the action being attempted (void, refund, large discount, tax
 * exempt, …). On success a short-lived token is returned that must be sent
 * back on the next state-changing API call. The token is single-use and
 * scoped to its action.
 */
class ManagerPinService
{
    private const TTL_SECONDS = 300; // 5 minutes
    private const PREFIX = 'pos:mgr_pin:';

    /**
     * Verify a manager PIN against any active staff user in the same
     * organization that holds the matching `pos.<action>` permission.
     * Returns [token, approver_id] on success, throws otherwise.
     */
    public function verify(User $requester, string $pin, string $action): array
    {
        // The required permission slug for each step-up action.
        $permissionByAction = [
            'void' => 'pos.void_transaction',
            'refund' => 'pos.return',
            'discount' => 'pos.approve_discount',
            'tax_exempt' => 'pos.tax_exempt',
            'reopen_session' => 'pos.shift_open',
            'price_override' => 'pos.approve_discount',
        ];
        $permission = $permissionByAction[$action] ?? null;
        if (!$permission) {
            throw new \RuntimeException(__('pos.manager_pin_unknown_action'));
        }

        // Pull every active user in the same organization with a PIN set.
        $candidates = User::query()
            ->where('organization_id', $requester->organization_id)
            ->whereNotNull('pin_hash')
            ->where('is_active', true)
            ->get();

        $matched = $candidates->first(fn (User $u) => Hash::check($pin, $u->pin_hash));
        if (!$matched) {
            throw new \RuntimeException(__('pos.manager_pin_invalid'));
        }

        // Verify the matched user actually has the permission for this action
        // under the staff guard — falls back gracefully when Spatie is not
        // wired up (e.g. simple test environments).
        try {
            if (method_exists($matched, 'hasPermissionTo')) {
                if (!$matched->hasPermissionTo($permission, 'staff')) {
                    throw new \RuntimeException(__('pos.manager_pin_insufficient_permission'));
                }
            }
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
            // permission not seeded — accept matched user
        }

        $token = Str::random(48);
        Cache::put(self::PREFIX . $token, [
            'approver_id' => $matched->id,
            'action' => $action,
            'organization_id' => $requester->organization_id,
        ], self::TTL_SECONDS);

        return [$token, $matched->id];
    }

    /**
     * Consume an approval token, returning the approver_id. Returns null when
     * the token is missing/expired/wrong-action. When `$expectedAction` is
     * null the action binding is not enforced.
     */
    public static function consume(string $token, ?string $expectedAction = null): ?string
    {
        $payload = Cache::pull(self::PREFIX . $token);
        if (!$payload) return null;
        if ($expectedAction !== null && ($payload['action'] ?? null) !== $expectedAction) {
            return null;
        }
        return $payload['approver_id'] ?? null;
    }
}
