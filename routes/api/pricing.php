<?php

use App\Http\Controllers\Api\Content\PricingPageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Pricing Page API Routes
|--------------------------------------------------------------------------
| Public endpoints — no auth required (pricing page is public-facing).
*/

Route::prefix('pricing')->group(function () {
    // GET /v2/pricing — All published pricing page entries (ordered by sort_order)
    Route::get('/', [PricingPageController::class, 'index']);

    // GET /v2/pricing/{planSlug} — Pricing content by plan slug (e.g. "pro", "starter")
    Route::get('/{planSlug}', [PricingPageController::class, 'showBySlug'])
        ->where('planSlug', '[a-z0-9_-]+');

    // GET /v2/pricing/plan/{planId} — Pricing content by plan UUID
    Route::get('/plan/{planId}', [PricingPageController::class, 'showByPlan'])
        ->where('planId', '[0-9a-f-]{36}');
});
