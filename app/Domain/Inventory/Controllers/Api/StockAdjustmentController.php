<?php

namespace App\Domain\Inventory\Controllers\Api;

use App\Domain\Inventory\Requests\CreateStockAdjustmentRequest;
use App\Domain\Inventory\Resources\StockAdjustmentResource;
use App\Domain\Inventory\Services\StockAdjustmentService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockAdjustmentController extends BaseApiController
{
    public function __construct(
        private readonly StockAdjustmentService $stockAdjustmentService,
    ) {}

    /**
     * GET /api/v2/inventory/stock-adjustments
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => 'required|uuid',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $paginator = $this->stockAdjustmentService->list(
            storeId: $request->input('store_id'),
            perPage: $request->integer('per_page', 25),
        );

        $data = $paginator->toArray();
        $data['data'] = StockAdjustmentResource::collection($paginator->items())->resolve();

        return $this->success($data);
    }

    /**
     * POST /api/v2/inventory/stock-adjustments
     */
    public function store(CreateStockAdjustmentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $adjustment = $this->stockAdjustmentService->create(
            array_merge($validated, ['adjusted_by' => $request->user()->id]),
            $validated['items'],
        );

        return $this->created(new StockAdjustmentResource($adjustment));
    }

    /**
     * GET /api/v2/inventory/stock-adjustments/{id}
     */
    public function show(Request $request, string $stockAdjustment): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $adjustment = $this->stockAdjustmentService->find($storeId, $stockAdjustment);

        return $this->success(new StockAdjustmentResource($adjustment));
    }
}
