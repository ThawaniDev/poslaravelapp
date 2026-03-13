<?php

namespace App\Domain\Inventory\Controllers\Api;

use App\Domain\Inventory\Resources\StockLevelResource;
use App\Domain\Inventory\Resources\StockMovementResource;
use App\Domain\Inventory\Services\StockService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends BaseApiController
{
    public function __construct(
        private readonly StockService $stockService,
    ) {}

    /**
     * GET /api/v2/inventory/stock-levels
     */
    public function levels(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => 'required|uuid',
            'product_id' => 'nullable|uuid',
            'low_stock' => 'nullable|boolean',
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $paginator = $this->stockService->levels(
            storeId: $request->input('store_id'),
            filters: $request->only(['product_id', 'low_stock', 'search']),
            perPage: $request->integer('per_page', 25),
        );

        $data = $paginator->toArray();
        $data['data'] = StockLevelResource::collection($paginator->items())->resolve();

        return $this->success($data);
    }

    /**
     * GET /api/v2/inventory/stock-movements
     */
    public function movements(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => 'required|uuid',
            'product_id' => 'nullable|uuid',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $paginator = $this->stockService->movements(
            storeId: $request->input('store_id'),
            productId: $request->input('product_id'),
            perPage: $request->integer('per_page', 25),
        );

        $data = $paginator->toArray();
        $data['data'] = StockMovementResource::collection($paginator->items())->resolve();

        return $this->success($data);
    }

    /**
     * PUT /api/v2/inventory/stock-levels/{stockLevel}/reorder-point
     */
    public function setReorderPoint(Request $request, string $stockLevel): JsonResponse
    {
        $request->validate([
            'reorder_point' => 'required|numeric|min:0',
            'max_stock_level' => 'nullable|numeric|min:0',
        ]);

        $level = $this->stockService->setReorderPoint(
            $stockLevel,
            (float) $request->input('reorder_point'),
            $request->input('max_stock_level') !== null ? (float) $request->input('max_stock_level') : null,
        );

        return $this->success(new StockLevelResource($level));
    }
}
