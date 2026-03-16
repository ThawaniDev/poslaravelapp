<?php

use App\Domain\MobileCompanion\Controllers\Api\CompanionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('companion')->group(function () {
    Route::get('/quick-stats', [CompanionController::class, 'quickStats']);
    Route::get('/summary', [CompanionController::class, 'mobileSummary']);
    Route::post('/sessions', [CompanionController::class, 'registerSession']);
    Route::post('/sessions/{sessionId}/end', [CompanionController::class, 'endSession']);
    Route::get('/sessions', [CompanionController::class, 'listSessions']);
    Route::get('/preferences', [CompanionController::class, 'getPreferences']);
    Route::put('/preferences', [CompanionController::class, 'updatePreferences']);
    Route::get('/quick-actions', [CompanionController::class, 'getQuickActions']);
    Route::put('/quick-actions', [CompanionController::class, 'updateQuickActions']);
    Route::post('/events', [CompanionController::class, 'logEvent']);
});
