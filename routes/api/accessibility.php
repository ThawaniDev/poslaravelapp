<?php

use App\Domain\Shared\Controllers\Api\AccessibilityController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('accessibility')->group(function () {
    Route::get('preferences', [AccessibilityController::class, 'getPreferences'])->middleware('permission:accessibility.manage');
    Route::put('preferences', [AccessibilityController::class, 'updatePreferences'])->middleware('permission:accessibility.manage');
    Route::delete('preferences', [AccessibilityController::class, 'resetPreferences'])->middleware('permission:accessibility.manage');
    Route::get('shortcuts', [AccessibilityController::class, 'getShortcuts'])->middleware('permission:accessibility.manage');
    Route::put('shortcuts', [AccessibilityController::class, 'updateShortcuts'])->middleware('permission:accessibility.manage');
});
