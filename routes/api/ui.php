<?php

use App\Domain\ContentOnboarding\Controllers\Api\LayoutBuilderController;
use App\Domain\ContentOnboarding\Controllers\Api\MarketplaceController;
use App\Domain\ContentOnboarding\Controllers\Api\UiController;
use Illuminate\Support\Facades\Route;

Route::prefix('ui')->middleware('auth:sanctum')->group(function () {
    // Platform defaults (no auth level restriction — any authenticated user)
    Route::get('defaults', [UiController::class, 'defaults']);

    // Layouts (requires business_type param)
    Route::get('layouts', [UiController::class, 'layouts']);

    // Themes
    Route::get('themes', [UiController::class, 'themes']);

    // Resolved user preferences (cascade)
    Route::get('preferences', [UiController::class, 'preferences']);
    Route::put('preferences', [UiController::class, 'updatePreferences']);

    // Store-level defaults (owner only)
    Route::put('store-defaults', [UiController::class, 'updateStoreDefaults']);

    // Receipt layout templates
    Route::get('receipt-templates', [UiController::class, 'receiptTemplates']);
    Route::get('receipt-templates/{slug}', [UiController::class, 'receiptTemplateBySlug']);

    // CFD themes
    Route::get('cfd-themes', [UiController::class, 'cfdThemes']);
    Route::get('cfd-themes/{slug}', [UiController::class, 'cfdThemeBySlug']);

    // Signage templates
    Route::get('signage-templates', [UiController::class, 'signageTemplates']);
    Route::get('signage-templates/{slug}', [UiController::class, 'signageTemplateBySlug']);

    // Label templates
    Route::get('label-templates', [UiController::class, 'labelTemplates']);
    Route::get('label-templates/{slug}', [UiController::class, 'labelTemplateBySlug']);

    // ─── Layout Builder ──────────────────────────────────
    Route::prefix('layout-builder')->group(function () {
        // Widget catalog
        Route::get('widgets', [LayoutBuilderController::class, 'widgetCatalog']);
        Route::get('widgets/{id}', [LayoutBuilderController::class, 'widget']);

        // Canvas configuration
        Route::get('templates/{templateId}/canvas', [LayoutBuilderController::class, 'canvasConfig']);
        Route::put('templates/{templateId}/canvas', [LayoutBuilderController::class, 'updateCanvasConfig']);

        // Widget placements
        Route::get('templates/{templateId}/placements', [LayoutBuilderController::class, 'placements']);
        Route::post('templates/{templateId}/placements', [LayoutBuilderController::class, 'addPlacement']);
        Route::put('templates/{templateId}/placements/batch', [LayoutBuilderController::class, 'batchUpdatePlacements']);
        Route::put('placements/{placementId}', [LayoutBuilderController::class, 'updatePlacement']);
        Route::delete('placements/{placementId}', [LayoutBuilderController::class, 'removePlacement']);

        // Widget theme overrides
        Route::put('placements/{placementId}/theme-overrides', [LayoutBuilderController::class, 'setThemeOverrides']);
        Route::delete('placements/{placementId}/theme-overrides/{variableKey}', [LayoutBuilderController::class, 'removeThemeOverride']);

        // Template cloning
        Route::post('templates/{templateId}/clone', [LayoutBuilderController::class, 'cloneTemplate']);

        // Versioning
        Route::get('templates/{templateId}/versions', [LayoutBuilderController::class, 'versions']);
        Route::post('templates/{templateId}/versions', [LayoutBuilderController::class, 'createVersion']);

        // Full layout export
        Route::get('templates/{templateId}/full', [LayoutBuilderController::class, 'fullLayout']);
    });

    // ─── Marketplace ─────────────────────────────────────
    Route::prefix('marketplace')->group(function () {
        // Browse
        Route::get('listings', [MarketplaceController::class, 'listings']);
        Route::get('listings/{id}', [MarketplaceController::class, 'listing']);

        // Categories
        Route::get('categories', [MarketplaceController::class, 'categories']);
        Route::get('categories/{id}', [MarketplaceController::class, 'category']);

        // Purchases
        Route::post('listings/{listingId}/purchase', [MarketplaceController::class, 'purchase']);
        Route::get('my-purchases', [MarketplaceController::class, 'myPurchases']);
        Route::get('listings/{listingId}/check-access', [MarketplaceController::class, 'checkAccess']);
        Route::post('purchases/{purchaseId}/cancel', [MarketplaceController::class, 'cancelSubscription']);

        // Invoices
        Route::get('my-invoices', [MarketplaceController::class, 'myInvoices']);
        Route::get('invoices/{invoiceId}', [MarketplaceController::class, 'invoice']);

        // Reviews
        Route::get('listings/{listingId}/reviews', [MarketplaceController::class, 'reviews']);
        Route::post('listings/{listingId}/reviews', [MarketplaceController::class, 'createReview']);
        Route::put('reviews/{reviewId}', [MarketplaceController::class, 'updateReview']);
        Route::delete('reviews/{reviewId}', [MarketplaceController::class, 'deleteReview']);
    });
});
