<?php

namespace App\Domain\Report\Controllers\Api;

use App\Domain\Report\Requests\ExportReportRequest;
use App\Domain\Report\Requests\ReportFilterRequest;
use App\Domain\Report\Requests\ScheduleReportRequest;
use App\Domain\Report\Services\ReportService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    // ─── Product Sub-reports ─────────────────────────────────

    /**
     * GET /api/v2/reports/products/slow-movers
     */
    public function slowMovers(ReportFilterRequest $request): JsonResponse
    {
        $data = $this->reportService->slowMovers(
            $request->user()->store_id,
            $request->validated(),
        );

        return $this->success($data);
    }

    /**
     * GET /api/v2/reports/products/margin
     */
    public function productMargin(ReportFilterRequest $request): JsonResponse
    {
        $data = $this->reportService->productMargin(
            $request->user()->store_id,
            $request->validated(),
        );

        return $this->success($data);
    }

    // ─── Inventory Reports ───────────────────────────────────

    /**
     * GET /api/v2/reports/inventory/valuation
     */
    public function inventoryValuation(): JsonResponse
    {
        $data = $this->reportService->inventoryValuation(
            request()->user()->store_id,
        );

        return $this->success($data);
    }

    /**
     * GET /api/v2/reports/inventory/turnover
     */
    public function inventoryTurnover(ReportFilterRequest $request): JsonResponse
    {
        $data = $this->reportService->inventoryTurnover(
            $request->user()->store_id,
            $request->validated(),
        );

        return $this->success($data);
    }

    /**
     * GET /api/v2/reports/inventory/shrinkage
     */
    public function inventoryShrinkage(ReportFilterRequest $request): JsonResponse
    {
        $data = $this->reportService->inventoryShrinkage(
            $request->user()->store_id,
            $request->validated(),
        );

        return $this->success($data);
    }

    /**
     * GET /api/v2/reports/inventory/low-stock
     */
    public function inventoryLowStock(): JsonResponse
    {
        $data = $this->reportService->inventoryLowStock(
            request()->user()->store_id,
        );

        return $this->success($data);
    }

    // ─── Financial Reports ───────────────────────────────────

    /**
     * GET /api/v2/reports/financial/daily-pl
     */
    public function financialDailyPL(ReportFilterRequest $request): JsonResponse
    {
        $data = $this->reportService->financialDailyPL(
            $request->user()->store_id,
            $request->validated(),
        );

        return $this->success($data);
    }

    /**
     * GET /api/v2/reports/financial/expenses
     */
    public function financialExpenses(ReportFilterRequest $request): JsonResponse
    {
        $data = $this->reportService->financialExpenses(
            $request->user()->store_id,
            $request->validated(),
        );

        return $this->success($data);
    }

    /**
     * GET /api/v2/reports/financial/cash-variance
     */
    public function financialCashVariance(ReportFilterRequest $request): JsonResponse
    {
        $data = $this->reportService->financialCashVariance(
            $request->user()->store_id,
            $request->validated(),
        );

        return $this->success($data);
    }

    // ─── Customer Reports ────────────────────────────────────

    /**
     * GET /api/v2/reports/customers/top
     */
    public function topCustomers(ReportFilterRequest $request): JsonResponse
    {
        $data = $this->reportService->topCustomers(
            $request->user()->store_id,
            $request->validated(),
        );

        return $this->success($data);
    }

    /**
     * GET /api/v2/reports/customers/retention
     */
    public function customerRetention(ReportFilterRequest $request): JsonResponse
    {
        $data = $this->reportService->customerRetention(
            $request->user()->store_id,
            $request->validated(),
        );

        return $this->success($data);
    }

    // ─── Export ──────────────────────────────────────────────

    /**
     * POST /api/v2/reports/export
     */
    public function export(ExportReportRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $data = $this->reportService->exportReport(
            $request->user()->store_id,
            $validated['report_type'],
            collect($validated)->except(['report_type', 'format'])->toArray(),
            $validated['format'],
        );

        return $this->success($data);
    }

    // ─── Scheduled Reports ───────────────────────────────────

    /**
     * GET /api/v2/reports/schedules
     */
    public function listSchedules(): JsonResponse
    {
        $data = $this->reportService->listScheduledReports(
            request()->user()->store_id,
        );

        return $this->success($data);
    }

    /**
     * POST /api/v2/reports/schedules
     */
    public function createSchedule(ScheduleReportRequest $request): JsonResponse
    {
        $report = $this->reportService->createScheduledReport(
            $request->user()->store_id,
            $request->validated(),
        );

        return $this->created($report);
    }

    /**
     * DELETE /api/v2/reports/schedules/{id}
     */
    public function deleteSchedule(Request $request, string $id): JsonResponse
    {
        $deleted = $this->reportService->deleteScheduledReport(
            $request->user()->store_id,
            $id,
        );

        if (! $deleted) {
            return $this->notFound('Scheduled report not found');
        }

        return $this->success(null, 'Scheduled report deleted');
    }

    // ─── Summary Refresh (on-demand) ─────────────────────────

    /**
     * POST /api/v2/reports/refresh-summaries
     */
    public function refreshSummaries(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $date = $request->input('date', now()->toDateString());

        $this->reportService->refreshDailySummary($storeId, $date);
        $this->reportService->refreshProductSummary($storeId, $date);

        return $this->success(null, 'Summaries refreshed for ' . $date);
    }
}
