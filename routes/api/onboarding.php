<?php

use App\Http\Controllers\Api\Content\BusinessTypeDefaultsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Onboarding — Public Business Types API Routes
|--------------------------------------------------------------------------
| Public endpoints: no auth required.
| These let the Flutter app show the business type picker and defaults
| preview before a store is even created.
*/

Route::prefix('onboarding/business-types')->group(function () {
    // GET /v2/onboarding/business-types — List active business types
    Route::get('/', [BusinessTypeDefaultsController::class, 'index']);

    // GET /v2/onboarding/business-types/{slug}/defaults — Full defaults bundle
    Route::get('/{slug}/defaults', [BusinessTypeDefaultsController::class, 'defaults'])
        ->where('slug', '[a-z0-9_-]+');

    // ── Individual sub-routes ─────────────────────────────────────────────
    Route::get('/{slug}/category-templates', [BusinessTypeDefaultsController::class, 'categoryTemplates'])
        ->where('slug', '[a-z0-9_-]+');
    Route::get('/{slug}/shift-templates', [BusinessTypeDefaultsController::class, 'shiftTemplates'])
        ->where('slug', '[a-z0-9_-]+');
    Route::get('/{slug}/receipt-template', [BusinessTypeDefaultsController::class, 'receiptTemplate'])
        ->where('slug', '[a-z0-9_-]+');
    Route::get('/{slug}/industry-config', [BusinessTypeDefaultsController::class, 'industryConfig'])
        ->where('slug', '[a-z0-9_-]+');
    Route::get('/{slug}/loyalty-config', [BusinessTypeDefaultsController::class, 'loyaltyConfig'])
        ->where('slug', '[a-z0-9_-]+');
    Route::get('/{slug}/customer-groups', [BusinessTypeDefaultsController::class, 'customerGroups'])
        ->where('slug', '[a-z0-9_-]+');
    Route::get('/{slug}/return-policy', [BusinessTypeDefaultsController::class, 'returnPolicy'])
        ->where('slug', '[a-z0-9_-]+');
    Route::get('/{slug}/waste-reasons', [BusinessTypeDefaultsController::class, 'wasteReasons'])
        ->where('slug', '[a-z0-9_-]+');
    Route::get('/{slug}/appointment-config', [BusinessTypeDefaultsController::class, 'appointmentConfig'])
        ->where('slug', '[a-z0-9_-]+');
    Route::get('/{slug}/gift-registry-types', [BusinessTypeDefaultsController::class, 'giftRegistryTypes'])
        ->where('slug', '[a-z0-9_-]+');
    Route::get('/{slug}/gamification-templates', [BusinessTypeDefaultsController::class, 'gamificationTemplates'])
        ->where('slug', '[a-z0-9_-]+');
});
