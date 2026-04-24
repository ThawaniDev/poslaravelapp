<?php

use App\Domain\ZatcaCompliance\Controllers\Api\ZatcaComplianceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'plan.feature:zatca_phase2'])->prefix('zatca')->group(function () {
    Route::post('/enroll', [ZatcaComplianceController::class, 'enroll'])->middleware('permission:zatca.manage');
    Route::post('/renew', [ZatcaComplianceController::class, 'renew'])->middleware('permission:zatca.manage');
    Route::post('/submit-invoice', [ZatcaComplianceController::class, 'submitInvoice'])->middleware('permission:zatca.manage');
    Route::post('/submit-batch', [ZatcaComplianceController::class, 'submitBatch'])->middleware('permission:zatca.manage');
    Route::get('/invoices', [ZatcaComplianceController::class, 'invoices'])->middleware('permission:zatca.view');
    Route::get('/invoices/{invoiceId}', [ZatcaComplianceController::class, 'invoiceDetail'])->middleware('permission:zatca.view');
    Route::get('/invoices/{invoiceId}/xml', [ZatcaComplianceController::class, 'invoiceXml'])->middleware('permission:zatca.view');
    Route::post('/invoices/{invoiceId}/retry', [ZatcaComplianceController::class, 'retrySubmission'])->middleware('permission:zatca.manage');
    Route::get('/compliance-summary', [ZatcaComplianceController::class, 'complianceSummary'])->middleware('permission:zatca.view');
    Route::get('/connection', [ZatcaComplianceController::class, 'connectionStatus'])->middleware('permission:zatca.view');
    Route::get('/vat-report', [ZatcaComplianceController::class, 'vatReport'])->middleware('permission:zatca.view');

    // Phase 2 device + chain endpoints
    Route::get('/devices', [ZatcaComplianceController::class, 'listDevices'])->middleware('permission:zatca.view');
    Route::post('/devices', [ZatcaComplianceController::class, 'provisionDevice'])->middleware('permission:zatca.manage');
    Route::post('/devices/activate', [ZatcaComplianceController::class, 'activateDevice'])->middleware('permission:zatca.manage');
    Route::post('/devices/{deviceId}/reset-tamper', [ZatcaComplianceController::class, 'resetDeviceTamper'])->middleware('permission:zatca.manage');
    Route::get('/devices/{deviceId}/verify-chain', [ZatcaComplianceController::class, 'verifyChain'])->middleware('permission:zatca.view');
    Route::get('/dashboard', [ZatcaComplianceController::class, 'dashboard'])->middleware('permission:zatca.view');
});

// SaaS-admin cross-tenant ZATCA overview (org-scoped for non-platform admins).
Route::middleware(['auth:sanctum', 'permission:zatca.view'])->prefix('admin/zatca')->group(function () {
    Route::get('/overview', [ZatcaComplianceController::class, 'adminOverview']);
});
