<?php

namespace App\Domain\Security\Controllers\Api;

use App\Domain\Security\Requests\PinOverrideRequest;
use App\Domain\Security\Resources\PinOverrideResource;
use App\Domain\Security\Services\PinOverrideService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\Request;

class PinOverrideController extends BaseApiController
{
    public function __construct(
        private readonly PinOverrideService $pinOverrideService,
    ) {}

    /**
     * POST /api/v2/staff/pin-override
     *
     * Request a PIN override for a protected action.
     */
    public function authorizePin(PinOverrideRequest $request)
    {
        try {
            $override = $this->pinOverrideService->authorize(
                storeId: $request->store_id,
                requestingUser: $request->user(),
                authorizingPin: $request->pin,
                permissionCode: $request->permission_code,
                context: $request->context ?? [],
            );

            return $this->success(new PinOverrideResource($override), 'PIN override authorized.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 403);
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
     * GET /api/v2/staff/pin-override/history?store_id=xxx
     *
     * Get PIN override audit history.
     */
    public function history(Request $request)
    {
        $request->validate(['store_id' => 'required|uuid|exists:stores,id']);

        $history = $this->pinOverrideService->history($request->store_id);

        return $this->success(PinOverrideResource::collection($history));
    }
}
