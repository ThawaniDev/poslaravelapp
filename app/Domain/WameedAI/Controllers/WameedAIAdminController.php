<?php

namespace App\Domain\WameedAI\Controllers;

use App\Domain\WameedAI\Models\AIFeatureDefinition;
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
        $configs = AIProviderConfig::orderBy('provider')->get();
        return $this->success(AIProviderConfigResource::collection($configs));
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
        $feature->update(['is_active' => !$feature->is_active]);
        return $this->success(new AIFeatureDefinitionResource($feature));
    }

    // ─── Platform Usage ───

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
}
