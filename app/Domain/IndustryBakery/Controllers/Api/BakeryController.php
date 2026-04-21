<?php

namespace App\Domain\IndustryBakery\Controllers\Api;

use App\Domain\IndustryBakery\Requests\CreateBakeryRecipeRequest;
use App\Domain\IndustryBakery\Requests\CreateCustomCakeOrderRequest;
use App\Domain\IndustryBakery\Requests\CreateProductionScheduleRequest;
use App\Domain\IndustryBakery\Requests\UpdateBakeryRecipeRequest;
use App\Domain\IndustryBakery\Requests\UpdateCustomCakeOrderRequest;
use App\Domain\IndustryBakery\Requests\UpdateProductionScheduleRequest;
use App\Domain\IndustryBakery\Resources\BakeryRecipeResource;
use App\Domain\IndustryBakery\Resources\CustomCakeOrderResource;
use App\Domain\IndustryBakery\Resources\ProductionScheduleResource;
use App\Domain\IndustryBakery\Services\BakeryService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BakeryController extends BaseApiController
{
    public function __construct(private readonly BakeryService $service) {}

    // ── Recipes ──────────────────────────────────────────

    public function listRecipes(Request $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $paginator = $this->service->listRecipes($storeId, $request->only(['search', 'per_page']));

        $data = $paginator->toArray();
        $data['data'] = BakeryRecipeResource::collection($paginator->items())->resolve();

        return $this->success($data, __('industry.recipes_retrieved'));
    }

    public function createRecipe(CreateBakeryRecipeRequest $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $recipe = $this->service->createRecipe($storeId, $request->validated());
        return $this->created(new BakeryRecipeResource($recipe), __('industry.recipe_created'));
    }

    public function updateRecipe(UpdateBakeryRecipeRequest $request, string $id): JsonResponse
    {
        $recipe = $this->service->updateRecipe($id, $this->resolvedStoreId($request) ?? $request->user()->store_id, $request->validated());
        return $this->success(new BakeryRecipeResource($recipe), __('industry.recipe_updated'));
    }

    public function deleteRecipe(Request $request, string $id): JsonResponse
    {
        $this->service->deleteRecipe($id, $this->resolvedStoreId($request) ?? $request->user()->store_id);
        return $this->success(null, __('industry.recipe_deleted'));
    }

    // ── Production Schedules ─────────────────────────────

    public function listProductionSchedules(Request $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $paginator = $this->service->listProductionSchedules($storeId, $request->only(['status', 'schedule_date', 'per_page']));

        $data = $paginator->toArray();
        $data['data'] = ProductionScheduleResource::collection($paginator->items())->resolve();

        return $this->success($data, __('industry.schedules_retrieved'));
    }

    public function createProductionSchedule(CreateProductionScheduleRequest $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $schedule = $this->service->createProductionSchedule($storeId, $request->validated());
        return $this->created(new ProductionScheduleResource($schedule), __('industry.schedule_created'));
    }

    public function updateProductionSchedule(UpdateProductionScheduleRequest $request, string $id): JsonResponse
    {
        $schedule = $this->service->updateProductionSchedule($id, $this->resolvedStoreId($request) ?? $request->user()->store_id, $request->validated());
        return $this->success(new ProductionScheduleResource($schedule), __('industry.schedule_updated'));
    }

    public function updateProductionScheduleStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:planned,in_progress,completed',
        ]);

        $schedule = $this->service->updateProductionScheduleStatus($id, $this->resolvedStoreId($request) ?? $request->user()->store_id, $validated['status']);
        return $this->success(new ProductionScheduleResource($schedule), __('industry.schedule_status_updated'));
    }

    // ── Custom Cake Orders ───────────────────────────────

    public function listCustomCakeOrders(Request $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $paginator = $this->service->listCustomCakeOrders($storeId, $request->only(['status', 'customer_id', 'per_page']));

        $data = $paginator->toArray();
        $data['data'] = CustomCakeOrderResource::collection($paginator->items())->resolve();

        return $this->success($data, __('industry.cake_orders_retrieved'));
    }

    public function createCustomCakeOrder(CreateCustomCakeOrderRequest $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $order = $this->service->createCustomCakeOrder($storeId, $request->validated());
        return $this->created(new CustomCakeOrderResource($order), __('industry.cake_order_created'));
    }

    public function updateCustomCakeOrder(UpdateCustomCakeOrderRequest $request, string $id): JsonResponse
    {
        $order = $this->service->updateCustomCakeOrder($id, $this->resolvedStoreId($request) ?? $request->user()->store_id, $request->validated());
        return $this->success(new CustomCakeOrderResource($order), __('industry.cake_order_updated'));
    }

    public function updateCustomCakeOrderStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:ordered,in_production,ready,delivered',
        ]);

        $order = $this->service->updateCustomCakeOrderStatus($id, $this->resolvedStoreId($request) ?? $request->user()->store_id, $validated['status']);
        return $this->success(new CustomCakeOrderResource($order), __('industry.cake_order_status_updated'));
    }
}
