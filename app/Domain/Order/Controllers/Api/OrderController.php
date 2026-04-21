<?php

namespace App\Domain\Order\Controllers\Api;

use App\Domain\Order\Requests\CreateOrderRequest;
use App\Domain\Order\Requests\CreateReturnRequest;
use App\Domain\Order\Resources\OrderResource;
use App\Domain\Order\Resources\SaleReturnResource;
use App\Domain\Order\Services\OrderService;
use App\Domain\Order\Services\ReturnService;
use App\Domain\Subscription\Traits\TracksSubscriptionUsage;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends BaseApiController
{
    use TracksSubscriptionUsage;
    public function __construct(
        private readonly OrderService $orderService,
        private readonly ReturnService $returnService,
    ) {}

    // ─── Orders ──────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->orderService->list(
            $this->resolvedStoreIds($request),
            $request->only(['status', 'source', 'search']),
            (int) $request->get('per_page', 20),
        );

        $result = $paginator->toArray();
        $result['data'] = OrderResource::collection($paginator->items())->resolve();
        return $this->success($result);
    }

    public function store(CreateOrderRequest $request): JsonResponse
    {
        // Check transaction limit before creating order
        $orgId = $this->resolveOrganizationId($request);
        if ($orgId) {
            $limitResponse = $this->checkLimitOrFail($orgId, 'transactions_per_month');
            if ($limitResponse) {
                return $limitResponse;
            }
        }

        try {
            $order = $this->orderService->create(
                $request->validated(),
                $request->user(),
            );

            // Refresh transaction usage snapshot after creation
            if ($orgId) {
                $this->refreshUsageFor($orgId, 'transactions_per_month');
            }

            return $this->created(new OrderResource($order));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function show(string $order): JsonResponse
    {
        $found = $this->orderService->find($order);
        return $this->success(new OrderResource($found));
    }

    public function updateStatus(Request $request, string $order): JsonResponse
    {
        try {
            $request->validate(['status' => 'required|string', 'notes' => 'nullable|string']);
            $found = $this->orderService->find($order);
            $updated = $this->orderService->updateStatus(
                $found,
                $request->input('status'),
                $request->user(),
                $request->input('notes'),
            );
            return $this->success(new OrderResource($updated));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function void(Request $request, string $order): JsonResponse
    {
        try {
            $found = $this->orderService->find($order);
            $voided = $this->orderService->void(
                $found,
                $request->user(),
                $request->input('notes'),
            );
            return $this->success(new OrderResource($voided));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    // ─── Returns ─────────────────────────────────────────────

    public function returns(Request $request): JsonResponse
    {
        $paginator = $this->returnService->listReturns(
            $this->resolvedStoreIds($request),
            (int) $request->get('per_page', 20),
        );

        $result = $paginator->toArray();
        $result['data'] = SaleReturnResource::collection($paginator->items())->resolve();
        return $this->success($result);
    }

    public function createReturn(CreateReturnRequest $request, string $order): JsonResponse
    {
        try {
            $found = $this->orderService->find($order);
            $return = $this->returnService->createReturn(
                $found,
                $request->validated(),
                $request->user(),
            );
            return $this->created(new SaleReturnResource($return));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function showReturn(string $returnId): JsonResponse
    {
        $found = $this->returnService->find($returnId);
        return $this->success(new SaleReturnResource($found));
    }
}
