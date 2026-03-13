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
    Route::get('payments', [PaymentController::class, 'listPayments']);
    Route::post('payments', [PaymentController::class, 'createPayment']);

    // Cash Sessions
    Route::get('cash-sessions', [PaymentController::class, 'listCashSessions']);
    Route::post('cash-sessions', [PaymentController::class, 'openCashSession']);
    Route::get('cash-sessions/{id}', [PaymentController::class, 'showCashSession']);
    Route::put('cash-sessions/{id}/close', [PaymentController::class, 'closeCashSession']);

    // Cash Events
    Route::post('cash-events', [PaymentController::class, 'createCashEvent']);

    // Expenses
    Route::get('expenses', [PaymentController::class, 'listExpenses']);
    Route::post('expenses', [PaymentController::class, 'createExpense']);

    // Gift Cards
    Route::post('gift-cards', [PaymentController::class, 'issueGiftCard']);
    Route::get('gift-cards/{code}/balance', [PaymentController::class, 'checkGiftCardBalance']);
    Route::post('gift-cards/{code}/redeem', [PaymentController::class, 'redeemGiftCard']);
});
