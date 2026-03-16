<?php

use App\Domain\AppUpdateManagement\Controllers\Api\AutoUpdateController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('auto-update')->group(function () {
    Route::post('check', [AutoUpdateController::class, 'checkForUpdate']);
    Route::post('report-status', [AutoUpdateController::class, 'reportStatus']);
    Route::get('changelog', [AutoUpdateController::class, 'changelog']);
    Route::get('history', [AutoUpdateController::class, 'updateHistory']);
    Route::get('current-version', [AutoUpdateController::class, 'currentVersion']);
});
