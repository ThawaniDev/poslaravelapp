<?php

namespace App\Domain\Core\Controllers\Api;

use App\Domain\Core\Models\Register;
use App\Domain\Core\Requests\StoreRegisterRequest;
use App\Domain\Core\Requests\UpdateRegisterRequest;
use App\Domain\Core\Resources\RegisterResource;
use App\Domain\Subscription\Traits\TracksSubscriptionUsage;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegisterController extends BaseApiController
{
    use TracksSubscriptionUsage;

    /**
     * List all registers (terminals) for the authenticated user's store.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 20);
        $search  = $request->get('search');
        $storeIds = $this->resolvedStoreIds($request);

        $query = Register::query()
            ->when(is_array($storeIds), fn ($q) => $q->whereIn('store_id', $storeIds), fn ($q) => $q->where('store_id', $storeIds))
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name');

        $paginator = $query->paginate($perPage);

        $result                  = $paginator->toArray();
        $result['data']          = RegisterResource::collection($paginator->items())->resolve();

        return $this->success($result);
    }

    /**
     * List active registers for the cashier to select during shift opening.
     *
     * If the request includes a `device_id` header/parameter, the backend will:
     *  1. Try to find an existing register already claimed by that device_id.
     *  2. If found → return only that register (auto-select flow).
     *  3. If NOT found and exactly one register has no device_id → claim it and return it.
     *  4. If NOT found and multiple unclaimed registers exist → return them all so the
     *     cashier can pick one (the app then sends a follow-up claim call on confirm).
     *  5. If NOT found and ALL registers are already claimed by OTHER devices → error.
     */
    public function listActive(Request $request): JsonResponse
    {
        $storeIds = $this->resolvedStoreIds($request);
        $deviceId = trim((string) ($request->header('X-Device-Id') ?? $request->get('device_id', '')));

        $query = Register::query()
            ->when(is_array($storeIds), fn ($q) => $q->whereIn('store_id', $storeIds), fn ($q) => $q->where('store_id', $storeIds))
            ->where('is_active', true)
            ->orderBy('name');

        $registers = $query->get();

        if ($deviceId === '') {
            // No device_id supplied — legacy behaviour, return all active registers.
            return $this->success(RegisterResource::collection($registers)->resolve());
        }

        // ── Step 1: device already claimed a register? ────────────────────────────
        $mine = $registers->firstWhere('device_id', $deviceId);
        if ($mine) {
            return $this->success(
                RegisterResource::collection(collect([$mine]))->resolve(),
                __('terminals.device_matched'),
            );
        }

        // ── Step 2: device not yet registered — look at unclaimed registers ───────
        $unclaimed = $registers->filter(fn ($r) => blank($r->device_id))->values();

        if ($unclaimed->isEmpty()) {
            // All registers are claimed by OTHER devices.
            return $this->error(
                __('terminals.device_not_assigned_title'),
                422,
                ['device_id' => [__('terminals.device_not_assigned')]],
            );
        }

        if ($unclaimed->count() === 1) {
            // Exactly one unclaimed → auto-claim it now.
            $claim = $unclaimed->first();
            $claim->update(['device_id' => $deviceId]);
            return $this->success(
                RegisterResource::collection(collect([$claim->fresh()]))->resolve(),
                __('terminals.device_auto_claimed'),
            );
        }

        // Multiple unclaimed → let the cashier choose; app will send a separate
        // claim call after the cashier selects the register.
        return $this->success(
            RegisterResource::collection($unclaimed)->resolve(),
            __('terminals.device_select_register'),
        );
    }

    /**
     * Claim a register for this device.
     * Called when the cashier selects a register from a multi-unclaimed list.
     */
    public function claimDevice(Request $request, string $register): JsonResponse
    {
        $found = Register::findOrFail($register);

        if (! $this->canAccessStore($request, $found->store_id)) {
            return $this->notFound('Register not found');
        }

        $deviceId = trim((string) ($request->header('X-Device-Id') ?? $request->get('device_id', '')));
        if ($deviceId === '') {
            return $this->error('Device ID is required', 422);
        }

        // Guard: register must not already be claimed by a different device
        if (! blank($found->device_id) && $found->device_id !== $deviceId) {
            return $this->error(
                __('terminals.device_already_claimed'),
                409,
                ['device_id' => [__('terminals.device_already_claimed')]],
            );
        }

        $found->update(['device_id' => $deviceId]);

        return $this->success(
            new RegisterResource($found->fresh()),
            __('terminals.device_auto_claimed'),
        );
    }

    /**
     * Create a new register (terminal).
     */
    public function store(StoreRegisterRequest $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;

        $register = Register::create(array_merge(
            $request->validated(),
            ['store_id' => $storeId],
        ));

        // Refresh terminal usage snapshot after creation
        $orgId = $this->resolveOrganizationId($request);
        if ($orgId) {
            $this->refreshUsageFor($orgId, 'cashier_terminals');
        }

        return $this->created(new RegisterResource($register), __('terminals.created'));
    }

    /**
     * Show a single register (terminal).
     */
    public function show(Request $request, string $register): JsonResponse
    {
        $found = Register::findOrFail($register);

        if (!$this->canAccessStore($request, $found->store_id)) {
            return $this->notFound('Register not found');
        }

        return $this->success(new RegisterResource($found));
    }

    /**
     * Update an existing register (terminal).
     */
    public function update(UpdateRegisterRequest $request, string $register): JsonResponse
    {
        $found = Register::findOrFail($register);

        if (!$this->canAccessStore($request, $found->store_id)) {
            return $this->notFound('Register not found');
        }

        $found->update($request->validated());

        return $this->success(new RegisterResource($found->fresh()), __('terminals.updated'));
    }

    /**
     * Delete a register (terminal).
     */
    public function destroy(Request $request, string $register): JsonResponse
    {
        $found = Register::findOrFail($register);

        if (!$this->canAccessStore($request, $found->store_id)) {
            return $this->notFound('Register not found');
        }

        $found->delete();

        // Refresh terminal usage snapshot after deletion
        $orgId = $this->resolveOrganizationId($request);
        if ($orgId) {
            $this->refreshUsageFor($orgId, 'cashier_terminals');
        }

        return $this->success(null, __('terminals.deleted'));
    }

    /**
     * Toggle the is_active status of a register.
     */
    public function toggleStatus(Request $request, string $register): JsonResponse
    {
        $found = Register::findOrFail($register);

        if (!$this->canAccessStore($request, $found->store_id)) {
            return $this->notFound('Register not found');
        }

        $found->update(['is_active' => !$found->is_active]);

        return $this->success(
            new RegisterResource($found->fresh()),
            $found->is_active ? __('terminals.activated') : __('terminals.deactivated'),
        );
    }
}
