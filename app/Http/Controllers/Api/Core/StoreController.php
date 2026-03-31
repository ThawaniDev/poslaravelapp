<?php

namespace App\Http\Controllers\Api\Core;

use App\Domain\Core\Models\Store;
use App\Domain\Core\Services\StoreService;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Core\CreateStoreRequest;
use App\Http\Requests\Core\UpdateStoreRequest;
use App\Http\Requests\Core\UpdateStoreSettingsRequest;
use App\Http\Requests\Core\UpdateWorkingHoursRequest;
use App\Http\Resources\Core\StoreResource;
use App\Http\Resources\Core\StoreSettingsResource;
use App\Http\Resources\Core\StoreWorkingHourResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreController extends BaseApiController
{
    public function __construct(
        private readonly StoreService $storeService,
    ) {}

    // ─── Store CRUD ──────────────────────────────────────────────

    /**
     * GET /core/stores/mine — Get the authenticated user's store.
     */
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->store_id) {
            return $this->error(__('No store assigned to this user.'), 404);
        }

        $store = $this->storeService->getStore($user->store_id);
        return $this->success(new StoreResource($store));
    }

    /**
     * GET /core/stores — List organization's stores (branches).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->organization_id) {
            return $this->error(__('No organization assigned.'), 404);
        }

        $stores = $this->storeService->listStores(
            $user->organization_id,
            $request->only([
                'search', 'is_active', 'is_main_branch', 'is_warehouse',
                'business_type', 'city', 'region', 'manager_id',
                'has_delivery', 'accepts_online_orders',
                'sort_by', 'sort_dir', 'per_page',
            ]),
        );

        return $this->success(StoreResource::collection($stores));
    }

    /**
     * GET /core/stores/{id} — Get a specific store (detail view).
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $store = Store::where('id', $id)
                ->where('organization_id', $user->organization_id)
                ->firstOrFail();

            $store = $this->storeService->getStore($store->id);
            return $this->success(new StoreResource($store));
        } catch (ModelNotFoundException) {
            return $this->notFound(__('Store not found.'));
        }
    }

    /**
     * POST /core/stores — Create a new store (branch).
     */
    public function store(CreateStoreRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->organization_id) {
            return $this->error(__('No organization assigned.'), 404);
        }

        $store = $this->storeService->createStore(
            $user->organization_id,
            $request->validated(),
        );

        return $this->created(new StoreResource($store), __('Branch created successfully.'));
    }

    /**
     * PUT /core/stores/{id} — Update store basic info.
     */
    public function update(UpdateStoreRequest $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $store = Store::where('id', $id)
                ->where('organization_id', $user->organization_id)
                ->firstOrFail();

            $store = $this->storeService->updateStore($store, $request->validated());
            return $this->success(new StoreResource($store), __('Branch updated successfully.'));
        } catch (ModelNotFoundException) {
            return $this->notFound(__('Store not found.'));
        }
    }

    /**
     * DELETE /core/stores/{id} — Delete a store (branch).
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $store = Store::where('id', $id)
                ->where('organization_id', $user->organization_id)
                ->firstOrFail();

            $this->storeService->deleteStore($store);
            return $this->success(null, __('Branch deleted successfully.'));
        } catch (ModelNotFoundException) {
            return $this->notFound(__('Store not found.'));
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * POST /core/stores/{id}/toggle-active — Toggle active/inactive.
     */
    public function toggleActive(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $store = Store::where('id', $id)
                ->where('organization_id', $user->organization_id)
                ->firstOrFail();

            $store = $this->storeService->toggleActive($store);
            return $this->success(
                new StoreResource($store),
                $store->is_active ? __('Branch activated.') : __('Branch deactivated.'),
            );
        } catch (ModelNotFoundException) {
            return $this->notFound(__('Store not found.'));
        }
    }

    /**
     * GET /core/stores/stats — Get branch statistics for the organization.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->organization_id) {
            return $this->error(__('No organization assigned.'), 404);
        }

        $stats = $this->storeService->getOrganizationBranchStats($user->organization_id);
        return $this->success($stats);
    }

    /**
     * PUT /core/stores/sort-order — Bulk update sort order.
     */
    public function updateSortOrder(Request $request): JsonResponse
    {
        $request->validate([
            'items'              => ['required', 'array', 'min:1'],
            'items.*.id'         => ['required', 'uuid'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $user = $request->user();
        $this->storeService->updateSortOrder($user->organization_id, $request->input('items'));
        return $this->success(null, __('Sort order updated.'));
    }

    /**
     * GET /core/stores/managers — Available managers for assignment.
     */
    public function managers(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->organization_id) {
            return $this->error(__('No organization assigned.'), 404);
        }

        $managers = $this->storeService->getAvailableManagers($user->organization_id);
        return $this->success($managers);
    }

    // ─── Store Settings ──────────────────────────────────────────

    /**
     * GET /core/stores/{id}/settings — Get store settings.
     */
    public function settings(Request $request, string $id): JsonResponse
    {
        $this->authorizeStoreAccess($request, $id);
        $settings = $this->storeService->getSettings($id);
        return $this->success(new StoreSettingsResource($settings));
    }

    /**
     * PUT /core/stores/{id}/settings — Update store settings.
     */
    public function updateSettings(UpdateStoreSettingsRequest $request, string $id): JsonResponse
    {
        $this->authorizeStoreAccess($request, $id);
        $settings = $this->storeService->updateSettings($id, $request->validated());
        return $this->success(new StoreSettingsResource($settings), __('Settings updated.'));
    }

    /**
     * POST /core/stores/{id}/copy-settings — Copy settings from another store.
     */
    public function copySettings(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'source_store_id' => ['required', 'uuid', 'exists:stores,id'],
        ]);

        try {
            $this->authorizeStoreAccess($request, $id);
            $settings = $this->storeService->copySettings($request->input('source_store_id'), $id);
            return $this->success(new StoreSettingsResource($settings), __('Settings copied.'));
        } catch (ModelNotFoundException) {
            return $this->notFound(__('Store not found.'));
        }
    }

    // ─── Working Hours ───────────────────────────────────────────

    /**
     * GET /core/stores/{id}/working-hours — Get working hours.
     */
    public function workingHours(Request $request, string $id): JsonResponse
    {
        $this->authorizeStoreAccess($request, $id);
        $hours = $this->storeService->getWorkingHours($id);
        return $this->success(StoreWorkingHourResource::collection($hours));
    }

    /**
     * PUT /core/stores/{id}/working-hours — Bulk update working hours.
     */
    public function updateWorkingHours(UpdateWorkingHoursRequest $request, string $id): JsonResponse
    {
        $this->authorizeStoreAccess($request, $id);
        $hours = $this->storeService->updateWorkingHours($id, $request->validated('days'));
        return $this->success(
            StoreWorkingHourResource::collection($hours),
            __('Working hours updated.'),
        );
    }

    /**
     * POST /core/stores/{id}/copy-working-hours — Copy working hours from another store.
     */
    public function copyWorkingHours(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'source_store_id' => ['required', 'uuid', 'exists:stores,id'],
        ]);

        try {
            $this->authorizeStoreAccess($request, $id);
            $hours = $this->storeService->copyWorkingHours($request->input('source_store_id'), $id);
            return $this->success(
                StoreWorkingHourResource::collection($hours),
                __('Working hours copied.'),
            );
        } catch (ModelNotFoundException) {
            return $this->notFound(__('Store not found.'));
        }
    }

    // ─── Business Types ──────────────────────────────────────────

    /**
     * GET /core/business-types — List available business type templates.
     */
    public function businessTypes(): JsonResponse
    {
        $templates = $this->storeService->getBusinessTypeTemplates();
        return $this->success($templates);
    }

    /**
     * POST /core/stores/{id}/business-type — Apply a business type.
     */
    public function applyBusinessType(Request $request, string $id): JsonResponse
    {
        $request->validate(['business_type' => 'required|string']);

        try {
            $this->authorizeStoreAccess($request, $id);
            $store = Store::findOrFail($id);
            $store = $this->storeService->applyBusinessType($store, $request->input('business_type'));
            return $this->success(new StoreResource($store), __('Business type applied.'));
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (ModelNotFoundException) {
            return $this->notFound(__('Store not found.'));
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────

    /**
     * Verify that the authenticated user belongs to the store's organization.
     */
    private function authorizeStoreAccess(Request $request, string $storeId): void
    {
        $user = $request->user();
        $store = Store::findOrFail($storeId);

        if ($store->organization_id !== $user->organization_id) {
            abort(403, __('You do not have access to this branch.'));
        }
    }
}
