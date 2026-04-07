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
    Route::get('stats', [ThawaniController::class, 'stats'])->middleware('permission:thawani.view_dashboard');
    Route::get('config', [ThawaniController::class, 'config'])->middleware('permission:thawani.manage_config');
    Route::post('config', [ThawaniController::class, 'saveConfig'])->middleware('permission:thawani.manage_config');
    Route::put('disconnect', [ThawaniController::class, 'disconnect'])->middleware('permission:thawani.manage_config');
    Route::get('orders', [ThawaniController::class, 'orders'])->middleware('permission:thawani.view_dashboard');
    Route::get('product-mappings', [ThawaniController::class, 'productMappings'])->middleware('permission:thawani.menu');
    Route::get('settlements', [ThawaniController::class, 'settlements'])->middleware('permission:thawani.view_dashboard');
});
