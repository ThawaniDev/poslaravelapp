<?php

use App\Domain\Hardware\Controllers\Api\HardwareController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Hardware API Routes
|--------------------------------------------------------------------------
|
| Routes for the Hardware feature.
| Prefix: /api/v2/hardware
|
*/

Route::prefix('hardware')->middleware('auth:sanctum')->group(function () {
    Route::get('config', [HardwareController::class, 'listConfigs']);
    Route::post('config', [HardwareController::class, 'saveConfig']);
    Route::delete('config/{id}', [HardwareController::class, 'removeConfig']);
    Route::get('supported-models', [HardwareController::class, 'supportedModels']);
    Route::post('test', [HardwareController::class, 'testDevice']);
    Route::post('event-log', [HardwareController::class, 'recordEvent']);
    Route::get('event-logs', [HardwareController::class, 'eventLogs']);
});
