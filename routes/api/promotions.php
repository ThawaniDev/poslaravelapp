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
    Route::get('/', [PromotionController::class, 'index']);
    Route::post('/', [PromotionController::class, 'store']);
    Route::get('/{promotion}', [PromotionController::class, 'show']);
    Route::put('/{promotion}', [PromotionController::class, 'update']);
    Route::delete('/{promotion}', [PromotionController::class, 'destroy']);
    Route::post('/{promotion}/toggle', [PromotionController::class, 'toggle']);
    Route::post('/{promotion}/generate-coupons', [PromotionController::class, 'generateCoupons']);
    Route::get('/{promotion}/analytics', [PromotionController::class, 'analytics']);
});

Route::prefix('coupons')->middleware('auth:sanctum')->group(function () {
    Route::post('/validate', [PromotionController::class, 'validateCoupon']);
    Route::post('/redeem', [PromotionController::class, 'redeemCoupon']);
});
