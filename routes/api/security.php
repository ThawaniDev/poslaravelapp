<?php

use App\Domain\Security\Controllers\Api\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'plan.active'])->prefix('security')->group(function () {
    // Overview
    Route::get('overview', [SecurityController::class, 'overview'])->middleware('permission:security.view_dashboard');

    // Policies
    Route::get('policy', [SecurityController::class, 'getPolicy'])->middleware('permission:security.manage_policies');
    Route::put('policy', [SecurityController::class, 'updatePolicy'])->middleware('permission:security.manage_policies');

    // Audit Logs
    Route::get('audit-logs', [SecurityController::class, 'listAuditLogs'])->middleware('permission:security.view_audit');
    Route::post('audit-logs', [SecurityController::class, 'recordAudit'])->middleware('permission:security.view_audit');
    Route::get('audit-logs/export', [SecurityController::class, 'exportAuditLogs'])->middleware(['permission:security.view_audit', 'throttle:5,60']);
    Route::get('audit-stats', [SecurityController::class, 'auditStats'])->middleware('permission:security.view_audit');

    // Devices
    Route::get('devices', [SecurityController::class, 'listDevices'])->middleware('permission:security.view_dashboard');
    Route::post('devices', [SecurityController::class, 'registerDevice'])->middleware('permission:security.manage_policies');
    Route::get('devices/{id}', [SecurityController::class, 'showDevice'])->middleware('permission:security.view_dashboard');
    Route::put('devices/{id}/deactivate', [SecurityController::class, 'deactivateDevice'])->middleware('permission:security.manage_policies');
    Route::put('devices/{id}/remote-wipe', [SecurityController::class, 'requestRemoteWipe'])->middleware('permission:security.manage_policies');
    Route::put('devices/{id}/heartbeat', [SecurityController::class, 'touchDevice'])->middleware('permission:security.view_dashboard');

    // Login Attempts
    Route::get('login-attempts', [SecurityController::class, 'listLoginAttempts'])->middleware('permission:security.view_audit');
    Route::post('login-attempts', [SecurityController::class, 'recordLoginAttempt'])->middleware('permission:security.view_audit');
    Route::get('login-attempts/failed-count', [SecurityController::class, 'failedAttemptCount'])->middleware('permission:security.view_audit');
    Route::get('login-attempts/is-locked-out', [SecurityController::class, 'isLockedOut'])->middleware('permission:security.view_audit');
    Route::get('login-attempts/stats', [SecurityController::class, 'loginAttemptStats'])->middleware('permission:security.view_audit');

    // Sessions
    Route::get('sessions', [SecurityController::class, 'listSessions'])->middleware('permission:security.view_dashboard');
    Route::post('sessions', [SecurityController::class, 'startSession'])->middleware('permission:security.view_dashboard');
    Route::put('sessions/{id}/end', [SecurityController::class, 'endSession'])->middleware('permission:security.view_dashboard');
    Route::put('sessions/{id}/heartbeat', [SecurityController::class, 'sessionHeartbeat'])->middleware('permission:security.view_dashboard');
    Route::post('sessions/end-all', [SecurityController::class, 'endAllSessions'])->middleware('permission:security.manage_policies');

    // Incidents
    Route::get('incidents', [SecurityController::class, 'listIncidents'])->middleware('permission:security.view_dashboard');
    Route::post('incidents', [SecurityController::class, 'createIncident'])->middleware('permission:security.manage_policies');
    Route::put('incidents/{id}/resolve', [SecurityController::class, 'resolveIncident'])->middleware('permission:security.manage_policies');
});
