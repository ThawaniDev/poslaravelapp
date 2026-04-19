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

        $query = Register::where('store_id', $request->user()->store_id)
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name');

        $paginator = $query->paginate($perPage);

        $result                  = $paginator->toArray();
        $result['data']          = RegisterResource::collection($paginator->items())->resolve();

        return $this->success($result);
    }

    /**
     * List active registers for the cashier to select during shift opening.
     * Lightweight endpoint — no pagination, only active registers, minimal fields.
     */
    public function listActive(Request $request): JsonResponse
    {
        $registers = Register::where('store_id', $request->user()->store_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'store_id', 'name', 'device_id', 'platform', 'is_online']);

        return $this->success($registers);
    }

    /**
     * Create a new register (terminal).
     */
    public function store(StoreRegisterRequest $request): JsonResponse
    {
        $register = Register::create(array_merge(
            $request->validated(),
            ['store_id' => $request->user()->store_id],
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
        $found = Register::where('store_id', $request->user()->store_id)->findOrFail($register);

        return $this->success(new RegisterResource($found));
    }

    /**
     * Update an existing register (terminal).
     */
    public function update(UpdateRegisterRequest $request, string $register): JsonResponse
    {
        $found = Register::where('store_id', $request->user()->store_id)->findOrFail($register);

        $found->update($request->validated());

        return $this->success(new RegisterResource($found->fresh()), __('terminals.updated'));
    }

    /**
     * Delete a register (terminal).
     */
    public function destroy(Request $request, string $register): JsonResponse
    {
        $found = Register::where('store_id', $request->user()->store_id)->findOrFail($register);

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
        $found = Register::where('store_id', $request->user()->store_id)->findOrFail($register);

        $found->update(['is_active' => !$found->is_active]);

        return $this->success(
            new RegisterResource($found->fresh()),
            $found->is_active ? __('terminals.activated') : __('terminals.deactivated'),
        );
    }
}
