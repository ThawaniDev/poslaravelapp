<?php

namespace App\Domain\IndustryPharmacy\Services;

use App\Domain\IndustryPharmacy\Models\Prescription;
use App\Domain\IndustryPharmacy\Models\DrugSchedule;
use App\Domain\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;

class PharmacyService
{
    public function listPrescriptions(string $storeId, array $filters = [])
    {
        $query = Prescription::where('store_id', $storeId);

        if (! empty($filters['search'])) {
            $query->where('patient_name', 'like', '%' . $filters['search'] . '%');
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 15);
    }

    public function createPrescription(string $storeId, array $data): Prescription
    {
        return Prescription::create(array_merge($data, ['store_id' => $storeId]));
    }

    public function updatePrescription(string $id, string $storeId, array $data): Prescription
    {
        $prescription = Prescription::where('store_id', $storeId)->findOrFail($id);
        $prescription->update($data);
        return $prescription->fresh();
    }

    public function listDrugSchedules(array $filters = [])
    {
        $query = DrugSchedule::query();

        if (! empty($filters['schedule_type'])) {
            $query->where('schedule_type', $filters['schedule_type']);
        }
        if (! empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        return $query->orderBy('schedule_type')->paginate($filters['per_page'] ?? 15);
    }

    public function createDrugSchedule(array $data): DrugSchedule
    {
        return DrugSchedule::create($data);
    }

    public function updateDrugSchedule(string $id, array $data): DrugSchedule
    {
        $schedule = DrugSchedule::findOrFail($id);
        $schedule->update($data);
        return $schedule->fresh();
    }

    /**
     * Returns products expiring within $days days for the given store.
     * Joins inventory_items (or products table expiry_date if available).
     */
    public function getExpiryAlerts(string $storeId, int $days = 90): array
    {
        $cutoff = now()->addDays($days)->toDateString();

        // Query products with expiry_date set, scoped to store via inventory
        $results = DB::table('products as p')
            ->join('inventory_items as ii', 'ii.product_id', '=', 'p.id')
            ->where('ii.store_id', $storeId)
            ->whereNotNull('p.expiry_date')
            ->whereDate('p.expiry_date', '<=', $cutoff)
            ->select(
                'p.id',
                'p.name',
                'p.sku',
                'p.expiry_date',
                DB::raw('SUM(ii.quantity) as total_quantity'),
                DB::raw("CASE WHEN p.expiry_date <= CURRENT_DATE THEN 'expired'
                              WHEN p.expiry_date <= (CURRENT_DATE + INTERVAL '30 days') THEN 'critical'
                              ELSE 'warning' END as severity")
            )
            ->groupBy('p.id', 'p.name', 'p.sku', 'p.expiry_date')
            ->orderBy('p.expiry_date')
            ->get();

        return $results->toArray();
    }
}
