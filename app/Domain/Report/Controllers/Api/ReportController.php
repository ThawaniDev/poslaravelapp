<?php

namespace App\Domain\Report\Controllers\Api;

use App\Domain\Report\Requests\ExportReportRequest;
use App\Domain\Report\Requests\ReportFilterRequest;
use App\Domain\Report\Requests\ScheduleReportRequest;
use App\Domain\Report\Services\ReportService;
use App\Domain\Subscription\Traits\TracksSubscriptionUsage;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReportController extends BaseApiController
{
    use TracksSubscriptionUsage;
    public function __construct(
        private readonly ReportService $reportService,
    ) {}

    /**
     * Resolve the effective store_id for report queries.
     *
     * Uses the base trait's resolved branch first (set by BranchScope middleware
     * if `branch_id` was passed in the request), then falls back to the
     * authenticated user's own store_id.
     *
     * Additionally validates that the requested branch belongs to the same
     * organisation as the authenticated user's store.
     */
    protected function resolveStoreId(Request $request): string
    {
        $user = $request->user();
        $requestedBranch = $request->input('branch_id');

        if ($requestedBranch && $requestedBranch !== $user->store_id) {
            $orgId = DB::table('stores')->where('id', $user->store_id)->value('organization_id');
            $valid = $orgId && DB::table('stores')
                ->where('id', $requestedBranch)
                ->where('organization_id', $orgId)
                ->exists();

            if ($valid) {
                return $requestedBranch;
            }
        }

        return $user->store_id;
    }

    /**
     * GET /api/v2/reports/sales-summary
     */
    public function salesSummary(ReportFilterRequest $request): JsonResponse
    {
        $data = $this->reportService->salesSummary(
            $this->resolveStoreId($request),
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
            $this->resolveStoreId($request),
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
            $this->resolveStoreId($request),
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
            $this->resolveStoreId($request),
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
            $this->resolveStoreId($request),
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
            $this->resolveStoreId($request),
            $request->validated(),
        );

        return $this->success($data);
    }

    /**
     * GET /api/v2/reports/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $data = $this->reportService->dashboard(
            $this->resolveStoreId($request),
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
            $this->resolveStoreId($request),
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
            $this->resolveStoreId($request),
            $request->validated(),
        );

        return $this->success($data);
    }

    // ─── Inventory Reports ───────────────────────────────────

    /**
     * GET /api/v2/reports/inventory/valuation
     */
    public function inventoryValuation(Request $request): JsonResponse
    {
        $data = $this->reportService->inventoryValuation(
            $this->resolveStoreId($request),
        );

        return $this->success($data);
    }

    /**
     * GET /api/v2/reports/inventory/turnover
     */
    public function inventoryTurnover(ReportFilterRequest $request): JsonResponse
    {
        $data = $this->reportService->inventoryTurnover(
            $this->resolveStoreId($request),
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
            $this->resolveStoreId($request),
            $request->validated(),
        );

        return $this->success($data);
    }

    /**
     * GET /api/v2/reports/inventory/low-stock
     */
    public function inventoryLowStock(Request $request): JsonResponse
    {
        $data = $this->reportService->inventoryLowStock(
            $this->resolveStoreId($request),
        );

        return $this->success($data);
    }

    /**
     * GET /api/v2/reports/inventory/expiry
     */
    public function inventoryExpiry(ReportFilterRequest $request): JsonResponse
    {
        $data = $this->reportService->inventoryExpiry(
            $this->resolveStoreId($request),
            $request->validated(),
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
            $this->resolveStoreId($request),
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
            $this->resolveStoreId($request),
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
            $this->resolveStoreId($request),
            $request->validated(),
        );

        return $this->success($data);
    }

    /**
     * GET /api/v2/reports/financial/delivery-commission
     */
    public function financialDeliveryCommission(ReportFilterRequest $request): JsonResponse
    {
        $data = $this->reportService->financialDeliveryCommission(
            $this->resolveStoreId($request),
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
            $this->resolveStoreId($request),
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
            $this->resolveStoreId($request),
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

        // Check PDF report export limit before proceeding
        $orgId = $this->resolveOrganizationId($request);
        if ($orgId) {
            $limitResponse = $this->checkLimitOrFail($orgId, 'pdf_reports_per_month');
            if ($limitResponse) {
                return $limitResponse;
            }
        }

        $data = $this->reportService->exportReport(
            $this->resolveStoreId($request),
            $validated['report_type'],
            collect($validated)->except(['report_type', 'format'])->toArray(),
            $validated['format'],
        );

        // Record the export for usage tracking
        if ($orgId) {
            DB::table('report_exports')->insert([
                'id' => Str::uuid()->toString(),
                'organization_id' => $orgId,
                'store_id' => $this->resolvedStoreId($request) ?? $request->user()->store_id,
                'user_id' => $request->user()->id,
                'report_type' => $validated['report_type'],
                'format' => $validated['format'] ?? 'pdf',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->refreshUsageFor($orgId, 'pdf_reports_per_month');
        }

        return $this->success($data);
    }

    // ─── Scheduled Reports ───────────────────────────────────

    /**
     * GET /api/v2/reports/schedules
     */
    public function listSchedules(Request $request): JsonResponse
    {
        $data = $this->reportService->listScheduledReports(
            $this->resolveStoreId($request),
        );

        return $this->success($data);
    }

    /**
     * POST /api/v2/reports/schedules
     */
    public function createSchedule(ScheduleReportRequest $request): JsonResponse
    {
        $report = $this->reportService->createScheduledReport(
            $this->resolveStoreId($request),
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
            $this->resolveStoreId($request),
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
        $storeId = $this->resolveStoreId($request);
        $date = $request->input('date', now()->toDateString());

        $this->reportService->refreshDailySummary($storeId, $date);
        $this->reportService->refreshProductSummary($storeId, $date);

        return $this->success(null, 'Summaries refreshed for ' . $date);
    }
}
