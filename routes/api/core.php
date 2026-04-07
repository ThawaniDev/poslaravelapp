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

        // ─── Store (Branches) ────────────────────────────────
        Route::get('stores/mine', [StoreController::class, 'mine'])->middleware('permission:branches.view');
        Route::get('stores/stats', [StoreController::class, 'stats'])->middleware('permission:branches.view');
        Route::get('stores/managers', [StoreController::class, 'managers'])->middleware('permission:branches.view');
        Route::put('stores/sort-order', [StoreController::class, 'updateSortOrder'])->middleware('permission:branches.manage');
        Route::get('stores', [StoreController::class, 'index'])->middleware('permission:branches.view');
        Route::post('stores', [StoreController::class, 'store'])->middleware('permission:branches.manage');
        Route::get('stores/{id}', [StoreController::class, 'show'])->middleware('permission:branches.view');
        Route::put('stores/{id}', [StoreController::class, 'update'])->middleware('permission:branches.manage');
        Route::delete('stores/{id}', [StoreController::class, 'destroy'])->middleware('permission:branches.manage');
        Route::post('stores/{id}/toggle-active', [StoreController::class, 'toggleActive'])->middleware('permission:branches.manage');
        Route::get('stores/{id}/settings', [StoreController::class, 'settings'])->middleware('permission:settings.view');
        Route::put('stores/{id}/settings', [StoreController::class, 'updateSettings'])->middleware('permission:settings.manage');
        Route::post('stores/{id}/copy-settings', [StoreController::class, 'copySettings'])->middleware('permission:settings.manage');
        Route::get('stores/{id}/working-hours', [StoreController::class, 'workingHours'])->middleware('permission:branches.view');
        Route::put('stores/{id}/working-hours', [StoreController::class, 'updateWorkingHours'])->middleware('permission:branches.manage');
        Route::post('stores/{id}/copy-working-hours', [StoreController::class, 'copyWorkingHours'])->middleware('permission:branches.manage');
        Route::post('stores/{id}/business-type', [StoreController::class, 'applyBusinessType'])->middleware('permission:branches.manage');

        // ─── Business Types ──────────────────────────────────
        Route::get('business-types', [StoreController::class, 'businessTypes'])->middleware('permission:branches.view');

        // ─── Onboarding ──────────────────────────────────────
        Route::get('onboarding/steps', [OnboardingController::class, 'steps'])->middleware('permission:onboarding.manage');
        Route::get('onboarding/progress', [OnboardingController::class, 'progress'])->middleware('permission:onboarding.manage');
        Route::post('onboarding/complete-step', [OnboardingController::class, 'completeStep'])->middleware('permission:onboarding.manage');
        Route::post('onboarding/skip', [OnboardingController::class, 'skip'])->middleware('permission:onboarding.manage');
        Route::post('onboarding/checklist', [OnboardingController::class, 'updateChecklist'])->middleware('permission:onboarding.manage');
        Route::post('onboarding/dismiss-checklist', [OnboardingController::class, 'dismissChecklist'])->middleware('permission:onboarding.manage');
        Route::post('onboarding/reset', [OnboardingController::class, 'reset'])->middleware('permission:onboarding.manage');
    });
});
