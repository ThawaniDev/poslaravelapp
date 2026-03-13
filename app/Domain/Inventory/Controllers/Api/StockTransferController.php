<?php

namespace App\Domain\Inventory\Controllers\Api;

use App\Domain\Inventory\Requests\CreateStockTransferRequest;
use App\Domain\Inventory\Requests\ReceiveTransferRequest;
use App\Domain\Inventory\Resources\StockTransferResource;
use App\Domain\Inventory\Services\StockTransferService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockTransferController extends BaseApiController
{
    public function __construct(
        private readonly StockTransferService $stockTransferService,
    ) {}

    /**
     * GET /api/v2/inventory/stock-transfers
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $paginator = $this->stockTransferService->list(
            organizationId: $request->user()->organization_id,
            perPage: $request->integer('per_page', 25),
        );

        $data = $paginator->toArray();
        $data['data'] = StockTransferResource::collection($paginator->items())->resolve();

        return $this->success($data);
    }

    /**
     * POST /api/v2/inventory/stock-transfers
     */
    public function store(CreateStockTransferRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $transfer = $this->stockTransferService->create(
            array_merge($validated, [
                'organization_id' => $request->user()->organization_id,
                'created_by' => $request->user()->id,
            ]),
            $validated['items'],
        );

        return $this->created(new StockTransferResource($transfer));
    }

    /**
     * GET /api/v2/inventory/stock-transfers/{id}
     */
    public function show(string $stockTransfer): JsonResponse
    {
        $transfer = $this->stockTransferService->find($stockTransfer);

        return $this->success(new StockTransferResource($transfer));
    }

    /**
     * POST /api/v2/inventory/stock-transfers/{id}/approve
     */
    public function approve(Request $request, string $stockTransfer): JsonResponse
    {
        try {
            $transfer = $this->stockTransferService->approve($stockTransfer, $request->user()->id);

            return $this->success(new StockTransferResource($transfer), 'Transfer approved.');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/v2/inventory/stock-transfers/{id}/receive
     */
    public function receive(ReceiveTransferRequest $request, string $stockTransfer): JsonResponse
    {
        try {
            $transfer = $this->stockTransferService->receive(
                $stockTransfer,
                $request->user()->id,
                $request->validated()['items'] ?? [],
            );

            return $this->success(new StockTransferResource($transfer), 'Transfer received.');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/v2/inventory/stock-transfers/{id}/cancel
     */
    public function cancel(string $stockTransfer): JsonResponse
    {
        try {
            $transfer = $this->stockTransferService->cancel($stockTransfer);

            return $this->success(new StockTransferResource($transfer), 'Transfer cancelled.');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
