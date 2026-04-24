<?php

use App\Domain\Customer\Controllers\Api\CustomerController;
use App\Domain\Customer\Controllers\Api\LoyaltyController;
use Illuminate\Support\Facades\Route;

Route::prefix('customers')
    ->middleware(['auth:sanctum', 'plan.active', 'plan.feature:customer_management'])
    ->group(function () {

        // Customer CRUD
        Route::get('/', [CustomerController::class, 'index'])->middleware('permission:customers.view');
        Route::get('/search', [CustomerController::class, 'search'])->middleware('permission:customers.view');
        Route::post('/bulk/assign-group', [CustomerController::class, 'bulkAssignGroup'])->middleware('permission:customers.manage');
        Route::post('/', [CustomerController::class, 'store'])->middleware('permission:customers.manage');
        Route::get('/{customer}', [CustomerController::class, 'show'])->middleware('permission:customers.view');
        Route::put('/{customer}', [CustomerController::class, 'update'])->middleware('permission:customers.manage');
        Route::delete('/{customer}', [CustomerController::class, 'destroy'])->middleware('permission:customers.manage');

        // Customer history & receipts
        Route::get('/{customer}/orders', [CustomerController::class, 'orders'])->middleware('permission:customers.view');
        Route::post('/{customer}/receipt', [CustomerController::class, 'sendReceipt'])->middleware('permission:customers.view');

        // Customer groups
        Route::get('/groups/list', [CustomerController::class, 'groups'])->middleware('permission:customers.view');
        Route::post('/groups', [CustomerController::class, 'storeGroup'])->middleware('permission:customers.manage');
        Route::put('/groups/{group}', [CustomerController::class, 'updateGroup'])->middleware('permission:customers.manage');
        Route::delete('/groups/{group}', [CustomerController::class, 'destroyGroup'])->middleware('permission:customers.manage');

        // Loyalty config + adjustments (requires customer_loyalty add-on)
        Route::middleware('plan.feature:customer_loyalty')->group(function () {
            Route::get('/loyalty/config', [LoyaltyController::class, 'config'])->middleware('permission:customers.manage_loyalty');
            Route::put('/loyalty/config', [LoyaltyController::class, 'saveConfig'])->middleware('permission:customers.manage_loyalty');

            Route::get('/{customer}/loyalty', [LoyaltyController::class, 'loyaltyLog'])->middleware('permission:customers.view');
            Route::post('/{customer}/loyalty/adjust', [LoyaltyController::class, 'adjustPoints'])->middleware('permission:customers.manage_loyalty');
            Route::post('/{customer}/loyalty/redeem', [LoyaltyController::class, 'redeemPoints'])->middleware('permission:customers.manage_loyalty');
        });

        // Store credit
        Route::get('/{customer}/store-credit', [LoyaltyController::class, 'storeCreditLog'])->middleware('permission:customers.view');
        Route::post('/{customer}/store-credit/top-up', [LoyaltyController::class, 'topUpCredit'])->middleware('permission:customers.manage_credit');
        Route::post('/{customer}/store-credit/adjust', [LoyaltyController::class, 'adjustCredit'])->middleware('permission:customers.manage_credit');
    });

// Delta sync endpoint for the desktop POS
Route::middleware(['auth:sanctum', 'plan.active', 'plan.feature:customer_management', 'permission:customers.view'])
    ->get('/pos/customers/sync', [CustomerController::class, 'sync']);
