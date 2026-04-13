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
        $config = $this->thawaniService->getConfig($request->user()->store_id);
        return $this->success($config, __('thawani.config_retrieved'));
    }

    /**
     * POST /api/v2/thawani/config
     */
    public function saveConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'thawani_store_id' => 'nullable|string|max:255',
            'is_connected' => 'nullable|boolean',
            'auto_sync_products' => 'nullable|boolean',
            'auto_sync_inventory' => 'nullable|boolean',
            'auto_accept_orders' => 'nullable|boolean',
            'operating_hours' => 'nullable|array',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $config = $this->thawaniService->saveConfig($request->user()->store_id, $validated);
        return $this->success($config, __('thawani.config_saved'));
    }

    /**
     * PUT /api/v2/thawani/disconnect
     */
    public function disconnect(Request $request): JsonResponse
    {
        $result = $this->thawaniService->disconnect($request->user()->store_id);

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
        $result = $this->thawaniService->testConnection($request->user()->store_id);

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
            $request->user()->store_id,
            $request->only(['status', 'per_page']),
        );

        return $this->success($orders, __('thawani.orders_retrieved'));
    }

    /**
     * GET /api/v2/thawani/product-mappings
     */
    public function productMappings(Request $request): JsonResponse
    {
        $mappings = $this->thawaniService->getProductMappings($request->user()->store_id);
        return $this->success($mappings, __('thawani.product_mappings_retrieved'));
    }

    /**
     * POST /api/v2/thawani/push-products
     */
    public function pushProducts(Request $request): JsonResponse
    {
        $result = $this->thawaniService->pushProductsToThawani($request->user()->store_id);

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
        $result = $this->thawaniService->pullProductsFromThawani($request->user()->store_id);

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
        $mappings = $this->thawaniService->getCategoryMappings($request->user()->store_id);
        return $this->success($mappings, __('thawani.category_mappings_retrieved'));
    }

    /**
     * POST /api/v2/thawani/push-categories
     */
    public function pushCategories(Request $request): JsonResponse
    {
        $result = $this->thawaniService->pushCategoriesToThawani($request->user()->store_id);

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
        $result = $this->thawaniService->pullCategoriesFromThawani($request->user()->store_id);

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
            $request->user()->store_id,
            $request->only(['per_page', 'entity_type', 'status']),
        );

        return $this->success($logs, __('thawani.sync_logs_retrieved'));
    }

    /**
     * GET /api/v2/thawani/queue-stats
     */
    public function queueStats(Request $request): JsonResponse
    {
        $stats = $this->thawaniService->getQueueStats($request->user()->store_id);
        return $this->success($stats, __('thawani.queue_stats_retrieved'));
    }

    /**
     * POST /api/v2/thawani/process-queue
     */
    public function processQueue(Request $request): JsonResponse
    {
        $result = $this->thawaniService->processQueue($request->user()->store_id);
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
            $request->user()->store_id,
            $request->only(['per_page']),
        );

        return $this->success($settlements, __('thawani.settlements_retrieved'));
    }

    /**
     * GET /api/v2/thawani/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $stats = $this->thawaniService->getStats($request->user()->store_id);
        return $this->success($stats, __('thawani.stats_retrieved'));
    }
}
