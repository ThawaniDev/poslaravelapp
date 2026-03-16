<?php

use App\Domain\PosCustomization\Controllers\Api\CustomizationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('customization')->group(function () {
    // POS Settings
    Route::get('settings', [CustomizationController::class, 'getSettings']);
    Route::put('settings', [CustomizationController::class, 'updateSettings']);
    Route::delete('settings', [CustomizationController::class, 'resetSettings']);

    // Receipt Template
    Route::get('receipt', [CustomizationController::class, 'getReceiptTemplate']);
    Route::put('receipt', [CustomizationController::class, 'updateReceiptTemplate']);
    Route::delete('receipt', [CustomizationController::class, 'resetReceiptTemplate']);

    // Quick Access
    Route::get('quick-access', [CustomizationController::class, 'getQuickAccess']);
    Route::put('quick-access', [CustomizationController::class, 'updateQuickAccess']);
    Route::delete('quick-access', [CustomizationController::class, 'resetQuickAccess']);

    // Export
    Route::get('export', [CustomizationController::class, 'exportAll']);
});
