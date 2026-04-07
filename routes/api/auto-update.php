<?php

use App\Domain\AppUpdateManagement\Controllers\Api\AutoUpdateController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('auto-update')->group(function () {
    Route::post('check', [AutoUpdateController::class, 'checkForUpdate'])->middleware('permission:auto_update.view');
    Route::post('report-status', [AutoUpdateController::class, 'reportStatus'])->middleware('permission:auto_update.view');
    Route::get('changelog', [AutoUpdateController::class, 'changelog'])->middleware('permission:auto_update.view');
    Route::get('history', [AutoUpdateController::class, 'updateHistory'])->middleware('permission:auto_update.view');
    Route::get('current-version', [AutoUpdateController::class, 'currentVersion'])->middleware('permission:auto_update.view');
    Route::get('manifest/{version}', [AutoUpdateController::class, 'manifest'])->middleware('permission:auto_update.manage');
    Route::get('download/{version}', [AutoUpdateController::class, 'download'])->middleware('permission:auto_update.manage');
    Route::get('rollout-status', [AutoUpdateController::class, 'rolloutStatus'])->middleware('permission:auto_update.view');
});
