<?php

namespace App\Domain\IndustryBakery\Services;

use App\Domain\IndustryBakery\Models\BakeryRecipe;
use App\Domain\IndustryBakery\Models\ProductionSchedule;
use App\Domain\IndustryBakery\Models\CustomCakeOrder;

class BakeryService
{
    public function listRecipes(string $storeId, array $filters = [])
    {
        $query = BakeryRecipe::where('store_id', $storeId);

        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }

        return $query->orderBy('name')->paginate($filters['per_page'] ?? 15);
    }

    public function createRecipe(string $storeId, array $data): BakeryRecipe
    {
        return BakeryRecipe::create(array_merge($data, ['store_id' => $storeId]));
    }

    public function updateRecipe(string $id, array $data): BakeryRecipe
    {
        $recipe = BakeryRecipe::findOrFail($id);
        $recipe->update($data);
        return $recipe->fresh();
    }

    public function deleteRecipe(string $id): void
    {
        BakeryRecipe::findOrFail($id)->delete();
    }

    public function listProductionSchedules(string $storeId, array $filters = [])
    {
        $query = ProductionSchedule::where('store_id', $storeId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['schedule_date'])) {
            $query->where('schedule_date', $filters['schedule_date']);
        }

        return $query->orderByDesc('schedule_date')->paginate($filters['per_page'] ?? 15);
    }

    public function createProductionSchedule(string $storeId, array $data): ProductionSchedule
    {
        return ProductionSchedule::create(array_merge($data, ['store_id' => $storeId]));
    }

    public function updateProductionSchedule(string $id, array $data): ProductionSchedule
    {
        $schedule = ProductionSchedule::findOrFail($id);
        $schedule->update($data);
        return $schedule->fresh();
    }

    public function updateProductionScheduleStatus(string $id, string $status): ProductionSchedule
    {
        $schedule = ProductionSchedule::findOrFail($id);
        $schedule->update(['status' => $status]);
        return $schedule->fresh();
    }

    public function listCustomCakeOrders(string $storeId, array $filters = [])
    {
        $query = CustomCakeOrder::where('store_id', $storeId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        return $query->orderByDesc('delivery_date')->paginate($filters['per_page'] ?? 15);
    }

    public function createCustomCakeOrder(string $storeId, array $data): CustomCakeOrder
    {
        return CustomCakeOrder::create(array_merge($data, ['store_id' => $storeId]));
    }

    public function updateCustomCakeOrder(string $id, array $data): CustomCakeOrder
    {
        $order = CustomCakeOrder::findOrFail($id);
        $order->update($data);
        return $order->fresh();
    }

    public function updateCustomCakeOrderStatus(string $id, string $status): CustomCakeOrder
    {
        $order = CustomCakeOrder::findOrFail($id);
        $order->update(['status' => $status]);
        return $order->fresh();
    }
}
