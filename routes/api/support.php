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

Route::prefix('support')->middleware(['auth:sanctum', 'plan.active'])->group(function () {
    Route::get('stats', [SupportController::class, 'stats'])->middleware('permission:support.view');
    Route::get('tickets', [SupportController::class, 'index'])->middleware('permission:support.view');
    Route::post('tickets', [SupportController::class, 'store'])->middleware('permission:support.create_ticket');
    Route::get('tickets/{id}', [SupportController::class, 'show'])->middleware('permission:support.view');
    Route::post('tickets/{id}/messages', [SupportController::class, 'addMessage'])->middleware('permission:support.create_ticket');
    Route::put('tickets/{id}/close', [SupportController::class, 'close'])->middleware('permission:support.create_ticket');

    // Knowledge Base (published articles only)
    Route::get('kb', [SupportController::class, 'kbIndex'])->middleware('permission:support.view');
    Route::get('kb/{slug}', [SupportController::class, 'kbShow'])->middleware('permission:support.view');
});
