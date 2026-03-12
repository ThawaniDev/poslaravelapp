<?php

namespace App\Http\Controllers\Api\Core;

use App\Domain\Core\Services\StoreService;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Core\UpdateStoreRequest;
use App\Http\Requests\Core\UpdateStoreSettingsRequest;
use App\Http\Requests\Core\UpdateWorkingHoursRequest;
use App\Http\Resources\Core\StoreResource;
use App\Http\Resources\Core\StoreSettingsResource;
use App\Http\Resources\Core\StoreWorkingHourResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreController extends BaseApiController
{
    public function __construct(
        private readonly StoreService $storeService,
    ) {}

    /**
     * GET /core/stores/mine — Get the authenticated user's store.
     */
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->store_id) {
            return $this->error('No store assigned to this user.', 404);
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
            return $this->error('No organization assigned.', 404);
        }

        $stores = $this->storeService->listStores($user->organization_id);
        return $this->success(StoreResource::collection($stores));
    }

    /**
     * GET /core/stores/{id} — Get a specific store.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $store = $this->storeService->getStore($id);
            return $this->success(new StoreResource($store));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFound('Store not found.');
        }
    }

    /**
     * PUT /core/stores/{id} — Update store basic info.
     */
    public function update(UpdateStoreRequest $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $store = \App\Domain\Core\Models\Store::where('id', $id)
                ->where('organization_id', $user->organization_id)
                ->firstOrFail();
            $store = $this->storeService->updateStore($store, $request->validated());
            return $this->success(new StoreResource($store), 'Store updated.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFound('Store not found.');
        }
    }

    /**
     * GET /core/stores/{id}/settings — Get store settings.
     */
    public function settings(string $id): JsonResponse
    {
        $settings = $this->storeService->getSettings($id);
        return $this->success(new StoreSettingsResource($settings));
    }

    /**
     * PUT /core/stores/{id}/settings — Update store settings.
     */
    public function updateSettings(UpdateStoreSettingsRequest $request, string $id): JsonResponse
    {
        $settings = $this->storeService->updateSettings($id, $request->validated());
        return $this->success(new StoreSettingsResource($settings), 'Settings updated.');
    }

    /**
     * GET /core/stores/{id}/working-hours — Get working hours.
     */
    public function workingHours(string $id): JsonResponse
    {
        $hours = $this->storeService->getWorkingHours($id);
        return $this->success(StoreWorkingHourResource::collection($hours));
    }

    /**
     * PUT /core/stores/{id}/working-hours — Bulk update working hours.
     */
    public function updateWorkingHours(UpdateWorkingHoursRequest $request, string $id): JsonResponse
    {
        $hours = $this->storeService->updateWorkingHours($id, $request->validated('days'));
        return $this->success(
            StoreWorkingHourResource::collection($hours),
            'Working hours updated.',
        );
    }

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
            $store = \App\Domain\Core\Models\Store::findOrFail($id);
            $store = $this->storeService->applyBusinessType($store, $request->input('business_type'));
            return $this->success(new StoreResource($store), 'Business type applied.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFound('Store not found.');
        }
    }
}
