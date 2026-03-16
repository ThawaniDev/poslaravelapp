<?php

use App\Domain\ZatcaCompliance\Controllers\Api\ZatcaComplianceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('zatca')->group(function () {
    Route::post('/enroll', [ZatcaComplianceController::class, 'enroll']);
    Route::post('/renew', [ZatcaComplianceController::class, 'renew']);
    Route::post('/submit-invoice', [ZatcaComplianceController::class, 'submitInvoice']);
    Route::post('/submit-batch', [ZatcaComplianceController::class, 'submitBatch']);
    Route::get('/invoices', [ZatcaComplianceController::class, 'invoices']);
    Route::get('/invoices/{invoiceId}/xml', [ZatcaComplianceController::class, 'invoiceXml']);
    Route::get('/compliance-summary', [ZatcaComplianceController::class, 'complianceSummary']);
    Route::get('/vat-report', [ZatcaComplianceController::class, 'vatReport']);
});
