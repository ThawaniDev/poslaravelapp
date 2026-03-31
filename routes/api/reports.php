<?php

use App\Domain\Report\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Report API Routes
|--------------------------------------------------------------------------
|
| Routes for the Report feature.
| Prefix: /api/v2/reports
|
*/

Route::prefix('reports')->middleware('auth:sanctum')->group(function () {
    // Dashboard
    Route::get('dashboard', [ReportController::class, 'dashboard']);

    // Sales
    Route::get('sales-summary', [ReportController::class, 'salesSummary']);
    Route::get('hourly-sales', [ReportController::class, 'hourlySales']);
    Route::get('payment-methods', [ReportController::class, 'paymentMethods']);

    // Products
    Route::get('product-performance', [ReportController::class, 'productPerformance']);
    Route::get('category-breakdown', [ReportController::class, 'categoryBreakdown']);
    Route::get('products/slow-movers', [ReportController::class, 'slowMovers']);
    Route::get('products/margin', [ReportController::class, 'productMargin']);

    // Staff
    Route::get('staff-performance', [ReportController::class, 'staffPerformance']);

    // Inventory
    Route::get('inventory/valuation', [ReportController::class, 'inventoryValuation']);
    Route::get('inventory/turnover', [ReportController::class, 'inventoryTurnover']);
    Route::get('inventory/shrinkage', [ReportController::class, 'inventoryShrinkage']);
    Route::get('inventory/low-stock', [ReportController::class, 'inventoryLowStock']);

    // Financial
    Route::get('financial/daily-pl', [ReportController::class, 'financialDailyPL']);
    Route::get('financial/expenses', [ReportController::class, 'financialExpenses']);
    Route::get('financial/cash-variance', [ReportController::class, 'financialCashVariance']);

    // Customers
    Route::get('customers/top', [ReportController::class, 'topCustomers']);
    Route::get('customers/retention', [ReportController::class, 'customerRetention']);

    // Export
    Route::post('export', [ReportController::class, 'export']);

    // Scheduled Reports
    Route::get('schedules', [ReportController::class, 'listSchedules']);
    Route::post('schedules', [ReportController::class, 'createSchedule']);
    Route::delete('schedules/{id}', [ReportController::class, 'deleteSchedule']);

    // Summary Refresh (on-demand)
    Route::post('refresh-summaries', [ReportController::class, 'refreshSummaries']);
});
