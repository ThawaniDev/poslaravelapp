<?php

use App\Domain\PredefinedCatalog\Controllers\Api\PredefinedCategoryController;
use App\Domain\PredefinedCatalog\Controllers\Api\PredefinedProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Predefined Catalog API Routes
|--------------------------------------------------------------------------
|
| Routes for managing and cloning predefined products & categories.
| Prefix: /api/v2/predefined-catalog
|
*/

Route::prefix('predefined-catalog')->middleware(['auth:sanctum', 'permission:products.use_predefined'])->group(function () {

    // ─── Predefined Categories ───────────────────────────────
    Route::prefix('categories')->group(function () {
        Route::get('/', [PredefinedCategoryController::class, 'index']);
        Route::get('/tree', [PredefinedCategoryController::class, 'tree']);
        Route::post('/', [PredefinedCategoryController::class, 'store']);
        Route::get('/{id}', [PredefinedCategoryController::class, 'show']);
        Route::put('/{id}', [PredefinedCategoryController::class, 'update']);
        Route::delete('/{id}', [PredefinedCategoryController::class, 'destroy']);
        Route::post('/{id}/clone', [PredefinedCategoryController::class, 'clone']);
    });

    // ─── Predefined Products ─────────────────────────────────
    Route::prefix('products')->group(function () {
        Route::get('/', [PredefinedProductController::class, 'index']);
        Route::post('/', [PredefinedProductController::class, 'store']);
        Route::post('/bulk-action', [PredefinedProductController::class, 'bulkAction']);
        Route::get('/{id}', [PredefinedProductController::class, 'show']);
        Route::put('/{id}', [PredefinedProductController::class, 'update']);
        Route::delete('/{id}', [PredefinedProductController::class, 'destroy']);
        Route::post('/{id}/clone', [PredefinedProductController::class, 'clone']);
    });

    // ─── Clone All ────────────────────────────────────────────
    Route::post('/clone-all', [PredefinedProductController::class, 'cloneAll']);
});
