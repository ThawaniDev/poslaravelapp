<?php

use App\Domain\ThawaniIntegration\Controllers\Api\ThawaniController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ThawaniIntegration API Routes
|--------------------------------------------------------------------------
|
| Routes for the ThawaniIntegration feature.
| Prefix: /api/v2/thawani
|
*/

Route::prefix('thawani')->middleware('auth:sanctum')->group(function () {
    // Dashboard & Config
    Route::get('stats', [ThawaniController::class, 'stats'])->middleware('permission:thawani.view_dashboard');
    Route::get('config', [ThawaniController::class, 'config'])->middleware('permission:thawani.manage_config');
    Route::post('config', [ThawaniController::class, 'saveConfig'])->middleware('permission:thawani.manage_config');
    Route::put('disconnect', [ThawaniController::class, 'disconnect'])->middleware('permission:thawani.manage_config');
    Route::post('test-connection', [ThawaniController::class, 'testConnection'])->middleware('permission:thawani.manage_config');

    // Orders & Settlements
    Route::get('orders', [ThawaniController::class, 'orders'])->middleware('permission:thawani.view_dashboard');
    Route::get('settlements', [ThawaniController::class, 'settlements'])->middleware('permission:thawani.view_dashboard');

    // Product Sync
    Route::get('product-mappings', [ThawaniController::class, 'productMappings'])->middleware('permission:thawani.menu');
    Route::post('push-products', [ThawaniController::class, 'pushProducts'])->middleware('permission:thawani.manage_sync');
    Route::post('pull-products', [ThawaniController::class, 'pullProducts'])->middleware('permission:thawani.manage_sync');

    // Category Sync
    Route::get('category-mappings', [ThawaniController::class, 'categoryMappings'])->middleware('permission:thawani.menu');
    Route::post('push-categories', [ThawaniController::class, 'pushCategories'])->middleware('permission:thawani.manage_sync');
    Route::post('pull-categories', [ThawaniController::class, 'pullCategories'])->middleware('permission:thawani.manage_sync');

    // Column Mappings
    Route::get('column-mappings', [ThawaniController::class, 'columnMappings'])->middleware('permission:thawani.menu');
    Route::post('column-mappings/seed-defaults', [ThawaniController::class, 'seedColumnDefaults'])->middleware('permission:thawani.manage_sync');

    // Sync Logs & Queue
    Route::get('sync-logs', [ThawaniController::class, 'syncLogs'])->middleware('permission:thawani.view_sync_logs');
    Route::get('queue-stats', [ThawaniController::class, 'queueStats'])->middleware('permission:thawani.view_dashboard');
    Route::post('process-queue', [ThawaniController::class, 'processQueue'])->middleware('permission:thawani.manage_sync');
});
