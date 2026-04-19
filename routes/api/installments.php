<?php

use App\Http\Controllers\Api\Admin\InstallmentAdminController;
use App\Http\Controllers\Api\InstallmentCheckoutController;
use App\Http\Controllers\Api\StoreInstallmentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Installment Payment Routes
|--------------------------------------------------------------------------
|
| Platform admin: manage installment providers
| Store admin:    configure per-store credentials
| POS checkout:   initiate installment payments
| Callbacks:      provider callback/webhook endpoints (no auth)
|
*/

// ─── Platform Admin ──────────────────────────────────────────────
Route::prefix('admin')->middleware('auth:admin-api')->group(function () {
    Route::prefix('installment-providers')->group(function () {
        Route::get('/', [InstallmentAdminController::class, 'index']);
        Route::get('{id}', [InstallmentAdminController::class, 'show']);
        Route::put('{id}', [InstallmentAdminController::class, 'update']);
        Route::post('{id}/toggle', [InstallmentAdminController::class, 'toggle']);
        Route::post('{id}/maintenance', [InstallmentAdminController::class, 'setMaintenance']);
    });
});

// ─── Store Admin — Installment Config ────────────────────────────
Route::prefix('installments/config')->middleware(['auth:sanctum', 'branch.scope', 'plan.feature:installments'])->group(function () {
    Route::get('available', [StoreInstallmentController::class, 'available'])
        ->middleware('permission:installments.configure');
    Route::get('/', [StoreInstallmentController::class, 'index'])
        ->middleware('permission:installments.configure');
    Route::get('{provider}', [StoreInstallmentController::class, 'show'])
        ->middleware('permission:installments.configure');
    Route::post('/', [StoreInstallmentController::class, 'upsert'])
        ->middleware('permission:installments.configure');
    Route::post('{provider}/toggle', [StoreInstallmentController::class, 'toggle'])
        ->middleware('permission:installments.configure');
    Route::delete('{provider}', [StoreInstallmentController::class, 'destroy'])
        ->middleware('permission:installments.configure');
    Route::post('{provider}/test', [StoreInstallmentController::class, 'testConnection'])
        ->middleware('permission:installments.configure');
});

// ─── POS Checkout — Installment Payments ─────────────────────────
Route::prefix('installments')->middleware(['auth:sanctum', 'branch.scope'])->group(function () {
    Route::get('providers', [InstallmentCheckoutController::class, 'providers'])
        ->middleware('permission:installments.use');
    Route::post('tamara-precheck', [InstallmentCheckoutController::class, 'tamaraPreCheck'])
        ->middleware('permission:installments.use');
    Route::post('checkout', [InstallmentCheckoutController::class, 'createCheckout'])
        ->middleware('permission:installments.use');
    Route::post('{id}/confirm', [InstallmentCheckoutController::class, 'confirmPayment'])
        ->middleware('permission:installments.use');
    Route::post('{id}/cancel', [InstallmentCheckoutController::class, 'cancelPayment'])
        ->middleware('permission:installments.use');
    Route::post('{id}/fail', [InstallmentCheckoutController::class, 'failPayment'])
        ->middleware('permission:installments.use');
    Route::get('{id}', [InstallmentCheckoutController::class, 'show'])
        ->middleware('permission:installments.view_history');
    Route::get('/', [InstallmentCheckoutController::class, 'history'])
        ->middleware('permission:installments.view_history');
});

// ─── Provider Callbacks (no auth — called by providers) ──────────
Route::prefix('installments/callback')->group(function () {
    Route::get('{provider}/success', [InstallmentCheckoutController::class, 'callbackSuccess']);
    Route::get('{provider}/failure', [InstallmentCheckoutController::class, 'callbackFailure']);
    Route::get('{provider}/cancel', [InstallmentCheckoutController::class, 'callbackCancel']);
    Route::post('{provider}/success', [InstallmentCheckoutController::class, 'callbackSuccess']);
    Route::post('{provider}/failure', [InstallmentCheckoutController::class, 'callbackFailure']);
    Route::post('{provider}/cancel', [InstallmentCheckoutController::class, 'callbackCancel']);
});

Route::prefix('installments/webhook')->group(function () {
    Route::post('{provider}', [InstallmentCheckoutController::class, 'webhook']);
});
