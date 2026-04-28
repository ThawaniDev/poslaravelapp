<?php

use App\Domain\BackupSync\Controllers\Api\BackupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'plan.active'])->prefix('backup')->group(function () {
    Route::post('/create', [BackupController::class, 'create'])->middleware('permission:backup.manage');
    Route::get('/list', [BackupController::class, 'index'])->middleware('permission:backup.view');
    Route::get('/schedule', [BackupController::class, 'schedule'])->middleware('permission:backup.view');
    Route::put('/schedule', [BackupController::class, 'updateSchedule'])->middleware('permission:backup.manage');
    Route::get('/storage', [BackupController::class, 'storageUsage'])->middleware('permission:backup.view');
    Route::post('/export', [BackupController::class, 'export'])->middleware('permission:backup.manage');
    Route::get('/provider-status', [BackupController::class, 'providerStatus'])->middleware('permission:backup.view');
    Route::get('/{backupId}', [BackupController::class, 'show'])->middleware('permission:backup.view');
    Route::post('/{backupId}/restore', [BackupController::class, 'restore'])->middleware('permission:backup.manage');
    Route::post('/{backupId}/verify', [BackupController::class, 'verify'])->middleware('permission:backup.manage');
    Route::delete('/{backupId}', [BackupController::class, 'destroy'])->middleware('permission:backup.manage');
});
