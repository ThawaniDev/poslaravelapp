<?php

use App\Domain\Customer\Controllers\Api\CustomerController;
use App\Domain\Customer\Controllers\Api\LoyaltyController;
use Illuminate\Support\Facades\Route;

Route::prefix('customers')->middleware('auth:sanctum')->group(function () {

    // Customer CRUD
    Route::get('/', [CustomerController::class, 'index']);
    Route::post('/', [CustomerController::class, 'store']);
    Route::get('/{customer}', [CustomerController::class, 'show']);
    Route::put('/{customer}', [CustomerController::class, 'update']);
    Route::delete('/{customer}', [CustomerController::class, 'destroy']);

    // Customer groups
    Route::get('/groups/list', [CustomerController::class, 'groups']);
    Route::post('/groups', [CustomerController::class, 'storeGroup']);
    Route::put('/groups/{group}', [CustomerController::class, 'updateGroup']);
    Route::delete('/groups/{group}', [CustomerController::class, 'destroyGroup']);

    // Loyalty config
    Route::get('/loyalty/config', [LoyaltyController::class, 'config']);
    Route::put('/loyalty/config', [LoyaltyController::class, 'saveConfig']);

    // Loyalty transactions
    Route::get('/{customer}/loyalty', [LoyaltyController::class, 'loyaltyLog']);
    Route::post('/{customer}/loyalty/adjust', [LoyaltyController::class, 'adjustPoints']);

    // Store credit
    Route::get('/{customer}/store-credit', [LoyaltyController::class, 'storeCreditLog']);
    Route::post('/{customer}/store-credit/top-up', [LoyaltyController::class, 'topUpCredit']);
});
