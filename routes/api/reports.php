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
    Route::get('dashboard', [ReportController::class, 'dashboard'])->middleware('permission:reports.view');

    // Sales
    Route::get('sales-summary', [ReportController::class, 'salesSummary'])->middleware('permission:reports.sales');
    Route::get('hourly-sales', [ReportController::class, 'hourlySales'])->middleware('permission:reports.sales');
    Route::get('payment-methods', [ReportController::class, 'paymentMethods'])->middleware('permission:reports.sales');

    // Products
    Route::get('product-performance', [ReportController::class, 'productPerformance'])->middleware('permission:reports.sales');
    Route::get('category-breakdown', [ReportController::class, 'categoryBreakdown'])->middleware('permission:reports.sales');
    Route::get('products/slow-movers', [ReportController::class, 'slowMovers'])->middleware('permission:reports.sales');
    Route::get('products/margin', [ReportController::class, 'productMargin'])->middleware('permission:reports.view_margin');

    // Staff
    Route::get('staff-performance', [ReportController::class, 'staffPerformance'])->middleware('permission:reports.staff');

    // Inventory
    Route::get('inventory/valuation', [ReportController::class, 'inventoryValuation'])->middleware('permission:reports.inventory');
    Route::get('inventory/turnover', [ReportController::class, 'inventoryTurnover'])->middleware('permission:reports.inventory');
    Route::get('inventory/shrinkage', [ReportController::class, 'inventoryShrinkage'])->middleware('permission:reports.inventory');
    Route::get('inventory/low-stock', [ReportController::class, 'inventoryLowStock'])->middleware('permission:reports.inventory');

    // Financial
    Route::get('financial/daily-pl', [ReportController::class, 'financialDailyPL'])->middleware('permission:reports.view_financial');
    Route::get('financial/expenses', [ReportController::class, 'financialExpenses'])->middleware('permission:reports.view_financial');
    Route::get('financial/cash-variance', [ReportController::class, 'financialCashVariance'])->middleware('permission:reports.view_financial');

    // Customers
    Route::get('customers/top', [ReportController::class, 'topCustomers'])->middleware('permission:reports.customers');
    Route::get('customers/retention', [ReportController::class, 'customerRetention'])->middleware('permission:reports.customers');

    // Export
    Route::post('export', [ReportController::class, 'export'])->middleware('permission:reports.export');

    // Scheduled Reports
    Route::get('schedules', [ReportController::class, 'listSchedules'])->middleware('permission:reports.export');
    Route::post('schedules', [ReportController::class, 'createSchedule'])->middleware('permission:reports.export');
    Route::delete('schedules/{id}', [ReportController::class, 'deleteSchedule'])->middleware('permission:reports.export');

    // Summary Refresh (on-demand)
    Route::post('refresh-summaries', [ReportController::class, 'refreshSummaries'])->middleware('permission:reports.view');
});
