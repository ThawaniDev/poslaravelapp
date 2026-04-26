<?php

use App\Domain\BackupSync\Controllers\Api\SyncController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'plan.active'])->prefix('sync')->group(function () {
    Route::post('/push', [SyncController::class, 'push'])->middleware('permission:sync.manage');
    Route::get('/pull', [SyncController::class, 'pull'])->middleware('permission:sync.view');
    Route::get('/full', [SyncController::class, 'full'])->middleware('permission:sync.manage');
    Route::get('/status', [SyncController::class, 'status'])->middleware('permission:sync.view');
    Route::post('/resolve-conflict/{conflictId}', [SyncController::class, 'resolveConflict'])->middleware('permission:sync.manage');
    Route::get('/conflicts', [SyncController::class, 'conflicts'])->middleware('permission:sync.view');
    Route::post('/heartbeat', [SyncController::class, 'heartbeat'])->middleware('permission:sync.view');
    Route::get('/logs', [SyncController::class, 'logs'])->middleware('permission:sync.view');
});
