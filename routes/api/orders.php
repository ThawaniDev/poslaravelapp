<?php

use App\Domain\Order\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Order API Routes
|--------------------------------------------------------------------------
|
| Routes for the Order feature.
| Prefix: /api/v2/orders
|
*/

Route::prefix('orders')->middleware('auth:sanctum')->group(function () {
    // Returns (before wildcard routes)
    Route::get('/returns/list', [OrderController::class, 'returns']);
    Route::get('/returns/{returnId}', [OrderController::class, 'showReturn']);

    // Orders
    Route::get('/', [OrderController::class, 'index']);
    Route::post('/', [OrderController::class, 'store']);
    Route::get('/{order}', [OrderController::class, 'show']);
    Route::put('/{order}/status', [OrderController::class, 'updateStatus']);
    Route::post('/{order}/void', [OrderController::class, 'void']);
    Route::post('/{order}/return', [OrderController::class, 'createReturn']);
});
