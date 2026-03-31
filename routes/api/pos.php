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
    Route::get('/sessions', [PosTerminalController::class, 'sessions']);
    Route::post('/sessions', [PosTerminalController::class, 'openSession']);
    Route::get('/sessions/{session}', [PosTerminalController::class, 'showSession']);
    Route::put('/sessions/{session}/close', [PosTerminalController::class, 'closeSession']);

    // Transactions
    Route::get('/transactions', [PosTerminalController::class, 'transactions']);
    Route::post('/transactions', [PosTerminalController::class, 'createTransaction']);
    Route::post('/transactions/return', [PosTerminalController::class, 'returnTransaction']);
    Route::get('/transactions/by-number/{number}', [PosTerminalController::class, 'showTransactionByNumber']);
    Route::get('/transactions/{transaction}', [PosTerminalController::class, 'showTransaction']);
    Route::post('/transactions/{transaction}/void', [PosTerminalController::class, 'voidTransaction']);

    // Held Carts
    Route::get('/held-carts', [PosTerminalController::class, 'heldCarts']);
    Route::post('/held-carts', [PosTerminalController::class, 'holdCart']);
    Route::put('/held-carts/{cart}/recall', [PosTerminalController::class, 'recallCart']);
    Route::delete('/held-carts/{cart}', [PosTerminalController::class, 'deleteCart']);

    // Products (POS catalog)
    Route::get('/products', [PosTerminalController::class, 'products']);

    // Customers (POS search)
    Route::get('/customers', [PosTerminalController::class, 'customers']);

    // Terminals (Registers)
    Route::get('/terminals', [RegisterController::class, 'index']);
    Route::post('/terminals', [RegisterController::class, 'store']);
    Route::get('/terminals/{register}', [RegisterController::class, 'show']);
    Route::put('/terminals/{register}', [RegisterController::class, 'update']);
    Route::delete('/terminals/{register}', [RegisterController::class, 'destroy']);
    Route::post('/terminals/{register}/toggle-status', [RegisterController::class, 'toggleStatus']);
});
