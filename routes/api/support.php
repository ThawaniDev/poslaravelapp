<?php

use App\Domain\Support\Controllers\Api\SupportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Support API Routes
|--------------------------------------------------------------------------
|
| Routes for the Support feature.
| Prefix: /api/v2/support
|
*/

Route::prefix('support')->middleware('auth:sanctum')->group(function () {
    Route::get('stats', [SupportController::class, 'stats']);
    Route::get('tickets', [SupportController::class, 'index']);
    Route::post('tickets', [SupportController::class, 'store']);
    Route::get('tickets/{id}', [SupportController::class, 'show']);
    Route::post('tickets/{id}/messages', [SupportController::class, 'addMessage']);
    Route::put('tickets/{id}/close', [SupportController::class, 'close']);
});
