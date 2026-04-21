<?php

use App\Domain\Core\Controllers\Api\RegisterController;
use App\Domain\PosTerminal\Controllers\Api\PosTerminalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PosTerminal API Routes
|--------------------------------------------------------------------------
|
| Routes for the PosTerminal feature.
| Prefix: /api/v2/pos
|
*/

Route::prefix('pos')->middleware('auth:sanctum')->group(function () {
    // Sessions
    Route::get('/sessions', [PosTerminalController::class, 'sessions'])->middleware('permission:pos.view_sessions');
    Route::get('/sessions/mine/open', [PosTerminalController::class, 'myOpenSessions'])->middleware('permission:pos.sell');
    Route::post('/sessions', [PosTerminalController::class, 'openSession'])->middleware('permission:pos.shift_open');
    Route::get('/sessions/{session}', [PosTerminalController::class, 'showSession'])->middleware('permission:pos.view_sessions');
    Route::put('/sessions/{session}/close', [PosTerminalController::class, 'closeSession'])->middleware('permission:pos.shift_close');

    // Cash events (drop / payout / paid-in) on an open session
    Route::get('/sessions/{session}/cash-events', [PosTerminalController::class, 'cashEvents'])->middleware('permission:pos.view_sessions');
    Route::post('/sessions/{session}/cash-events', [PosTerminalController::class, 'recordCashEvent'])->middleware('permission:pos.shift_open');

    // Shift reports (X mid-shift snapshot, Z end-of-shift)
    Route::get('/sessions/{session}/x-report', [PosTerminalController::class, 'xReport'])->middleware('permission:pos.view_sessions');
    Route::get('/sessions/{session}/z-report', [PosTerminalController::class, 'zReport'])->middleware('permission:pos.shift_close');

    // Transactions
    Route::get('/transactions', [PosTerminalController::class, 'transactions'])->middleware('permission:pos.sell');
    Route::post('/transactions', [PosTerminalController::class, 'createTransaction'])->middleware('permission:pos.sell');
    Route::post('/transactions/return', [PosTerminalController::class, 'returnTransaction'])->middleware('permission:pos.return');
    Route::get('/transactions/by-number/{number}', [PosTerminalController::class, 'showTransactionByNumber'])->middleware('permission:pos.sell');
    Route::get('/transactions/{transaction}', [PosTerminalController::class, 'showTransaction'])->middleware('permission:pos.sell');
    Route::get('/transactions/{transaction}/receipt', [PosTerminalController::class, 'transactionReceipt'])->middleware('permission:pos.sell');
    Route::post('/transactions/{transaction}/void', [PosTerminalController::class, 'voidTransaction'])->middleware('permission:pos.void_transaction');
    Route::post('/transactions/exchange', [PosTerminalController::class, 'exchangeTransaction'])->middleware('permission:pos.return');

    // Held Carts
    Route::get('/held-carts', [PosTerminalController::class, 'heldCarts'])->middleware('permission:pos.hold_recall');
    Route::post('/held-carts', [PosTerminalController::class, 'holdCart'])->middleware('permission:pos.hold_recall');
    Route::put('/held-carts/{cart}/recall', [PosTerminalController::class, 'recallCart'])->middleware('permission:pos.hold_recall');
    Route::delete('/held-carts/{cart}', [PosTerminalController::class, 'deleteCart'])->middleware('permission:pos.hold_recall');

    // Products (POS catalog)
    Route::get('/products', [PosTerminalController::class, 'products'])->middleware('permission:pos.sell');

    // Customers (POS search)
    Route::get('/customers', [PosTerminalController::class, 'customers'])->middleware('permission:pos.sell');

    // Registers (read-only for cashiers to select during shift opening)
    Route::get('/registers', [RegisterController::class, 'listActive'])->middleware('permission:pos.sell');

    // Terminals (Registers) — full CRUD for admins
    Route::middleware('permission:pos.manage_terminals')->group(function () {
        Route::get('/terminals', [RegisterController::class, 'index']);
        Route::post('/terminals', [RegisterController::class, 'store'])->middleware('plan.limit:cashier_terminals');
        Route::get('/terminals/{register}', [RegisterController::class, 'show']);
        Route::put('/terminals/{register}', [RegisterController::class, 'update']);
        Route::delete('/terminals/{register}', [RegisterController::class, 'destroy']);
        Route::post('/terminals/{register}/toggle-status', [RegisterController::class, 'toggleStatus']);
    });
});
