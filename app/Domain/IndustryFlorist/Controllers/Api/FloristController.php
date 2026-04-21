<?php

namespace App\Domain\IndustryFlorist\Controllers\Api;

use App\Domain\IndustryFlorist\Requests\CreateFlowerArrangementRequest;
use App\Domain\IndustryFlorist\Requests\CreateFlowerSubscriptionRequest;
use App\Domain\IndustryFlorist\Requests\UpdateFlowerArrangementRequest;
use App\Domain\IndustryFlorist\Requests\UpdateFlowerSubscriptionRequest;
use App\Domain\IndustryFlorist\Requests\CreateFreshnessLogRequest;
use App\Domain\IndustryFlorist\Resources\FlowerArrangementResource;
use App\Domain\IndustryFlorist\Resources\FlowerFreshnessLogResource;
use App\Domain\IndustryFlorist\Resources\FlowerSubscriptionResource;
use App\Domain\IndustryFlorist\Services\FloristService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FloristController extends BaseApiController
{
    public function __construct(private readonly FloristService $service) {}

    // ── Arrangements ─────────────────────────────────────

    public function listArrangements(Request $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $paginator = $this->service->listArrangements($storeId, $request->only(['is_template', 'occasion', 'search', 'per_page']));

        $data = $paginator->toArray();
        $data['data'] = FlowerArrangementResource::collection($paginator->items())->resolve();

        return $this->success($data, __('industry.arrangements_retrieved'));
    }

    public function createArrangement(CreateFlowerArrangementRequest $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $arrangement = $this->service->createArrangement($storeId, $request->validated());
        return $this->created(new FlowerArrangementResource($arrangement), __('industry.arrangement_created'));
    }

    public function updateArrangement(UpdateFlowerArrangementRequest $request, string $id): JsonResponse
    {
        $arrangement = $this->service->updateArrangement($id, $this->resolvedStoreId($request) ?? $request->user()->store_id, $request->validated());
        return $this->success(new FlowerArrangementResource($arrangement), __('industry.arrangement_updated'));
    }

    public function deleteArrangement(Request $request, string $id): JsonResponse
    {
        $this->service->deleteArrangement($id, $this->resolvedStoreId($request) ?? $request->user()->store_id);
        return $this->success(null, __('industry.arrangement_deleted'));
    }

    // ── Freshness Logs ───────────────────────────────────

    public function listFreshnessLogs(Request $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $paginator = $this->service->listFreshnessLogs($storeId, $request->only(['status', 'product_id', 'per_page']));

        $data = $paginator->toArray();
        $data['data'] = FlowerFreshnessLogResource::collection($paginator->items())->resolve();

        return $this->success($data, __('industry.freshness_logs_retrieved'));
    }

    public function createFreshnessLog(CreateFreshnessLogRequest $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $log = $this->service->createFreshnessLog($storeId, $request->validated());
        return $this->created(new FlowerFreshnessLogResource($log), __('industry.freshness_log_created'));
    }

    public function updateFreshnessLogStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:fresh,marked_down,disposed',
        ]);

        $log = $this->service->updateFreshnessLogStatus($id, $this->resolvedStoreId($request) ?? $request->user()->store_id, $validated['status']);
        return $this->success(new FlowerFreshnessLogResource($log), __('industry.freshness_log_updated'));
    }

    // ── Subscriptions ────────────────────────────────────

    public function listSubscriptions(Request $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $paginator = $this->service->listSubscriptions($storeId, $request->only(['is_active', 'customer_id', 'per_page']));

        $data = $paginator->toArray();
        $data['data'] = FlowerSubscriptionResource::collection($paginator->items())->resolve();

        return $this->success($data, __('industry.subscriptions_retrieved'));
    }

    public function createSubscription(CreateFlowerSubscriptionRequest $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $sub = $this->service->createSubscription($storeId, $request->validated());
        return $this->created(new FlowerSubscriptionResource($sub), __('industry.subscription_created'));
    }

    public function updateSubscription(UpdateFlowerSubscriptionRequest $request, string $id): JsonResponse
    {
        $sub = $this->service->updateSubscription($id, $this->resolvedStoreId($request) ?? $request->user()->store_id, $request->validated());
        return $this->success(new FlowerSubscriptionResource($sub), __('industry.subscription_updated'));
    }

    public function toggleSubscription(Request $request, string $id): JsonResponse
    {
        $sub = $this->service->toggleSubscription($id, $this->resolvedStoreId($request) ?? $request->user()->store_id);
        return $this->success(new FlowerSubscriptionResource($sub), __('industry.subscription_toggled'));
    }
}
