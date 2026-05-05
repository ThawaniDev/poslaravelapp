<?php

namespace App\Domain\Security\Controllers\Api;

use App\Domain\Security\Requests\PinOverrideRequest;
use App\Domain\Security\Resources\PinOverrideResource;
use App\Domain\Security\Services\PinOverrideService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PinOverrideController extends BaseApiController
{
    public function __construct(
        private readonly PinOverrideService $pinOverrideService,
    ) {}

    /**
     * POST /api/v2/staff/pin-override
     *
     * Request a PIN override for a protected action.
     *
     * Success: 200 { success, authorized_by, authorized_by_name, permission_code, data{...} }
     * Lockout: 429 { success, message, minutes_remaining }
     * Wrong PIN: 401 { success, message, remaining_attempts }
     * Not PIN-protected: 422 { success, message }
     */
    public function authorizePin(PinOverrideRequest $request)
    {
        $storeId = $request->store_id;
        $userId  = $request->user()->id;

        try {
            $override = $this->pinOverrideService->authorize(
                storeId: $storeId,
                requestingUser: $request->user(),
                authorizingPin: $request->pin,
                permissionCode: $request->permission_code,
                context: $request->context ?? [],
            );

            $resource = new PinOverrideResource($override);

            return response()->json([
                'success'            => true,
                'message'            => 'PIN override authorized.',
                'authorized_by'      => $override->authorizing_user_id,
                'authorized_by_name' => $override->authorizingUser?->name,
                'permission_code'    => $override->permission_code,
                'data'               => $resource->resolve($request),
            ]);
        } catch (\InvalidArgumentException $e) {
            $message = $e->getMessage();

            // ── Lockout ──────────────────────────────────────────
            if (str_contains($message, 'locked out')) {
                preg_match('/(\d+) minutes/', $message, $matches);
                $minutesRemaining = (int) ($matches[1] ?? PinOverrideService::LOCKOUT_MINUTES);

                return response()->json([
                    'success'          => false,
                    'message'          => $message,
                    'minutes_remaining' => $minutesRemaining,
                ], 429);
            }

            // ── Wrong PIN / no authorized user ───────────────────
            if (str_contains($message, 'Invalid PIN')) {
                $lockoutBase     = "pin_override_lockout:{$storeId}:{$userId}";
                $attempts        = (int) Cache::get($lockoutBase . ':attempts', 0);
                $remainingAttempts = max(0, PinOverrideService::MAX_ATTEMPTS - $attempts);

                return response()->json([
                    'success'            => false,
                    'message'            => $message,
                    'remaining_attempts' => $remainingAttempts,
                ], 401);
            }

            // ── Permission does not require PIN ───────────────────
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 422);
        }
    }

    /**
     * GET /api/v2/staff/pin-override/check?permission_code=xxx
     *
     * Check if a permission requires PIN override.
     */
    public function check(Request $request)
    {
        $request->validate(['permission_code' => 'required|string']);

        return $this->success([
            'requires_pin' => $this->pinOverrideService->requiresPin($request->permission_code),
        ]);
    }

    /**
     * GET /api/v2/staff/pin-override/history
     *
     * Get PIN override audit history for the authenticated user's store.
     * Resolves store from the authenticated user (no query param required).
     */
    public function history(Request $request)
    {
        $user    = $request->user();
        $storeId = $user->store_id
            ?? $request->header('X-Store-Id')
            ?? $request->query('store_id');

        if (!$storeId) {
            return $this->error('Store ID could not be resolved.', 400);
        }

        $perPage = (int) $request->query('per_page', 20);

        $history = \App\Domain\Security\Models\PinOverride::where('store_id', $storeId)
            ->with(['requestingUser:id,name,email', 'authorizingUser:id,name,email'])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => PinOverrideResource::collection($history)->resolve($request),
            'meta'    => [
                'total'        => $history->total(),
                'per_page'     => $history->perPage(),
                'current_page' => $history->currentPage(),
                'last_page'    => $history->lastPage(),
            ],
        ]);
    }
}
