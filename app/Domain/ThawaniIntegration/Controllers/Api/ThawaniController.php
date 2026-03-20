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
