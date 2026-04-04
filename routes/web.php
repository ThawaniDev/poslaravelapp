<?php

use App\Http\Controllers\TemplatePreviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

// ─── Public Template Previews (signed URLs) ──────────────
Route::get('preview/receipt-template/{id}', [TemplatePreviewController::class, 'receiptTemplate'])
    ->name('preview.receipt-template');
Route::get('preview/cfd-theme/{id}', [TemplatePreviewController::class, 'cfdTheme'])
    ->name('preview.cfd-theme');
Route::get('preview/label-template/{id}', [TemplatePreviewController::class, 'labelTemplate'])
    ->name('preview.label-template');
Route::get('preview/marketplace-listing/{id}', [TemplatePreviewController::class, 'marketplaceListing'])
    ->name('preview.marketplace-listing');
