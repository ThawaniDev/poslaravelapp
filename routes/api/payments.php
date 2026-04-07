<?php

use App\Domain\Payment\Controllers\Api\PaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Payment API Routes
|--------------------------------------------------------------------------
|
| Routes for the Payment feature.
| Prefix: /api/v2/payments
|
*/

Route::middleware('auth:sanctum')->group(function () {
    // Payments
    Route::get('payments', [PaymentController::class, 'listPayments'])->middleware('permission:payments.process');
    Route::post('payments', [PaymentController::class, 'createPayment'])->middleware('permission:payments.process');

    // Cash Sessions
    Route::get('cash-sessions', [PaymentController::class, 'listCashSessions'])->middleware('permission:cash.view_sessions');
    Route::post('cash-sessions', [PaymentController::class, 'openCashSession'])->middleware('permission:cash.manage');
    Route::get('cash-sessions/{id}', [PaymentController::class, 'showCashSession'])->middleware('permission:cash.view_sessions');
    Route::put('cash-sessions/{id}/close', [PaymentController::class, 'closeCashSession'])->middleware('permission:cash.manage');

    // Cash Events
    Route::post('cash-events', [PaymentController::class, 'createCashEvent'])->middleware('permission:cash.manage');

    // Expenses
    Route::get('expenses', [PaymentController::class, 'listExpenses'])->middleware('permission:finance.expenses');
    Route::post('expenses', [PaymentController::class, 'createExpense'])->middleware('permission:finance.expenses');

    // Gift Cards
    Route::post('gift-cards', [PaymentController::class, 'issueGiftCard'])->middleware('permission:finance.gift_cards');
    Route::get('gift-cards/{code}/balance', [PaymentController::class, 'checkGiftCardBalance'])->middleware('permission:finance.gift_cards');
    Route::post('gift-cards/{code}/redeem', [PaymentController::class, 'redeemGiftCard'])->middleware('permission:finance.gift_cards');

    // Financial Summary
    Route::get('finance/daily-summary', [PaymentController::class, 'dailySummary'])->middleware('permission:cash.view_daily_summary');
    Route::get('finance/reconciliation', [PaymentController::class, 'reconciliation'])->middleware('permission:cash.reconciliation');
});
