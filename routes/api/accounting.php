<?php

use App\Domain\AccountingIntegration\Controllers\Api\AccountingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AccountingIntegration API Routes
|--------------------------------------------------------------------------
|
| Routes for the AccountingIntegration feature.
| Prefix: /api/v2/accounting
|
*/

Route::prefix('accounting')->middleware('auth:sanctum')->group(function () {
    // Connection
    Route::get('status', [AccountingController::class, 'status'])->middleware('permission:accounting.view_history');
    Route::post('connect', [AccountingController::class, 'connect'])->middleware('permission:accounting.connect');
    Route::post('disconnect', [AccountingController::class, 'disconnect'])->middleware('permission:accounting.connect');
    Route::post('refresh-token', [AccountingController::class, 'refreshToken'])->middleware('permission:accounting.connect');

    // POS Account Keys reference
    Route::get('pos-account-keys', [AccountingController::class, 'posAccountKeys'])->middleware('permission:accounting.manage_mappings');

    // Account Mapping
    Route::get('mapping', [AccountingController::class, 'getMappings'])->middleware('permission:accounting.manage_mappings');
    Route::put('mapping', [AccountingController::class, 'saveMappings'])->middleware('permission:accounting.manage_mappings');
    Route::delete('mapping/{id}', [AccountingController::class, 'deleteMapping'])->middleware('permission:accounting.manage_mappings');

    // Auto-Export (before exports/{id} to avoid wildcard match)
    Route::get('auto-export', [AccountingController::class, 'getAutoExport'])->middleware('permission:accounting.export');
    Route::put('auto-export', [AccountingController::class, 'updateAutoExport'])->middleware('permission:accounting.export');

    // Exports
    Route::get('exports', [AccountingController::class, 'listExports'])->middleware('permission:accounting.view_history');
    Route::post('exports', [AccountingController::class, 'triggerExport'])->middleware('permission:accounting.export');
    Route::get('exports/{id}', [AccountingController::class, 'getExport'])->middleware('permission:accounting.view_history');
    Route::post('exports/{id}/retry', [AccountingController::class, 'retryExport'])->middleware('permission:accounting.export');
});
