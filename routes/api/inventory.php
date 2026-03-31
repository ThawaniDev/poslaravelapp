<?php

use App\Domain\Inventory\Controllers\Api\GoodsReceiptController;
use App\Domain\Inventory\Controllers\Api\PurchaseOrderController;
use App\Domain\Inventory\Controllers\Api\RecipeController;
use App\Domain\Inventory\Controllers\Api\StockAdjustmentController;
use App\Domain\Inventory\Controllers\Api\StockController;
use App\Domain\Inventory\Controllers\Api\StockTransferController;
use App\Domain\Inventory\Controllers\Api\StocktakeController;
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

Route::prefix('inventory')->middleware('auth:sanctum')->group(function () {

    // Stock Levels & Movements
    Route::get('stock-levels', [StockController::class, 'levels']);
    Route::put('stock-levels/{stockLevel}/reorder-point', [StockController::class, 'setReorderPoint']);
    Route::get('stock-movements', [StockController::class, 'movements']);
    Route::get('expiry-alerts', [StockController::class, 'expiryAlerts']);
    Route::get('low-stock', [StockController::class, 'lowStock']);

    // Goods Receipts
    Route::get('goods-receipts', [GoodsReceiptController::class, 'index']);
    Route::post('goods-receipts', [GoodsReceiptController::class, 'store']);
    Route::get('goods-receipts/{goodsReceipt}', [GoodsReceiptController::class, 'show']);
    Route::post('goods-receipts/{goodsReceipt}/confirm', [GoodsReceiptController::class, 'confirm']);

    // Stock Adjustments
    Route::get('stock-adjustments', [StockAdjustmentController::class, 'index']);
    Route::post('stock-adjustments', [StockAdjustmentController::class, 'store']);
    Route::get('stock-adjustments/{stockAdjustment}', [StockAdjustmentController::class, 'show']);

    // Stock Transfers
    Route::get('stock-transfers', [StockTransferController::class, 'index']);
    Route::post('stock-transfers', [StockTransferController::class, 'store']);
    Route::get('stock-transfers/{stockTransfer}', [StockTransferController::class, 'show']);
    Route::post('stock-transfers/{stockTransfer}/approve', [StockTransferController::class, 'approve']);
    Route::post('stock-transfers/{stockTransfer}/receive', [StockTransferController::class, 'receive']);
    Route::post('stock-transfers/{stockTransfer}/cancel', [StockTransferController::class, 'cancel']);

    // Purchase Orders
    Route::get('purchase-orders', [PurchaseOrderController::class, 'index']);
    Route::post('purchase-orders', [PurchaseOrderController::class, 'store']);
    Route::get('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show']);
    Route::post('purchase-orders/{purchaseOrder}/send', [PurchaseOrderController::class, 'send']);
    Route::post('purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive']);
    Route::post('purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel']);

    // Recipes
    Route::get('recipes', [RecipeController::class, 'index']);
    Route::post('recipes', [RecipeController::class, 'store']);
    Route::get('recipes/{recipe}', [RecipeController::class, 'show']);
    Route::put('recipes/{recipe}', [RecipeController::class, 'update']);
    Route::delete('recipes/{recipe}', [RecipeController::class, 'destroy']);

    // Stocktakes
    Route::get('stocktakes', [StocktakeController::class, 'index']);
    Route::post('stocktakes', [StocktakeController::class, 'store']);
    Route::get('stocktakes/{stocktake}', [StocktakeController::class, 'show']);
    Route::put('stocktakes/{stocktake}/counts', [StocktakeController::class, 'updateCounts']);
    Route::post('stocktakes/{stocktake}/apply', [StocktakeController::class, 'apply']);
    Route::post('stocktakes/{stocktake}/cancel', [StocktakeController::class, 'cancel']);

    // Waste Records
    Route::get('waste-records', [WasteController::class, 'index']);
    Route::post('waste-records', [WasteController::class, 'store']);
});
