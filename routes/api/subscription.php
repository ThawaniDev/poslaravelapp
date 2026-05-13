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
        // Discount code validation (public — available without active subscription)
        Route::post('validate-discount', [SubscriptionController::class, 'validateDiscount']);

        // Current subscription & lifecycle
        Route::get('current', [SubscriptionController::class, 'current'])->middleware('permission:subscription.view');
        Route::post('subscribe', [SubscriptionController::class, 'subscribe'])->middleware('permission:subscription.manage');
        Route::put('change-plan', [SubscriptionController::class, 'changePlan'])->middleware('permission:subscription.manage');
        Route::post('cancel', [SubscriptionController::class, 'cancel'])->middleware('permission:subscription.manage');
        Route::post('resume', [SubscriptionController::class, 'resume'])->middleware('permission:subscription.manage');

        // Usage & enforcement
        Route::get('usage', [SubscriptionController::class, 'usage'])->middleware('permission:subscription.view');
        Route::get('check-feature/{featureKey}', [SubscriptionController::class, 'checkFeature'])->middleware('permission:subscription.view');
        Route::get('check-limit/{limitKey}', [SubscriptionController::class, 'checkLimit'])->middleware('permission:subscription.view');
        Route::get('features', [SubscriptionController::class, 'allFeatures'])->middleware('permission:subscription.view');
        Route::get('feature-route-mapping', [SubscriptionController::class, 'featureRouteMapping'])->middleware('permission:subscription.view');

        // SoftPOS threshold & transactions
        Route::prefix('softpos')->middleware('permission:subscription.view')->group(function () {
            Route::get('info', [SubscriptionController::class, 'softPosInfo']);
            Route::get('statistics', [SubscriptionController::class, 'softPosStatistics']);
            Route::get('transactions', [SubscriptionController::class, 'softPosTransactions']);
            Route::post('record', [SubscriptionController::class, 'recordSoftPosTransaction'])
                ->middleware(['permission:subscription.manage', 'throttle:60,1']);
        });

        // Invoices
        Route::get('invoices', [InvoiceController::class, 'index'])->middleware('permission:subscription.view');
        Route::get('invoices/{invoiceId}', [InvoiceController::class, 'show'])->middleware('permission:subscription.view');
        Route::get('invoices/{invoiceId}/pdf', [InvoiceController::class, 'downloadPdf'])->middleware('permission:subscription.view');

        // Sync — offline entitlement cache
        Route::get('sync/entitlements', [SubscriptionController::class, 'syncEntitlements'])->middleware('permission:subscription.view');

        // Add-ons for current store
        Route::get('store-add-ons', [SubscriptionController::class, 'storeAddOns'])->middleware('permission:subscription.view');
        Route::post('store-add-ons/{addOnId}/activate', [SubscriptionController::class, 'activateAddOn'])->middleware('permission:subscription.manage');
        Route::delete('store-add-ons/{addOnId}', [SubscriptionController::class, 'removeAddOn'])->middleware('permission:subscription.manage');

        // Admin-only plan management
        Route::post('plans', [PlanController::class, 'store'])->middleware('permission:subscription.manage');
        Route::put('plans/{planId}', [PlanController::class, 'update'])->middleware('permission:subscription.manage');
        Route::patch('plans/{planId}/toggle', [PlanController::class, 'toggle'])->middleware('permission:subscription.manage');
        Route::delete('plans/{planId}', [PlanController::class, 'destroy'])->middleware('permission:subscription.manage');
    });
});
