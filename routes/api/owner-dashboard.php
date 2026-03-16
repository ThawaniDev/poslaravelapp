<?php

use App\Domain\OwnerDashboard\Controllers\Api\OwnerDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Owner Dashboard API Routes
|--------------------------------------------------------------------------
|
| Routes for the Store Owner Web Dashboard feature.
| Prefix: /api/v2/owner-dashboard
|
*/

Route::prefix('owner-dashboard')->middleware('auth:sanctum')->group(function () {
    Route::get('stats', [OwnerDashboardController::class, 'stats']);
    Route::get('sales-trend', [OwnerDashboardController::class, 'salesTrend']);
    Route::get('top-products', [OwnerDashboardController::class, 'topProducts']);
    Route::get('low-stock', [OwnerDashboardController::class, 'lowStock']);
    Route::get('active-cashiers', [OwnerDashboardController::class, 'activeCashiers']);
    Route::get('recent-orders', [OwnerDashboardController::class, 'recentOrders']);
    Route::get('financial-summary', [OwnerDashboardController::class, 'financialSummary']);
    Route::get('hourly-sales', [OwnerDashboardController::class, 'hourlySales']);
    Route::get('branches', [OwnerDashboardController::class, 'branches']);
    Route::get('staff-performance', [OwnerDashboardController::class, 'staffPerformance']);
});
