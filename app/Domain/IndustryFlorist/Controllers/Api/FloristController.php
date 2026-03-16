<?php

namespace App\Domain\IndustryFlorist\Controllers\Api;

use App\Domain\IndustryFlorist\Services\FloristService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FloristController extends BaseApiController
{
    public function __construct(private FloristService $service) {}

    // ── Arrangements ─────────────────────────────────────

    public function listArrangements(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $data = $this->service->listArrangements($storeId, $request->only(['is_template', 'search', 'per_page']));
        return $this->success($data, __('industry.arrangements_retrieved'));
    }

    public function createArrangement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'occasion' => 'nullable|string|max:100',
            'items_json' => 'required|array',
            'total_price' => 'required|numeric|min:0',
            'is_template' => 'nullable|boolean',
        ]);

        $storeId = $request->user()->store_id;
        $arrangement = $this->service->createArrangement($storeId, $validated);
        return $this->created($arrangement, __('industry.arrangement_created'));
    }

    public function updateArrangement(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'occasion' => 'nullable|string|max:100',
            'items_json' => 'nullable|array',
            'total_price' => 'nullable|numeric|min:0',
            'is_template' => 'nullable|boolean',
        ]);

        $arrangement = $this->service->updateArrangement($id, $validated);
        return $this->success($arrangement, __('industry.arrangement_updated'));
    }

    public function deleteArrangement(string $id): JsonResponse
    {
        $this->service->deleteArrangement($id);
        return $this->success(null, __('industry.arrangement_deleted'));
    }

    // ── Freshness Logs ───────────────────────────────────

    public function listFreshnessLogs(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $data = $this->service->listFreshnessLogs($storeId, $request->only(['status', 'product_id', 'per_page']));
        return $this->success($data, __('industry.freshness_logs_retrieved'));
    }

    public function createFreshnessLog(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|string',
            'received_date' => 'required|date',
            'expected_vase_life_days' => 'required|integer|min:1',
            'quantity' => 'required|integer|min:1',
            'status' => 'nullable|string|in:fresh,marked_down,disposed',
        ]);

        $validated['status'] = $validated['status'] ?? 'fresh';

        $storeId = $request->user()->store_id;
        $log = $this->service->createFreshnessLog($storeId, $validated);
        return $this->created($log, __('industry.freshness_log_created'));
    }

    public function updateFreshnessLogStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:fresh,marked_down,disposed',
        ]);

        $log = $this->service->updateFreshnessLogStatus($id, $validated['status']);
        return $this->success($log, __('industry.freshness_log_updated'));
    }

    // ── Subscriptions ────────────────────────────────────

    public function listSubscriptions(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $data = $this->service->listSubscriptions($storeId, $request->only(['is_active', 'customer_id', 'per_page']));
        return $this->success($data, __('industry.subscriptions_retrieved'));
    }

    public function createSubscription(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|string',
            'arrangement_template_id' => 'nullable|string',
            'frequency' => 'required|string|in:weekly,biweekly,monthly',
            'delivery_day' => 'required|string|max:20',
            'delivery_address' => 'required|string|max:500',
            'price_per_delivery' => 'required|numeric|min:0',
            'next_delivery_date' => 'required|date',
        ]);

        $validated['is_active'] = true;

        $storeId = $request->user()->store_id;
        $sub = $this->service->createSubscription($storeId, $validated);
        return $this->created($sub, __('industry.subscription_created'));
    }

    public function updateSubscription(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'frequency' => 'nullable|string|in:weekly,biweekly,monthly',
            'delivery_day' => 'nullable|string|max:20',
            'delivery_address' => 'nullable|string|max:500',
            'price_per_delivery' => 'nullable|numeric|min:0',
            'next_delivery_date' => 'nullable|date',
        ]);

        $sub = $this->service->updateSubscription($id, $validated);
        return $this->success($sub, __('industry.subscription_updated'));
    }

    public function toggleSubscription(string $id): JsonResponse
    {
        $sub = $this->service->toggleSubscription($id);
        return $this->success($sub, __('industry.subscription_toggled'));
    }
}
