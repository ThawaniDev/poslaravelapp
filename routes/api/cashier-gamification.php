<?php

use App\Domain\CashierGamification\Controllers\Api\CashierGamificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Cashier Gamification API Routes
|--------------------------------------------------------------------------
|
| Routes for Cashier Gamification & Theft Deterrence features.
| Prefix: /api/v2/cashier-gamification
|
*/

Route::prefix('cashier-gamification')->middleware('auth:sanctum')->group(function () {
    // Leaderboard
    Route::get('/leaderboard', [CashierGamificationController::class, 'leaderboard'])->middleware('permission:cashier_performance.view_leaderboard');
    Route::get('/cashier/{cashierId}/history', [CashierGamificationController::class, 'cashierHistory'])->middleware('permission:cashier_performance.view_leaderboard');

    // Generate snapshot (manual trigger or session-close hook)
    Route::post('/generate-snapshot', [CashierGamificationController::class, 'generateSnapshot'])->middleware('permission:cashier_performance.manage_settings');

    // Badge definitions
    Route::get('/badges', [CashierGamificationController::class, 'badgeDefinitions'])->middleware('permission:cashier_performance.view_badges');
    Route::post('/badges', [CashierGamificationController::class, 'createBadge'])->middleware('permission:cashier_performance.manage_badges');
    Route::put('/badges/{badgeId}', [CashierGamificationController::class, 'updateBadge'])->middleware('permission:cashier_performance.manage_badges');
    Route::delete('/badges/{badgeId}', [CashierGamificationController::class, 'deleteBadge'])->middleware('permission:cashier_performance.manage_badges');
    Route::post('/badges/seed', [CashierGamificationController::class, 'seedBadges'])->middleware('permission:cashier_performance.manage_badges');

    // Badge awards
    Route::get('/badge-awards', [CashierGamificationController::class, 'badgeAwards'])->middleware('permission:cashier_performance.view_badges');

    // Anomalies
    Route::get('/anomalies', [CashierGamificationController::class, 'anomalies'])->middleware('permission:cashier_performance.view_anomalies');
    Route::post('/anomalies/{anomalyId}/review', [CashierGamificationController::class, 'reviewAnomaly'])->middleware('permission:cashier_performance.view_anomalies');

    // Shift reports
    Route::get('/shift-reports', [CashierGamificationController::class, 'shiftReports'])->middleware('permission:cashier_performance.view_reports');
    Route::get('/shift-reports/{reportId}', [CashierGamificationController::class, 'showShiftReport'])->middleware('permission:cashier_performance.view_reports');
    Route::post('/shift-reports/{reportId}/mark-sent', [CashierGamificationController::class, 'markShiftReportSent'])->middleware('permission:cashier_performance.manage_settings');

    // Settings
    Route::get('/settings', [CashierGamificationController::class, 'settings'])->middleware('permission:cashier_performance.manage_settings');
    Route::put('/settings', [CashierGamificationController::class, 'updateSettings'])->middleware('permission:cashier_performance.manage_settings');
});
