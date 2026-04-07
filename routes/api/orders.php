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
    Route::get('/returns/list', [OrderController::class, 'returns'])->middleware('permission:orders.view');
    Route::get('/returns/{returnId}', [OrderController::class, 'showReturn'])->middleware('permission:orders.view');

    // Orders
    Route::get('/', [OrderController::class, 'index'])->middleware('permission:orders.view');
    Route::post('/', [OrderController::class, 'store'])->middleware('permission:orders.manage');
    Route::get('/{order}', [OrderController::class, 'show'])->middleware('permission:orders.view');
    Route::put('/{order}/status', [OrderController::class, 'updateStatus'])->middleware('permission:orders.update_status');
    Route::post('/{order}/void', [OrderController::class, 'void'])->middleware('permission:orders.void');
    Route::post('/{order}/return', [OrderController::class, 'createReturn'])->middleware('permission:orders.return');
});
