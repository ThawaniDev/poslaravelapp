<?php

namespace App\Http\Controllers\Api;

use App\Domain\Payment\Requests\UpsertStoreInstallmentConfigRequest;
use App\Domain\Payment\Resources\StoreInstallmentConfigResource;
use App\Domain\Payment\Services\InstallmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreInstallmentController extends BaseApiController
{
    public function __construct(
        private readonly InstallmentService $installmentService,
    ) {}

    /**
     * List all platform providers with store-level config status.
     */
    public function available(Request $request): JsonResponse
    {
        $storeId = $request->attributes->get('resolved_store_id');
        $providers = $this->installmentService->getAvailableProviders($storeId);
        return $this->success($providers);
    }

    /**
     * List store configs.
     */
    public function index(Request $request): JsonResponse
    {
        $storeId = $request->attributes->get('resolved_store_id');
        $configs = $this->installmentService->getStoreConfigs($storeId);
        return $this->success(StoreInstallmentConfigResource::collection($configs));
    }

    /**
     * Get store config for a specific provider.
     */
    public function show(Request $request, string $provider): JsonResponse
    {
        $storeId = $request->attributes->get('resolved_store_id');
        $config = $this->installmentService->getStoreConfig($storeId, $provider);

        if (!$config) {
            return $this->notFound('No configuration found for this provider');
        }

        return $this->success(new StoreInstallmentConfigResource($config));
    }

    /**
     * Create or update store config for a provider.
     */
    public function upsert(UpsertStoreInstallmentConfigRequest $request): JsonResponse
    {
        $storeId = $request->attributes->get('resolved_store_id');
        $validated = $request->validated();
        $provider = $validated['provider'];
        unset($validated['provider']);

        try {
            $config = $this->installmentService->upsertStoreConfig($storeId, $provider, $validated);
            return $this->success(new StoreInstallmentConfigResource($config), 'Configuration saved');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Toggle store provider enabled/disabled.
     */
    public function toggle(Request $request, string $provider): JsonResponse
    {
        $storeId = $request->attributes->get('resolved_store_id');

        try {
            $config = $this->installmentService->toggleStoreConfig($storeId, $provider);
            $state = $config->is_enabled ? 'enabled' : 'disabled';
            return $this->success(new StoreInstallmentConfigResource($config), "Provider {$state}");
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound('No configuration found for this provider');
        }
    }

    /**
     * Delete store config for a provider.
     */
    public function destroy(Request $request, string $provider): JsonResponse
    {
        $storeId = $request->attributes->get('resolved_store_id');
        $this->installmentService->deleteStoreConfig($storeId, $provider);
        return $this->success(null, 'Configuration removed');
    }
}
