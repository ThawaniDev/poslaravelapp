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
    Route::get('config', [HardwareController::class, 'listConfigs'])->middleware('permission:hardware.view');
    Route::post('config', [HardwareController::class, 'saveConfig'])->middleware('permission:hardware.manage');
    Route::delete('config/{id}', [HardwareController::class, 'removeConfig'])->middleware('permission:hardware.manage');
    Route::get('supported-models', [HardwareController::class, 'supportedModels'])->middleware('permission:hardware.view');
    Route::post('test', [HardwareController::class, 'testDevice'])->middleware('permission:hardware.manage');
    Route::post('event-log', [HardwareController::class, 'recordEvent'])->middleware('permission:hardware.view');
    Route::get('event-logs', [HardwareController::class, 'eventLogs'])->middleware('permission:hardware.view');
});
