<?php

use App\Http\Controllers\Api\Core\OnboardingController;
use App\Http\Controllers\Api\Core\StoreController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Core API Routes
|--------------------------------------------------------------------------
|
| Routes for the Core feature.
| Prefix: /api/v2/core
|
*/

Route::prefix('core')->group(function () {

    // All core routes require authentication
    Route::middleware('auth:sanctum')->group(function () {

        // ─── Store ───────────────────────────────────────────
        Route::get('stores/mine', [StoreController::class, 'mine']);
        Route::get('stores', [StoreController::class, 'index']);
        Route::get('stores/{id}', [StoreController::class, 'show']);
        Route::put('stores/{id}', [StoreController::class, 'update']);
        Route::get('stores/{id}/settings', [StoreController::class, 'settings']);
        Route::put('stores/{id}/settings', [StoreController::class, 'updateSettings']);
        Route::get('stores/{id}/working-hours', [StoreController::class, 'workingHours']);
        Route::put('stores/{id}/working-hours', [StoreController::class, 'updateWorkingHours']);
        Route::post('stores/{id}/business-type', [StoreController::class, 'applyBusinessType']);

        // ─── Business Types ──────────────────────────────────
        Route::get('business-types', [StoreController::class, 'businessTypes']);

        // ─── Onboarding ──────────────────────────────────────
        Route::get('onboarding/steps', [OnboardingController::class, 'steps']);
        Route::get('onboarding/progress', [OnboardingController::class, 'progress']);
        Route::post('onboarding/complete-step', [OnboardingController::class, 'completeStep']);
        Route::post('onboarding/skip', [OnboardingController::class, 'skip']);
        Route::post('onboarding/checklist', [OnboardingController::class, 'updateChecklist']);
        Route::post('onboarding/dismiss-checklist', [OnboardingController::class, 'dismissChecklist']);
        Route::post('onboarding/reset', [OnboardingController::class, 'reset']);
    });
});
