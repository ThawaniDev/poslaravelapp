<?php

namespace App\Domain\IndustryElectronics\Controllers\Api;

use App\Domain\IndustryElectronics\Requests\RegisterImeiRequest;
use App\Domain\IndustryElectronics\Requests\CreateRepairJobRequest;
use App\Domain\IndustryElectronics\Requests\UpdateImeiRecordRequest;
use App\Domain\IndustryElectronics\Requests\UpdateRepairJobRequest;
use App\Domain\IndustryElectronics\Requests\CreateTradeInRequest;
use App\Domain\IndustryElectronics\Resources\DeviceImeiRecordResource;
use App\Domain\IndustryElectronics\Resources\RepairJobResource;
use App\Domain\IndustryElectronics\Resources\TradeInRecordResource;
use App\Domain\IndustryElectronics\Services\ElectronicsService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ElectronicsController extends BaseApiController
{
    public function __construct(private readonly ElectronicsService $service) {}

    public function listImeiRecords(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $paginator = $this->service->listImeiRecords($storeId, $request->only(['status', 'search', 'per_page']));

        $data = $paginator->toArray();
        $data['data'] = DeviceImeiRecordResource::collection($paginator->items())->resolve();

        return $this->success($data, __('industry.imei_records_retrieved'));
    }

    public function createImeiRecord(RegisterImeiRequest $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $record = $this->service->createImeiRecord($storeId, $request->validated());
        return $this->created(new DeviceImeiRecordResource($record), __('industry.imei_record_created'));
    }

    public function updateImeiRecord(UpdateImeiRecordRequest $request, string $id): JsonResponse
    {
        $record = $this->service->updateImeiRecord($id, $request->user()->store_id, $request->validated());
        return $this->success(new DeviceImeiRecordResource($record), __('industry.imei_record_updated'));
    }

    public function listRepairJobs(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $paginator = $this->service->listRepairJobs($storeId, $request->only(['status', 'search', 'per_page']));

        $data = $paginator->toArray();
        $data['data'] = RepairJobResource::collection($paginator->items())->resolve();

        return $this->success($data, __('industry.repair_jobs_retrieved'));
    }

    public function createRepairJob(CreateRepairJobRequest $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $job = $this->service->createRepairJob($storeId, $request->validated());
        return $this->created(new RepairJobResource($job), __('industry.repair_job_created'));
    }

    public function updateRepairJob(UpdateRepairJobRequest $request, string $id): JsonResponse
    {
        $job = $this->service->updateRepairJob($id, $request->user()->store_id, $request->validated());
        return $this->success(new RepairJobResource($job), __('industry.repair_job_updated'));
    }

    public function updateRepairJobStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:received,diagnosing,repairing,testing,ready,collected,cancelled',
        ]);

        $job = $this->service->updateRepairJobStatus($id, $request->user()->store_id, $validated['status']);
        return $this->success(new RepairJobResource($job), __('industry.repair_job_status_updated'));
    }

    public function listTradeIns(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $paginator = $this->service->listTradeIns($storeId, $request->only(['customer_id', 'search', 'per_page']));

        $data = $paginator->toArray();
        $data['data'] = TradeInRecordResource::collection($paginator->items())->resolve();

        return $this->success($data, __('industry.trade_ins_retrieved'));
    }

    public function createTradeIn(CreateTradeInRequest $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $tradeIn = $this->service->createTradeIn($storeId, $request->validated());
        return $this->created(new TradeInRecordResource($tradeIn), __('industry.trade_in_created'));
    }
}
