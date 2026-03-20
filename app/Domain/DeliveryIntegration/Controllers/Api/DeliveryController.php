<?php

namespace App\Domain\DeliveryIntegration\Controllers\Api;

use App\Domain\DeliveryIntegration\Services\DeliveryService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends BaseApiController
{
    public function __construct(
        private readonly DeliveryService $deliveryService,
    ) {}

    /**
     * GET /api/v2/delivery/configs
     */
    public function configs(Request $request): JsonResponse
    {
        $configs = $this->deliveryService->getConfigs($request->user()->store_id);
        return $this->success($configs, __('delivery.configs_retrieved'));
    }

    /**
     * POST /api/v2/delivery/configs
     */
    public function saveConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => 'required|string|in:hungerstation,jahez,marsool',
            'api_key' => 'nullable|string|max:255',
            'merchant_id' => 'nullable|string|max:255',
            'webhook_secret' => 'nullable|string|max:255',
            'branch_id_on_platform' => 'nullable|string|max:255',
            'is_enabled' => 'nullable|boolean',
            'auto_accept' => 'nullable|boolean',
            'throttle_limit' => 'nullable|integer|min:1|max:100',
        ]);

        $config = $this->deliveryService->saveConfig($request->user()->store_id, $validated);
        return $this->success($config, __('delivery.config_saved'));
    }

    /**
     * PUT /api/v2/delivery/configs/{id}/toggle
     */
    public function toggleConfig(Request $request, string $id): JsonResponse
    {
        $config = $this->deliveryService->toggleConfig($id, $request->user()->store_id);

        if (!$config) {
            return $this->notFound(__('delivery.config_not_found'));
        }

        return $this->success($config, __('delivery.config_toggled'));
    }

    /**
     * GET /api/v2/delivery/orders
     */
    public function orders(Request $request): JsonResponse
    {
        $request->validate([
            'platform' => 'nullable|string',
            'status' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $orders = $this->deliveryService->getOrders(
            $request->user()->store_id,
            $request->only(['platform', 'status', 'per_page']),
        );

        return $this->success($orders, __('delivery.orders_retrieved'));
    }

    /**
     * GET /api/v2/delivery/sync-logs
     */
    public function syncLogs(Request $request): JsonResponse
    {
        $request->validate([
            'platform' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $logs = $this->deliveryService->getSyncLogs(
            $request->user()->store_id,
            $request->only(['platform', 'per_page']),
        );

        return $this->success($logs, __('delivery.sync_logs_retrieved'));
    }

    /**
     * GET /api/v2/delivery/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $stats = $this->deliveryService->getStats($request->user()->store_id);
        return $this->success($stats, __('delivery.stats_retrieved'));
    }
}
