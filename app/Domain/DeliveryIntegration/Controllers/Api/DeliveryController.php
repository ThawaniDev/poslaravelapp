<?php

namespace App\Domain\DeliveryIntegration\Controllers\Api;

use App\Domain\DeliveryIntegration\Enums\DeliveryConfigPlatform;
use App\Domain\DeliveryIntegration\Enums\DeliveryOrderStatus;
use App\Domain\DeliveryIntegration\Jobs\MenuSyncJob;
use App\Domain\DeliveryIntegration\Services\DeliveryService;
use App\Domain\DeliveryIntegration\Services\MenuSyncService;
use App\Domain\DeliveryPlatformRegistry\Models\DeliveryPlatform;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeliveryController extends BaseApiController
{
    public function __construct(
        private readonly DeliveryService $deliveryService,
        private readonly MenuSyncService $menuSyncService,
    ) {}

    /**
     * GET /api/v2/delivery/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $stats = $this->deliveryService->getStats($request->user()->store_id);

        return $this->success($stats, __('delivery.stats_retrieved'));
    }

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
            'platform' => ['required', 'string', Rule::exists('delivery_platforms', 'slug')->where('is_active', true)],
            'api_key' => 'nullable|string|max:255',
            'merchant_id' => 'nullable|string|max:255',
            'webhook_secret' => 'nullable|string|max:255',
            'branch_id_on_platform' => 'nullable|string|max:255',
            'is_enabled' => 'nullable|boolean',
            'auto_accept' => 'nullable|boolean',
            'throttle_limit' => 'nullable|integer|min:1|max:100',
            'max_daily_orders' => 'nullable|integer|min:1|max:10000',
            'sync_menu_on_product_change' => 'nullable|boolean',
            'menu_sync_interval_hours' => 'nullable|integer|min:1|max:168',
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

        if (! $config) {
            return $this->notFound(__('delivery.config_not_found'));
        }

        return $this->success($config, __('delivery.config_toggled'));
    }

    /**
     * POST /api/v2/delivery/configs/{id}/test-connection
     */
    public function testConnection(Request $request, string $id): JsonResponse
    {
        $result = $this->deliveryService->testConnection($id, $request->user()->store_id);

        if (! $result['success']) {
            return $this->error($result['message'] ?? __('delivery.connection_failed'), 422);
        }

        return $this->success($result, __('delivery.connection_success'));
    }

    /**
     * GET /api/v2/delivery/orders
     */
    public function orders(Request $request): JsonResponse
    {
        $request->validate([
            'platform' => 'nullable|string',
            'status' => ['nullable', 'string', Rule::in(array_column(DeliveryOrderStatus::cases(), 'value'))],
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $orders = $this->deliveryService->getOrders(
            $request->user()->store_id,
            $request->only(['platform', 'status', 'date_from', 'date_to', 'per_page']),
        );

        return $this->success($orders, __('delivery.orders_retrieved'));
    }

    /**
     * GET /api/v2/delivery/orders/active
     */
    public function activeOrders(Request $request): JsonResponse
    {
        $orders = $this->deliveryService->getActiveOrders($request->user()->store_id);

        return $this->success($orders, __('delivery.orders_retrieved'));
    }

    /**
     * GET /api/v2/delivery/orders/{id}
     */
    public function orderDetail(Request $request, string $id): JsonResponse
    {
        $order = $this->deliveryService->getOrder($id, $request->user()->store_id);

        if (! $order) {
            return $this->notFound(__('delivery.order_not_found'));
        }

        return $this->success($order, __('delivery.order_retrieved'));
    }

    /**
     * PUT /api/v2/delivery/orders/{id}/status
     */
    public function updateOrderStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(array_column(DeliveryOrderStatus::cases(), 'value'))],
            'reason' => 'nullable|string|max:500',
        ]);

        $order = $this->deliveryService->updateOrderStatus(
            $id,
            $request->user()->store_id,
            $validated['status'],
            $validated['reason'] ?? null,
        );

        if (! $order) {
            return $this->error(__('delivery.status_update_failed'), 422);
        }

        return $this->success($order, __('delivery.status_updated'));
    }

    /**
     * POST /api/v2/delivery/menu-sync
     */
    public function triggerMenuSync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => 'nullable|string',
            'products' => 'required|array|min:1',
            'products.*.id' => 'required',
            'products.*.name' => 'required|string',
            'products.*.price' => 'required|numeric|min:0',
        ]);

        $storeId = $request->user()->store_id;
        $products = $validated['products'];

        if (! empty($validated['platform'])) {
            $config = $this->deliveryService->getConfigByPlatform($storeId, $validated['platform']);
            if (! $config) {
                return $this->notFound(__('delivery.config_not_found'));
            }
            MenuSyncJob::dispatch($config->id, $products);
        } else {
            $configs = $this->deliveryService->getConfigs($storeId);
            foreach ($configs as $config) {
                if ($config['is_enabled'] ?? false) {
                    MenuSyncJob::dispatch($config['id'], $products);
                }
            }
        }

        return $this->success(null, __('delivery.menu_sync_queued'));
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
     * GET /api/v2/delivery/platforms
     */
    public function platforms(): JsonResponse
    {
        $platforms = DeliveryPlatform::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (DeliveryPlatform $p) => [
                'value' => $p->slug,
                'label' => $p->name,
                'name_ar' => $p->name_ar,
                'logo_url' => $p->logo_url,
                'description' => $p->description,
                'description_ar' => $p->description_ar,
                'color' => DeliveryConfigPlatform::tryFrom($p->slug)?->color() ?? '#666666',
            ])
            ->values();

        return $this->success($platforms, __('delivery.platforms_retrieved'));
    }
}
