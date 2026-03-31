<?php

use App\Domain\Security\Controllers\Api\PinOverrideController;
use App\Domain\StaffManagement\Controllers\Api\PermissionController;
use App\Domain\StaffManagement\Controllers\Api\RoleController;
use App\Domain\StaffManagement\Controllers\Api\StaffUserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| StaffManagement API Routes
|--------------------------------------------------------------------------
|
| Routes for Staff CRUD, Attendance, Shifts, Commissions, Roles & Permissions.
| Prefix: /api/v2/staff
|
*/

Route::prefix('staff')->middleware('auth:sanctum')->group(function () {
    // ─── Staff Members ──────────────────────────────────────
    Route::get('members', [StaffUserController::class, 'index']);
    Route::post('members', [StaffUserController::class, 'store']);
    Route::get('members/stats', [StaffUserController::class, 'stats']);
    Route::get('members/linkable-users', [StaffUserController::class, 'linkableUsers']);
    Route::get('members/{id}', [StaffUserController::class, 'show']);
    Route::put('members/{id}', [StaffUserController::class, 'update']);
    Route::delete('members/{id}', [StaffUserController::class, 'destroy']);
    Route::post('members/{id}/pin', [StaffUserController::class, 'setPin']);
    Route::post('members/{id}/nfc', [StaffUserController::class, 'registerNfc']);
    Route::post('members/{id}/link-user', [StaffUserController::class, 'linkUser']);
    Route::delete('members/{id}/link-user', [StaffUserController::class, 'unlinkUser']);
    Route::get('members/{id}/commissions', [StaffUserController::class, 'commissions']);
    Route::put('members/{id}/commission-config', [StaffUserController::class, 'setCommissionConfig']);
    Route::get('members/{id}/activity-log', [StaffUserController::class, 'activityLog']);
    Route::get('members/{id}/branch-assignments', [StaffUserController::class, 'branchAssignments']);
    Route::post('members/{id}/branch-assignments', [StaffUserController::class, 'assignBranch']);
    Route::delete('members/{id}/branch-assignments', [StaffUserController::class, 'unassignBranch']);

    // ─── Attendance ─────────────────────────────────────────
    Route::get('attendance', [StaffUserController::class, 'attendance']);
    Route::post('attendance/clock', [StaffUserController::class, 'clock']);
    Route::get('attendance/export', [StaffUserController::class, 'attendanceExport']);

    // ─── Shifts ─────────────────────────────────────────────
    Route::get('shifts', [StaffUserController::class, 'shifts']);
    Route::post('shifts', [StaffUserController::class, 'storeShift']);
    Route::put('shifts/{id}', [StaffUserController::class, 'updateShift']);
    Route::delete('shifts/{id}', [StaffUserController::class, 'destroyShift']);

    // ─── Shift Templates ────────────────────────────────────
    Route::get('shift-templates', [StaffUserController::class, 'shiftTemplates']);
    Route::post('shift-templates', [StaffUserController::class, 'storeShiftTemplate']);

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
