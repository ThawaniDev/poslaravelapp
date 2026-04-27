<?php

use App\Domain\MobileCompanion\Controllers\Api\CompanionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'plan.feature:companion_app', 'permission:companion.view'])->prefix('companion')->group(function () {
    Route::get('/quick-stats', [CompanionController::class, 'quickStats']);
    Route::get('/summary', [CompanionController::class, 'mobileSummary']);
    Route::get('/dashboard', [CompanionController::class, 'dashboard']);
    Route::get('/branches', [CompanionController::class, 'branches']);
    Route::get('/sales/summary', [CompanionController::class, 'salesSummary']);
    Route::get('/orders/active', [CompanionController::class, 'activeOrders']);
    Route::get('/inventory/alerts', [CompanionController::class, 'inventoryAlerts']);
    Route::get('/staff/active', [CompanionController::class, 'activeStaff']);
    Route::put('/store/availability', [CompanionController::class, 'toggleAvailability']);
    Route::post('/sessions', [CompanionController::class, 'registerSession']);
    Route::post('/sessions/{sessionId}/end', [CompanionController::class, 'endSession']);
    Route::get('/sessions', [CompanionController::class, 'listSessions']);
    Route::get('/preferences', [CompanionController::class, 'getPreferences']);
    Route::put('/preferences', [CompanionController::class, 'updatePreferences']);
    Route::get('/quick-actions', [CompanionController::class, 'getQuickActions']);
    Route::put('/quick-actions', [CompanionController::class, 'updateQuickActions']);
    Route::post('/events', [CompanionController::class, 'logEvent']);
});
