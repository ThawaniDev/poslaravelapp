<?php

use App\Domain\Security\Controllers\Api\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('security')->group(function () {
    // Overview
    Route::get('overview', [SecurityController::class, 'overview']);

    // Policies
    Route::get('policy', [SecurityController::class, 'getPolicy']);
    Route::put('policy', [SecurityController::class, 'updatePolicy']);

    // Audit Logs
    Route::get('audit-logs', [SecurityController::class, 'listAuditLogs']);
    Route::post('audit-logs', [SecurityController::class, 'recordAudit']);
    Route::get('audit-stats', [SecurityController::class, 'auditStats']);

    // Devices
    Route::get('devices', [SecurityController::class, 'listDevices']);
    Route::post('devices', [SecurityController::class, 'registerDevice']);
    Route::get('devices/{id}', [SecurityController::class, 'showDevice']);
    Route::put('devices/{id}/deactivate', [SecurityController::class, 'deactivateDevice']);
    Route::put('devices/{id}/remote-wipe', [SecurityController::class, 'requestRemoteWipe']);
    Route::put('devices/{id}/heartbeat', [SecurityController::class, 'touchDevice']);

    // Login Attempts
    Route::get('login-attempts', [SecurityController::class, 'listLoginAttempts']);
    Route::post('login-attempts', [SecurityController::class, 'recordLoginAttempt']);
    Route::get('login-attempts/failed-count', [SecurityController::class, 'failedAttemptCount']);
    Route::get('login-attempts/is-locked-out', [SecurityController::class, 'isLockedOut']);
    Route::get('login-attempts/stats', [SecurityController::class, 'loginAttemptStats']);

    // Sessions
    Route::get('sessions', [SecurityController::class, 'listSessions']);
    Route::post('sessions', [SecurityController::class, 'startSession']);
    Route::put('sessions/{id}/end', [SecurityController::class, 'endSession']);
    Route::put('sessions/{id}/heartbeat', [SecurityController::class, 'sessionHeartbeat']);
    Route::post('sessions/end-all', [SecurityController::class, 'endAllSessions']);

    // Incidents
    Route::get('incidents', [SecurityController::class, 'listIncidents']);
    Route::post('incidents', [SecurityController::class, 'createIncident']);
    Route::put('incidents/{id}/resolve', [SecurityController::class, 'resolveIncident']);
});
