<?php

namespace App\Domain\IndustryPharmacy\Controllers\Api;

use App\Domain\IndustryPharmacy\Requests\CreateDrugScheduleRequest;
use App\Domain\IndustryPharmacy\Requests\CreatePrescriptionRequest;
use App\Domain\IndustryPharmacy\Requests\UpdateDrugScheduleRequest;
use App\Domain\IndustryPharmacy\Requests\UpdatePrescriptionRequest;
use App\Domain\IndustryPharmacy\Resources\DrugScheduleResource;
use App\Domain\IndustryPharmacy\Resources\PrescriptionResource;
use App\Domain\IndustryPharmacy\Services\PharmacyService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PharmacyController extends BaseApiController
{
    public function __construct(private readonly PharmacyService $service) {}

    public function listPrescriptions(Request $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $paginator = $this->service->listPrescriptions($storeId, $request->only(['search', 'per_page']));

        $data = $paginator->toArray();
        $data['data'] = PrescriptionResource::collection($paginator->items())->resolve();

        return $this->success($data, __('industry.prescriptions_retrieved'));
    }

    public function createPrescription(CreatePrescriptionRequest $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $prescription = $this->service->createPrescription($storeId, $request->validated());
        return $this->created(new PrescriptionResource($prescription), __('industry.prescription_created'));
    }

    public function updatePrescription(UpdatePrescriptionRequest $request, string $id): JsonResponse
    {
        $prescription = $this->service->updatePrescription($id, $this->resolvedStoreId($request) ?? $request->user()->store_id, $request->validated());
        return $this->success(new PrescriptionResource($prescription), __('industry.prescription_updated'));
    }

    public function listDrugSchedules(Request $request): JsonResponse
    {
        $paginator = $this->service->listDrugSchedules($request->only(['schedule_type', 'product_id', 'per_page']));

        $data = $paginator->toArray();
        $data['data'] = DrugScheduleResource::collection($paginator->items())->resolve();

        return $this->success($data, __('industry.drug_schedules_retrieved'));
    }

    public function createDrugSchedule(CreateDrugScheduleRequest $request): JsonResponse
    {
        $schedule = $this->service->createDrugSchedule($request->validated());
        return $this->created(new DrugScheduleResource($schedule), __('industry.drug_schedule_created'));
    }

    public function updateDrugSchedule(UpdateDrugScheduleRequest $request, string $id): JsonResponse
    {
        $schedule = $this->service->updateDrugSchedule($id, $request->validated());
        return $this->success(new DrugScheduleResource($schedule), __('industry.drug_schedule_updated'));
    }

    public function expiryAlerts(Request $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $days = (int) $request->query('days', 90);
        $days = max(1, min($days, 365));
        $alerts = $this->service->getExpiryAlerts($storeId, $days);
        return $this->success(['data' => $alerts, 'days' => $days], __('industry.expiry_alerts_retrieved'));
    }
}
