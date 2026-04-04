<?php

namespace App\Domain\IndustryFlorist\Services;

use App\Domain\IndustryFlorist\Models\FlowerArrangement;
use App\Domain\IndustryFlorist\Models\FlowerFreshnessLog;
use App\Domain\IndustryFlorist\Models\FlowerSubscription;

class FloristService
{
    public function listArrangements(string $storeId, array $filters = [])
    {
        $query = FlowerArrangement::where('store_id', $storeId);

        if (isset($filters['is_template'])) {
            $query->where('is_template', $filters['is_template']);
        }
        if (! empty($filters['occasion'])) {
            $query->where('occasion', $filters['occasion']);
        }
        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('occasion', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 15);
    }

    public function createArrangement(string $storeId, array $data): FlowerArrangement
    {
        return FlowerArrangement::create(array_merge($data, ['store_id' => $storeId]));
    }

    public function updateArrangement(string $id, string $storeId, array $data): FlowerArrangement
    {
        $arrangement = FlowerArrangement::where('store_id', $storeId)->findOrFail($id);
        $arrangement->update($data);
        return $arrangement->fresh();
    }

    public function deleteArrangement(string $id, string $storeId): void
    {
        FlowerArrangement::where('store_id', $storeId)->findOrFail($id)->delete();
    }

    public function listFreshnessLogs(string $storeId, array $filters = [])
    {
        $query = FlowerFreshnessLog::where('store_id', $storeId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        return $query->orderByDesc('received_date')->paginate($filters['per_page'] ?? 15);
    }

    public function createFreshnessLog(string $storeId, array $data): FlowerFreshnessLog
    {
        return FlowerFreshnessLog::create(array_merge($data, ['store_id' => $storeId]));
    }

    public function updateFreshnessLogStatus(string $id, string $storeId, string $status): FlowerFreshnessLog
    {
        $log = FlowerFreshnessLog::where('store_id', $storeId)->findOrFail($id);
        $updateData = ['status' => $status];
        if ($status === 'marked_down') {
            $updateData['markdown_date'] = now()->toDateString();
        }
        if ($status === 'disposed') {
            $updateData['dispose_date'] = now()->toDateString();
        }
        $log->update($updateData);
        return $log->fresh();
    }

    public function listSubscriptions(string $storeId, array $filters = [])
    {
        $query = FlowerSubscription::where('store_id', $storeId);

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }
        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 15);
    }

    public function createSubscription(string $storeId, array $data): FlowerSubscription
    {
        return FlowerSubscription::create(array_merge($data, ['store_id' => $storeId]));
    }

    public function updateSubscription(string $id, string $storeId, array $data): FlowerSubscription
    {
        $sub = FlowerSubscription::where('store_id', $storeId)->findOrFail($id);
        $sub->update($data);
        return $sub->fresh();
    }

    public function toggleSubscription(string $id, string $storeId): FlowerSubscription
    {
        $sub = FlowerSubscription::where('store_id', $storeId)->findOrFail($id);
        $sub->update(['is_active' => ! $sub->is_active]);
        return $sub->fresh();
    }
}
