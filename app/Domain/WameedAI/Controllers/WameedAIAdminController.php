<?php

namespace App\Domain\WameedAI\Controllers;

use App\Domain\WameedAI\Models\AIChat;
use App\Domain\WameedAI\Models\AIChatMessage;
use App\Domain\WameedAI\Models\AIFeatureDefinition;
use App\Domain\WameedAI\Models\AILlmModel;
use App\Domain\WameedAI\Models\AIPlatformDailySummary;
use App\Domain\WameedAI\Models\AIProviderConfig;
use App\Domain\WameedAI\Models\AIUsageLog;
use App\Domain\WameedAI\Requests\AdminUpdateProviderConfigRequest;
use App\Domain\WameedAI\Resources\AIFeatureDefinitionResource;
use App\Domain\WameedAI\Resources\AIProviderConfigResource;
use App\Domain\WameedAI\Resources\AIUsageLogResource;
use App\Domain\WameedAI\Services\AIUsageTrackingService;
use App\Domain\WameedAI\Services\Features\PlatformTrendService;
use App\Domain\WameedAI\Services\Features\StoreHealthService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WameedAIAdminController extends BaseApiController
{
    public function __construct(
        private readonly AIUsageTrackingService $usageService,
        private readonly StoreHealthService $storeHealth,
        private readonly PlatformTrendService $platformTrend,
    ) {}

    // ─── Provider Config ───

    public function providerConfigs(): JsonResponse
    {
        // Aggregate provider info from the actual ai_llm_models table
        // (AIProviderConfig is legacy and may be empty)
        $providers = AILlmModel::query()
            ->select('provider')
            ->selectRaw('COUNT(*) as model_count')
            ->selectRaw('SUM(CASE WHEN is_enabled = true THEN 1 ELSE 0 END) as enabled_count')
            ->selectRaw('SUM(CASE WHEN is_default = true THEN 1 ELSE 0 END) as has_default')
            ->selectRaw('SUM(CASE WHEN api_key_encrypted IS NOT NULL THEN 1 ELSE 0 END) as models_with_keys')
            ->groupBy('provider')
            ->orderBy('provider')
            ->get()
            ->map(fn ($row) => [
                'provider' => $row->provider,
                'model_count' => (int) $row->model_count,
                'enabled_count' => (int) $row->enabled_count,
                'has_default' => (int) $row->has_default > 0,
                'models_with_keys' => (int) $row->models_with_keys,
                'is_active' => (int) $row->enabled_count > 0,
            ]);

        // Also include any legacy AIProviderConfig entries
        $legacyConfigs = AIProviderConfig::orderBy('provider')->get();

        return $this->success([
            'providers' => $providers,
            'legacy_configs' => AIProviderConfigResource::collection($legacyConfigs),
        ]);
    }

    public function updateProviderConfig(AdminUpdateProviderConfigRequest $request, ?string $id = null): JsonResponse
    {
        $data = $request->validated();
        $apiKey = $data['api_key'];
        unset($data['api_key']);
        $data['api_key_encrypted'] = encrypt($apiKey);

        $config = $id
            ? AIProviderConfig::findOrFail($id)
            : new AIProviderConfig();

        $config->fill($data);
        $config->save();

        return $this->success(new AIProviderConfigResource($config));
    }

    // ─── Feature Management ───

    public function allFeatures(): JsonResponse
    {
        $features = AIFeatureDefinition::orderBy('category')->orderBy('name')->get();
        return $this->success(AIFeatureDefinitionResource::collection($features));
    }

    public function toggleFeature(Request $request, string $featureId): JsonResponse
    {
        $feature = AIFeatureDefinition::findOrFail($featureId);
        $feature->update(['is_enabled' => !$feature->is_enabled]);
        return $this->success(new AIFeatureDefinitionResource($feature->fresh()));
    }

    // ═══════════════════════════════════════════════════════════
    // COMPREHENSIVE ANALYTICS DASHBOARD
    // ═══════════════════════════════════════════════════════════

    /**
     * GET /admin/wameed-ai/analytics/dashboard
     *
     * Full analytics dashboard with KPIs, breakdowns, and trends.
     * Filters: from, to, store_id, model, feature_slug
     */
    public function analyticsDashboard(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->subDays(30)->toDateString());
        $to = $request->query('to', now()->toDateString());
        $storeId = $request->query('store_id');
        $model = $request->query('model');
        $featureSlug = $request->query('feature_slug');

        // ── Base query builder with filters ──
        $baseUsage = AIUsageLog::query()
            ->where('created_at', '>=', "{$from} 00:00:00")
            ->where('created_at', '<=', "{$to} 23:59:59")
            ->when($storeId, fn ($q, $s) => $q->where('store_id', $s))
            ->when($model, fn ($q, $m) => $q->where('model_used', $m))
            ->when($featureSlug, fn ($q, $f) => $q->where('feature_slug', $f));

        $baseChats = AIChat::query()
            ->where('created_at', '>=', "{$from} 00:00:00")
            ->where('created_at', '<=', "{$to} 23:59:59")
            ->when($storeId, fn ($q, $s) => $q->where('store_id', $s));

        $baseMsgs = AIChatMessage::query()
            ->whereHas('chat', function ($q) use ($from, $to, $storeId) {
                $q->where('created_at', '>=', "{$from} 00:00:00")
                  ->where('created_at', '<=', "{$to} 23:59:59")
                  ->when($storeId, fn ($qq, $s) => $qq->where('store_id', $s));
            })
            ->when($model, fn ($q, $m) => $q->where('model_used', $m));

        // ── KPIs ──
        $kpis = [
            'total_requests' => (clone $baseUsage)->count(),
            'successful_requests' => (clone $baseUsage)->where('status', 'success')->count(),
            'cached_requests' => (clone $baseUsage)->where('response_cached', true)->count(),
            'failed_requests' => (clone $baseUsage)->where('status', 'error')->count(),
            'rate_limited' => (clone $baseUsage)->where('status', 'rate_limited')->count(),
            'total_tokens' => (int) (clone $baseUsage)->sum('total_tokens'),
            'total_input_tokens' => (int) (clone $baseUsage)->sum('input_tokens'),
            'total_output_tokens' => (int) (clone $baseUsage)->sum('output_tokens'),
            'total_cost_usd' => round((float) (clone $baseUsage)->sum('estimated_cost_usd'), 4),
            'avg_latency_ms' => round((float) (clone $baseUsage)->where('status', 'success')->avg('latency_ms'), 0),
            'total_chats' => (clone $baseChats)->count(),
            'total_chat_messages' => (int) (clone $baseMsgs)->count(),
            'unique_users' => (clone $baseChats)->distinct('user_id')->count('user_id'),
            'unique_stores' => (clone $baseUsage)->distinct('store_id')->count('store_id'),
        ];

        // ── Cost Breakdown by Model ──
        $costByModel = (clone $baseUsage)
            ->select('model_used')
            ->selectRaw('COUNT(*) as request_count')
            ->selectRaw('SUM(total_tokens) as total_tokens')
            ->selectRaw('SUM(input_tokens) as input_tokens')
            ->selectRaw('SUM(output_tokens) as output_tokens')
            ->selectRaw('SUM(estimated_cost_usd) as total_cost')
            ->selectRaw('AVG(latency_ms) as avg_latency')
            ->where('status', 'success')
            ->groupBy('model_used')
            ->orderByDesc('total_cost')
            ->get();

        // ── Usage by Feature ──
        $usageByFeature = (clone $baseUsage)
            ->select('feature_slug')
            ->selectRaw('COUNT(*) as request_count')
            ->selectRaw('SUM(CASE WHEN status = \'success\' THEN 1 ELSE 0 END) as success_count')
            ->selectRaw('SUM(CASE WHEN status = \'error\' THEN 1 ELSE 0 END) as error_count')
            ->selectRaw('SUM(total_tokens) as total_tokens')
            ->selectRaw('SUM(estimated_cost_usd) as total_cost')
            ->selectRaw('AVG(latency_ms) as avg_latency')
            ->groupBy('feature_slug')
            ->orderByDesc('request_count')
            ->get();

        // ── Usage by Store ──
        $usageByStore = (clone $baseUsage)
            ->select('ai_usage_logs.store_id')
            ->selectRaw('COUNT(*) as request_count')
            ->selectRaw('SUM(ai_usage_logs.total_tokens) as total_tokens')
            ->selectRaw('SUM(ai_usage_logs.estimated_cost_usd) as total_cost')
            ->selectRaw('COUNT(DISTINCT ai_usage_logs.user_id) as unique_users')
            ->join('stores', 'stores.id', '=', 'ai_usage_logs.store_id')
            ->addSelect('stores.name as store_name')
            ->groupBy('ai_usage_logs.store_id', 'stores.name')
            ->orderByDesc('request_count')
            ->limit(50)
            ->get();

        // ── Daily Trend ──
        $dailyTrend = (clone $baseUsage)
            ->selectRaw("DATE(created_at) as date")
            ->selectRaw('COUNT(*) as requests')
            ->selectRaw('SUM(total_tokens) as tokens')
            ->selectRaw('SUM(estimated_cost_usd) as cost')
            ->selectRaw('SUM(CASE WHEN status = \'success\' THEN 1 ELSE 0 END) as successes')
            ->selectRaw('SUM(CASE WHEN status = \'error\' THEN 1 ELSE 0 END) as errors')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();

        // ── Chat Analytics ──
        $chatStats = [
            'avg_messages_per_chat' => round((float) (clone $baseChats)->avg('message_count'), 1),
            'avg_tokens_per_chat' => round((float) (clone $baseChats)->avg('total_tokens'), 0),
            'avg_cost_per_chat' => round((float) (clone $baseChats)->avg('total_cost_usd'), 4),
            'model_distribution' => (clone $baseChats)
                ->join('ai_llm_models', 'ai_llm_models.id', '=', 'ai_chats.llm_model_id')
                ->select('ai_llm_models.display_name as model_name', 'ai_llm_models.model_id')
                ->selectRaw('COUNT(*) as chat_count')
                ->groupBy('ai_llm_models.display_name', 'ai_llm_models.model_id')
                ->orderByDesc('chat_count')
                ->get(),
        ];

        // ── Top Users ──
        $topUsers = (clone $baseChats)
            ->select('ai_chats.user_id')
            ->selectRaw('COUNT(*) as chat_count')
            ->selectRaw('SUM(ai_chats.message_count) as total_messages')
            ->selectRaw('SUM(ai_chats.total_tokens) as total_tokens')
            ->selectRaw('SUM(ai_chats.total_cost_usd) as total_cost')
            ->join('users', 'users.id', '=', 'ai_chats.user_id')
            ->addSelect('users.name as user_name')
            ->groupBy('ai_chats.user_id', 'users.name')
            ->orderByDesc('total_messages')
            ->limit(20)
            ->get();

        // ── Hourly Distribution ──
        $hourlyDistribution = (clone $baseUsage)
            ->selectRaw("EXTRACT(HOUR FROM created_at) as hour")
            ->selectRaw('COUNT(*) as requests')
            ->groupByRaw("EXTRACT(HOUR FROM created_at)")
            ->orderBy('hour')
            ->get();

        // ── Error Breakdown ──
        $errorBreakdown = (clone $baseUsage)
            ->where('status', 'error')
            ->select('feature_slug', 'model_used', 'error_message')
            ->selectRaw('COUNT(*) as error_count')
            ->groupBy('feature_slug', 'model_used', 'error_message')
            ->orderByDesc('error_count')
            ->limit(20)
            ->get();

        return $this->success([
            'period' => ['from' => $from, 'to' => $to],
            'filters' => ['store_id' => $storeId, 'model' => $model, 'feature_slug' => $featureSlug],
            'kpis' => $kpis,
            'cost_by_model' => $costByModel,
            'usage_by_feature' => $usageByFeature,
            'usage_by_store' => $usageByStore,
            'daily_trend' => $dailyTrend,
            'chat_stats' => $chatStats,
            'top_users' => $topUsers,
            'hourly_distribution' => $hourlyDistribution,
            'error_breakdown' => $errorBreakdown,
        ]);
    }

    /**
     * GET /admin/wameed-ai/analytics/chats
     *
     * Paginated chat list with search, filters, and message counts.
     */
    public function analyticsChats(Request $request): JsonResponse
    {
        $chats = AIChat::query()
            ->with(['llmModel:id,display_name,model_id,provider'])
            ->when($request->query('store_id'), fn ($q, $s) => $q->where('store_id', $s))
            ->when($request->query('user_id'), fn ($q, $u) => $q->where('user_id', $u))
            ->when($request->query('model_id'), fn ($q, $m) => $q->where('llm_model_id', $m))
            ->when($request->query('search'), fn ($q, $s) => $q->where('title', 'ilike', "%{$s}%"))
            ->when($request->query('from'), fn ($q, $f) => $q->where('created_at', '>=', "{$f} 00:00:00"))
            ->when($request->query('to'), fn ($q, $t) => $q->where('created_at', '<=', "{$t} 23:59:59"))
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->paginate($request->query('per_page', 25));

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => [
                'chats' => $chats->items(),
                'total' => $chats->total(),
                'current_page' => $chats->currentPage(),
                'last_page' => $chats->lastPage(),
                'per_page' => $chats->perPage(),
            ],
        ]);
    }

    /**
     * GET /admin/wameed-ai/analytics/chats/{chatId}
     *
     * View specific chat with all messages (admin can view any chat).
     */
    public function analyticsChatDetail(string $chatId): JsonResponse
    {
        $chat = AIChat::with(['messages' => fn ($q) => $q->orderBy('created_at'), 'llmModel'])
            ->withCount('messages')
            ->findOrFail($chatId);

        return $this->success($chat);
    }

    // ─── Legacy Platform Usage / Logs (kept for backward compat) ───

    public function platformUsage(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->subDays(30)->toDateString());
        $to = $request->query('to', now()->toDateString());

        $summaries = AIPlatformDailySummary::where('date', '>=', $from)
            ->where('date', '<=', $to)
            ->orderBy('date')
            ->get();

        return $this->success($summaries);
    }

    public function platformLogs(Request $request): JsonResponse
    {
        $logs = AIUsageLog::when($request->query('store_id'), fn ($q, $s) => $q->where('store_id', $s))
            ->when($request->query('feature'), fn ($q, $f) => $q->where('feature_slug', $f))
            ->when($request->query('model'), fn ($q, $m) => $q->where('model_used', $m))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('from'), fn ($q, $f) => $q->where('created_at', '>=', "{$f} 00:00:00"))
            ->when($request->query('to'), fn ($q, $t) => $q->where('created_at', '<=', "{$t} 23:59:59"))
            ->orderByDesc('created_at')
            ->paginate($request->query('per_page', 50));

        return $this->successPaginated(AIUsageLogResource::collection($logs), $logs);
    }

    // ─── Platform AI Features ───

    public function storeHealth(Request $request): JsonResponse
    {
        $result = $this->storeHealth->calculateAll($request->user()?->id);
        if ($result === null) {
            return $this->error('AI feature unavailable', 503);
        }
        return $this->success($result);
    }

    public function platformTrends(Request $request): JsonResponse
    {
        $result = $this->platformTrend->analyze($request->user()?->id);
        if ($result === null) {
            return $this->error('AI feature unavailable', 503);
        }
        return $this->success($result);
    }

    // ═══════════════════════════════════════════════════════════
    // LLM MODEL MANAGEMENT (FULL CRUD + METRICS)
    // ═══════════════════════════════════════════════════════════

    public function llmModels(Request $request): JsonResponse
    {
        $models = AILlmModel::orderBy('provider')->orderBy('sort_order')->get();

        // Attach per-model usage metrics
        $modelMetrics = AIUsageLog::query()
            ->select('model_used')
            ->selectRaw('COUNT(*) as total_requests')
            ->selectRaw('SUM(total_tokens) as total_tokens')
            ->selectRaw('SUM(estimated_cost_usd) as total_cost')
            ->selectRaw('AVG(latency_ms) as avg_latency')
            ->selectRaw('SUM(CASE WHEN status = \'success\' THEN 1 ELSE 0 END) as success_count')
            ->selectRaw('SUM(CASE WHEN status = \'error\' THEN 1 ELSE 0 END) as error_count')
            ->when($request->query('from'), fn ($q, $f) => $q->where('created_at', '>=', "{$f} 00:00:00"))
            ->when($request->query('to'), fn ($q, $t) => $q->where('created_at', '<=', "{$t} 23:59:59"))
            ->groupBy('model_used')
            ->get()
            ->keyBy('model_used');

        $chatModelMetrics = AIChat::query()
            ->select('llm_model_id')
            ->selectRaw('COUNT(*) as chat_count')
            ->selectRaw('SUM(message_count) as total_messages')
            ->groupBy('llm_model_id')
            ->get()
            ->keyBy('llm_model_id');

        $modelsWithMetrics = $models->map(function ($model) use ($modelMetrics, $chatModelMetrics) {
            $usage = $modelMetrics->get($model->model_id);
            $chats = $chatModelMetrics->get($model->id);
            $modelArray = $model->toArray();
            unset($modelArray['api_key_encrypted']);
            $modelArray['has_custom_api_key'] = !empty($model->api_key_encrypted);
            $modelArray['metrics'] = [
                'total_requests' => (int) ($usage->total_requests ?? 0),
                'total_tokens' => (int) ($usage->total_tokens ?? 0),
                'total_cost' => round((float) ($usage->total_cost ?? 0), 4),
                'avg_latency' => round((float) ($usage->avg_latency ?? 0), 0),
                'success_count' => (int) ($usage->success_count ?? 0),
                'error_count' => (int) ($usage->error_count ?? 0),
                'chat_count' => (int) ($chats->chat_count ?? 0),
                'total_messages' => (int) ($chats->total_messages ?? 0),
            ];
            return $modelArray;
        });

        return $this->success(['models' => $modelsWithMetrics]);
    }

    public function createLlmModel(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider' => 'required|string|in:openai,anthropic,google',
            'model_id' => 'required|string|max:100',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'api_key' => 'nullable|string',
            'supports_vision' => 'boolean',
            'supports_json_mode' => 'boolean',
            'max_context_tokens' => 'nullable|integer|min:1',
            'max_output_tokens' => 'nullable|integer|min:1',
            'input_price_per_1m' => 'nullable|numeric|min:0',
            'output_price_per_1m' => 'nullable|numeric|min:0',
            'is_enabled' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        if (isset($data['api_key'])) {
            $data['api_key_encrypted'] = encrypt($data['api_key']);
            unset($data['api_key']);
        }

        if (!empty($data['is_default'])) {
            AILlmModel::where('is_default', true)->update(['is_default' => false]);
        }

        $model = AILlmModel::create($data);
        return $this->created($model);
    }

    public function updateLlmModel(Request $request, string $id): JsonResponse
    {
        $model = AILlmModel::findOrFail($id);

        $data = $request->validate([
            'display_name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'api_key' => 'nullable|string',
            'supports_vision' => 'boolean',
            'supports_json_mode' => 'boolean',
            'max_context_tokens' => 'nullable|integer|min:1',
            'max_output_tokens' => 'nullable|integer|min:1',
            'input_price_per_1m' => 'nullable|numeric|min:0',
            'output_price_per_1m' => 'nullable|numeric|min:0',
            'is_enabled' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        if (isset($data['api_key'])) {
            $data['api_key_encrypted'] = encrypt($data['api_key']);
            unset($data['api_key']);
        }

        if (!empty($data['is_default'])) {
            AILlmModel::where('is_default', true)->where('id', '!=', $id)->update(['is_default' => false]);
        }

        $model->update($data);
        return $this->success($model->fresh());
    }

    public function toggleLlmModel(string $id): JsonResponse
    {
        $model = AILlmModel::findOrFail($id);
        $model->update(['is_enabled' => !$model->is_enabled]);
        return $this->success($model->fresh());
    }

    public function deleteLlmModel(string $id): JsonResponse
    {
        $model = AILlmModel::findOrFail($id);
        $model->delete();
        return $this->success(null, 'Model deleted');
    }
}
