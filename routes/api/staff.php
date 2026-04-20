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
    Route::get('members', [StaffUserController::class, 'index'])->middleware('permission:staff.view');
    Route::post('members', [StaffUserController::class, 'store'])->middleware(['permission:staff.create', 'plan.limit:staff_members']);
    Route::get('members/stats', [StaffUserController::class, 'stats'])->middleware('permission:staff.view');
    Route::get('members/linkable-users', [StaffUserController::class, 'linkableUsers'])->middleware('permission:staff.view');
    Route::get('members/{id}', [StaffUserController::class, 'show'])->middleware('permission:staff.view');
    Route::put('members/{id}', [StaffUserController::class, 'update'])->middleware('permission:staff.edit');
    Route::delete('members/{id}', [StaffUserController::class, 'destroy'])->middleware('permission:staff.delete');
    Route::post('members/{id}/pin', [StaffUserController::class, 'setPin'])->middleware('permission:staff.manage_pin');
    Route::post('members/{id}/nfc', [StaffUserController::class, 'registerNfc'])->middleware('permission:staff.edit');
    Route::post('members/{id}/link-user', [StaffUserController::class, 'linkUser'])->middleware('permission:staff.edit');
    Route::delete('members/{id}/link-user', [StaffUserController::class, 'unlinkUser'])->middleware('permission:staff.edit');
    Route::get('members/{id}/commissions', [StaffUserController::class, 'commissions'])->middleware('permission:finance.commissions');
    Route::put('members/{id}/commission-config', [StaffUserController::class, 'setCommissionConfig'])->middleware('permission:finance.commissions');
    Route::get('members/{id}/activity-log', [StaffUserController::class, 'activityLog'])->middleware('permission:staff.view');
    Route::get('members/{id}/branch-assignments', [StaffUserController::class, 'branchAssignments'])->middleware('permission:staff.view');
    Route::post('members/{id}/branch-assignments', [StaffUserController::class, 'assignBranch'])->middleware('permission:staff.manage');
    Route::delete('members/{id}/branch-assignments', [StaffUserController::class, 'unassignBranch'])->middleware('permission:staff.manage');

    // ─── Attendance ─────────────────────────────────────────
    Route::get('attendance', [StaffUserController::class, 'attendance'])->middleware('permission:reports.attendance');
    Route::get('attendance/summary', [StaffUserController::class, 'attendanceSummary'])->middleware('permission:reports.attendance');
    Route::post('attendance/clock', [StaffUserController::class, 'clock'])->middleware('permission:staff.view');
    Route::get('attendance/export', [StaffUserController::class, 'attendanceExport'])->middleware('permission:reports.attendance');

    // ─── Shifts ─────────────────────────────────────────────
    Route::get('shifts', [StaffUserController::class, 'shifts'])->middleware('permission:staff.manage_shifts');
    Route::post('shifts', [StaffUserController::class, 'storeShift'])->middleware('permission:staff.manage_shifts');
    Route::post('shifts/bulk', [StaffUserController::class, 'bulkStoreShift'])->middleware('permission:staff.manage_shifts');
    Route::put('shifts/{id}', [StaffUserController::class, 'updateShift'])->middleware('permission:staff.manage_shifts');
    Route::delete('shifts/{id}', [StaffUserController::class, 'destroyShift'])->middleware('permission:staff.manage_shifts');

    // ─── Shift Templates ────────────────────────────────────
    Route::get('shift-templates', [StaffUserController::class, 'shiftTemplates'])->middleware('permission:staff.manage_shifts');
    Route::post('shift-templates', [StaffUserController::class, 'storeShiftTemplate'])->middleware('permission:staff.manage_shifts');
    Route::put('shift-templates/{id}', [StaffUserController::class, 'updateShiftTemplate'])->middleware('permission:staff.manage_shifts');
    Route::delete('shift-templates/{id}', [StaffUserController::class, 'destroyShiftTemplate'])->middleware('permission:staff.manage_shifts');

    // ─── Roles ───────────────────────────────────────────────
    Route::get('roles/user-permissions', [RoleController::class, 'userPermissions']);
    Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.view');
    Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.create');
    Route::get('roles/{role}', [RoleController::class, 'show'])->middleware('permission:roles.view');
    Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.edit');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete');
    Route::post('roles/{role}/assign', [RoleController::class, 'assign'])->middleware('permission:roles.assign');
    Route::post('roles/{role}/unassign', [RoleController::class, 'unassign'])->middleware('permission:roles.assign');

    // ─── Permissions ─────────────────────────────────────────
    Route::get('permissions', [PermissionController::class, 'index'])->middleware('permission:roles.view');
    Route::get('permissions/grouped', [PermissionController::class, 'grouped'])->middleware('permission:roles.view');
    Route::get('permissions/modules', [PermissionController::class, 'modules'])->middleware('permission:roles.view');
    Route::get('permissions/pin-protected', [PermissionController::class, 'pinProtected'])->middleware('permission:roles.view');
    Route::get('permissions/module/{module}', [PermissionController::class, 'forModule'])->middleware('permission:roles.view');

    // ─── PIN Override ────────────────────────────────────────
    Route::post('pin-override', [PinOverrideController::class, 'authorizePin']);
    Route::get('pin-override/check', [PinOverrideController::class, 'check']);
    Route::get('pin-override/history', [PinOverrideController::class, 'history'])->middleware('permission:security.view_audit');
});
