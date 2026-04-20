<?php

use App\Domain\Inventory\Controllers\Api\GoodsReceiptController;
use App\Domain\Inventory\Controllers\Api\PurchaseOrderController;
use App\Domain\Inventory\Controllers\Api\RecipeController;
use App\Domain\Inventory\Controllers\Api\StockAdjustmentController;
use App\Domain\Inventory\Controllers\Api\StockController;
use App\Domain\Inventory\Controllers\Api\StockTransferController;
use App\Domain\Inventory\Controllers\Api\StocktakeController;
use App\Domain\Inventory\Controllers\Api\SupplierReturnController;
use App\Domain\Inventory\Controllers\Api\WasteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Inventory API Routes
|--------------------------------------------------------------------------
|
| Routes for the Inventory feature.
| Prefix: /api/v2/inventory
|
*/

Route::prefix('inventory')->middleware(['auth:sanctum', 'plan.active'])->group(function () {

    // Stock Levels & Movements
    Route::get('stock-levels', [StockController::class, 'levels'])->middleware('permission:inventory.view');
    Route::put('stock-levels/{stockLevel}/reorder-point', [StockController::class, 'setReorderPoint'])->middleware('permission:inventory.manage');
    Route::get('stock-movements', [StockController::class, 'movements'])->middleware('permission:inventory.view');
    Route::get('expiry-alerts', [StockController::class, 'expiryAlerts'])->middleware('permission:inventory.view');
    Route::get('low-stock', [StockController::class, 'lowStock'])->middleware('permission:inventory.view');

    // Goods Receipts
    Route::get('goods-receipts', [GoodsReceiptController::class, 'index'])->middleware('permission:inventory.receive');
    Route::post('goods-receipts', [GoodsReceiptController::class, 'store'])->middleware('permission:inventory.receive');
    Route::get('goods-receipts/{goodsReceipt}', [GoodsReceiptController::class, 'show'])->middleware('permission:inventory.receive');
    Route::post('goods-receipts/{goodsReceipt}/confirm', [GoodsReceiptController::class, 'confirm'])->middleware('permission:inventory.receive');

    // Stock Adjustments
    Route::get('stock-adjustments', [StockAdjustmentController::class, 'index'])->middleware('permission:inventory.adjust');
    Route::post('stock-adjustments', [StockAdjustmentController::class, 'store'])->middleware('permission:inventory.adjust');
    Route::get('stock-adjustments/{stockAdjustment}', [StockAdjustmentController::class, 'show'])->middleware('permission:inventory.adjust');

    // Stock Transfers
    Route::get('stock-transfers', [StockTransferController::class, 'index'])->middleware('permission:inventory.transfer');
    Route::post('stock-transfers', [StockTransferController::class, 'store'])->middleware('permission:inventory.transfer');
    Route::get('stock-transfers/{stockTransfer}', [StockTransferController::class, 'show'])->middleware('permission:inventory.transfer');
    Route::post('stock-transfers/{stockTransfer}/approve', [StockTransferController::class, 'approve'])->middleware('permission:inventory.transfer');
    Route::post('stock-transfers/{stockTransfer}/receive', [StockTransferController::class, 'receive'])->middleware('permission:inventory.transfer');
    Route::post('stock-transfers/{stockTransfer}/cancel', [StockTransferController::class, 'cancel'])->middleware('permission:inventory.transfer');

    // Purchase Orders
    Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])->middleware('permission:inventory.purchase_orders');
    Route::post('purchase-orders', [PurchaseOrderController::class, 'store'])->middleware('permission:inventory.purchase_orders');
    Route::get('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->middleware('permission:inventory.purchase_orders');
    Route::post('purchase-orders/{purchaseOrder}/send', [PurchaseOrderController::class, 'send'])->middleware('permission:inventory.purchase_orders');
    Route::post('purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->middleware('permission:inventory.purchase_orders');
    Route::post('purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->middleware('permission:inventory.purchase_orders');

    // Recipes
    Route::get('recipes', [RecipeController::class, 'index'])->middleware('permission:inventory.recipes');
    Route::post('recipes', [RecipeController::class, 'store'])->middleware('permission:inventory.recipes');
    Route::get('recipes/{recipe}', [RecipeController::class, 'show'])->middleware('permission:inventory.recipes');
    Route::put('recipes/{recipe}', [RecipeController::class, 'update'])->middleware('permission:inventory.recipes');
    Route::delete('recipes/{recipe}', [RecipeController::class, 'destroy'])->middleware('permission:inventory.recipes');

    // Stocktakes
    Route::get('stocktakes', [StocktakeController::class, 'index'])->middleware('permission:inventory.stocktake');
    Route::post('stocktakes', [StocktakeController::class, 'store'])->middleware('permission:inventory.stocktake');
    Route::get('stocktakes/{stocktake}', [StocktakeController::class, 'show'])->middleware('permission:inventory.stocktake');
    Route::put('stocktakes/{stocktake}/counts', [StocktakeController::class, 'updateCounts'])->middleware('permission:inventory.stocktake');
    Route::post('stocktakes/{stocktake}/apply', [StocktakeController::class, 'apply'])->middleware('permission:inventory.stocktake');
    Route::post('stocktakes/{stocktake}/cancel', [StocktakeController::class, 'cancel'])->middleware('permission:inventory.stocktake');

    // Waste Records
    Route::get('waste-records', [WasteController::class, 'index'])->middleware('permission:inventory.write_off');
    Route::post('waste-records', [WasteController::class, 'store'])->middleware('permission:inventory.write_off');

    // Supplier Returns
    Route::get('supplier-returns', [SupplierReturnController::class, 'index'])->middleware('permission:inventory.supplier_returns');
    Route::post('supplier-returns', [SupplierReturnController::class, 'store'])->middleware('permission:inventory.supplier_returns');
    Route::get('supplier-returns/{supplierReturn}', [SupplierReturnController::class, 'show'])->middleware('permission:inventory.supplier_returns');
    Route::put('supplier-returns/{supplierReturn}', [SupplierReturnController::class, 'update'])->middleware('permission:inventory.supplier_returns');
    Route::delete('supplier-returns/{supplierReturn}', [SupplierReturnController::class, 'destroy'])->middleware('permission:inventory.supplier_returns');
    Route::post('supplier-returns/{supplierReturn}/submit', [SupplierReturnController::class, 'submit'])->middleware('permission:inventory.supplier_returns');
    Route::post('supplier-returns/{supplierReturn}/approve', [SupplierReturnController::class, 'approve'])->middleware('permission:inventory.supplier_returns');
    Route::post('supplier-returns/{supplierReturn}/complete', [SupplierReturnController::class, 'complete'])->middleware('permission:inventory.supplier_returns');
    Route::post('supplier-returns/{supplierReturn}/cancel', [SupplierReturnController::class, 'cancel'])->middleware('permission:inventory.supplier_returns');
});
