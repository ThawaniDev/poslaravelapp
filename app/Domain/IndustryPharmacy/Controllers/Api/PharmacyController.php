<?php

namespace App\Domain\IndustryPharmacy\Controllers\Api;

use App\Domain\IndustryPharmacy\Services\PharmacyService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PharmacyController extends BaseApiController
{
    public function __construct(private PharmacyService $service) {}

    public function listPrescriptions(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $data = $this->service->listPrescriptions($storeId, $request->only(['search', 'per_page']));
        return $this->success($data, __('industry.prescriptions_retrieved'));
    }

    public function createPrescription(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'nullable|string',
            'prescription_number' => 'required|string|max:100',
            'patient_name' => 'required|string|max:255',
            'patient_id' => 'nullable|string|max:100',
            'doctor_name' => 'required|string|max:255',
            'doctor_license' => 'required|string|max:100',
            'insurance_provider' => 'nullable|string|max:255',
            'insurance_claim_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $storeId = $request->user()->store_id;
        $prescription = $this->service->createPrescription($storeId, $validated);
        return $this->created($prescription, __('industry.prescription_created'));
    }

    public function updatePrescription(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'insurance_provider' => 'nullable|string|max:255',
            'insurance_claim_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $prescription = $this->service->updatePrescription($id, $validated);
        return $this->success($prescription, __('industry.prescription_updated'));
    }

    public function listDrugSchedules(Request $request): JsonResponse
    {
        $data = $this->service->listDrugSchedules($request->only(['schedule_type', 'product_id', 'per_page']));
        return $this->success($data, __('industry.drug_schedules_retrieved'));
    }

    public function createDrugSchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|string',
            'schedule_type' => 'required|string|in:otc,prescription_only,controlled',
            'active_ingredient' => 'nullable|string|max:255',
            'dosage_form' => 'nullable|string|max:100',
            'strength' => 'nullable|string|max:100',
            'manufacturer' => 'nullable|string|max:255',
            'requires_prescription' => 'required|boolean',
        ]);

        $schedule = $this->service->createDrugSchedule($validated);
        return $this->created($schedule, __('industry.drug_schedule_created'));
    }

    public function updateDrugSchedule(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'schedule_type' => 'nullable|string|in:otc,prescription_only,controlled',
            'active_ingredient' => 'nullable|string|max:255',
            'dosage_form' => 'nullable|string|max:100',
            'strength' => 'nullable|string|max:100',
            'manufacturer' => 'nullable|string|max:255',
            'requires_prescription' => 'nullable|boolean',
        ]);

        $schedule = $this->service->updateDrugSchedule($id, $validated);
        return $this->success($schedule, __('industry.drug_schedule_updated'));
    }
}
