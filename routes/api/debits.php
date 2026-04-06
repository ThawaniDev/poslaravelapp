<?php

use App\Domain\Debit\Controllers\Api\DebitController;
use Illuminate\Support\Facades\Route;

Route::prefix('debits')->middleware('auth:sanctum')->group(function () {
    Route::get('/summary', [DebitController::class, 'summary']);
    Route::get('/customer/{customerId}/balance', [DebitController::class, 'customerBalance']);
    Route::get('/customer/{customerId}', [DebitController::class, 'customerDebits']);

    Route::get('/', [DebitController::class, 'index']);
    Route::post('/', [DebitController::class, 'store']);
    Route::get('/{debit}', [DebitController::class, 'show']);
    Route::put('/{debit}', [DebitController::class, 'update']);
    Route::delete('/{debit}', [DebitController::class, 'destroy']);
    Route::post('/{debit}/allocate', [DebitController::class, 'allocate']);
    Route::get('/{debit}/allocations', [DebitController::class, 'allocations']);
    Route::post('/{debit}/reverse', [DebitController::class, 'reverse']);
});
