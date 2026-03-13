<?php

use App\Domain\LabelPrinting\Controllers\Api\LabelController;
use Illuminate\Support\Facades\Route;

Route::prefix('labels')->middleware('auth:sanctum')->group(function () {

    // Templates
    Route::get('/templates', [LabelController::class, 'index']);
    Route::get('/templates/presets', [LabelController::class, 'presets']);
    Route::post('/templates', [LabelController::class, 'store']);
    Route::get('/templates/{template}', [LabelController::class, 'show']);
    Route::put('/templates/{template}', [LabelController::class, 'update']);
    Route::delete('/templates/{template}', [LabelController::class, 'destroy']);

    // Print history
    Route::get('/print-history', [LabelController::class, 'printHistory']);
    Route::post('/print-history', [LabelController::class, 'recordPrint']);
});
