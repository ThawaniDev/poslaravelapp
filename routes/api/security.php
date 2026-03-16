<?php

use App\Domain\Security\Controllers\Api\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('security')->group(function () {
    // Policies
    Route::get('policy', [SecurityController::class, 'getPolicy']);
    Route::put('policy', [SecurityController::class, 'updatePolicy']);

    // Audit Logs
    Route::get('audit-logs', [SecurityController::class, 'listAuditLogs']);
    Route::post('audit-logs', [SecurityController::class, 'recordAudit']);

    // Devices
    Route::get('devices', [SecurityController::class, 'listDevices']);
    Route::post('devices', [SecurityController::class, 'registerDevice']);
    Route::put('devices/{id}/deactivate', [SecurityController::class, 'deactivateDevice']);
    Route::put('devices/{id}/remote-wipe', [SecurityController::class, 'requestRemoteWipe']);

    // Login Attempts
    Route::get('login-attempts', [SecurityController::class, 'listLoginAttempts']);
    Route::post('login-attempts', [SecurityController::class, 'recordLoginAttempt']);
    Route::get('login-attempts/failed-count', [SecurityController::class, 'failedAttemptCount']);
});
