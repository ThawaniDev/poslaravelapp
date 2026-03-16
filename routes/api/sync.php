<?php

use App\Domain\BackupSync\Controllers\Api\SyncController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('sync')->group(function () {
    Route::post('/push', [SyncController::class, 'push']);
    Route::get('/pull', [SyncController::class, 'pull']);
    Route::get('/full', [SyncController::class, 'full']);
    Route::get('/status', [SyncController::class, 'status']);
    Route::post('/resolve-conflict/{conflictId}', [SyncController::class, 'resolveConflict']);
    Route::get('/conflicts', [SyncController::class, 'conflicts']);
    Route::post('/heartbeat', [SyncController::class, 'heartbeat']);
});
