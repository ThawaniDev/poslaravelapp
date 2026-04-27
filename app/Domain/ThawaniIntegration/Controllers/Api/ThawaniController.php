<?php

namespace App\Domain\ThawaniIntegration\Controllers\Api;

use App\Domain\ThawaniIntegration\Services\ThawaniService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThawaniController extends BaseApiController
{
    public function __construct(
        private readonly ThawaniService $thawaniService,
    ) {}

    /**
     * GET /api/v2/thawani/config
     */
    public function config(Request $request): JsonResponse
    {
        $config = $this->thawaniService->getConfig($this->resolvedStoreId($request) ?? $request->user()->store_id);
        return $this->success($config, __('thawani.config_retrieved'));
    }

    /**
     * POST /api/v2/thawani/config
     */
    public function saveConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'thawani_store_id' => 'nullable|string|max:255',
            'marketplace_url' => 'nullable|url|max:500',
            'api_key' => 'nullable|string|max:255',
            'api_secret' => 'nullable|string|max:255',
            'is_connected' => 'nullable|boolean',
            'auto_sync_products' => 'nullable|boolean',
            'auto_sync_inventory' => 'nullable|boolean',
            'auto_accept_orders' => 'nullable|boolean',
            'operating_hours' => 'nullable|array',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $config = $this->thawaniService->saveConfig($this->resolvedStoreId($request) ?? $request->user()->store_id, $validated);
        return $this->success($config, __('thawani.config_saved'));
    }

    /**
     * PUT /api/v2/thawani/disconnect
     */
    public function disconnect(Request $request): JsonResponse
    {
        $result = $this->thawaniService->disconnect($this->resolvedStoreId($request) ?? $request->user()->store_id);

        if (!$result) {
            return $this->notFound(__('thawani.config_not_found'));
        }

        return $this->success(null, __('thawani.disconnected'));
    }

    /**
     * POST /api/v2/thawani/test-connection
     */
    public function testConnection(Request $request): JsonResponse
    {
        $result = $this->thawaniService->testConnection($this->resolvedStoreId($request) ?? $request->user()->store_id);

        if ($result['success']) {
            return $this->success($result['data'] ?? $result, __('thawani.connection_successful'));
        }

        return $this->error($result['message'] ?? __('thawani.connection_failed'), 422);
    }

    /**
     * GET /api/v2/thawani/orders
     */
    public function orders(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $orders = $this->thawaniService->getOrders(
            $this->resolvedStoreId($request) ?? $request->user()->store_id,
            $request->only(['status', 'per_page']),
        );

        return $this->success($orders, __('thawani.orders_retrieved'));
    }

    /**
     * GET /api/v2/thawani/product-mappings
     */
    public function productMappings(Request $request): JsonResponse
    {
        $mappings = $this->thawaniService->getProductMappings($this->resolvedStoreId($request) ?? $request->user()->store_id);
        return $this->success($mappings, __('thawani.product_mappings_retrieved'));
    }

    /**
     * POST /api/v2/thawani/push-products
     */
    public function pushProducts(Request $request): JsonResponse
    {
        $result = $this->thawaniService->pushProductsToThawani($this->resolvedStoreId($request) ?? $request->user()->store_id);

        if (!($result['success'] ?? false)) {
            return $this->error($result['message'] ?? __('thawani.sync_failed'), 422);
        }

        return $this->success($result['data'] ?? null, __('thawani.products_synced'));
    }

    /**
     * POST /api/v2/thawani/pull-products
     */
    public function pullProducts(Request $request): JsonResponse
    {
        $result = $this->thawaniService->pullProductsFromThawani($this->resolvedStoreId($request) ?? $request->user()->store_id);

        if (!($result['success'] ?? false)) {
            return $this->error($result['message'] ?? __('thawani.sync_failed'), 422);
        }

        return $this->success($result['data'] ?? null, __('thawani.products_synced'));
    }

    /**
     * GET /api/v2/thawani/category-mappings
     */
    public function categoryMappings(Request $request): JsonResponse
    {
        $mappings = $this->thawaniService->getCategoryMappings($this->resolvedStoreId($request) ?? $request->user()->store_id);
        return $this->success($mappings, __('thawani.category_mappings_retrieved'));
    }

    /**
     * POST /api/v2/thawani/push-categories
     */
    public function pushCategories(Request $request): JsonResponse
    {
        $result = $this->thawaniService->pushCategoriesToThawani($this->resolvedStoreId($request) ?? $request->user()->store_id);

        if (!($result['success'] ?? false)) {
            return $this->error($result['message'] ?? __('thawani.sync_failed'), 422);
        }

        return $this->success($result['data'] ?? null, __('thawani.categories_synced'));
    }

    /**
     * POST /api/v2/thawani/pull-categories
     */
    public function pullCategories(Request $request): JsonResponse
    {
        $result = $this->thawaniService->pullCategoriesFromThawani($this->resolvedStoreId($request) ?? $request->user()->store_id);

        if (!($result['success'] ?? false)) {
            return $this->error($result['message'] ?? __('thawani.sync_failed'), 422);
        }

        return $this->success($result['data'] ?? null, __('thawani.categories_synced'));
    }

    /**
     * GET /api/v2/thawani/column-mappings
     */
    public function columnMappings(Request $request): JsonResponse
    {
        $mappings = $this->thawaniService->getColumnMappings();
        return $this->success($mappings, __('thawani.column_mappings_retrieved'));
    }

    /**
     * POST /api/v2/thawani/column-mappings/seed-defaults
     */
    public function seedColumnDefaults(Request $request): JsonResponse
    {
        $this->thawaniService->seedDefaultColumnMappings();
        return $this->success(null, __('thawani.defaults_seeded'));
    }

    /**
     * GET /api/v2/thawani/sync-logs
     */
    public function syncLogs(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'entity_type' => 'nullable|string|in:product,category,connection',
            'status' => 'nullable|string|in:success,failed,pending',
        ]);

        $logs = $this->thawaniService->getSyncLogs(
            $this->resolvedStoreId($request) ?? $request->user()->store_id,
            $request->only(['per_page', 'entity_type', 'status']),
        );

        return $this->success($logs, __('thawani.sync_logs_retrieved'));
    }

    /**
     * GET /api/v2/thawani/queue-stats
     */
    public function queueStats(Request $request): JsonResponse
    {
        $stats = $this->thawaniService->getQueueStats($this->resolvedStoreId($request) ?? $request->user()->store_id);
        return $this->success($stats, __('thawani.queue_stats_retrieved'));
    }

    /**
     * POST /api/v2/thawani/process-queue
     */
    public function processQueue(Request $request): JsonResponse
    {
        $result = $this->thawaniService->processQueue($this->resolvedStoreId($request) ?? $request->user()->store_id);
        return $this->success($result, __('thawani.sync_completed'));
    }

    /**
     * GET /api/v2/thawani/settlements
     */
    public function settlements(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $settlements = $this->thawaniService->getSettlements(
            $this->resolvedStoreId($request) ?? $request->user()->store_id,
            $request->only(['per_page']),
        );

        return $this->success($settlements, __('thawani.settlements_retrieved'));
    }

    /**
     * GET /api/v2/thawani/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $stats = $this->thawaniService->getStats($this->resolvedStoreId($request) ?? $request->user()->store_id);
        return $this->success($stats, __('thawani.stats_retrieved'));
    }

    // ─── Order Management ──────────────────────────────────

    /**
     * GET /api/v2/thawani/orders/{id}
     */
    public function orderDetail(Request $request, string $id): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $order = $this->thawaniService->getOrderDetail($storeId, $id);

        if (!$order) {
            return $this->notFound(__('thawani.order_not_found'));
        }

        return $this->success($order, __('thawani.order_retrieved'));
    }

    /**
     * POST /api/v2/thawani/orders/{id}/accept
     */
    public function acceptOrder(Request $request, string $id): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $result = $this->thawaniService->acceptOrder($storeId, $id);

        if (!($result['success'] ?? false)) {
            return $this->error($result['message'] ?? __('thawani.order_action_failed'), 422);
        }

        return $this->success($result['order'], __('thawani.order_accepted'));
    }

    /**
     * POST /api/v2/thawani/orders/{id}/reject
     */
    public function rejectOrder(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $result = $this->thawaniService->rejectOrder($storeId, $id, $validated['reason']);

        if (!($result['success'] ?? false)) {
            return $this->error($result['message'] ?? __('thawani.order_action_failed'), 422);
        }

        return $this->success($result['order'], __('thawani.order_rejected'));
    }

    /**
     * PUT /api/v2/thawani/orders/{id}/status
     */
    public function updateOrderStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:preparing,ready,dispatched,completed',
        ]);

        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $result = $this->thawaniService->updateOrderStatus($storeId, $id, $validated['status']);

        if (!($result['success'] ?? false)) {
            return $this->error($result['message'] ?? __('thawani.order_action_failed'), 422);
        }

        return $this->success($result['order'], __('thawani.order_status_updated'));
    }

    // ─── Online Menu Management ────────────────────────────

    /**
     * GET /api/v2/thawani/products
     */
    public function products(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:100',
            'is_published' => 'nullable|boolean',
        ]);

        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $result = $this->thawaniService->getProductsWithMappings($storeId, $request->only(['per_page', 'search', 'is_published']));
        return $this->success($result, __('thawani.products_retrieved'));
    }

    /**
     * PUT /api/v2/thawani/products/{id}/publish
     */
    public function publishProduct(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'is_published' => 'required|boolean',
            'online_price' => 'nullable|numeric|min:0',
            'display_order' => 'nullable|integer|min:0',
        ]);

        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $result = $this->thawaniService->publishProduct(
            $storeId,
            $id,
            (bool) $validated['is_published'],
            isset($validated['online_price']) ? (float) $validated['online_price'] : null,
            $validated['display_order'] ?? null,
        );

        if (!($result['success'] ?? false)) {
            return $this->error($result['message'] ?? __('thawani.sync_failed'), 422);
        }

        return $this->success($result['mapping'], __('thawani.product_updated'));
    }

    /**
     * POST /api/v2/thawani/products/bulk-publish
     */
    public function bulkPublishProducts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'required|string',
            'is_published' => 'required|boolean',
        ]);

        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $result = $this->thawaniService->bulkPublishProducts($storeId, $validated['product_ids'], (bool) $validated['is_published']);

        if (!($result['success'] ?? false)) {
            return $this->error($result['message'] ?? __('thawani.sync_failed'), 422);
        }

        return $this->success(['updated' => $result['updated']], __('thawani.products_bulk_updated'));
    }

    // ─── Store Availability ────────────────────────────────

    /**
     * PUT /api/v2/thawani/store/availability
     */
    public function updateStoreAvailability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'is_open' => 'required|boolean',
            'closed_reason' => 'nullable|string|max:255',
        ]);

        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $result = $this->thawaniService->updateStoreAvailability(
            $storeId,
            (bool) $validated['is_open'],
            $validated['closed_reason'] ?? null,
        );

        if (!($result['success'] ?? false)) {
            return $this->error($result['message'] ?? __('thawani.update_failed'), 422);
        }

        return $this->success($result, __('thawani.store_availability_updated'));
    }

    // ─── Inventory Sync ────────────────────────────────────

    /**
     * POST /api/v2/thawani/inventory/sync
     */
    public function syncInventory(Request $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $result = $this->thawaniService->syncInventory($storeId);

        if (!($result['success'] ?? false)) {
            return $this->error($result['message'] ?? __('thawani.sync_failed'), 422);
        }

        return $this->success(['updated' => $result['updated'] ?? 0], __('thawani.inventory_synced'));
    }

    // ─── Webhook ───────────────────────────────────────────

    /**
     * POST /webhook/thawani/orders
     * Receives incoming orders from the Thawani marketplace.
     * Verified via X-Thawani-Signature HMAC-SHA256 header.
     */
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Thawani-Signature');

        // Identify store by thawani_store_id in payload
        $data = json_decode($payload, true) ?? [];
        $thawaniStoreId = $data['store_id'] ?? null;

        if (!$thawaniStoreId) {
            return $this->error('Missing store_id', 400);
        }

        $config = \App\Domain\ThawaniIntegration\Models\ThawaniStoreConfig::where('thawani_store_id', $thawaniStoreId)->first();

        if (!$config) {
            return $this->error('Store not found', 404);
        }

        // Verify signature if api_secret is set
        if ($signature && $config->api_secret) {
            $expected = hash_hmac('sha256', $payload, $config->api_secret);
            if (!hash_equals($expected, $signature)) {
                return $this->error('Invalid signature', 401);
            }
        }

        $result = $this->thawaniService->ingestOrderFromWebhook($config->store_id, $data);

        if (!($result['success'] ?? false)) {
            return $this->error($result['message'] ?? 'Webhook processing failed', 422);
        }

        return $this->success(['order_id' => $result['order']->id ?? null], 'Webhook processed');
    }
}
