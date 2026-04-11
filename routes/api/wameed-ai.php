<?php

use App\Domain\WameedAI\Controllers\AIChatController;
use App\Domain\WameedAI\Controllers\WameedAIController;
use App\Domain\WameedAI\Controllers\WameedAIAdminController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Wameed AI API Routes
|--------------------------------------------------------------------------
|
| Routes for the Wameed AI module.
| Prefix: /api/v2/wameed-ai
|
*/

Route::prefix('wameed-ai')->middleware('auth:sanctum')->group(function () {

    // ─── Chat (ChatGPT-like interface) ───────────────────────
    Route::get('/models', [AIChatController::class, 'availableModels'])->middleware('permission:wameed_ai.view');
    Route::get('/features/cards', [AIChatController::class, 'featureCards'])->middleware('permission:wameed_ai.view');

    Route::prefix('chats')->middleware('permission:wameed_ai.use')->group(function () {
        Route::get('/', [AIChatController::class, 'index']);
        Route::post('/', [AIChatController::class, 'store']);
        Route::get('/{chatId}', [AIChatController::class, 'show']);
        Route::delete('/{chatId}', [AIChatController::class, 'destroy']);
        Route::put('/{chatId}/title', [AIChatController::class, 'renameChat']);
        Route::post('/{chatId}/messages', [AIChatController::class, 'sendMessage']);
        Route::post('/{chatId}/feature', [AIChatController::class, 'invokeFeature']);
        Route::put('/{chatId}/model', [AIChatController::class, 'changeModel']);
    });

    // ─── Features & Config ───────────────────────────────────
    Route::get('/features', [WameedAIController::class, 'features'])->middleware('permission:wameed_ai.view');
    Route::get('/config', [WameedAIController::class, 'storeConfig'])->middleware('permission:wameed_ai.manage');
    Route::put('/config/{featureId}', [WameedAIController::class, 'updateStoreConfig'])->middleware('permission:wameed_ai.manage');

    // ─── Suggestions ─────────────────────────────────────────
    Route::get('/suggestions', [WameedAIController::class, 'suggestions'])->middleware('permission:wameed_ai.view');
    Route::patch('/suggestions/{suggestionId}/status', [WameedAIController::class, 'updateSuggestionStatus'])->middleware('permission:wameed_ai.view');

    // ─── Feedback ────────────────────────────────────────────
    Route::post('/feedback', [WameedAIController::class, 'submitFeedback'])->middleware('permission:wameed_ai.view');

    // ─── Usage ───────────────────────────────────────────────
    Route::get('/usage', [WameedAIController::class, 'usage'])->middleware('permission:wameed_ai.view');
    Route::get('/usage/history', [WameedAIController::class, 'usageHistory'])->middleware('permission:wameed_ai.view');
    Route::get('/usage/logs', [WameedAIController::class, 'usageLogs'])->middleware('permission:wameed_ai.manage');

    // ─── Inventory Features ──────────────────────────────────
    Route::prefix('inventory')->middleware('permission:wameed_ai.use')->group(function () {
        Route::post('/smart-reorder', [WameedAIController::class, 'smartReorder']);
        Route::post('/expiry-manager', [WameedAIController::class, 'expiryManager']);
        Route::post('/dead-stock', [WameedAIController::class, 'deadStock']);
        Route::post('/shrinkage-detection', [WameedAIController::class, 'shrinkageDetection']);
        Route::post('/supplier-analysis', [WameedAIController::class, 'supplierAnalysis']);
        Route::post('/seasonal-planning', [WameedAIController::class, 'seasonalPlanning']);
    });

    // ─── Sales Features ──────────────────────────────────────
    Route::prefix('sales')->middleware('permission:wameed_ai.use')->group(function () {
        Route::post('/daily-summary', [WameedAIController::class, 'dailySummary']);
        Route::post('/forecast', [WameedAIController::class, 'salesForecast']);
        Route::post('/peak-hours', [WameedAIController::class, 'peakHours']);
        Route::post('/pricing-optimization', [WameedAIController::class, 'pricingOptimization']);
        Route::post('/bundle-suggestions', [WameedAIController::class, 'bundleSuggestions']);
        Route::post('/revenue-anomaly', [WameedAIController::class, 'revenueAnomaly']);
    });

    // ─── Catalog Features ────────────────────────────────────
    Route::prefix('catalog')->middleware('permission:wameed_ai.use')->group(function () {
        Route::post('/product-categorization', [WameedAIController::class, 'productCategorization']);
        Route::post('/invoice-ocr', [WameedAIController::class, 'invoiceOCR']);
        Route::post('/product-description', [WameedAIController::class, 'productDescription']);
        Route::post('/barcode-enrichment', [WameedAIController::class, 'barcodeEnrichment']);
    });

    // ─── Customer Intelligence Features ──────────────────────
    Route::prefix('customers')->middleware('permission:wameed_ai.use')->group(function () {
        Route::post('/segmentation', [WameedAIController::class, 'customerSegmentation']);
        Route::post('/churn-prediction', [WameedAIController::class, 'churnPrediction']);
        Route::post('/personalized-promotions', [WameedAIController::class, 'personalizedPromotions']);
        Route::post('/spending-patterns', [WameedAIController::class, 'spendingPatterns']);
        Route::post('/sentiment-analysis', [WameedAIController::class, 'sentimentAnalysis']);
    });

    // ─── Operations Features ─────────────────────────────────
    Route::prefix('operations')->middleware('permission:wameed_ai.use')->group(function () {
        Route::post('/smart-search', [WameedAIController::class, 'smartSearchQuery']);
        Route::post('/staff-performance', [WameedAIController::class, 'staffPerformance']);
        Route::post('/cashier-errors', [WameedAIController::class, 'cashierErrors']);
        Route::post('/efficiency-score', [WameedAIController::class, 'efficiencyScore']);
    });

    // ─── Communication Features ──────────────────────────────
    Route::prefix('communication')->middleware('permission:wameed_ai.use')->group(function () {
        Route::post('/marketing-generator', [WameedAIController::class, 'marketingGenerator']);
        Route::post('/social-content', [WameedAIController::class, 'socialContent']);
        Route::post('/translate', [WameedAIController::class, 'translateContent']);
    });

    // ─── Financial Features ──────────────────────────────────
    Route::prefix('financial')->middleware('permission:wameed_ai.use')->group(function () {
        Route::post('/margin-analyzer', [WameedAIController::class, 'marginAnalyzer']);
        Route::post('/expense-analysis', [WameedAIController::class, 'expenseAnalysis']);
        Route::post('/cashflow-prediction', [WameedAIController::class, 'cashFlowPrediction']);
    });
});

