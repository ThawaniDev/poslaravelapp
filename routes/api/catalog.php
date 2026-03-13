<?php

use App\Domain\Catalog\Controllers\Api\CategoryController;
use App\Domain\Catalog\Controllers\Api\ProductController;
use App\Domain\Catalog\Controllers\Api\SupplierController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Catalog API Routes
|--------------------------------------------------------------------------
|
| Routes for the Catalog feature.
| Prefix: /api/v2/catalog
|
*/

Route::prefix('catalog')->middleware('auth:sanctum')->group(function () {

    // ─── Products ────────────────────────────────────────────
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::post('/', [ProductController::class, 'store']);
        Route::get('/catalog', [ProductController::class, 'catalog']);
        Route::get('/changes', [ProductController::class, 'changes']);
        Route::get('/{product}', [ProductController::class, 'show']);
        Route::put('/{product}', [ProductController::class, 'update']);
        Route::delete('/{product}', [ProductController::class, 'destroy']);
        Route::post('/{product}/barcode', [ProductController::class, 'generateBarcode']);
        Route::get('/{product}/variants', [ProductController::class, 'variants']);
        Route::put('/{product}/variants', [ProductController::class, 'syncVariants']);
        Route::get('/{product}/modifiers', [ProductController::class, 'modifiers']);
        Route::put('/{product}/modifiers', [ProductController::class, 'syncModifiers']);
    });

    // ─── Categories ──────────────────────────────────────────
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'tree']);
        Route::post('/', [CategoryController::class, 'store']);
        Route::get('/{category}', [CategoryController::class, 'show']);
        Route::put('/{category}', [CategoryController::class, 'update']);
        Route::delete('/{category}', [CategoryController::class, 'destroy']);
    });

    // ─── Suppliers ───────────────────────────────────────────
    Route::prefix('suppliers')->group(function () {
        Route::get('/', [SupplierController::class, 'index']);
        Route::post('/', [SupplierController::class, 'store']);
        Route::get('/{supplier}', [SupplierController::class, 'show']);
        Route::put('/{supplier}', [SupplierController::class, 'update']);
        Route::delete('/{supplier}', [SupplierController::class, 'destroy']);
    });
});
