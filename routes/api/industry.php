<?php

use App\Domain\IndustryPharmacy\Controllers\Api\PharmacyController;
use App\Domain\IndustryJewelry\Controllers\Api\JewelryController;
use App\Domain\IndustryElectronics\Controllers\Api\ElectronicsController;
use App\Domain\IndustryFlorist\Controllers\Api\FloristController;
use App\Domain\IndustryBakery\Controllers\Api\BakeryController;
use App\Domain\IndustryRestaurant\Controllers\Api\RestaurantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Industry API Routes
|--------------------------------------------------------------------------
|
| Routes for the Industry feature.
| Prefix: /api/v2/industry
|
*/

Route::prefix('industry')->middleware('auth:sanctum')->group(function () {

    // ── Pharmacy ─────────────────────────────────────────
    Route::prefix('pharmacy')->middleware(['permission:pharmacy.view', 'plan.feature:industry_pharmacy'])->group(function () {
        Route::get('prescriptions', [PharmacyController::class, 'listPrescriptions']);
        Route::post('prescriptions', [PharmacyController::class, 'createPrescription'])->middleware('permission:pharmacy.prescriptions');
        Route::put('prescriptions/{id}', [PharmacyController::class, 'updatePrescription'])->middleware('permission:pharmacy.prescriptions');
        Route::get('drug-schedules', [PharmacyController::class, 'listDrugSchedules']);
        Route::post('drug-schedules', [PharmacyController::class, 'createDrugSchedule'])->middleware('permission:pharmacy.drug_schedules');
        Route::put('drug-schedules/{id}', [PharmacyController::class, 'updateDrugSchedule'])->middleware('permission:pharmacy.drug_schedules');
    });

    // ── Jewelry ──────────────────────────────────────────
    Route::prefix('jewelry')->middleware(['permission:jewelry.view', 'plan.feature:industry_jewelry'])->group(function () {
        Route::get('metal-rates', [JewelryController::class, 'listMetalRates']);
        Route::post('metal-rates', [JewelryController::class, 'upsertMetalRate'])->middleware('permission:jewelry.manage_rates');
        Route::get('product-details', [JewelryController::class, 'listProductDetails']);
        Route::post('product-details', [JewelryController::class, 'createProductDetail'])->middleware('permission:jewelry.manage_details');
        Route::put('product-details/{id}', [JewelryController::class, 'updateProductDetail'])->middleware('permission:jewelry.manage_details');
        Route::get('buybacks', [JewelryController::class, 'listBuybacks']);
        Route::post('buybacks', [JewelryController::class, 'createBuyback'])->middleware('permission:jewelry.buyback');
    });

    // ── Electronics ──────────────────────────────────────
    Route::prefix('electronics')->middleware(['permission:mobile.view', 'plan.feature:industry_electronics'])->group(function () {
        Route::get('imei-records', [ElectronicsController::class, 'listImeiRecords']);
        Route::post('imei-records', [ElectronicsController::class, 'createImeiRecord'])->middleware('permission:mobile.imei');
        Route::put('imei-records/{id}', [ElectronicsController::class, 'updateImeiRecord'])->middleware('permission:mobile.imei');
        Route::get('repair-jobs', [ElectronicsController::class, 'listRepairJobs']);
        Route::post('repair-jobs', [ElectronicsController::class, 'createRepairJob'])->middleware('permission:mobile.repairs');
        Route::put('repair-jobs/{id}', [ElectronicsController::class, 'updateRepairJob'])->middleware('permission:mobile.repairs');
        Route::patch('repair-jobs/{id}/status', [ElectronicsController::class, 'updateRepairJobStatus'])->middleware('permission:mobile.repairs');
        Route::get('trade-ins', [ElectronicsController::class, 'listTradeIns']);
        Route::post('trade-ins', [ElectronicsController::class, 'createTradeIn'])->middleware('permission:mobile.trade_in');
    });

    // ── Florist ──────────────────────────────────────────
    Route::prefix('florist')->middleware(['permission:flowers.view', 'plan.feature:industry_florist'])->group(function () {
        Route::get('arrangements', [FloristController::class, 'listArrangements']);
        Route::post('arrangements', [FloristController::class, 'createArrangement'])->middleware('permission:flowers.arrangements');
        Route::put('arrangements/{id}', [FloristController::class, 'updateArrangement'])->middleware('permission:flowers.arrangements');
        Route::delete('arrangements/{id}', [FloristController::class, 'deleteArrangement'])->middleware('permission:flowers.arrangements');
        Route::get('freshness-logs', [FloristController::class, 'listFreshnessLogs']);
        Route::post('freshness-logs', [FloristController::class, 'createFreshnessLog'])->middleware('permission:flowers.freshness');
        Route::patch('freshness-logs/{id}/status', [FloristController::class, 'updateFreshnessLogStatus'])->middleware('permission:flowers.freshness');
        Route::get('subscriptions', [FloristController::class, 'listSubscriptions']);
        Route::post('subscriptions', [FloristController::class, 'createSubscription'])->middleware('permission:flowers.subscriptions');
        Route::put('subscriptions/{id}', [FloristController::class, 'updateSubscription'])->middleware('permission:flowers.subscriptions');
        Route::patch('subscriptions/{id}/toggle', [FloristController::class, 'toggleSubscription'])->middleware('permission:flowers.subscriptions');
    });

    // ── Bakery ───────────────────────────────────────────
    Route::prefix('bakery')->middleware(['permission:bakery.view', 'plan.feature:industry_bakery'])->group(function () {
        Route::get('recipes', [BakeryController::class, 'listRecipes']);
        Route::post('recipes', [BakeryController::class, 'createRecipe'])->middleware('permission:bakery.recipes');
        Route::put('recipes/{id}', [BakeryController::class, 'updateRecipe'])->middleware('permission:bakery.recipes');
        Route::delete('recipes/{id}', [BakeryController::class, 'deleteRecipe'])->middleware('permission:bakery.recipes');
        Route::get('production-schedules', [BakeryController::class, 'listProductionSchedules']);
        Route::post('production-schedules', [BakeryController::class, 'createProductionSchedule'])->middleware('permission:bakery.production');
        Route::put('production-schedules/{id}', [BakeryController::class, 'updateProductionSchedule'])->middleware('permission:bakery.production');
        Route::patch('production-schedules/{id}/status', [BakeryController::class, 'updateProductionScheduleStatus'])->middleware('permission:bakery.production');
        Route::get('cake-orders', [BakeryController::class, 'listCustomCakeOrders']);
        Route::post('cake-orders', [BakeryController::class, 'createCustomCakeOrder'])->middleware('permission:bakery.custom_orders');
        Route::put('cake-orders/{id}', [BakeryController::class, 'updateCustomCakeOrder'])->middleware('permission:bakery.custom_orders');
        Route::patch('cake-orders/{id}/status', [BakeryController::class, 'updateCustomCakeOrderStatus'])->middleware('permission:bakery.custom_orders');
    });

    // ── Restaurant ───────────────────────────────────────
    Route::prefix('restaurant')->middleware(['permission:restaurant.view', 'plan.feature:industry_restaurant'])->group(function () {
        Route::get('tables', [RestaurantController::class, 'listTables']);
        Route::post('tables', [RestaurantController::class, 'createTable'])->middleware('permission:restaurant.tables');
        Route::put('tables/{id}', [RestaurantController::class, 'updateTable'])->middleware('permission:restaurant.tables');
        Route::patch('tables/{id}/status', [RestaurantController::class, 'updateTableStatus'])->middleware('permission:restaurant.tables');
        Route::get('kitchen-tickets', [RestaurantController::class, 'listKitchenTickets']);
        Route::post('kitchen-tickets', [RestaurantController::class, 'createKitchenTicket'])->middleware('permission:restaurant.kds');
        Route::patch('kitchen-tickets/{id}/status', [RestaurantController::class, 'updateKitchenTicketStatus'])->middleware('permission:restaurant.kds');
        Route::get('reservations', [RestaurantController::class, 'listReservations']);
        Route::post('reservations', [RestaurantController::class, 'createReservation'])->middleware('permission:restaurant.reservations');
        Route::put('reservations/{id}', [RestaurantController::class, 'updateReservation'])->middleware('permission:restaurant.reservations');
        Route::patch('reservations/{id}/status', [RestaurantController::class, 'updateReservationStatus'])->middleware('permission:restaurant.reservations');
        Route::get('tabs', [RestaurantController::class, 'listOpenTabs']);
        Route::post('tabs', [RestaurantController::class, 'openTab'])->middleware('permission:restaurant.tabs');
        Route::patch('tabs/{id}/close', [RestaurantController::class, 'closeTab'])->middleware('permission:restaurant.tabs');
    });
});
