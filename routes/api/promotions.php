<?php

use App\Domain\Promotion\Controllers\Api\PromotionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Promotion API Routes
|--------------------------------------------------------------------------
|
| Routes for the Promotion feature.
| Prefix: /api/v2/promotions
|
*/

Route::prefix('promotions')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [PromotionController::class, 'index'])->middleware('permission:promotions.manage');
    Route::post('/', [PromotionController::class, 'store'])->middleware('permission:promotions.manage');
    Route::get('/{promotion}', [PromotionController::class, 'show'])->middleware('permission:promotions.manage');
    Route::put('/{promotion}', [PromotionController::class, 'update'])->middleware('permission:promotions.manage');
    Route::delete('/{promotion}', [PromotionController::class, 'destroy'])->middleware('permission:promotions.manage');
    Route::post('/{promotion}/toggle', [PromotionController::class, 'toggle'])->middleware('permission:promotions.manage');
    Route::post('/{promotion}/generate-coupons', [PromotionController::class, 'generateCoupons'])->middleware('permission:promotions.manage');
    Route::get('/{promotion}/analytics', [PromotionController::class, 'analytics'])->middleware('permission:promotions.view_analytics');
});

Route::prefix('coupons')->middleware('auth:sanctum')->group(function () {
    Route::post('/validate', [PromotionController::class, 'validateCoupon'])->middleware('permission:promotions.apply_manual');
    Route::post('/redeem', [PromotionController::class, 'redeemCoupon'])->middleware('permission:promotions.apply_manual');
});
