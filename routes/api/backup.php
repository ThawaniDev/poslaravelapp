<?php

use App\Domain\BackupSync\Controllers\Api\BackupController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('backup')->group(function () {
    Route::post('/create', [BackupController::class, 'create']);
    Route::get('/list', [BackupController::class, 'index']);
    Route::get('/schedule', [BackupController::class, 'schedule']);
    Route::put('/schedule', [BackupController::class, 'updateSchedule']);
    Route::get('/storage', [BackupController::class, 'storageUsage']);
    Route::post('/export', [BackupController::class, 'export']);
    Route::get('/provider-status', [BackupController::class, 'providerStatus']);
    Route::get('/{backupId}', [BackupController::class, 'show']);
    Route::post('/{backupId}/restore', [BackupController::class, 'restore']);
    Route::post('/{backupId}/verify', [BackupController::class, 'verify']);
    Route::delete('/{backupId}', [BackupController::class, 'destroy']);
});
