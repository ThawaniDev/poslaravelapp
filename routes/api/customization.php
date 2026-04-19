<?php

use App\Domain\PosCustomization\Controllers\Api\CustomizationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'plan.feature:pos_customization'])->prefix('customization')->group(function () {
    // POS Settings
    Route::get('settings', [CustomizationController::class, 'getSettings'])->middleware('permission:pos_customization.view');
    Route::put('settings', [CustomizationController::class, 'updateSettings'])->middleware('permission:pos_customization.manage');
    Route::delete('settings', [CustomizationController::class, 'resetSettings'])->middleware('permission:pos_customization.manage');

    // Receipt Template
    Route::get('receipt', [CustomizationController::class, 'getReceiptTemplate'])->middleware('permission:pos_customization.view');
    Route::put('receipt', [CustomizationController::class, 'updateReceiptTemplate'])->middleware('permission:pos_customization.manage');
    Route::delete('receipt', [CustomizationController::class, 'resetReceiptTemplate'])->middleware('permission:pos_customization.manage');

    // Quick Access
    Route::get('quick-access', [CustomizationController::class, 'getQuickAccess'])->middleware('permission:pos_customization.view');
    Route::put('quick-access', [CustomizationController::class, 'updateQuickAccess'])->middleware('permission:pos_customization.manage');
    Route::delete('quick-access', [CustomizationController::class, 'resetQuickAccess'])->middleware('permission:pos_customization.manage');

    // Export
    Route::get('export', [CustomizationController::class, 'exportAll'])->middleware('permission:pos_customization.view');
});
