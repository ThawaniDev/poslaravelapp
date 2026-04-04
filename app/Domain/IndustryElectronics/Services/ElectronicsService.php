<?php

namespace App\Domain\IndustryElectronics\Services;

use App\Domain\IndustryElectronics\Models\DeviceImeiRecord;
use App\Domain\IndustryElectronics\Models\RepairJob;
use App\Domain\IndustryElectronics\Models\TradeInRecord;
use Illuminate\Support\Facades\DB;

class ElectronicsService
{
    public function listImeiRecords(string $storeId, array $filters = [])
    {
        $query = DeviceImeiRecord::where('store_id', $storeId);
        $like = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters, $like) {
                $q->where('imei', $like, '%' . $filters['search'] . '%')
                  ->orWhere('serial_number', $like, '%' . $filters['search'] . '%');
            });
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 15);
    }

    public function createImeiRecord(string $storeId, array $data): DeviceImeiRecord
    {
        return DeviceImeiRecord::create(array_merge(['status' => 'in_stock'], $data, ['store_id' => $storeId]));
    }

    public function updateImeiRecord(string $id, string $storeId, array $data): DeviceImeiRecord
    {
        $record = DeviceImeiRecord::where('store_id', $storeId)->findOrFail($id);
        $record->update($data);
        return $record->fresh();
    }

    public function listRepairJobs(string $storeId, array $filters = [])
    {
        $query = RepairJob::where('store_id', $storeId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['search'])) {
            $like = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where('device_description', $like, '%' . $filters['search'] . '%');
        }

        return $query->orderByDesc('received_at')->paginate($filters['per_page'] ?? 15);
    }

    public function createRepairJob(string $storeId, array $data): RepairJob
    {
        $data['received_at'] = now();
        $data['status'] = 'received';
        return RepairJob::create(array_merge($data, ['store_id' => $storeId]));
    }

    public function updateRepairJob(string $id, string $storeId, array $data): RepairJob
    {
        $job = RepairJob::where('store_id', $storeId)->findOrFail($id);
        $job->update($data);
        return $job->fresh();
    }

    public function updateRepairJobStatus(string $id, string $storeId, string $status): RepairJob
    {
        $job = RepairJob::where('store_id', $storeId)->findOrFail($id);
        $updateData = ['status' => $status];
        if ($status === 'collected') {
            $updateData['collected_at'] = now();
        }
        if (in_array($status, ['ready', 'collected'])) {
            $updateData['completed_at'] = $updateData['completed_at'] ?? now();
        }
        $job->update($updateData);
        return $job->fresh();
    }

    public function listTradeIns(string $storeId, array $filters = [])
    {
        $query = TradeInRecord::where('store_id', $storeId);

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }
        if (! empty($filters['search'])) {
            $like = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where('device_description', $like, '%' . $filters['search'] . '%');
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 15);
    }

    public function createTradeIn(string $storeId, array $data): TradeInRecord
    {
        return TradeInRecord::create(array_merge($data, ['store_id' => $storeId]));
    }
}
