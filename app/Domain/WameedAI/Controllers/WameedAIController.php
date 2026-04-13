<?php

namespace App\Domain\WameedAI\Controllers;

use App\Domain\WameedAI\Models\AIFeatureDefinition;
use App\Domain\WameedAI\Models\AIStoreFeatureConfig;
use App\Domain\WameedAI\Models\AISuggestion;
use App\Domain\WameedAI\Models\AIFeedback;
use App\Domain\WameedAI\Models\AIUsageLog;
use App\Domain\WameedAI\Models\AIDailyUsageSummary;
use App\Domain\WameedAI\Requests\BarcodeEnrichmentRequest;
use App\Domain\WameedAI\Requests\GenerateMarketingRequest;
use App\Domain\WameedAI\Requests\GenerateSocialContentRequest;
use App\Domain\WameedAI\Requests\InvoiceOCRRequest;
use App\Domain\WameedAI\Requests\InvokeFeatureRequest;
use App\Domain\WameedAI\Requests\ProductDescriptionRequest;
use App\Domain\WameedAI\Requests\SmartSearchRequest;
use App\Domain\WameedAI\Requests\SpendingPatternRequest;
use App\Domain\WameedAI\Requests\SubmitFeedbackRequest;
use App\Domain\WameedAI\Requests\TranslateRequest;
use App\Domain\WameedAI\Requests\UpdateFeatureConfigRequest;
use App\Domain\WameedAI\Requests\UpdateSuggestionStatusRequest;
use App\Domain\WameedAI\Resources\AIFeatureDefinitionResource;
use App\Domain\WameedAI\Resources\AIStoreFeatureConfigResource;
use App\Domain\WameedAI\Resources\AISuggestionResource;
use App\Domain\WameedAI\Resources\AIFeedbackResource;
use App\Domain\WameedAI\Resources\AIUsageLogResource;
use App\Domain\WameedAI\Resources\AIDailyUsageSummaryResource;
use App\Domain\WameedAI\Services\AIBillingService;
use App\Domain\WameedAI\Services\AIUsageTrackingService;
use App\Domain\WameedAI\Services\Features\BarcodeEnrichmentService;
use App\Domain\WameedAI\Services\Features\BundleSuggestionService;
use App\Domain\WameedAI\Services\Features\CashFlowPredictionService;
use App\Domain\WameedAI\Services\Features\CashierErrorService;
use App\Domain\WameedAI\Services\Features\ChurnPredictionService;
use App\Domain\WameedAI\Services\Features\CustomerSegmentationService;
use App\Domain\WameedAI\Services\Features\DailySummaryService;
use App\Domain\WameedAI\Services\Features\DeadStockService;
use App\Domain\WameedAI\Services\Features\EfficiencyScoreService;
use App\Domain\WameedAI\Services\Features\ExpenseAnalysisService;
use App\Domain\WameedAI\Services\Features\ExpiryManagerService;
use App\Domain\WameedAI\Services\Features\InvoiceOCRService;
use App\Domain\WameedAI\Services\Features\MarginAnalyzerService;
use App\Domain\WameedAI\Services\Features\MarketingGeneratorService;
use App\Domain\WameedAI\Services\Features\PeakHoursService;
use App\Domain\WameedAI\Services\Features\PersonalizedPromotionService;
use App\Domain\WameedAI\Services\Features\PricingOptimizationService;
use App\Domain\WameedAI\Services\Features\ProductCategorizationService;
use App\Domain\WameedAI\Services\Features\ProductDescriptionService;
use App\Domain\WameedAI\Services\Features\RevenueAnomalyService;
use App\Domain\WameedAI\Services\Features\SalesForecastService;
use App\Domain\WameedAI\Services\Features\SeasonalPlanningService;
use App\Domain\WameedAI\Services\Features\SentimentAnalysisService;
use App\Domain\WameedAI\Services\Features\ShrinkageDetectionService;
use App\Domain\WameedAI\Services\Features\SmartReorderService;
use App\Domain\WameedAI\Services\Features\SmartSearchService;
use App\Domain\WameedAI\Services\Features\SocialContentService;
use App\Domain\WameedAI\Services\Features\SpendingPatternService;
use App\Domain\WameedAI\Services\Features\StaffPerformanceService;
use App\Domain\WameedAI\Services\Features\SupplierAnalysisService;
use App\Domain\WameedAI\Services\Features\TranslationService;
use App\Domain\WameedAI\Events\AIFeatureInvoked;
use App\Domain\WameedAI\Events\AISuggestionAccepted;
use App\Domain\WameedAI\Events\AISuggestionDismissed;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WameedAIController extends BaseApiController
{
    public function __construct(
        private readonly AIUsageTrackingService $usageService,
        private readonly AIBillingService $billingService,
        private readonly SmartReorderService $smartReorder,
        private readonly DailySummaryService $dailySummary,
        private readonly SalesForecastService $salesForecast,
        private readonly PeakHoursService $peakHours,
        private readonly PricingOptimizationService $pricingOptimization,
        private readonly BundleSuggestionService $bundleSuggestion,
        private readonly RevenueAnomalyService $revenueAnomaly,
        private readonly ExpiryManagerService $expiryManager,
        private readonly DeadStockService $deadStock,
        private readonly ShrinkageDetectionService $shrinkageDetection,
        private readonly SupplierAnalysisService $supplierAnalysis,
        private readonly SeasonalPlanningService $seasonalPlanning,
        private readonly ProductCategorizationService $productCategorization,
        private readonly InvoiceOCRService $invoiceOCR,
        private readonly ProductDescriptionService $productDescription,
        private readonly BarcodeEnrichmentService $barcodeEnrichment,
        private readonly CustomerSegmentationService $customerSegmentation,
        private readonly ChurnPredictionService $churnPrediction,
        private readonly PersonalizedPromotionService $personalizedPromotion,
        private readonly SpendingPatternService $spendingPattern,
        private readonly SentimentAnalysisService $sentimentAnalysis,
        private readonly SmartSearchService $smartSearch,
        private readonly StaffPerformanceService $staffPerformance,
        private readonly CashierErrorService $cashierError,
        private readonly EfficiencyScoreService $efficiencyScore,
        private readonly MarketingGeneratorService $marketingGenerator,
        private readonly SocialContentService $socialContent,
        private readonly TranslationService $translation,
        private readonly MarginAnalyzerService $marginAnalyzer,
        private readonly ExpenseAnalysisService $expenseAnalysis,
        private readonly CashFlowPredictionService $cashFlowPrediction,
    ) {}

    // ─── Feature Definitions ───

    public function features(Request $request): JsonResponse
    {
        $storeId = $request->header('X-Store-Id');
        $features = AIFeatureDefinition::where('is_enabled', true)
            ->when($storeId, fn ($q) => $q->with(['storeConfigs' => fn ($q2) => $q2->where('store_id', $storeId)]))
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->success(AIFeatureDefinitionResource::collection($features));
    }

    // ─── Store Feature Config ───

    public function storeConfig(Request $request): JsonResponse
    {
        $storeId = $request->header('X-Store-Id');
        $configs = AIStoreFeatureConfig::where('store_id', $storeId)
            ->with('featureDefinition')
            ->get();

        return $this->success(AIStoreFeatureConfigResource::collection($configs));
    }

    public function updateStoreConfig(UpdateFeatureConfigRequest $request, string $featureId): JsonResponse
    {
        $storeId = $request->header('X-Store-Id');
        $config = AIStoreFeatureConfig::updateOrCreate(
            ['store_id' => $storeId, 'ai_feature_definition_id' => $featureId],
            $request->validated()
        );

        return $this->success(new AIStoreFeatureConfigResource($config->load('featureDefinition')));
    }

    // ─── Suggestions ───

    public function suggestions(Request $request): JsonResponse
    {
        $storeId = $request->header('X-Store-Id');
        $suggestions = AISuggestion::where('store_id', $storeId)
            ->when($request->query('feature'), fn ($q, $f) => $q->where('feature_slug', $f))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('created_at')
            ->paginate($request->query('per_page', 20));

        return $this->successPaginated(
            AISuggestionResource::collection($suggestions),
            $suggestions
        );
    }

    public function updateSuggestionStatus(UpdateSuggestionStatusRequest $request, string $suggestionId): JsonResponse
    {
        $suggestion = AISuggestion::where('store_id', $request->header('X-Store-Id'))
            ->findOrFail($suggestionId);
        $status = $request->validated()['status'];
        $suggestion->update(['status' => $status]);

        $userId = $request->user()->id;
        $storeId = $request->header('X-Store-Id');

        if ($status === 'accepted') {
            event(new AISuggestionAccepted(
                suggestionId: $suggestionId,
                storeId: $storeId,
                featureSlug: $suggestion->feature_slug,
                userId: $userId,
            ));
        } elseif ($status === 'dismissed') {
            event(new AISuggestionDismissed(
                suggestionId: $suggestionId,
                storeId: $storeId,
                featureSlug: $suggestion->feature_slug,
                userId: $userId,
                dismissalReason: $request->input('reason'),
            ));
        }

        return $this->success(new AISuggestionResource($suggestion));
    }

    // ─── Feedback ───

    public function submitFeedback(SubmitFeedbackRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        $data['store_id'] = $request->header('X-Store-Id');

        $feedback = AIFeedback::create($data);

        return $this->created(new AIFeedbackResource($feedback));
    }

    // ─── Usage ───

    public function usage(Request $request): JsonResponse
    {
        $storeId = $request->header('X-Store-Id');
        $today = $this->usageService->getTodayUsage($storeId);
        $monthly = $this->usageService->getMonthlyUsage($storeId);
        $byFeature = $this->usageService->getUsageByFeature($storeId);

        return $this->success([
            'today'      => $today,
            'monthly'    => $monthly,
            'by_feature' => $byFeature,
        ]);
    }

    public function usageHistory(Request $request): JsonResponse
    {
        $storeId = $request->header('X-Store-Id');
        $summaries = AIDailyUsageSummary::where('store_id', $storeId)
            ->where('date', '>=', $request->query('from', now()->subDays(30)->toDateString()))
            ->where('date', '<=', $request->query('to', now()->toDateString()))
            ->orderBy('date')
            ->get();

        return $this->success(AIDailyUsageSummaryResource::collection($summaries));
    }

    public function usageLogs(Request $request): JsonResponse
    {
        $storeId = $request->header('X-Store-Id');
        $logs = AIUsageLog::where('store_id', $storeId)
            ->when($request->query('feature'), fn ($q, $f) => $q->where('feature_slug', $f))
            ->orderByDesc('created_at')
            ->paginate($request->query('per_page', 20));

        return $this->successPaginated(AIUsageLogResource::collection($logs), $logs);
    }

    // ─── Billing ───

    public function billingSummary(Request $request): JsonResponse
    {
        $storeId = $request->header('X-Store-Id');
        $organizationId = $request->header('X-Organization-Id');

        return $this->success($this->billingService->getStoreBillingSummary($storeId, $organizationId));
    }

    public function billingInvoices(Request $request): JsonResponse
    {
        $storeId = $request->header('X-Store-Id');
        $invoices = \App\Domain\WameedAI\Models\AIBillingInvoice::forStore($storeId)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate($request->query('per_page', 20));

        $data = $invoices->getCollection()->map(fn ($inv) => [
            'id' => $inv->id,
            'invoice_number' => $inv->invoice_number,
            'year' => $inv->year,
            'month' => $inv->month,
            'total_requests' => $inv->total_requests,
            'raw_cost_usd' => (float) $inv->raw_cost_usd,
            'margin_percentage' => (float) $inv->margin_percentage,
            'billed_amount_usd' => (float) $inv->billed_amount_usd,
            'status' => $inv->status,
            'due_date' => $inv->due_date->toDateString(),
            'paid_at' => $inv->paid_at?->toIso8601String(),
        ]);

        return $this->successPaginated($data, $invoices);
    }

    public function billingInvoiceDetail(Request $request, string $invoiceId): JsonResponse
    {
        $storeId = $request->header('X-Store-Id');
        $detail = $this->billingService->getInvoiceDetail($invoiceId, $storeId);

        if (!$detail) {
            return $this->notFound('Invoice not found');
        }

        return $this->success($detail);
    }

    // ─── AI Features — Inventory ───

    public function smartReorder(InvokeFeatureRequest $request): JsonResponse
    {
        return $this->invokeFeature($request, fn ($s, $o, $u) => $this->smartReorder->getSuggestions($s, $o, $u));
    }

    public function expiryManager(InvokeFeatureRequest $request): JsonResponse
    {
        return $this->invokeFeature($request, fn ($s, $o, $u) => $this->expiryManager->getAlerts($s, $o, 30, $u));
    }

    public function deadStock(InvokeFeatureRequest $request): JsonResponse
    {
        return $this->invokeFeature($request, fn ($s, $o, $u) => $this->deadStock->identify($s, $o, 30, $u));
    }

    public function shrinkageDetection(InvokeFeatureRequest $request): JsonResponse
    {
        return $this->invokeFeature($request, fn ($s, $o, $u) => $this->shrinkageDetection->detect($s, $o, $u));
    }

    public function supplierAnalysis(InvokeFeatureRequest $request): JsonResponse
    {
        return $this->invokeFeature($request, fn ($s, $o, $u) => $this->supplierAnalysis->analyze($s, $o, $u));
    }

    public function seasonalPlanning(InvokeFeatureRequest $request): JsonResponse
    {
        return $this->invokeFeature($request, fn ($s, $o, $u) => $this->seasonalPlanning->plan($s, $o, $u));
    }

    // ─── AI Features — Sales ───

    public function dailySummary(InvokeFeatureRequest $request): JsonResponse
    {
        return $this->invokeFeature($request, fn ($s, $o, $u) => $this->dailySummary->getSummary($s, $o, $request->query('date', now()->toDateString()), $u));
    }

    public function salesForecast(InvokeFeatureRequest $request): JsonResponse
    {
        return $this->invokeFeature($request, fn ($s, $o, $u) => $this->salesForecast->getForecast($s, $o, (int) $request->query('days', 7), $u));
    }

    public function peakHours(InvokeFeatureRequest $request): JsonResponse
    {
        return $this->invokeFeature($request, fn ($s, $o, $u) => $this->peakHours->analyze($s, $o, $u));
    }

    public function pricingOptimization(InvokeFeatureRequest $request): JsonResponse
    {
        return $this->invokeFeature($request, fn ($s, $o, $u) => $this->pricingOptimization->getSuggestions($s, $o, $u));
    }

    public function bundleSuggestions(InvokeFeatureRequest $request): JsonResponse
    {
        return $this->invokeFeature($request, fn ($s, $o, $u) => $this->bundleSuggestion->getSuggestions($s, $o, $u));
    }

    public function revenueAnomaly(InvokeFeatureRequest $request): JsonResponse
    {
        return $this->invokeFeature($request, fn ($s, $o, $u) => $this->revenueAnomaly->detect($s, $o, $u));
    }

    // ─── AI Features — Catalog ───

    public function productCategorization(InvokeFeatureRequest $request): JsonResponse
    {
        $productName = $request->input('product_name', '');
        $barcode = $request->input('barcode');
        return $this->invokeFeature($request, fn ($s, $o, $u) =>
            $this->productCategorization->categorize($s, $o, $productName, $barcode, $u)
        );
    }

    public function invoiceOCR(InvoiceOCRRequest $request): JsonResponse
    {
        return $this->invokeFeature($request, fn ($s, $o, $u) =>
            $this->invoiceOCR->scan($s, $o, $request->validated()['image'], $u)
        );
    }

    public function productDescription(ProductDescriptionRequest $request): JsonResponse
    {
        $data = $request->validated();
        return $this->invokeFeature($request, fn ($s, $o, $u) =>
            $this->productDescription->generate($s, $o, $data['product_id'], $u)
        );
    }

    public function barcodeEnrichment(BarcodeEnrichmentRequest $request): JsonResponse
    {
        return $this->invokeFeature($request, fn ($s, $o, $u) =>
            $this->barcodeEnrichment->enrich($s, $o, $request->validated()['barcode'], $u)
        );
    }

    // ─── AI Features — Customer Intelligence ───

    public function customerSegmentation(InvokeFeatureRequest $request): JsonResponse
    {
        return $this->invokeFeature($request, fn ($s, $o, $u) => $this->customerSegmentation->segment($s, $o, $u));
    }

    public function churnPrediction(InvokeFeatureRequest $request): JsonResponse
    {
        return $this->invokeFeature($request, fn ($s, $o, $u) => $this->churnPrediction->predict($s, $o, $u));
    }

    public function personalizedPromotions(InvokeFeatureRequest $request): JsonResponse
    {
        return $this->invokeFeature($request, fn ($s, $o, $u) =>
            $this->personalizedPromotion->suggest($s, $o, $request->query('segment'), $u)
        );
    }

    public function spendingPatterns(SpendingPatternRequest $request): JsonResponse
    {
        return $this->invokeFeature($request, fn ($s, $o, $u) =>
            $this->spendingPattern->analyze($s, $o, $request->validated()['customer_id'], $u)
        );
    }

    public function sentimentAnalysis(InvokeFeatureRequest $request): JsonResponse
    {
        return $this->invokeFeature($request, fn ($s, $o, $u) => $this->sentimentAnalysis->analyze($s, $o, $u));
    }

    // ─── AI Features — Operations ───

    public function smartSearchQuery(SmartSearchRequest $request): JsonResponse
    {
        return $this->invokeFeature($request, fn ($s, $o, $u) =>
            $this->smartSearch->search($s, $o, $request->validated()['query'], $u)
        );
    }

    public function staffPerformance(InvokeFeatureRequest $request): JsonResponse
    {
        return $this->invokeFeature($request, fn ($s, $o, $u) => $this->staffPerformance->analyze($s, $o, $u));
    }

    public function cashierErrors(InvokeFeatureRequest $request): JsonResponse
    {
        return $this->invokeFeature($request, fn ($s, $o, $u) => $this->cashierError->detect($s, $o, $u));
    }

    public function efficiencyScore(InvokeFeatureRequest $request): JsonResponse
    {
        return $this->invokeFeature($request, fn ($s, $o, $u) => $this->efficiencyScore->calculate($s, $o, $u));
    }

    // ─── AI Features — Communication ───

    public function marketingGenerator(GenerateMarketingRequest $request): JsonResponse
    {
        $data = $request->validated();
        return $this->invokeFeature($request, fn ($s, $o, $u) =>
            $this->marketingGenerator->generate($s, $o, $data['type'], $data['context'], $u)
        );
    }

    public function socialContent(GenerateSocialContentRequest $request): JsonResponse
    {
        $data = $request->validated();
        return $this->invokeFeature($request, fn ($s, $o, $u) =>
            $this->socialContent->generate($s, $o, $data['platform'], $data['topic'], $data['product_ids'] ?? null, $u)
        );
    }

    public function translateContent(TranslateRequest $request): JsonResponse
    {
        $data = $request->validated();
        return $this->invokeFeature($request, fn ($s, $o, $u) =>
            $this->translation->translate($s, $o, $data['texts'], $data['from'] ?? 'ar', $data['to'] ?? 'en', $u)
        );
    }

    // ─── AI Features — Financial ───

    public function marginAnalyzer(InvokeFeatureRequest $request): JsonResponse
    {
        return $this->invokeFeature($request, fn ($s, $o, $u) => $this->marginAnalyzer->analyze($s, $o, $u));
    }

    public function expenseAnalysis(InvokeFeatureRequest $request): JsonResponse
    {
        return $this->invokeFeature($request, fn ($s, $o, $u) => $this->expenseAnalysis->analyze($s, $o, $u));
    }

    public function cashFlowPrediction(InvokeFeatureRequest $request): JsonResponse
    {
        return $this->invokeFeature($request, fn ($s, $o, $u) =>
            $this->cashFlowPrediction->predict($s, $o, (int) $request->query('days', 30), $u)
        );
    }

    // ─── Helper ───

    private function invokeFeature(Request $request, callable $handler): JsonResponse
    {
        try {
            $storeId = $request->header('X-Store-Id');
            $orgId = $request->user()->organization_id;
            $userId = $request->user()->id;

            $startTime = microtime(true);
            $result = $handler($storeId, $orgId, $userId);
            $processingTimeMs = (int) round((microtime(true) - $startTime) * 1000);

            if ($result === null) {
                return $this->error('AI feature is unavailable or rate limited. Please try again later.', 503);
            }

            // Derive feature slug from route URL (e.g. /inventory/smart-reorder → smart_reorder)
            $featureSlug = str_replace('-', '_', basename($request->path()));

            $tokensUsed = $result['usage']['total_tokens'] ?? 0;
            $costEstimate = $result['usage']['estimated_cost_usd'] ?? 0.0;

            event(new AIFeatureInvoked(
                storeId: $storeId,
                featureSlug: $featureSlug,
                userId: $userId,
                organizationId: $orgId,
                tokensUsed: $tokensUsed,
                costEstimate: $costEstimate,
                processingTimeMs: $processingTimeMs,
            ));

            return $this->success($result);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("WameedAI invokeFeature error: {$e->getMessage()}", [
                'path' => $request->path(),
                'exception' => $e,
            ]);

            return $this->error('An error occurred while processing the AI request.', 500);
        }
    }
}
