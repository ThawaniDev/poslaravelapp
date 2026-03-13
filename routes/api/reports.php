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
    Route::get('sales-summary', [ReportController::class, 'salesSummary']);
    Route::get('product-performance', [ReportController::class, 'productPerformance']);
    Route::get('category-breakdown', [ReportController::class, 'categoryBreakdown']);
    Route::get('staff-performance', [ReportController::class, 'staffPerformance']);
    Route::get('hourly-sales', [ReportController::class, 'hourlySales']);
    Route::get('payment-methods', [ReportController::class, 'paymentMethods']);
    Route::get('dashboard', [ReportController::class, 'dashboard']);
});
