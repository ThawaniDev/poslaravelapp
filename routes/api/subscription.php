<?php

use App\Http\Controllers\Api\Subscription\InvoiceController;
use App\Http\Controllers\Api\Subscription\PlanController;
use App\Http\Controllers\Api\Subscription\SubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Subscription API Routes
|--------------------------------------------------------------------------
|
| Routes for the Subscription feature.
| Prefix: /api/v2/subscription
|
*/

Route::prefix('subscription')->group(function () {

    // ─── Public: Plan browsing (no auth required) ────────────────
    Route::get('plans', [PlanController::class, 'index']);
    Route::get('plans/slug/{slug}', [PlanController::class, 'showBySlug']);
    Route::get('plans/{planId}', [PlanController::class, 'show']);
    Route::post('plans/compare', [PlanController::class, 'compare']);
    Route::get('add-ons', [PlanController::class, 'addOns']);

    // ─── Authenticated: Subscription management ─────────────────
    Route::middleware('auth:sanctum')->group(function () {
        // Current subscription & lifecycle
        Route::get('current', [SubscriptionController::class, 'current']);
        Route::post('subscribe', [SubscriptionController::class, 'subscribe']);
        Route::put('change-plan', [SubscriptionController::class, 'changePlan']);
        Route::post('cancel', [SubscriptionController::class, 'cancel']);
        Route::post('resume', [SubscriptionController::class, 'resume']);

        // Usage & enforcement
        Route::get('usage', [SubscriptionController::class, 'usage']);
        Route::get('check-feature/{featureKey}', [SubscriptionController::class, 'checkFeature']);
        Route::get('check-limit/{limitKey}', [SubscriptionController::class, 'checkLimit']);

        // Invoices
        Route::get('invoices', [InvoiceController::class, 'index']);
        Route::get('invoices/{invoiceId}', [InvoiceController::class, 'show']);
        Route::get('invoices/{invoiceId}/pdf', [InvoiceController::class, 'downloadPdf']);

        // Sync — offline entitlement cache
        Route::get('sync/entitlements', [SubscriptionController::class, 'syncEntitlements']);

        // Add-ons for current store
        Route::get('store-add-ons', [SubscriptionController::class, 'storeAddOns']);

        // Admin-only plan management
        Route::post('plans', [PlanController::class, 'store']);
        Route::put('plans/{planId}', [PlanController::class, 'update']);
        Route::patch('plans/{planId}/toggle', [PlanController::class, 'toggle']);
        Route::delete('plans/{planId}', [PlanController::class, 'destroy']);
    });
});
