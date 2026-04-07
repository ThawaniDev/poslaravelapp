<?php

use App\Domain\LabelPrinting\Controllers\Api\LabelController;
use Illuminate\Support\Facades\Route;

Route::prefix('labels')->middleware('auth:sanctum')->group(function () {

    // Templates
    Route::get('/templates', [LabelController::class, 'index'])->middleware('permission:labels.view');
    Route::get('/templates/presets', [LabelController::class, 'presets'])->middleware('permission:labels.view');
    Route::post('/templates', [LabelController::class, 'store'])->middleware('permission:labels.manage');
    Route::get('/templates/{template}', [LabelController::class, 'show'])->middleware('permission:labels.view');
    Route::put('/templates/{template}', [LabelController::class, 'update'])->middleware('permission:labels.manage');
    Route::delete('/templates/{template}', [LabelController::class, 'destroy'])->middleware('permission:labels.manage');

    // Print history
    Route::get('/print-history', [LabelController::class, 'printHistory'])->middleware('permission:labels.view');
    Route::post('/print-history', [LabelController::class, 'recordPrint'])->middleware('permission:labels.print');
});
