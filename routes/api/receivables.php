<?php

use App\Domain\Receivable\Controllers\Api\ReceivableController;
use Illuminate\Support\Facades\Route;

Route::prefix('receivables')->middleware(['auth:sanctum', 'permission:customers.manage_receivables'])->group(function () {
    Route::get('/summary', [ReceivableController::class, 'summary']);
    Route::get('/customer/{customerId}/balance', [ReceivableController::class, 'customerBalance']);
    Route::get('/customer/{customerId}', [ReceivableController::class, 'customerReceivables']);

    Route::get('/', [ReceivableController::class, 'index']);
    Route::post('/', [ReceivableController::class, 'store']);
    Route::get('/{receivable}', [ReceivableController::class, 'show']);
    Route::put('/{receivable}', [ReceivableController::class, 'update']);
    Route::delete('/{receivable}', [ReceivableController::class, 'destroy']);

    Route::post('/{receivable}/payments', [ReceivableController::class, 'recordPayment']);
    Route::get('/{receivable}/payments', [ReceivableController::class, 'payments']);
    Route::post('/{receivable}/notes', [ReceivableController::class, 'addNote']);
    Route::get('/{receivable}/logs', [ReceivableController::class, 'logs']);
    Route::post('/{receivable}/reverse', [ReceivableController::class, 'reverse']);
});
