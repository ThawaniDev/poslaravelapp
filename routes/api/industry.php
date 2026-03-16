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
    Route::prefix('pharmacy')->group(function () {
        Route::get('prescriptions', [PharmacyController::class, 'listPrescriptions']);
        Route::post('prescriptions', [PharmacyController::class, 'createPrescription']);
        Route::put('prescriptions/{id}', [PharmacyController::class, 'updatePrescription']);
        Route::get('drug-schedules', [PharmacyController::class, 'listDrugSchedules']);
        Route::post('drug-schedules', [PharmacyController::class, 'createDrugSchedule']);
        Route::put('drug-schedules/{id}', [PharmacyController::class, 'updateDrugSchedule']);
    });

    // ── Jewelry ──────────────────────────────────────────
    Route::prefix('jewelry')->group(function () {
        Route::get('metal-rates', [JewelryController::class, 'listMetalRates']);
        Route::post('metal-rates', [JewelryController::class, 'upsertMetalRate']);
        Route::get('product-details', [JewelryController::class, 'listProductDetails']);
        Route::post('product-details', [JewelryController::class, 'createProductDetail']);
        Route::put('product-details/{id}', [JewelryController::class, 'updateProductDetail']);
        Route::get('buybacks', [JewelryController::class, 'listBuybacks']);
        Route::post('buybacks', [JewelryController::class, 'createBuyback']);
    });

    // ── Electronics ──────────────────────────────────────
    Route::prefix('electronics')->group(function () {
        Route::get('imei-records', [ElectronicsController::class, 'listImeiRecords']);
        Route::post('imei-records', [ElectronicsController::class, 'createImeiRecord']);
        Route::put('imei-records/{id}', [ElectronicsController::class, 'updateImeiRecord']);
        Route::get('repair-jobs', [ElectronicsController::class, 'listRepairJobs']);
        Route::post('repair-jobs', [ElectronicsController::class, 'createRepairJob']);
        Route::put('repair-jobs/{id}', [ElectronicsController::class, 'updateRepairJob']);
        Route::patch('repair-jobs/{id}/status', [ElectronicsController::class, 'updateRepairJobStatus']);
        Route::get('trade-ins', [ElectronicsController::class, 'listTradeIns']);
        Route::post('trade-ins', [ElectronicsController::class, 'createTradeIn']);
    });

    // ── Florist ──────────────────────────────────────────
    Route::prefix('florist')->group(function () {
        Route::get('arrangements', [FloristController::class, 'listArrangements']);
        Route::post('arrangements', [FloristController::class, 'createArrangement']);
        Route::put('arrangements/{id}', [FloristController::class, 'updateArrangement']);
        Route::delete('arrangements/{id}', [FloristController::class, 'deleteArrangement']);
        Route::get('freshness-logs', [FloristController::class, 'listFreshnessLogs']);
        Route::post('freshness-logs', [FloristController::class, 'createFreshnessLog']);
        Route::patch('freshness-logs/{id}/status', [FloristController::class, 'updateFreshnessLogStatus']);
        Route::get('subscriptions', [FloristController::class, 'listSubscriptions']);
        Route::post('subscriptions', [FloristController::class, 'createSubscription']);
        Route::put('subscriptions/{id}', [FloristController::class, 'updateSubscription']);
        Route::patch('subscriptions/{id}/toggle', [FloristController::class, 'toggleSubscription']);
    });

    // ── Bakery ───────────────────────────────────────────
    Route::prefix('bakery')->group(function () {
        Route::get('recipes', [BakeryController::class, 'listRecipes']);
        Route::post('recipes', [BakeryController::class, 'createRecipe']);
        Route::put('recipes/{id}', [BakeryController::class, 'updateRecipe']);
        Route::delete('recipes/{id}', [BakeryController::class, 'deleteRecipe']);
        Route::get('production-schedules', [BakeryController::class, 'listProductionSchedules']);
        Route::post('production-schedules', [BakeryController::class, 'createProductionSchedule']);
        Route::put('production-schedules/{id}', [BakeryController::class, 'updateProductionSchedule']);
        Route::patch('production-schedules/{id}/status', [BakeryController::class, 'updateProductionScheduleStatus']);
        Route::get('cake-orders', [BakeryController::class, 'listCustomCakeOrders']);
        Route::post('cake-orders', [BakeryController::class, 'createCustomCakeOrder']);
        Route::put('cake-orders/{id}', [BakeryController::class, 'updateCustomCakeOrder']);
        Route::patch('cake-orders/{id}/status', [BakeryController::class, 'updateCustomCakeOrderStatus']);
    });

    // ── Restaurant ───────────────────────────────────────
    Route::prefix('restaurant')->group(function () {
        Route::get('tables', [RestaurantController::class, 'listTables']);
        Route::post('tables', [RestaurantController::class, 'createTable']);
        Route::put('tables/{id}', [RestaurantController::class, 'updateTable']);
        Route::patch('tables/{id}/status', [RestaurantController::class, 'updateTableStatus']);
        Route::get('kitchen-tickets', [RestaurantController::class, 'listKitchenTickets']);
        Route::post('kitchen-tickets', [RestaurantController::class, 'createKitchenTicket']);
        Route::patch('kitchen-tickets/{id}/status', [RestaurantController::class, 'updateKitchenTicketStatus']);
        Route::get('reservations', [RestaurantController::class, 'listReservations']);
        Route::post('reservations', [RestaurantController::class, 'createReservation']);
        Route::put('reservations/{id}', [RestaurantController::class, 'updateReservation']);
        Route::patch('reservations/{id}/status', [RestaurantController::class, 'updateReservationStatus']);
        Route::get('tabs', [RestaurantController::class, 'listOpenTabs']);
        Route::post('tabs', [RestaurantController::class, 'openTab']);
        Route::patch('tabs/{id}/close', [RestaurantController::class, 'closeTab']);
    });
});
