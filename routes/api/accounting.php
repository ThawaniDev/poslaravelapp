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
    Route::get('status', [AccountingController::class, 'status']);
    Route::post('connect', [AccountingController::class, 'connect']);
    Route::post('disconnect', [AccountingController::class, 'disconnect']);
    Route::post('refresh-token', [AccountingController::class, 'refreshToken']);

    // POS Account Keys reference
    Route::get('pos-account-keys', [AccountingController::class, 'posAccountKeys']);

    // Account Mapping
    Route::get('mapping', [AccountingController::class, 'getMappings']);
    Route::put('mapping', [AccountingController::class, 'saveMappings']);
    Route::delete('mapping/{id}', [AccountingController::class, 'deleteMapping']);

    // Auto-Export (before exports/{id} to avoid wildcard match)
    Route::get('auto-export', [AccountingController::class, 'getAutoExport']);
    Route::put('auto-export', [AccountingController::class, 'updateAutoExport']);

    // Exports
    Route::get('exports', [AccountingController::class, 'listExports']);
    Route::post('exports', [AccountingController::class, 'triggerExport']);
    Route::get('exports/{id}', [AccountingController::class, 'getExport']);
    Route::post('exports/{id}/retry', [AccountingController::class, 'retryExport']);
});
