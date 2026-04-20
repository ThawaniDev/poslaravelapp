<?php

use App\Domain\Customer\Controllers\Api\CustomerController;
use App\Domain\Customer\Controllers\Api\LoyaltyController;
use Illuminate\Support\Facades\Route;

Route::prefix('customers')->middleware(['auth:sanctum', 'plan.active'])->group(function () {

    // Customer CRUD
    Route::get('/', [CustomerController::class, 'index'])->middleware('permission:customers.view');
    Route::post('/', [CustomerController::class, 'store'])->middleware('permission:customers.manage');
    Route::get('/{customer}', [CustomerController::class, 'show'])->middleware('permission:customers.view');
    Route::put('/{customer}', [CustomerController::class, 'update'])->middleware('permission:customers.manage');
    Route::delete('/{customer}', [CustomerController::class, 'destroy'])->middleware('permission:customers.manage');

    // Customer groups
    Route::get('/groups/list', [CustomerController::class, 'groups'])->middleware('permission:customers.manage');
    Route::post('/groups', [CustomerController::class, 'storeGroup'])->middleware('permission:customers.manage');
    Route::put('/groups/{group}', [CustomerController::class, 'updateGroup'])->middleware('permission:customers.manage');
    Route::delete('/groups/{group}', [CustomerController::class, 'destroyGroup'])->middleware('permission:customers.manage');

    // Loyalty config
    Route::get('/loyalty/config', [LoyaltyController::class, 'config'])->middleware('permission:customers.manage_loyalty');
    Route::put('/loyalty/config', [LoyaltyController::class, 'saveConfig'])->middleware('permission:customers.manage_loyalty');

    // Loyalty transactions
    Route::get('/{customer}/loyalty', [LoyaltyController::class, 'loyaltyLog'])->middleware('permission:customers.manage_loyalty');
    Route::post('/{customer}/loyalty/adjust', [LoyaltyController::class, 'adjustPoints'])->middleware('permission:customers.manage_loyalty');

    // Store credit
    Route::get('/{customer}/store-credit', [LoyaltyController::class, 'storeCreditLog'])->middleware('permission:customers.manage_credit');
    Route::post('/{customer}/store-credit/top-up', [LoyaltyController::class, 'topUpCredit'])->middleware('permission:customers.manage_credit');
});
