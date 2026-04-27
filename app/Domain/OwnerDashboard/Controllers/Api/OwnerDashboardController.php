<?php

namespace App\Domain\OwnerDashboard\Controllers\Api;

use App\Domain\OwnerDashboard\Requests\DashboardFilterRequest;
use App\Domain\OwnerDashboard\Services\OwnerDashboardService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OwnerDashboardController extends BaseApiController
{
    public function __construct(
        private readonly OwnerDashboardService $dashboardService,
    ) {}

    /**
     * GET /api/v2/owner-dashboard/summary
     *
     * Single aggregated response used by the provider app on dashboard load.
     * Replaces 10 individual API calls with one round-trip.
     */
    public function summary(DashboardFilterRequest $request): JsonResponse
    {
        $user  = $request->user();
        $store = $user->store;

        if (! $store?->organization_id) {
            return $this->error(__('owner_dashboard.no_organization'), 400);
        }

        $data = $this->dashboardService->summary(
            $this->resolvedStoreIds($request),
            $store->organization_id,
            $request->validated(),
        );

        return $this->success($data, __('owner_dashboard.summary_retrieved'));
    }

    /**
     * GET /api/v2/owner-dashboard/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $data = $this->dashboardService->stats(
            $this->resolvedStoreIds($request),
        );

        return $this->success($data, __('owner_dashboard.stats_retrieved'));
    }

    /**
     * GET /api/v2/owner-dashboard/sales-trend
     */
    public function salesTrend(DashboardFilterRequest $request): JsonResponse
    {
        $data = $this->dashboardService->salesTrend(
            $this->resolvedStoreIds($request),
            $request->validated(),
        );

        return $this->success($data, __('owner_dashboard.sales_trend_retrieved'));
    }

    /**
     * GET /api/v2/owner-dashboard/top-products
     */
    public function topProducts(DashboardFilterRequest $request): JsonResponse
    {
        $data = $this->dashboardService->topProducts(
            $this->resolvedStoreIds($request),
            $request->validated(),
        );

        return $this->success($data, __('owner_dashboard.top_products_retrieved'));
    }

    /**
     * GET /api/v2/owner-dashboard/low-stock
     */
    public function lowStock(Request $request): JsonResponse
    {
        $limit = (int) $request->input('limit', 10);

        $data = $this->dashboardService->lowStockAlerts(
            $this->resolvedStoreIds($request),
            min($limit, 50),
        );

        return $this->success($data, __('owner_dashboard.low_stock_retrieved'));
    }

    /**
     * GET /api/v2/owner-dashboard/active-cashiers
     */
    public function activeCashiers(Request $request): JsonResponse
    {
        $data = $this->dashboardService->activeCashiers(
            $this->resolvedStoreIds($request),
        );

        return $this->success($data, __('owner_dashboard.active_cashiers_retrieved'));
    }

    /**
     * GET /api/v2/owner-dashboard/recent-orders
     */
    public function recentOrders(Request $request): JsonResponse
    {
        $limit = (int) $request->input('limit', 10);

        $data = $this->dashboardService->recentOrders(
            $this->resolvedStoreIds($request),
            min($limit, 50),
        );

        return $this->success($data, __('owner_dashboard.recent_orders_retrieved'));
    }

    /**
     * GET /api/v2/owner-dashboard/financial-summary
     */
    public function financialSummary(DashboardFilterRequest $request): JsonResponse
    {
        $data = $this->dashboardService->financialSummary(
            $this->resolvedStoreIds($request),
            $request->validated(),
        );

        return $this->success($data, __('owner_dashboard.financial_summary_retrieved'));
    }

    /**
     * GET /api/v2/owner-dashboard/hourly-sales
     */
    public function hourlySales(Request $request): JsonResponse
    {
        $date = $request->input('date');

        $data = $this->dashboardService->hourlySales(
            $this->resolvedStoreIds($request),
            $date,
        );

        return $this->success($data, __('owner_dashboard.hourly_sales_retrieved'));
    }

    /**
     * GET /api/v2/owner-dashboard/branches
     */
    public function branches(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $user->store;

        if (! $store?->organization_id) {
            return $this->error(__('owner_dashboard.no_organization'), 400);
        }

        $data = $this->dashboardService->branchOverview(
            $store->organization_id,
        );

        return $this->success($data, __('owner_dashboard.branches_retrieved'));
    }

    /**
     * GET /api/v2/owner-dashboard/staff-performance
     */
    public function staffPerformance(DashboardFilterRequest $request): JsonResponse
    {
        $data = $this->dashboardService->staffPerformance(
            $this->resolvedStoreIds($request),
            $request->validated(),
        );

        return $this->success($data, __('owner_dashboard.staff_performance_retrieved'));
    }
}
