<?php

namespace App\Domain\Report\Controllers\Api;

use App\Domain\Report\Requests\ReportFilterRequest;
use App\Domain\Report\Services\ReportService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;

class ReportController extends BaseApiController
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {}

    /**
     * GET /api/v2/reports/sales-summary
     */
    public function salesSummary(ReportFilterRequest $request): JsonResponse
    {
        $data = $this->reportService->salesSummary(
            $request->user()->store_id,
            $request->validated(),
        );

        return $this->success($data);
    }

    /**
     * GET /api/v2/reports/product-performance
     */
    public function productPerformance(ReportFilterRequest $request): JsonResponse
    {
        $data = $this->reportService->productPerformance(
            $request->user()->store_id,
            $request->validated(),
        );

        return $this->success($data);
    }

    /**
     * GET /api/v2/reports/category-breakdown
     */
    public function categoryBreakdown(ReportFilterRequest $request): JsonResponse
    {
        $data = $this->reportService->categoryBreakdown(
            $request->user()->store_id,
            $request->validated(),
        );

        return $this->success($data);
    }

    /**
     * GET /api/v2/reports/staff-performance
     */
    public function staffPerformance(ReportFilterRequest $request): JsonResponse
    {
        $data = $this->reportService->staffPerformance(
            $request->user()->store_id,
            $request->validated(),
        );

        return $this->success($data);
    }

    /**
     * GET /api/v2/reports/hourly-sales
     */
    public function hourlySales(ReportFilterRequest $request): JsonResponse
    {
        $data = $this->reportService->hourlySales(
            $request->user()->store_id,
            $request->validated(),
        );

        return $this->success($data);
    }

    /**
     * GET /api/v2/reports/payment-methods
     */
    public function paymentMethods(ReportFilterRequest $request): JsonResponse
    {
        $data = $this->reportService->paymentMethodBreakdown(
            $request->user()->store_id,
            $request->validated(),
        );

        return $this->success($data);
    }

    /**
     * GET /api/v2/reports/dashboard
     */
    public function dashboard(): JsonResponse
    {
        $data = $this->reportService->dashboard(
            request()->user()->store_id,
        );

        return $this->success($data);
    }
}
