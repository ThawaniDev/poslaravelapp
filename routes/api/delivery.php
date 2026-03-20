<?php

use App\Domain\DeliveryIntegration\Controllers\Api\DeliveryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| DeliveryIntegration API Routes
|--------------------------------------------------------------------------
|
| Routes for the DeliveryIntegration feature.
| Prefix: /api/v2/delivery
|
*/

Route::prefix('delivery')->middleware('auth:sanctum')->group(function () {
    Route::get('stats', [DeliveryController::class, 'stats']);
    Route::get('configs', [DeliveryController::class, 'configs']);
    Route::post('configs', [DeliveryController::class, 'saveConfig']);
    Route::put('configs/{id}/toggle', [DeliveryController::class, 'toggleConfig']);
    Route::get('orders', [DeliveryController::class, 'orders']);
    Route::get('sync-logs', [DeliveryController::class, 'syncLogs']);
});
