<?php

namespace App\Domain\IndustryElectronics\Controllers\Api;

use App\Domain\IndustryElectronics\Services\ElectronicsService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ElectronicsController extends BaseApiController
{
    public function __construct(private ElectronicsService $service) {}

    public function listImeiRecords(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $data = $this->service->listImeiRecords($storeId, $request->only(['status', 'search', 'per_page']));
        return $this->success($data, __('industry.imei_records_retrieved'));
    }

    public function createImeiRecord(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|string',
            'imei' => 'required|string|max:20',
            'imei2' => 'nullable|string|max:20',
            'serial_number' => 'nullable|string|max:100',
            'condition_grade' => 'required|string|in:A,B,C,D',
            'status' => 'required|string|in:in_stock,sold,traded_in,returned',
            'purchase_price' => 'nullable|numeric|min:0',
            'warranty_end_date' => 'nullable|date',
            'store_warranty_end_date' => 'nullable|date',
            'sold_order_id' => 'nullable|string',
        ]);

        $storeId = $request->user()->store_id;
        $record = $this->service->createImeiRecord($storeId, $validated);
        return $this->created($record, __('industry.imei_record_created'));
    }

    public function updateImeiRecord(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'condition_grade' => 'nullable|string|in:A,B,C,D',
            'status' => 'nullable|string|in:in_stock,sold,traded_in,returned',
            'sold_order_id' => 'nullable|string',
        ]);

        $record = $this->service->updateImeiRecord($id, $validated);
        return $this->success($record, __('industry.imei_record_updated'));
    }

    public function listRepairJobs(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $data = $this->service->listRepairJobs($storeId, $request->only(['status', 'search', 'per_page']));
        return $this->success($data, __('industry.repair_jobs_retrieved'));
    }

    public function createRepairJob(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|string',
            'device_description' => 'required|string|max:500',
            'imei' => 'nullable|string|max:20',
            'issue_description' => 'required|string',
            'diagnosis_notes' => 'nullable|string',
            'repair_notes' => 'nullable|string',
            'parts_used' => 'nullable|array',
            'estimated_cost' => 'nullable|numeric|min:0',
            'final_cost' => 'nullable|numeric|min:0',
            'staff_user_id' => 'nullable|string',
            'estimated_ready_at' => 'nullable|date',
        ]);

        $validated['received_at'] = now();
        $validated['status'] = 'received';

        $storeId = $request->user()->store_id;
        $job = $this->service->createRepairJob($storeId, $validated);
        return $this->created($job, __('industry.repair_job_created'));
    }

    public function updateRepairJob(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'diagnosis_notes' => 'nullable|string',
            'repair_notes' => 'nullable|string',
            'parts_used' => 'nullable|array',
            'estimated_cost' => 'nullable|numeric|min:0',
            'final_cost' => 'nullable|numeric|min:0',
            'estimated_ready_at' => 'nullable|date',
        ]);

        $job = $this->service->updateRepairJob($id, $validated);
        return $this->success($job, __('industry.repair_job_updated'));
    }

    public function updateRepairJobStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:received,diagnosing,repairing,testing,ready,collected,cancelled',
        ]);

        $job = $this->service->updateRepairJobStatus($id, $validated['status']);
        return $this->success($job, __('industry.repair_job_status_updated'));
    }

    public function listTradeIns(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $data = $this->service->listTradeIns($storeId, $request->only(['customer_id', 'search', 'per_page']));
        return $this->success($data, __('industry.trade_ins_retrieved'));
    }

    public function createTradeIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|string',
            'device_description' => 'required|string|max:500',
            'imei' => 'nullable|string|max:20',
            'condition_grade' => 'required|string|in:A,B,C,D',
            'assessed_value' => 'required|numeric|min:0',
            'applied_to_order_id' => 'nullable|string',
            'staff_user_id' => 'nullable|string',
        ]);

        $storeId = $request->user()->store_id;
        $tradeIn = $this->service->createTradeIn($storeId, $validated);
        return $this->created($tradeIn, __('industry.trade_in_created'));
    }
}
