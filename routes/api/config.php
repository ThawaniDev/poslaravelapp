<?php

use App\Domain\SystemConfig\Controllers\Api\ConfigController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Config API Routes
|--------------------------------------------------------------------------
|
| Provider-facing configuration endpoints that the POS app consumes.
| Prefix: /api/v2/config
|
*/

Route::prefix('config')->group(function () {
    // Public (or lightly-authed) endpoint for maintenance check
    Route::get('maintenance', [ConfigController::class, 'maintenance']);

    // Authenticated endpoints
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('feature-flags', [ConfigController::class, 'featureFlags']);
        Route::get('tax', [ConfigController::class, 'tax']);
        Route::get('age-restrictions', [ConfigController::class, 'ageRestrictions']);
        Route::get('payment-methods', [ConfigController::class, 'paymentMethods']);
        Route::get('hardware-catalog', [ConfigController::class, 'hardwareCatalog']);
        Route::get('translations/version', [ConfigController::class, 'translationVersion']);
        Route::get('translations/{locale}', [ConfigController::class, 'translations']);
        Route::get('locales', [ConfigController::class, 'locales']);
        Route::get('security-policies', [ConfigController::class, 'securityPolicies']);
    });
});
