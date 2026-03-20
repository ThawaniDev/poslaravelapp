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
    Route::get('stats', [ThawaniController::class, 'stats']);
    Route::get('config', [ThawaniController::class, 'config']);
    Route::post('config', [ThawaniController::class, 'saveConfig']);
    Route::put('disconnect', [ThawaniController::class, 'disconnect']);
    Route::get('orders', [ThawaniController::class, 'orders']);
    Route::get('product-mappings', [ThawaniController::class, 'productMappings']);
    Route::get('settlements', [ThawaniController::class, 'settlements']);
});
