<?php

use App\Domain\Debit\Controllers\Api\DebitController;
use Illuminate\Support\Facades\Route;

Route::prefix('debits')->middleware('auth:sanctum')->group(function () {
    Route::get('/summary', [DebitController::class, 'summary'])->middleware('permission:customers.manage_debits');
    Route::get('/customer/{customerId}/balance', [DebitController::class, 'customerBalance'])->middleware('permission:customers.manage_debits');
    Route::get('/customer/{customerId}', [DebitController::class, 'customerDebits'])->middleware('permission:customers.manage_debits');

    Route::get('/', [DebitController::class, 'index'])->middleware('permission:customers.manage_debits');
    Route::post('/', [DebitController::class, 'store'])->middleware('permission:customers.manage_debits');
    Route::get('/{debit}', [DebitController::class, 'show'])->middleware('permission:customers.manage_debits');
    Route::put('/{debit}', [DebitController::class, 'update'])->middleware('permission:customers.manage_debits');
    Route::delete('/{debit}', [DebitController::class, 'destroy'])->middleware('permission:customers.manage_debits');
    Route::post('/{debit}/allocate', [DebitController::class, 'allocate'])->middleware('permission:customers.manage_debits');
    Route::get('/{debit}/allocations', [DebitController::class, 'allocations'])->middleware('permission:customers.manage_debits');
    Route::post('/{debit}/reverse', [DebitController::class, 'reverse'])->middleware('permission:customers.manage_debits');
});
