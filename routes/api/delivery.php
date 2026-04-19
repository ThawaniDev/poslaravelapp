<?php

use App\Domain\DeliveryIntegration\Controllers\Api\DeliveryController;
use App\Domain\DeliveryIntegration\Controllers\Api\DeliveryWebhookController;
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

// Webhook endpoint — NO auth middleware (verified via signature)
Route::post('delivery/webhook/{platform}/{storeId}', [DeliveryWebhookController::class, 'handle'])
    ->name('delivery.webhook');

// Authenticated endpoints
Route::prefix('delivery')->middleware(['auth:sanctum', 'plan.feature:delivery_integration'])->group(function () {
    // Dashboard & stats
    Route::get('stats', [DeliveryController::class, 'stats'])->middleware('permission:delivery.view_dashboard');
    Route::get('platforms', [DeliveryController::class, 'platforms'])->middleware('permission:delivery.view_dashboard');

    // Platform configs
    Route::get('configs', [DeliveryController::class, 'configs'])->middleware('permission:delivery.manage_config');
    Route::post('configs', [DeliveryController::class, 'saveConfig'])->middleware('permission:delivery.manage_config');
    Route::put('configs/{id}/toggle', [DeliveryController::class, 'toggleConfig'])->middleware('permission:delivery.manage_config');
    Route::post('configs/{id}/test-connection', [DeliveryController::class, 'testConnection'])->middleware('permission:delivery.manage_config');

    // Orders
    Route::get('orders', [DeliveryController::class, 'orders'])->middleware('permission:delivery.manage');
    Route::get('orders/active', [DeliveryController::class, 'activeOrders'])->middleware('permission:delivery.manage');
    Route::get('orders/{id}', [DeliveryController::class, 'orderDetail'])->middleware('permission:delivery.manage');
    Route::put('orders/{id}/status', [DeliveryController::class, 'updateOrderStatus'])->middleware('permission:delivery.manage');

    // Menu sync
    Route::post('menu-sync', [DeliveryController::class, 'triggerMenuSync'])->middleware('permission:delivery.sync_menu');

    // Sync logs
    Route::get('sync-logs', [DeliveryController::class, 'syncLogs'])->middleware('permission:delivery.view_logs');

    // Webhook logs
    Route::get('webhook-logs', [DeliveryController::class, 'webhookLogs'])->middleware('permission:delivery.view_logs');

    // Status push logs
    Route::get('status-push-logs', [DeliveryController::class, 'statusPushLogs'])->middleware('permission:delivery.view_logs');

    // Config detail (single config)
    Route::get('configs/{id}', [DeliveryController::class, 'configDetail'])->middleware('permission:delivery.manage_config');

    // Delete config
    Route::delete('configs/{id}', [DeliveryController::class, 'deleteConfig'])->middleware('permission:delivery.manage_config');
});
