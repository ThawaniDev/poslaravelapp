<?php

namespace App\Domain\WameedAI\Services;

use App\Domain\WameedAI\Enums\AIRequestStatus;
use App\Domain\WameedAI\Models\AICache;
use App\Domain\WameedAI\Models\AIChat;
use App\Domain\WameedAI\Models\AIChatMessage;
use App\Domain\WameedAI\Models\AIFeatureDefinition;
use App\Domain\WameedAI\Models\AILlmModel;
use App\Domain\WameedAI\Models\AIPrompt;
use App\Domain\WameedAI\Models\AIProviderConfig;
use App\Domain\WameedAI\Models\AIStoreFeatureConfig;
use App\Domain\WameedAI\Models\AIUsageLog;
use App\Domain\WameedAI\Models\AIBillingSetting;
use App\Domain\WameedAI\Models\AIStoreBillingConfig;
use App\Domain\WameedAI\Services\AIBillingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIGatewayService
{
    /**
     * Central entry point for ALL AI calls (feature-based).
     * Handles: rate limiting, caching, cost tracking, prompt management, retry, graceful degradation.
     */
    public function call(
        string $featureSlug,
        ?string $storeId,
        string $organizationId,
        array $contextData,
        ?string $userId = null,
        ?string $cacheKeyOverride = null,
        int $cacheTtlMinutes = 60,
        ?string $modelOverride = null,
    ): ?array {
        $startTime = microtime(true);

        try {
            // 1. Check feature is enabled globally
            $feature = AIFeatureDefinition::where('slug', $featureSlug)->where('is_enabled', true)->first();
            if (!$feature) {
                return null;
            }

            // 2. Check store-level config (skipped for org-scoped calls;
            //    org-level configs are looked up by organization_id only).
            $storeConfig = $storeId
                ? AIStoreFeatureConfig::where('store_id', $storeId)
                    ->where('ai_feature_definition_id', $feature->id)
                    ->first()
                : AIStoreFeatureConfig::whereNull('store_id')
                    ->where('organization_id', $organizationId)
                    ->where('ai_feature_definition_id', $feature->id)
                    ->first();

            if ($storeConfig && !$storeConfig->is_enabled) {
                return null;
            }

            // 2b. Billing check — is store allowed to use AI?
            $billingService = app(AIBillingService::class);
            [$allowed, $billingReason] = $billingService->canStoreUseAI($storeId, $organizationId);
            if (!$allowed) {
                $this->logUsage($organizationId, $storeId, $userId, $feature, 'billing_blocked', 0, 0, 0, 0, (int) ((microtime(true) - $startTime) * 1000), "Billing: {$billingReason}");
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

            $messages = $this->buildMessages($prompt->system_prompt, $userMessage, $imageBase64);

            // 7. Resolve model
            $llmModel = $this->resolveModel($modelOverride, $prompt->model, (bool) $imageBase64);

            // 8. Call LLM with retry
            $response = $this->callLlm($llmModel, $messages, $prompt);
            if (!$response) {
                $this->logUsage($organizationId, $storeId, $userId, $feature, 'error', 0, 0, 0, 0, (int) ((microtime(true) - $startTime) * 1000), 'API call failed after retries');
                return null;
            }

            $content = $response['content'];
            $inputTokens = $response['input_tokens'];
            $outputTokens = $response['output_tokens'];
            $totalTokens = $inputTokens + $outputTokens;
            $actualModel = $response['model'] ?? $llmModel->model_id;
            $cost = $this->estimateCost($llmModel, $inputTokens, $outputTokens);
            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

            // 9. Parse response
            $parsed = $this->parseResponse($content, $prompt->response_format->value);

            // 10. Cache the response
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

            // 11. Log usage
            $this->logUsage($organizationId, $storeId, $userId, $feature, 'success', $inputTokens, $outputTokens, $totalTokens, $cost, $latencyMs, null, false, $cacheKey, $messages);

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

    /**
     * Chat-based conversation call with full message history.
     */
    public function chatCall(
        AIChat $chat,
        array $conversationMessages,
        string $systemPrompt,
        ?string $imageBase64 = null,
        ?string $featureSlug = null,
    ): ?array {
        $startTime = microtime(true);

        try {
            $llmModel = $chat->llmModel ?? $this->resolveModel(null, 'gpt-4o-mini', (bool) $imageBase64);

            // Build OpenAI-style message array from conversation history
            $messages = [['role' => 'system', 'content' => $systemPrompt]];
            foreach ($conversationMessages as $msg) {
                $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }

            // Call LLM
            $prompt = new \stdClass();
            $prompt->max_tokens = $llmModel->max_output_tokens;
            $prompt->temperature = 0.7;
            $prompt->response_format = (object) ['value' => 'text'];

            $response = $this->callLlm($llmModel, $messages, $prompt);
            if (!$response) {
                $this->logUsage($chat->organization_id ?? ($chat->store->organization_id ?? ''), $chat->store_id, $chat->user_id, null, 'error', 0, 0, 0, 0, (int) ((microtime(true) - $startTime) * 1000), 'API call failed after retries');
                return null;
            }

            $inputTokens = $response['input_tokens'];
            $outputTokens = $response['output_tokens'];
            $totalTokens = $inputTokens + $outputTokens;
            $cost = $this->estimateCost($llmModel, $inputTokens, $outputTokens);
            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

            // Log usage for chat calls
            $feature = $featureSlug ? AIFeatureDefinition::where('slug', $featureSlug)->first() : null;
            $this->logUsage(
                $chat->organization_id ?? ($chat->store->organization_id ?? ''),
                $chat->store_id,
                $chat->user_id,
                $feature,
                'success',
                $inputTokens,
                $outputTokens,
                $totalTokens,
                $cost,
                $latencyMs,
                null,
                false,
                null,
                $messages,
                $featureSlug ?? 'wameed_chat',
                $llmModel->model_id,
            );

            return [
                'content' => $response['content'],
                'model' => $response['model'] ?? $llmModel->model_id,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost' => $cost,
                'latency_ms' => $latencyMs,
            ];
        } catch (\Throwable $e) {
            Log::error("WameedAI Chat Error: {$e->getMessage()}", ['chat_id' => $chat->id]);
            $this->logUsage($chat->organization_id ?? ($chat->store->organization_id ?? ''), $chat->store_id, $chat->user_id, null, 'error', 0, 0, 0, 0, (int) ((microtime(true) - $startTime) * 1000), $e->getMessage());
            return null;
        }
    }

    // ─── Multi-LLM Dispatch ─────────────────────────────────

    /**
     * Resolve which LLM model to use.
     */
    public function resolveModel(?string $modelOverride, string $fallbackModelId, bool $needsVision = false): AILlmModel
    {
        if ($modelOverride) {
            $model = AILlmModel::enabled()->where('model_id', $modelOverride)->first();
            if ($model) return $model;
        }

        if ($needsVision) {
            $model = AILlmModel::enabled()->withVision()->where('is_default', true)->first()
                ?? AILlmModel::enabled()->withVision()->first();
            if ($model) return $model;
        }

        $model = AILlmModel::enabled()->where('model_id', $fallbackModelId)->first();
        if ($model) return $model;

        // Global default
        $model = AILlmModel::enabled()->where('is_default', true)->first();
        if ($model) return $model;

        // Absolute fallback — create a virtual model object for gpt-4o-mini
        $virtual = new AILlmModel();
        $virtual->provider = 'openai';
        $virtual->model_id = 'gpt-4o-mini';
        $virtual->display_name = 'GPT-4o Mini';
        $virtual->max_output_tokens = 4096;
        $virtual->input_price_per_1m = 0.15;
        $virtual->output_price_per_1m = 0.60;
        $virtual->supports_vision = false;
        $virtual->supports_json_mode = true;
        return $virtual;
    }

    /**
     * Dispatch to the appropriate provider SDK.
     */
    private function callLlm(AILlmModel $model, array $messages, object $prompt, int $maxRetries = 2): ?array
    {
        $provider = is_string($model->provider) ? $model->provider : $model->provider->value;

        return match ($provider) {
            'openai' => $this->callOpenAI($model, $messages, $prompt, $maxRetries),
            'anthropic' => $this->callAnthropic($model, $messages, $prompt, $maxRetries),
            'google' => $this->callGemini($model, $messages, $prompt, $maxRetries),
            default => $this->callOpenAI($model, $messages, $prompt, $maxRetries),
        };
    }

    private function callOpenAI(AILlmModel $model, array $messages, object $prompt, int $maxRetries): ?array
    {
        $apiKey = $model->api_key_encrypted ?? config('openai.api_key');
        $attempt = 0;
        $lastException = null;

        while ($attempt <= $maxRetries) {
            try {
                $params = [
                    'model' => $model->model_id,
                    'messages' => $messages,
                    'max_tokens' => $prompt->max_tokens ?? $model->max_output_tokens,
                    'temperature' => (float) ($prompt->temperature ?? 0.7),
                ];

                $responseFormat = $prompt->response_format->value ?? $prompt->response_format ?? 'text';
                if ($responseFormat === 'json_object' && $model->supports_json_mode) {
                    $params['response_format'] = ['type' => 'json_object'];
                }

                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type' => 'application/json',
                ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', $params);

                if ($response->successful()) {
                    $data = $response->json();
                    return [
                        'content' => $data['choices'][0]['message']['content'] ?? '',
                        'input_tokens' => $data['usage']['prompt_tokens'] ?? 0,
                        'output_tokens' => $data['usage']['completion_tokens'] ?? 0,
                        'model' => $data['model'] ?? $model->model_id,
                    ];
                }

                throw new \RuntimeException("OpenAI HTTP {$response->status()}: {$response->body()}");
            } catch (\Throwable $e) {
                $lastException = $e;
                $attempt++;
                if ($attempt <= $maxRetries) {
                    usleep($attempt * 500000);
                }
            }
        }

        Log::error("WameedAI OpenAI call failed after retries", ['error' => $lastException?->getMessage(), 'model' => $model->model_id]);
        return null;
    }

    private function callAnthropic(AILlmModel $model, array $messages, object $prompt, int $maxRetries): ?array
    {
        $apiKey = $model->api_key_encrypted ?? config('services.anthropic.api_key');
        $attempt = 0;
        $lastException = null;

        // Extract system message and convert to Anthropic format
        $systemPrompt = '';
        $anthropicMessages = [];
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemPrompt = is_string($msg['content']) ? $msg['content'] : json_encode($msg['content']);
                continue;
            }
            // Convert image content blocks to Anthropic format
            if (is_array($msg['content'])) {
                $blocks = [];
                foreach ($msg['content'] as $part) {
                    if (($part['type'] ?? '') === 'image_url') {
                        $url = $part['image_url']['url'] ?? '';
                        if (str_starts_with($url, 'data:')) {
                            preg_match('/data:([^;]+);base64,(.+)/', $url, $matches);
                            $blocks[] = [
                                'type' => 'image',
                                'source' => [
                                    'type' => 'base64',
                                    'media_type' => $matches[1] ?? 'image/jpeg',
                                    'data' => $matches[2] ?? '',
                                ],
                            ];
                        }
                    } else {
                        $blocks[] = ['type' => 'text', 'text' => $part['text'] ?? ''];
                    }
                }
                $anthropicMessages[] = ['role' => $msg['role'], 'content' => $blocks];
            } else {
                $anthropicMessages[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }
        }

        while ($attempt <= $maxRetries) {
            try {
                $params = [
                    'model' => $model->model_id,
                    'max_tokens' => $prompt->max_tokens ?? $model->max_output_tokens,
                    'messages' => $anthropicMessages,
                ];

                if ($systemPrompt) {
                    $params['system'] = $systemPrompt;
                }

                $response = Http::withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ])->timeout(60)->post('https://api.anthropic.com/v1/messages', $params);

                if ($response->successful()) {
                    $data = $response->json();
                    $content = collect($data['content'] ?? [])->where('type', 'text')->pluck('text')->implode('');
                    return [
                        'content' => $content,
                        'input_tokens' => $data['usage']['input_tokens'] ?? 0,
                        'output_tokens' => $data['usage']['output_tokens'] ?? 0,
                        'model' => $data['model'] ?? $model->model_id,
                    ];
                }

                throw new \RuntimeException("Anthropic HTTP {$response->status()}: {$response->body()}");
            } catch (\Throwable $e) {
                $lastException = $e;
                $attempt++;
                if ($attempt <= $maxRetries) {
                    usleep($attempt * 500000);
                }
            }
        }

        Log::error("WameedAI Anthropic call failed after retries", ['error' => $lastException?->getMessage(), 'model' => $model->model_id]);
        return null;
    }

    private function callGemini(AILlmModel $model, array $messages, object $prompt, int $maxRetries): ?array
    {
        $apiKey = $model->api_key_encrypted ?? config('services.google.api_key');
        $attempt = 0;
        $lastException = null;

        // Convert to Gemini format
        $systemInstruction = null;
        $geminiContents = [];
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemInstruction = is_string($msg['content']) ? $msg['content'] : json_encode($msg['content']);
                continue;
            }
            $role = $msg['role'] === 'assistant' ? 'model' : 'user';
            if (is_array($msg['content'])) {
                $parts = [];
                foreach ($msg['content'] as $part) {
                    if (($part['type'] ?? '') === 'image_url') {
                        $url = $part['image_url']['url'] ?? '';
                        if (str_starts_with($url, 'data:')) {
                            preg_match('/data:([^;]+);base64,(.+)/', $url, $matches);
                            $parts[] = ['inline_data' => ['mime_type' => $matches[1] ?? 'image/jpeg', 'data' => $matches[2] ?? '']];
                        }
                    } else {
                        $parts[] = ['text' => $part['text'] ?? ''];
                    }
                }
                $geminiContents[] = ['role' => $role, 'parts' => $parts];
            } else {
                $geminiContents[] = ['role' => $role, 'parts' => [['text' => $msg['content']]]];
            }
        }

        while ($attempt <= $maxRetries) {
            try {
                $params = [
                    'contents' => $geminiContents,
                    'generationConfig' => [
                        'maxOutputTokens' => $prompt->max_tokens ?? $model->max_output_tokens,
                        'temperature' => (float) ($prompt->temperature ?? 0.7),
                    ],
                ];

                if ($systemInstruction) {
                    $params['systemInstruction'] = ['parts' => [['text' => $systemInstruction]]];
                }

                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model->model_id}:generateContent?key={$apiKey}";

                $response = Http::withHeaders(['Content-Type' => 'application/json'])
                    ->timeout(60)
                    ->post($url, $params);

                if ($response->successful()) {
                    $data = $response->json();
                    $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    $usage = $data['usageMetadata'] ?? [];
                    return [
                        'content' => $content,
                        'input_tokens' => $usage['promptTokenCount'] ?? 0,
                        'output_tokens' => $usage['candidatesTokenCount'] ?? 0,
                        'model' => $model->model_id,
                    ];
                }

                throw new \RuntimeException("Gemini HTTP {$response->status()}: {$response->body()}");
            } catch (\Throwable $e) {
                $lastException = $e;
                $attempt++;
                if ($attempt <= $maxRetries) {
                    usleep($attempt * 500000);
                }
            }
        }

        Log::error("WameedAI Gemini call failed after retries", ['error' => $lastException?->getMessage(), 'model' => $model->model_id]);
        return null;
    }

    // ─── Helper Methods ──────────────────────────────────────

    public function buildMessages(string $systemPrompt, string $userMessage, ?string $imageBase64 = null): array
    {
        if ($imageBase64) {
            return [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => [
                    ['type' => 'text', 'text' => $userMessage],
                    ['type' => 'image_url', 'image_url' => [
                        'url' => "data:image/jpeg;base64,{$imageBase64}",
                        'detail' => 'high',
                    ]],
                ]],
            ];
        }

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userMessage],
        ];
    }

    private function checkRateLimit(?string $storeId, string $featureId, ?AIStoreFeatureConfig $config): bool
    {
        $dailyLimit = $config?->daily_limit ?? 100;
        $monthlyLimit = $config?->monthly_limit ?? 3000;

        $base = AIUsageLog::where('ai_feature_definition_id', $featureId)
            ->whereIn('status', ['success', 'cached']);
        if ($storeId) {
            $base->where('store_id', $storeId);
        } else {
            $base->whereNull('store_id');
        }

        $todayCount = (clone $base)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        if ($todayCount >= $dailyLimit) {
            return false;
        }

        $monthCount = (clone $base)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        return $monthCount < $monthlyLimit;
    }

    private function buildCacheKey(string $featureSlug, ?string $storeId, array $contextData): string
    {
        $keyData = $contextData;
        if (isset($keyData['image_base64'])) {
            $keyData['image_base64'] = substr($keyData['image_base64'], 0, 64);
        }
        $dataHash = md5(json_encode($keyData));
        $scope = $storeId ?: 'org';
        return "wameed_ai:{$featureSlug}:{$scope}:{$dataHash}";
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

    public function interpolateTemplate(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            $template = str_replace('{{' . $key . '}}', (string) $value, $template);
        }

        return $template;
    }

    private function estimateCost(AILlmModel $model, int $inputTokens, int $outputTokens): float
    {
        return ($inputTokens * (float) $model->input_price_per_1m / 1_000_000)
             + ($outputTokens * (float) $model->output_price_per_1m / 1_000_000);
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
        ?string $storeId,
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
        ?array $requestMessages = null,
        ?string $featureSlugOverride = null,
        ?string $modelOverride = null,
    ): void {
        try {
            // Apply margin from settings to get billed cost
            $config = $storeId
                ? AIStoreBillingConfig::where('store_id', $storeId)->first()
                : AIStoreBillingConfig::where('organization_id', $organizationId)->whereNull('store_id')->first();
            $marginPercentage = ($config && $config->custom_margin_percentage !== null)
                ? (float) $config->custom_margin_percentage
                : AIBillingSetting::getFloat('margin_percentage', 20.0);
            $billedCost = round($cost * (1 + $marginPercentage / 100), 6);

            AIUsageLog::create([
                'organization_id' => $organizationId,
                'store_id' => $storeId,
                'user_id' => $userId,
                'ai_feature_definition_id' => $feature?->id,
                'feature_slug' => $featureSlugOverride ?? $feature?->slug ?? 'unknown',
                'model_used' => $modelOverride ?? $feature?->default_model ?? 'gpt-4o-mini',
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_tokens' => $totalTokens,
                'estimated_cost_usd' => $cost,
                'billed_cost_usd' => $billedCost,
                'margin_percentage_applied' => $marginPercentage,
                'request_payload_hash' => $payloadHash,
                'response_cached' => $cached,
                'latency_ms' => $latencyMs,
                'status' => $status,
                'error_message' => $errorMessage,
                'request_messages' => $requestMessages ? json_encode($requestMessages, JSON_UNESCAPED_UNICODE) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('WameedAI: Failed to log usage', ['error' => $e->getMessage()]);
        }
    }
}

