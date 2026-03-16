<?php

namespace App\Domain\IndustryPharmacy\Services;

use App\Domain\IndustryPharmacy\Models\Prescription;
use App\Domain\IndustryPharmacy\Models\DrugSchedule;

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

    public function updatePrescription(string $id, array $data): Prescription
    {
        $prescription = Prescription::findOrFail($id);
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
}
