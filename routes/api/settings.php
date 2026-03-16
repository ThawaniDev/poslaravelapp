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
    Route::get('locales', [LocalizationController::class, 'listLocales']);
    Route::post('locales', [LocalizationController::class, 'saveLocale']);

    // Master translations
    Route::get('translations', [LocalizationController::class, 'getTranslations']);
    Route::post('translations', [LocalizationController::class, 'saveTranslation']);
    Route::post('translations/bulk-import', [LocalizationController::class, 'bulkImport']);
    Route::get('export-translations', [LocalizationController::class, 'exportTranslations']);

    // Store overrides
    Route::get('translation-overrides', [LocalizationController::class, 'getOverrides']);
    Route::post('translation-overrides', [LocalizationController::class, 'saveOverride']);
    Route::delete('translation-overrides/{id}', [LocalizationController::class, 'removeOverride']);

    // Version control
    Route::post('publish-translations', [LocalizationController::class, 'publishVersion']);
    Route::get('translation-versions', [LocalizationController::class, 'listVersions']);
});
