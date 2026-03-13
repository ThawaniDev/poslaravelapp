<?php

namespace App\Domain\Inventory\Controllers\Api;

use App\Domain\Inventory\Requests\CreateGoodsReceiptRequest;
use App\Domain\Inventory\Resources\GoodsReceiptResource;
use App\Domain\Inventory\Services\GoodsReceiptService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoodsReceiptController extends BaseApiController
{
    public function __construct(
        private readonly GoodsReceiptService $goodsReceiptService,
    ) {}

    /**
     * GET /api/v2/inventory/goods-receipts
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => 'required|uuid',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $paginator = $this->goodsReceiptService->list(
            storeId: $request->input('store_id'),
            perPage: $request->integer('per_page', 25),
        );

        $data = $paginator->toArray();
        $data['data'] = GoodsReceiptResource::collection($paginator->items())->resolve();

        return $this->success($data);
    }

    /**
     * POST /api/v2/inventory/goods-receipts
     */
    public function store(CreateGoodsReceiptRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $receipt = $this->goodsReceiptService->create(
            array_merge($validated, ['received_by' => $request->user()->id]),
            $validated['items'],
        );

        return $this->created(new GoodsReceiptResource($receipt));
    }

    /**
     * GET /api/v2/inventory/goods-receipts/{id}
     */
    public function show(string $goodsReceipt): JsonResponse
    {
        $receipt = $this->goodsReceiptService->find($goodsReceipt);

        return $this->success(new GoodsReceiptResource($receipt));
    }

    /**
     * POST /api/v2/inventory/goods-receipts/{id}/confirm
     */
    public function confirm(Request $request, string $goodsReceipt): JsonResponse
    {
        try {
            $receipt = $this->goodsReceiptService->confirm($goodsReceipt, $request->user()->id);

            return $this->success(new GoodsReceiptResource($receipt), 'Goods receipt confirmed.');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
