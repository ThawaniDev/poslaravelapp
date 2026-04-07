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
        Route::get('/', [ProductController::class, 'index'])->middleware('permission:products.view');
        Route::post('/', [ProductController::class, 'store'])->middleware('permission:products.manage');
        Route::get('/catalog', [ProductController::class, 'catalog'])->middleware('permission:products.view');
        Route::get('/changes', [ProductController::class, 'changes'])->middleware('permission:products.view');
        Route::post('/bulk-action', [ProductController::class, 'bulkAction'])->middleware('permission:products.manage');
        Route::get('/{product}', [ProductController::class, 'show'])->middleware('permission:products.view');
        Route::put('/{product}', [ProductController::class, 'update'])->middleware('permission:products.manage');
        Route::delete('/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.manage');
        Route::post('/{product}/duplicate', [ProductController::class, 'duplicate'])->middleware('permission:products.manage');
        Route::post('/{product}/barcode', [ProductController::class, 'generateBarcode'])->middleware('permission:products.manage');
        Route::get('/{product}/barcodes', [ProductController::class, 'barcodes'])->middleware('permission:products.view');
        Route::get('/{product}/variants', [ProductController::class, 'variants'])->middleware('permission:products.view');
        Route::put('/{product}/variants', [ProductController::class, 'syncVariants'])->middleware('permission:products.manage');
        Route::get('/{product}/modifiers', [ProductController::class, 'modifiers'])->middleware('permission:products.view');
        Route::put('/{product}/modifiers', [ProductController::class, 'syncModifiers'])->middleware('permission:products.manage');
        Route::get('/{product}/store-prices', [ProductController::class, 'storePrices'])->middleware('permission:products.manage_pricing');
        Route::put('/{product}/store-prices', [ProductController::class, 'syncStorePrices'])->middleware('permission:products.manage_pricing');
        Route::get('/{product}/suppliers', [ProductController::class, 'suppliers'])->middleware('permission:products.view');
        Route::put('/{product}/suppliers', [ProductController::class, 'syncSuppliers'])->middleware('permission:products.manage_suppliers');
    });

    // ─── Categories ──────────────────────────────────────────
    Route::prefix('categories')->middleware('permission:products.manage_categories')->group(function () {
        Route::get('/', [CategoryController::class, 'tree']);
        Route::post('/', [CategoryController::class, 'store']);
        Route::get('/{category}', [CategoryController::class, 'show']);
        Route::put('/{category}', [CategoryController::class, 'update']);
        Route::delete('/{category}', [CategoryController::class, 'destroy']);
    });

    // ─── Suppliers ───────────────────────────────────────────
    Route::prefix('suppliers')->middleware('permission:products.manage_suppliers')->group(function () {
        Route::get('/', [SupplierController::class, 'index']);
        Route::post('/', [SupplierController::class, 'store']);
        Route::get('/{supplier}', [SupplierController::class, 'show']);
        Route::put('/{supplier}', [SupplierController::class, 'update']);
        Route::delete('/{supplier}', [SupplierController::class, 'destroy']);
    });
});
