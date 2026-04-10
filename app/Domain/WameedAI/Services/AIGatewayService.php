<?php

namespace App\Domain\WameedAI\Services;

use App\Domain\WameedAI\Enums\AIRequestStatus;
use App\Domain\WameedAI\Models\AICache;
use App\Domain\WameedAI\Models\AIFeatureDefinition;
use App\Domain\WameedAI\Models\AIPrompt;
use App\Domain\WameedAI\Models\AIProviderConfig;
use App\Domain\WameedAI\Models\AIStoreFeatureConfig;
use App\Domain\WameedAI\Models\AIUsageLog;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class AIGatewayService
{
    /**
     * Central entry point for ALL AI calls.
     * Handles: rate limiting, caching, cost tracking, prompt management, retry, graceful degradation.
     */
    public function call(
        string $featureSlug,
        string $storeId,
        string $organizationId,
        array $contextData,
        ?string $userId = null,
        ?string $cacheKeyOverride = null,
        int $cacheTtlMinutes = 60,
    ): ?array {
        $startTime = microtime(true);

        try {
            // 1. Check feature is enabled globally
            $feature = AIFeatureDefinition::where('slug', $featureSlug)->where('is_enabled', true)->first();
            if (!$feature) {
                return null;
            }

            // 2. Check store-level config
            $storeConfig = AIStoreFeatureConfig::where('store_id', $storeId)
                ->where('ai_feature_definition_id', $feature->id)
                ->first();

            if ($storeConfig && !$storeConfig->is_enabled) {
                return null;
            }

            // 3. Rate limiting check
            if (!$this->checkRateLimit($storeId, $feature->id, $storeConfig)) {
                $this->logUsage($organizationId, $storeId, $userId, $feature, 'rate_limited', 0, 0, 0, 0, (int) ((microtime(true) - $startTime) * 1000), 'Rate limit exceeded');
                return null;
            }

            // 4. Check cache
            $cacheKey = $cacheKeyOverride ?? $this->buildCacheKey($featureSlug, $storeId, $contextData);
            $cached = AICache::where('cache_key', $cacheKey)->notExpired()->first();
            if ($cached) {
                $this->logUsage($organizationId, $storeId, $userId, $feature, 'cached', 0, 0, $cached->tokens_used, 0, (int) ((microtime(true) - $startTime) * 1000), null, true, $cacheKey);
                return json_decode($cached->response_text, true) ?? ['text' => $cached->response_text];
            }

            // 5. Load prompt
            $prompt = $this->loadPrompt($featureSlug, $storeConfig?->custom_prompt_override);
            if (!$prompt) {
                Log::warning("WameedAI: No active prompt for feature {$featureSlug}");
                return null;
            }

            // 6. Build messages (with Vision API support for images)
            $imageBase64 = $contextData['image_base64'] ?? null;
            $textContext = array_diff_key($contextData, ['image_base64' => true]);
            $userMessage = $this->interpolateTemplate($prompt->user_prompt_template, $textContext);

            if ($imageBase64) {
                // OpenAI Vision API: multipart content with image
                $messages = [
                    ['role' => 'system', 'content' => $prompt->system_prompt],
                    ['role' => 'user', 'content' => [
                        ['type' => 'text', 'text' => $userMessage],
                        ['type' => 'image_url', 'image_url' => [
                            'url' => "data:image/jpeg;base64,{$imageBase64}",
                            'detail' => 'high',
                        ]],
                    ]],
                ];
            } else {
                $messages = [
                    ['role' => 'system', 'content' => $prompt->system_prompt],
                    ['role' => 'user', 'content' => $userMessage],
                ];
            }

            // 7. Call OpenAI with retry
            $response = $this->callWithRetry($messages, $prompt);
            if (!$response) {
                $this->logUsage($organizationId, $storeId, $userId, $feature, 'error', 0, 0, 0, 0, (int) ((microtime(true) - $startTime) * 1000), 'API call failed after retries');
                return null;
            }

            $content = $response['content'];
            $inputTokens = $response['input_tokens'];
            $outputTokens = $response['output_tokens'];
            $totalTokens = $inputTokens + $outputTokens;
            $actualModel = $response['model'] ?? $prompt->model;
            $cost = $this->estimateCost($actualModel, $inputTokens, $outputTokens);
            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

            // 8. Parse response
            $parsed = $this->parseResponse($content, $prompt->response_format->value);

            // 9. Cache the response
            if ($cacheTtlMinutes > 0) {
                AICache::updateOrCreate(
                    ['cache_key' => $cacheKey],
                    [
                        'feature_slug' => $featureSlug,
                        'store_id' => $storeId,
                        'response_text' => is_array($parsed) ? json_encode($parsed) : $content,
                        'tokens_used' => $totalTokens,
                        'expires_at' => now()->addMinutes($cacheTtlMinutes),
                        'created_at' => now(),
                    ],
                );
            }

            // 10. Log usage
            $this->logUsage($organizationId, $storeId, $userId, $feature, 'success', $inputTokens, $outputTokens, $totalTokens, $cost, $latencyMs, null, false, $cacheKey);

            return $parsed;
        } catch (\Throwable $e) {
            Log::error("WameedAI Gateway Error [{$featureSlug}]: {$e->getMessage()}", [
                'store_id' => $storeId,
                'exception' => $e,
            ]);

            $this->logUsage($organizationId, $storeId, $userId, $feature ?? null, 'error', 0, 0, 0, 0, (int) ((microtime(true) - $startTime) * 1000), $e->getMessage());

            return null;
        }
    }

    private function checkRateLimit(string $storeId, string $featureId, ?AIStoreFeatureConfig $config): bool
    {
        $dailyLimit = $config?->daily_limit ?? 100;
        $monthlyLimit = $config?->monthly_limit ?? 3000;

        $todayCount = AIUsageLog::where('store_id', $storeId)
            ->where('ai_feature_definition_id', $featureId)
            ->where('created_at', '>=', now()->startOfDay())
            ->whereIn('status', ['success', 'cached'])
            ->count();

        if ($todayCount >= $dailyLimit) {
            return false;
        }

        $monthCount = AIUsageLog::where('store_id', $storeId)
            ->where('ai_feature_definition_id', $featureId)
            ->where('created_at', '>=', now()->startOfMonth())
            ->whereIn('status', ['success', 'cached'])
            ->count();

        return $monthCount < $monthlyLimit;
    }

    private function buildCacheKey(string $featureSlug, string $storeId, array $contextData): string
    {
        // Exclude large binary data (images) from cache key — hash only the first 64 chars of image
        $keyData = $contextData;
        if (isset($keyData['image_base64'])) {
            $keyData['image_base64'] = substr($keyData['image_base64'], 0, 64);
        }
        $dataHash = md5(json_encode($keyData));
        return "wameed_ai:{$featureSlug}:{$storeId}:{$dataHash}";
    }

    private function loadPrompt(string $featureSlug, ?string $customOverride = null): ?AIPrompt
    {
        $prompt = AIPrompt::active()->forFeature($featureSlug)->orderByDesc('version')->first();

        if ($prompt && $customOverride) {
            $prompt = $prompt->replicate();
            $prompt->user_prompt_template = $customOverride;
        }

        return $prompt;
    }

    private function interpolateTemplate(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            $template = str_replace("{{$key}}", (string) $value, $template);
        }

        return $template;
    }

    private function callWithRetry(array $messages, AIPrompt $prompt, int $maxRetries = 2): ?array
    {
        $attempt = 0;
        $lastException = null;

        // Auto-upgrade to vision model if messages contain image content
        $hasImage = collect($messages)->contains(fn ($msg) =>
            is_array($msg['content'] ?? null) &&
            collect($msg['content'])->contains(fn ($part) => ($part['type'] ?? '') === 'image_url')
        );
        $model = $hasImage ? 'gpt-4o' : $prompt->model;

        while ($attempt <= $maxRetries) {
            try {
                $params = [
                    'model' => $model,
                    'messages' => $messages,
                    'max_tokens' => $hasImage ? max($prompt->max_tokens, 4096) : $prompt->max_tokens,
                    'temperature' => (float) $prompt->temperature,
                ];

                if ($prompt->response_format->value === 'json_object') {
                    $params['response_format'] = ['type' => 'json_object'];
                }

                $result = OpenAI::chat()->create($params);

                return [
                    'content' => $result->choices[0]->message->content ?? '',
                    'input_tokens' => $result->usage->promptTokens ?? 0,
                    'output_tokens' => $result->usage->completionTokens ?? 0,
                    'model' => $model,
                ];
            } catch (\Throwable $e) {
                $lastException = $e;
                $attempt++;
                if ($attempt <= $maxRetries) {
                    usleep($attempt * 500000); // exponential backoff: 0.5s, 1s
                }
            }
        }

        Log::error("WameedAI: OpenAI call failed after {$maxRetries} retries", [
            'error' => $lastException?->getMessage(),
        ]);

        return null;
    }

    private function estimateCost(string $model, int $inputTokens, int $outputTokens): float
    {
        // GPT-4o-mini pricing (per 1M tokens)
        $pricing = [
            'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
            'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
            'gpt-4-turbo' => ['input' => 10.00, 'output' => 30.00],
        ];

        $rates = $pricing[$model] ?? $pricing['gpt-4o-mini'];

        return ($inputTokens * $rates['input'] / 1_000_000) + ($outputTokens * $rates['output'] / 1_000_000);
    }

    private function parseResponse(string $content, string $format): array
    {
        if ($format === 'json_object') {
            $decoded = json_decode($content, true);
            return is_array($decoded) ? $decoded : ['text' => $content];
        }

        return ['text' => $content];
    }

    private function logUsage(
        string $organizationId,
        string $storeId,
        ?string $userId,
        ?AIFeatureDefinition $feature,
        string $status,
        int $inputTokens,
        int $outputTokens,
        int $totalTokens,
        float $cost,
        int $latencyMs,
        ?string $errorMessage = null,
        bool $cached = false,
        ?string $payloadHash = null,
    ): void {
        try {
            AIUsageLog::create([
                'organization_id' => $organizationId,
                'store_id' => $storeId,
                'user_id' => $userId,
                'ai_feature_definition_id' => $feature?->id,
                'feature_slug' => $feature?->slug ?? 'unknown',
                'model_used' => $feature?->default_model ?? 'gpt-4o-mini',
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_tokens' => $totalTokens,
                'estimated_cost_usd' => $cost,
                'request_payload_hash' => $payloadHash,
                'response_cached' => $cached,
                'latency_ms' => $latencyMs,
                'status' => $status,
                'error_message' => $errorMessage,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('WameedAI: Failed to log usage', ['error' => $e->getMessage()]);
        }
    }
}
