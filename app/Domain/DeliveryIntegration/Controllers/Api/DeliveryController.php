<?php

namespace App\Domain\DeliveryIntegration\Controllers\Api;

use App\Domain\DeliveryIntegration\Enums\DeliveryConfigPlatform;
use App\Domain\DeliveryIntegration\Enums\DeliveryOrderStatus;
use App\Domain\DeliveryIntegration\Jobs\MenuSyncJob;
use App\Domain\DeliveryIntegration\Resources\DeliveryMenuSyncLogResource;
use App\Domain\DeliveryIntegration\Resources\DeliveryOrderMappingResource;
use App\Domain\DeliveryIntegration\Resources\DeliveryPlatformConfigResource;
use App\Domain\DeliveryIntegration\Resources\DeliveryStatusPushLogResource;
use App\Domain\DeliveryIntegration\Resources\DeliveryWebhookLogResource;
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
        $stats = $this->deliveryService->getStats($this->resolvedStoreId($request) ?? $request->user()->store_id);

        return $this->success($stats, __('delivery.stats_retrieved'));
    }

    /**
     * GET /api/v2/delivery/configs
     */
    public function configs(Request $request): JsonResponse
    {
        $configs = $this->deliveryService->getConfigsCollection($this->resolvedStoreId($request) ?? $request->user()->store_id);

        return $this->success(
            DeliveryPlatformConfigResource::collection($configs)->resolve(),
            __('delivery.configs_retrieved'),
        );
    }

    /**
     * POST /api/v2/delivery/configs
     */
    public function saveConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => ['required', 'string', Rule::exists('delivery_platforms', 'slug')->where('is_active', true)],
            'api_key' => 'nullable|string|max:500',
            'merchant_id' => 'nullable|string|max:255',
            'webhook_secret' => 'nullable|string|max:500',
            'branch_id_on_platform' => 'nullable|string|max:255',
            'is_enabled' => 'nullable|boolean',
            'auto_accept' => 'nullable|boolean',
            'auto_accept_timeout_seconds' => 'nullable|integer|min:60|max:1800',
            'throttle_limit' => 'nullable|integer|min:1|max:1000',
            'max_daily_orders' => 'nullable|integer|min:1|max:10000',
            'sync_menu_on_product_change' => 'nullable|boolean',
            'menu_sync_interval_hours' => 'nullable|integer|min:1|max:168',
            'operating_hours_json' => 'nullable|array',
            'operating_hours_json.*.day_of_week' => 'required_with:operating_hours_json|integer|between:0,6',
            'operating_hours_json.*.open_time' => 'nullable|string|max:5',
            'operating_hours_json.*.close_time' => 'nullable|string|max:5',
            'operating_hours_json.*.is_closed' => 'nullable|boolean',
        ]);

        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;

        // Enforce max_delivery_platforms plan limit only when creating a new config
        $existingConfig = $this->deliveryService->getConfigByPlatform($storeId, $validated['platform']);
        if (! $existingConfig) {
            $limitInfo = $this->deliveryService->getPlatformLimitInfo($storeId);
            if ($limitInfo['reached']) {
                return $this->error(
                    __('delivery.plan_limit_reached', ['limit' => $limitInfo['limit']]),
                    403,
                );
            }
        }

        $config = $this->deliveryService->saveConfig($storeId, $validated);

        return $this->success(
            (new DeliveryPlatformConfigResource($config))->resolve(),
            __('delivery.config_saved'),
        );
    }

    /**
     * PUT /api/v2/delivery/configs/{id}/toggle
     */
    public function toggleConfig(Request $request, string $id): JsonResponse
    {
        $config = $this->deliveryService->toggleConfig($id, $this->resolvedStoreId($request) ?? $request->user()->store_id);

        if (! $config) {
            return $this->notFound(__('delivery.config_not_found'));
        }

        return $this->success(
            (new DeliveryPlatformConfigResource($config))->resolve(),
            __('delivery.config_toggled'),
        );
    }

    /**
     * POST /api/v2/delivery/configs/{id}/test-connection
     */
    public function testConnection(Request $request, string $id): JsonResponse
    {
        $result = $this->deliveryService->testConnection($id, $this->resolvedStoreId($request) ?? $request->user()->store_id);

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

        $paginator = $this->deliveryService->getOrders(
            $this->resolvedStoreId($request) ?? $request->user()->store_id,
            $request->only(['platform', 'status', 'date_from', 'date_to', 'per_page']),
        );

        return $this->successPaginated(
            DeliveryOrderMappingResource::collection($paginator)->resolve(),
            $paginator,
            __('delivery.orders_retrieved'),
        );
    }

    /**
     * GET /api/v2/delivery/orders/active
     */
    public function activeOrders(Request $request): JsonResponse
    {
        $orders = $this->deliveryService->getActiveOrdersCollection($this->resolvedStoreId($request) ?? $request->user()->store_id);

        return $this->success(
            DeliveryOrderMappingResource::collection($orders)->resolve(),
            __('delivery.orders_retrieved'),
        );
    }

    /**
     * GET /api/v2/delivery/orders/{id}
     */
    public function orderDetail(Request $request, string $id): JsonResponse
    {
        $order = $this->deliveryService->getOrder($id, $this->resolvedStoreId($request) ?? $request->user()->store_id);

        if (! $order) {
            return $this->notFound(__('delivery.order_not_found'));
        }

        return $this->success(
            (new DeliveryOrderMappingResource($order))->resolve(),
            __('delivery.order_retrieved'),
        );
    }

    /**
     * PUT /api/v2/delivery/orders/{id}/status
     */
    public function updateOrderStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(array_column(DeliveryOrderStatus::cases(), 'value'))],
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $order = $this->deliveryService->updateOrderStatus(
            $id,
            $this->resolvedStoreId($request) ?? $request->user()->store_id,
            $validated['status'],
            $validated['rejection_reason'] ?? null,
        );

        if (! $order) {
            return $this->error(__('delivery.status_update_failed'), 422);
        }

        return $this->success(
            (new DeliveryOrderMappingResource($order))->resolve(),
            __('delivery.status_updated'),
        );
    }

    /**
     * POST /api/v2/delivery/menu-sync
     */
    public function triggerMenuSync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => 'nullable|string',
            // products is optional — if omitted the MenuSyncJob loads the store catalog at dispatch time
            'products' => 'nullable|array',
            'products.*.id' => 'nullable',
            'products.*.name' => 'required_with:products|string',
            'products.*.price' => 'required_with:products|numeric|min:0',
        ]);

        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $products = $validated['products'] ?? [];

        if (! empty($validated['platform'])) {
            $config = $this->deliveryService->getConfigByPlatform($storeId, $validated['platform']);
            if (! $config) {
                return $this->notFound(__('delivery.config_not_found'));
            }
            MenuSyncJob::dispatch($config->id, $products);
        } else {
            $configs = $this->deliveryService->getConfigsCollection($storeId);
            foreach ($configs as $config) {
                if ($config->is_enabled) {
                    MenuSyncJob::dispatch($config->id, $products);
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

        $paginator = $this->deliveryService->getSyncLogs(
            $this->resolvedStoreId($request) ?? $request->user()->store_id,
            $request->only(['platform', 'per_page']),
        );

        return $this->successPaginated(
            DeliveryMenuSyncLogResource::collection($paginator)->resolve(),
            $paginator,
            __('delivery.sync_logs_retrieved'),
        );
    }

    /**
     * GET /api/v2/delivery/platforms
     */
    public function platforms(): JsonResponse
    {
        $platforms = DeliveryPlatform::where('is_active', true)
            ->with(['fields' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get()
            ->map(function (DeliveryPlatform $platform) {
                return [
                    'id'                         => $platform->id,
                    'name'                       => $platform->name,
                    'name_ar'                    => $platform->name_ar,
                    'slug'                       => $platform->slug,
                    'description'                => $platform->description,
                    'description_ar'             => $platform->description_ar,
                    'logo_url'                   => $platform->logo_url,
                    'api_type'                   => $platform->api_type,
                    'auth_method'                => $platform->auth_method,
                    'base_url'                   => $platform->base_url,
                    'documentation_url'          => $platform->documentation_url,
                    'default_commission_percent' => $platform->default_commission_percent,
                    'supported_countries'        => $platform->supported_countries ?? [],
                    'fields'                     => $platform->fields->map(fn ($f) => [
                        'field_key'   => $f->field_key,
                        'field_label' => $f->field_label,
                        'field_type'  => $f->field_type,
                        'is_required' => (bool) $f->is_required,
                        'sort_order'  => $f->sort_order,
                    ])->values(),
                ];
            });

        return response()->json(['data' => $platforms]);
    }

    /**
     * GET /api/v2/delivery/webhook-logs
     */
    public function webhookLogs(Request $request): JsonResponse
    {
        $request->validate([
            'platform' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $paginator = $this->deliveryService->getWebhookLogs(
            $this->resolvedStoreId($request) ?? $request->user()->store_id,
            $request->only(['platform', 'per_page']),
        );

        return $this->successPaginated(
            DeliveryWebhookLogResource::collection($paginator)->resolve(),
            $paginator,
            __('delivery.webhook_logs_retrieved'),
        );
    }

    /**
     * GET /api/v2/delivery/status-push-logs
     */
    public function statusPushLogs(Request $request): JsonResponse
    {
        $request->validate([
            'platform' => 'nullable|string',
            'order_id' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $paginator = $this->deliveryService->getStatusPushLogs(
            $this->resolvedStoreId($request) ?? $request->user()->store_id,
            $request->only(['platform', 'order_id', 'per_page']),
        );

        return $this->successPaginated(
            DeliveryStatusPushLogResource::collection($paginator)->resolve(),
            $paginator,
            __('delivery.status_push_logs_retrieved'),
        );
    }

    /**
     * GET /api/v2/delivery/configs/{id}
     */
    public function configDetail(Request $request, string $id): JsonResponse
    {
        $config = $this->deliveryService->getConfig($id, $this->resolvedStoreId($request) ?? $request->user()->store_id);

        if (! $config) {
            return $this->notFound(__('delivery.config_not_found'));
        }

        return $this->success(
            (new DeliveryPlatformConfigResource($config))->resolve(),
            __('delivery.config_retrieved'),
        );
    }

    /**
     * DELETE /api/v2/delivery/configs/{id}
     */
    public function deleteConfig(Request $request, string $id): JsonResponse
    {
        $deleted = $this->deliveryService->deleteConfig($id, $this->resolvedStoreId($request) ?? $request->user()->store_id);

        if (! $deleted) {
            return $this->notFound(__('delivery.config_not_found'));
        }

        return $this->success(null, __('delivery.config_deleted'));
    }
}
