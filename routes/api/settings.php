<?php

use App\Domain\SystemConfig\Controllers\Api\LocalizationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SystemConfig API Routes
|--------------------------------------------------------------------------
|
| Routes for the SystemConfig feature.
| Prefix: /api/v2/settings
|
*/

Route::prefix('settings')->middleware('auth:sanctum')->group(function () {
    // Locales
    Route::get('locales', [LocalizationController::class, 'listLocales'])->middleware('permission:settings.localization');
    Route::post('locales', [LocalizationController::class, 'saveLocale'])->middleware('permission:settings.localization');

    // Master translations
    Route::get('translations', [LocalizationController::class, 'getTranslations'])->middleware('permission:settings.localization');
    Route::post('translations', [LocalizationController::class, 'saveTranslation'])->middleware('permission:settings.localization');
    Route::post('translations/bulk-import', [LocalizationController::class, 'bulkImport'])->middleware('permission:settings.localization');
    Route::get('export-translations', [LocalizationController::class, 'exportTranslations'])->middleware('permission:settings.localization');

    // Store overrides
    Route::get('translation-overrides', [LocalizationController::class, 'getOverrides'])->middleware('permission:settings.localization');
    Route::post('translation-overrides', [LocalizationController::class, 'saveOverride'])->middleware('permission:settings.localization');
    Route::delete('translation-overrides/{id}', [LocalizationController::class, 'removeOverride'])->middleware('permission:settings.localization');

    // Version control
    Route::post('publish-translations', [LocalizationController::class, 'publishVersion'])->middleware('permission:settings.localization');
    Route::get('translation-versions', [LocalizationController::class, 'listVersions'])->middleware('permission:settings.localization');
});
