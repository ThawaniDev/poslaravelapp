<?php

use App\Domain\ProviderPayment\Controllers\Api\ProviderPaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Provider Payment API Routes
|--------------------------------------------------------------------------
|
| Routes for provider payments via PayTabs gateway.
| Prefix: /api/v2/provider-payments
|
*/

// ─── Public: IPN & Payment Return (no auth) ─────────────────
Route::prefix('provider-payments')->group(function () {
    Route::post('ipn', [ProviderPaymentController::class, 'ipn']);
    Route::match(['get', 'post'], 'return', [ProviderPaymentController::class, 'paymentReturn']);
});

// ─── Authenticated: Payment management ──────────────────────
Route::middleware('auth:sanctum')->prefix('provider-payments')->group(function () {
    Route::get('/', [ProviderPaymentController::class, 'index'])->middleware('permission:provider_payments.view');
    Route::get('statistics', [ProviderPaymentController::class, 'statistics'])->middleware('permission:provider_payments.view');
    Route::get('{id}', [ProviderPaymentController::class, 'show'])->middleware('permission:provider_payments.view');
    Route::post('initiate', [ProviderPaymentController::class, 'initiate'])->middleware('permission:provider_payments.create');
    Route::post('{id}/resend-email', [ProviderPaymentController::class, 'resendEmail'])->middleware('permission:provider_payments.view');
});
