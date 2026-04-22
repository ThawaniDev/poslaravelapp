<?php

namespace App\Domain\Inventory\Controllers\Api;

use App\Domain\Inventory\Requests\CreatePurchaseOrderRequest;
use App\Domain\Inventory\Requests\ReceivePurchaseOrderRequest;
use App\Domain\Inventory\Resources\PurchaseOrderResource;
use App\Domain\Inventory\Services\PurchaseOrderService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends BaseApiController
{
    public function __construct(
        private readonly PurchaseOrderService $purchaseOrderService,
    ) {}

    /**
     * GET /api/v2/inventory/purchase-orders
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => 'required|uuid',
            'status' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $paginator = $this->purchaseOrderService->list(
            storeId: $request->input('store_id'),
            perPage: $request->integer('per_page', 25),
            status: $request->input('status'),
        );

        $data = $paginator->toArray();
        $data['data'] = PurchaseOrderResource::collection($paginator->items())->resolve();

        return $this->success($data);
    }

    /**
     * POST /api/v2/inventory/purchase-orders
     */
    public function store(CreatePurchaseOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $po = $this->purchaseOrderService->create(
            array_merge($validated, [
                'organization_id' => $request->user()->organization_id,
                'created_by' => $request->user()->id,
            ]),
            $validated['items'],
        );

        return $this->created(new PurchaseOrderResource($po));
    }

    /**
     * GET /api/v2/inventory/purchase-orders/{id}
     */
public function show(Request $request, string $purchaseOrder): JsonResponse
{
    $po = $this->purchaseOrderService->find($purchaseOrder, $this->resolvedStoreId($request) ?? $request->user()->store_id);

        return $this->success(new PurchaseOrderResource($po));
    }

    /**
     * POST /api/v2/inventory/purchase-orders/{id}/send
     */
public function send(Request $request, string $purchaseOrder): JsonResponse
{
    try {
        $po = $this->purchaseOrderService->send($purchaseOrder, $this->resolvedStoreId($request) ?? $request->user()->store_id);

            return $this->success(new PurchaseOrderResource($po), 'Purchase order sent.');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/v2/inventory/purchase-orders/{id}/receive
     */
    public function receive(ReceivePurchaseOrderRequest $request, string $purchaseOrder): JsonResponse
    {
        try {
            // Idempotency: prefer client-supplied header, otherwise derive a
            // deterministic key from the request body so an accidental retry
            // of the same payload doesn't double-credit stock.
            $idempotencyKey = $request->header('Idempotency-Key')
                ?: substr(hash('sha256', $purchaseOrder . ':' . json_encode($request->validated()['items'])), 0, 64);

            $po = $this->purchaseOrderService->receive(
                $purchaseOrder,
                $this->resolvedStoreId($request) ?? $request->user()->store_id,
                $request->validated()['items'],
                $idempotencyKey,
            );

            return $this->success(new PurchaseOrderResource($po), 'Purchase order received.');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/v2/inventory/purchase-orders/{id}/cancel
     */
public function cancel(Request $request, string $purchaseOrder): JsonResponse
{
    try {
        $po = $this->purchaseOrderService->cancel($purchaseOrder, $this->resolvedStoreId($request) ?? $request->user()->store_id);

            return $this->success(new PurchaseOrderResource($po), 'Purchase order cancelled.');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
