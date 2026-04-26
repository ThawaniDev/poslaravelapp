<?php

namespace App\Domain\IndustryJewelry\Services;

use App\Domain\IndustryJewelry\Models\DailyMetalRate;
use App\Domain\IndustryJewelry\Models\JewelryProductDetail;
use App\Domain\IndustryJewelry\Models\BuybackTransaction;

class JewelryService
{
    public function listMetalRates(string $storeId, array $filters = [])
    {
        $query = DailyMetalRate::where('store_id', $storeId);

        if (! empty($filters['metal_type'])) {
            $query->where('metal_type', $filters['metal_type']);
        }

        return $query->orderByDesc('effective_date')->paginate($filters['per_page'] ?? 15);
    }

    public function upsertMetalRate(string $storeId, array $data): DailyMetalRate
    {
        return DailyMetalRate::updateOrCreate(
            [
                'store_id' => $storeId,
                'metal_type' => $data['metal_type'],
                'karat' => $data['karat'],
                'effective_date' => $data['effective_date'],
            ],
            array_merge($data, ['store_id' => $storeId])
        );
    }

    public function listProductDetails(array $filters = [])
    {
        $query = JewelryProductDetail::query();

        if (! empty($filters['metal_type'])) {
            $query->where('metal_type', $filters['metal_type']);
        }
        if (! empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 15);
    }

    public function createProductDetail(array $data): JewelryProductDetail
    {
        return JewelryProductDetail::create($data);
    }

    public function updateProductDetail(string $storeId, string $id, array $data): JewelryProductDetail
    {
        $detail = JewelryProductDetail::findOrFail($id);
        $detail->update($data);
        return $detail->fresh();
    }

    public function listBuybacks(string $storeId, array $filters = [])
    {
        $query = BuybackTransaction::where('store_id', $storeId);

        if (! empty($filters['metal_type'])) {
            $query->where('metal_type', $filters['metal_type']);
        }
        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 15);
    }

    public function createBuyback(string $storeId, array $data): BuybackTransaction
    {
        return BuybackTransaction::create(array_merge($data, ['store_id' => $storeId]));
    }
}