// ─── Admin Routes ────────────────────────────────────────────
Route::prefix('admin/wameed-ai')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/providers', [WameedAIAdminController::class, 'providerConfigs'])->middleware('permission:admin.wameed_ai.manage');
    Route::post('/providers', [WameedAIAdminController::class, 'updateProviderConfig'])->middleware('permission:admin.wameed_ai.manage');
    Route::put('/providers/{id}', [WameedAIAdminController::class, 'updateProviderConfig'])->middleware('permission:admin.wameed_ai.manage');

    Route::get('/features', [WameedAIAdminController::class, 'allFeatures'])->middleware('permission:admin.wameed_ai.manage');
    Route::patch('/features/{featureId}/toggle', [WameedAIAdminController::class, 'toggleFeature'])->middleware('permission:admin.wameed_ai.manage');

    // ─── Comprehensive Analytics ─────────────────────────────
    Route::get('/analytics/dashboard', [WameedAIAdminController::class, 'analyticsDashboard'])->middleware('permission:admin.wameed_ai.view');
    Route::get('/analytics/chats', [WameedAIAdminController::class, 'analyticsChats'])->middleware('permission:admin.wameed_ai.view');
    Route::get('/analytics/chats/{chatId}', [WameedAIAdminController::class, 'analyticsChatDetail'])->middleware('permission:admin.wameed_ai.view');

    // ─── Legacy endpoints (backward compat) ─────────────────
    Route::get('/platform-usage', [WameedAIAdminController::class, 'platformUsage'])->middleware('permission:admin.wameed_ai.view');
    Route::get('/platform-logs', [WameedAIAdminController::class, 'platformLogs'])->middleware('permission:admin.wameed_ai.view');

    Route::post('/store-health', [WameedAIAdminController::class, 'storeHealth'])->middleware('permission:admin.wameed_ai.manage');
    Route::post('/platform-trends', [WameedAIAdminController::class, 'platformTrends'])->middleware('permission:admin.wameed_ai.manage');

    // ─── LLM Model Management (with metrics) ─────────────────
    Route::get('/llm-models', [WameedAIAdminController::class, 'llmModels'])->middleware('permission:admin.wameed_ai.manage');
    Route::post('/llm-models', [WameedAIAdminController::class, 'createLlmModel'])->middleware('permission:admin.wameed_ai.manage');
    Route::put('/llm-models/{id}', [WameedAIAdminController::class, 'updateLlmModel'])->middleware('permission:admin.wameed_ai.manage');
    Route::patch('/llm-models/{id}/toggle', [WameedAIAdminController::class, 'toggleLlmModel'])->middleware('permission:admin.wameed_ai.manage');
    Route::delete('/llm-models/{id}', [WameedAIAdminController::class, 'deleteLlmModel'])->middleware('permission:admin.wameed_ai.manage');
});
