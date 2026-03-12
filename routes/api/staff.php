<?php

use App\Domain\Security\Controllers\Api\PinOverrideController;
use App\Domain\StaffManagement\Controllers\Api\PermissionController;
use App\Domain\StaffManagement\Controllers\Api\RoleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| StaffManagement API Routes
|--------------------------------------------------------------------------
|
| Routes for Roles, Permissions, PIN Overrides, and Staff management.
| Prefix: /api/v2/staff
|
*/

Route::prefix('staff')->middleware('auth:sanctum')->group(function () {
    // ─── Roles ───────────────────────────────────────────────
    Route::get('roles/user-permissions', [RoleController::class, 'userPermissions']);
    Route::apiResource('roles', RoleController::class);
    Route::post('roles/{role}/assign', [RoleController::class, 'assign']);
    Route::post('roles/{role}/unassign', [RoleController::class, 'unassign']);

    // ─── Permissions ─────────────────────────────────────────
    Route::get('permissions', [PermissionController::class, 'index']);
    Route::get('permissions/grouped', [PermissionController::class, 'grouped']);
    Route::get('permissions/modules', [PermissionController::class, 'modules']);
    Route::get('permissions/pin-protected', [PermissionController::class, 'pinProtected']);
    Route::get('permissions/module/{module}', [PermissionController::class, 'forModule']);

    // ─── PIN Override ────────────────────────────────────────
    Route::post('pin-override', [PinOverrideController::class, 'authorizePin']);
    Route::get('pin-override/check', [PinOverrideController::class, 'check']);
    Route::get('pin-override/history', [PinOverrideController::class, 'history']);
});
