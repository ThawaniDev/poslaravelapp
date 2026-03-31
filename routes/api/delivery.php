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
Route::prefix('delivery')->middleware('auth:sanctum')->group(function () {
    // Dashboard & stats
    Route::get('stats', [DeliveryController::class, 'stats']);
    Route::get('platforms', [DeliveryController::class, 'platforms']);

    // Platform configs
    Route::get('configs', [DeliveryController::class, 'configs']);
    Route::post('configs', [DeliveryController::class, 'saveConfig']);
    Route::put('configs/{id}/toggle', [DeliveryController::class, 'toggleConfig']);
    Route::post('configs/{id}/test-connection', [DeliveryController::class, 'testConnection']);

    // Orders
    Route::get('orders', [DeliveryController::class, 'orders']);
    Route::get('orders/active', [DeliveryController::class, 'activeOrders']);
    Route::get('orders/{id}', [DeliveryController::class, 'orderDetail']);
    Route::put('orders/{id}/status', [DeliveryController::class, 'updateOrderStatus']);

    // Menu sync
    Route::post('menu-sync', [DeliveryController::class, 'triggerMenuSync']);

    // Sync logs
    Route::get('sync-logs', [DeliveryController::class, 'syncLogs']);

    // Webhook logs
    Route::get('webhook-logs', [DeliveryController::class, 'webhookLogs']);

    // Status push logs
    Route::get('status-push-logs', [DeliveryController::class, 'statusPushLogs']);

    // Config detail (single config)
    Route::get('configs/{id}', [DeliveryController::class, 'configDetail']);

    // Delete config
    Route::delete('configs/{id}', [DeliveryController::class, 'deleteConfig']);
});
