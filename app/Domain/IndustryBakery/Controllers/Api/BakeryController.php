<?php

namespace App\Domain\IndustryBakery\Controllers\Api;

use App\Domain\IndustryBakery\Services\BakeryService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BakeryController extends BaseApiController
{
    public function __construct(private BakeryService $service) {}

    // ── Recipes ──────────────────────────────────────────

    public function listRecipes(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $data = $this->service->listRecipes($storeId, $request->only(['search', 'per_page']));
        return $this->success($data, __('industry.recipes_retrieved'));
    }

    public function createRecipe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'nullable|string',
            'name' => 'required|string|max:255',
            'expected_yield' => 'nullable|integer|min:1',
            'prep_time_minutes' => 'nullable|integer|min:0',
            'bake_time_minutes' => 'nullable|integer|min:0',
            'bake_temperature_c' => 'nullable|integer|min:0',
            'instructions' => 'nullable|string',
        ]);

        $storeId = $request->user()->store_id;
        $recipe = $this->service->createRecipe($storeId, $validated);
        return $this->created($recipe, __('industry.recipe_created'));
    }

    public function updateRecipe(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'expected_yield' => 'nullable|integer|min:1',
            'prep_time_minutes' => 'nullable|integer|min:0',
            'bake_time_minutes' => 'nullable|integer|min:0',
            'bake_temperature_c' => 'nullable|integer|min:0',
            'instructions' => 'nullable|string',
        ]);

        $recipe = $this->service->updateRecipe($id, $validated);
        return $this->success($recipe, __('industry.recipe_updated'));
    }

    public function deleteRecipe(string $id): JsonResponse
    {
        $this->service->deleteRecipe($id);
        return $this->success(null, __('industry.recipe_deleted'));
    }

    // ── Production Schedules ─────────────────────────────

    public function listProductionSchedules(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $data = $this->service->listProductionSchedules($storeId, $request->only(['status', 'schedule_date', 'per_page']));
        return $this->success($data, __('industry.schedules_retrieved'));
    }

    public function createProductionSchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipe_id' => 'required|string',
            'schedule_date' => 'required|date',
            'planned_batches' => 'required|integer|min:1',
            'planned_yield' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $validated['status'] = 'planned';

        $storeId = $request->user()->store_id;
        $schedule = $this->service->createProductionSchedule($storeId, $validated);
        return $this->created($schedule, __('industry.schedule_created'));
    }

    public function updateProductionSchedule(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'actual_batches' => 'nullable|integer|min:0',
            'actual_yield' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        $schedule = $this->service->updateProductionSchedule($id, $validated);
        return $this->success($schedule, __('industry.schedule_updated'));
    }

    public function updateProductionScheduleStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:planned,in_progress,completed',
        ]);

        $schedule = $this->service->updateProductionScheduleStatus($id, $validated['status']);
        return $this->success($schedule, __('industry.schedule_status_updated'));
    }

    // ── Custom Cake Orders ───────────────────────────────

    public function listCustomCakeOrders(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $data = $this->service->listCustomCakeOrders($storeId, $request->only(['status', 'customer_id', 'per_page']));
        return $this->success($data, __('industry.cake_orders_retrieved'));
    }

    public function createCustomCakeOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|string',
            'order_id' => 'nullable|string',
            'description' => 'required|string',
            'size' => 'required|string|max:50',
            'flavor' => 'required|string|max:100',
            'decoration_notes' => 'nullable|string',
            'delivery_date' => 'required|date',
            'delivery_time' => 'nullable|string|max:10',
            'price' => 'required|numeric|min:0',
            'deposit_paid' => 'nullable|numeric|min:0',
            'reference_image_url' => 'nullable|string|max:500',
        ]);

        $validated['status'] = 'ordered';

        $storeId = $request->user()->store_id;
        $order = $this->service->createCustomCakeOrder($storeId, $validated);
        return $this->created($order, __('industry.cake_order_created'));
    }

    public function updateCustomCakeOrder(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'decoration_notes' => 'nullable|string',
            'delivery_date' => 'nullable|date',
            'delivery_time' => 'nullable|string|max:10',
            'price' => 'nullable|numeric|min:0',
            'deposit_paid' => 'nullable|numeric|min:0',
        ]);

        $order = $this->service->updateCustomCakeOrder($id, $validated);
        return $this->success($order, __('industry.cake_order_updated'));
    }

    public function updateCustomCakeOrderStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:ordered,in_production,ready,delivered',
        ]);

        $order = $this->service->updateCustomCakeOrderStatus($id, $validated['status']);
        return $this->success($order, __('industry.cake_order_status_updated'));
    }
}
