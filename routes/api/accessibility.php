<?php

use App\Domain\Shared\Controllers\Api\AccessibilityController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('accessibility')->group(function () {
    Route::get('preferences', [AccessibilityController::class, 'getPreferences']);
    Route::put('preferences', [AccessibilityController::class, 'updatePreferences']);
    Route::delete('preferences', [AccessibilityController::class, 'resetPreferences']);
    Route::get('shortcuts', [AccessibilityController::class, 'getShortcuts']);
    Route::put('shortcuts', [AccessibilityController::class, 'updateShortcuts']);
});
