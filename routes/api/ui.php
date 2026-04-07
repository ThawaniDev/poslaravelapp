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
    Route::get('themes', [UiController::class, 'themes'])->middleware('permission:pos_customization.view');

    // Resolved user preferences (cascade)
    Route::get('preferences', [UiController::class, 'preferences']);
    Route::put('preferences', [UiController::class, 'updatePreferences'])->middleware('permission:accessibility.manage');

    // Store-level defaults (owner only)
    Route::put('store-defaults', [UiController::class, 'updateStoreDefaults'])->middleware('permission:pos_customization.manage');

    // Receipt layout templates
    Route::get('receipt-templates', [UiController::class, 'receiptTemplates'])->middleware('permission:pos_customization.view');
    Route::get('receipt-templates/{slug}', [UiController::class, 'receiptTemplateBySlug'])->middleware('permission:pos_customization.view');
    Route::get('receipt-templates/{id}/preview-url', [UiController::class, 'receiptTemplatePreviewUrl'])->middleware('permission:pos_customization.view');

    // CFD themes
    Route::get('cfd-themes', [UiController::class, 'cfdThemes'])->middleware('permission:pos_customization.view');
    Route::get('cfd-themes/{slug}', [UiController::class, 'cfdThemeBySlug'])->middleware('permission:pos_customization.view');
    Route::get('cfd-themes/{id}/preview-url', [UiController::class, 'cfdThemePreviewUrl'])->middleware('permission:pos_customization.view');

    // Signage templates
    Route::get('signage-templates', [UiController::class, 'signageTemplates'])->middleware('permission:pos_customization.view');
    Route::get('signage-templates/{slug}', [UiController::class, 'signageTemplateBySlug'])->middleware('permission:pos_customization.view');

    // Label templates
    Route::get('label-templates', [UiController::class, 'labelTemplates'])->middleware('permission:pos_customization.view');
    Route::get('label-templates/{slug}', [UiController::class, 'labelTemplateBySlug'])->middleware('permission:pos_customization.view');
    Route::get('label-templates/{id}/preview-url', [UiController::class, 'labelTemplatePreviewUrl'])->middleware('permission:pos_customization.view');

    // ─── Layout Builder ──────────────────────────────────
    Route::prefix('layout-builder')->middleware('permission:layout_builder.view')->group(function () {
        // Widget catalog
        Route::get('widgets', [LayoutBuilderController::class, 'widgetCatalog']);
        Route::get('widgets/{id}', [LayoutBuilderController::class, 'widget']);

        // Flat convenience routes (resolve user's active template)
        Route::get('canvas', [LayoutBuilderController::class, 'activeCanvasConfig']);
        Route::put('canvas', [LayoutBuilderController::class, 'updateActiveCanvasConfig'])->middleware('permission:layout_builder.manage');
        Route::get('placements', [LayoutBuilderController::class, 'activePlacements']);
        Route::post('placements', [LayoutBuilderController::class, 'addActivePlacement'])->middleware('permission:layout_builder.manage');
        Route::get('versions', [LayoutBuilderController::class, 'activeVersions']);
        Route::post('versions', [LayoutBuilderController::class, 'createActiveVersion'])->middleware('permission:layout_builder.manage');
        Route::post('clone', [LayoutBuilderController::class, 'cloneActiveTemplate'])->middleware('permission:layout_builder.manage');
        Route::get('full', [LayoutBuilderController::class, 'activeFullLayout']);

        // Template-scoped routes (explicit templateId)
        Route::get('templates/{templateId}/canvas', [LayoutBuilderController::class, 'canvasConfig']);
        Route::put('templates/{templateId}/canvas', [LayoutBuilderController::class, 'updateCanvasConfig'])->middleware('permission:layout_builder.manage');

        // Widget placements
        Route::get('templates/{templateId}/placements', [LayoutBuilderController::class, 'placements']);
        Route::post('templates/{templateId}/placements', [LayoutBuilderController::class, 'addPlacement'])->middleware('permission:layout_builder.manage');
        Route::put('templates/{templateId}/placements/batch', [LayoutBuilderController::class, 'batchUpdatePlacements'])->middleware('permission:layout_builder.manage');
        Route::put('placements/{placementId}', [LayoutBuilderController::class, 'updatePlacement'])->middleware('permission:layout_builder.manage');
        Route::delete('placements/{placementId}', [LayoutBuilderController::class, 'removePlacement'])->middleware('permission:layout_builder.manage');

        // Widget theme overrides
        Route::put('placements/{placementId}/theme-overrides', [LayoutBuilderController::class, 'setThemeOverrides'])->middleware('permission:layout_builder.manage');
        Route::delete('placements/{placementId}/theme-overrides/{variableKey}', [LayoutBuilderController::class, 'removeThemeOverride'])->middleware('permission:layout_builder.manage');

        // Template cloning
        Route::post('templates/{templateId}/clone', [LayoutBuilderController::class, 'cloneTemplate'])->middleware('permission:layout_builder.manage');

        // Versioning
        Route::get('templates/{templateId}/versions', [LayoutBuilderController::class, 'versions']);
        Route::post('templates/{templateId}/versions', [LayoutBuilderController::class, 'createVersion'])->middleware('permission:layout_builder.manage');

        // Full layout export
        Route::get('templates/{templateId}/full', [LayoutBuilderController::class, 'fullLayout']);
    });

    // ─── Marketplace ─────────────────────────────────────
    Route::prefix('marketplace')->middleware('permission:marketplace.view')->group(function () {
        // Browse
        Route::get('listings', [MarketplaceController::class, 'listings']);
        Route::get('listings/{id}', [MarketplaceController::class, 'listing']);
        Route::get('listings/{id}/preview-url', [UiController::class, 'marketplaceListingPreviewUrl']);

        // Categories
        Route::get('categories', [MarketplaceController::class, 'categories']);
        Route::get('categories/{id}', [MarketplaceController::class, 'category']);

        // Purchases
        Route::post('listings/{listingId}/purchase', [MarketplaceController::class, 'purchase'])->middleware('permission:marketplace.purchase');
        Route::get('my-purchases', [MarketplaceController::class, 'myPurchases']);
        Route::get('listings/{listingId}/check-access', [MarketplaceController::class, 'checkAccess']);
        Route::post('purchases/{purchaseId}/cancel', [MarketplaceController::class, 'cancelSubscription'])->middleware('permission:marketplace.purchase');

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
