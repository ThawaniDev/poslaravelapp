<?php

namespace App\Domain\WameedAI\Services;

use App\Domain\WameedAI\Models\AIChat;
use App\Domain\WameedAI\Models\AIChatMessage;
use App\Domain\WameedAI\Models\AIFeatureDefinition;
use App\Domain\WameedAI\Models\AILlmModel;
use App\Domain\WameedAI\Services\AIGatewayService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AIChatService
{
    private const MAX_CONTEXT_MESSAGES = 50;

    public function __construct(
        private readonly AIGatewayService $gateway,
        private readonly AIStoreDataService $storeDataService,
    ) {}

    /**
     * Create a new chat. store_id is OPTIONAL: org-level users (no store)
     * create org-scoped chats; store-bound users tag the chat with their store.
     */
    public function createChat(
        string $organizationId,
        ?string $storeId,
        string $userId,
        ?string $llmModelId = null,
        ?string $title = null,
    ): AIChat {
        $model = $llmModelId
            ? AILlmModel::enabled()->where('id', $llmModelId)->first()
            : null;

        // Fall back to default model if none found or none provided
        if (!$model) {
            $model = AILlmModel::enabled()->where('is_default', true)->first();
        }

        return AIChat::create([
            'organization_id' => $organizationId,
            'store_id' => $storeId,
            'user_id' => $userId,
            'title' => $title ?? 'New Chat',
            'llm_model_id' => $model?->id,
            'message_count' => 0,
            'total_tokens' => 0,
            'total_cost_usd' => 0,
            'last_message_at' => now(),
        ]);
    }

    /**
     * List chats. Filtering rules:
     *  - storeId provided  → only chats for that store
     *  - accessibleStoreIds provided AND storeId null → all chats in those
     *    stores PLUS the user's own org-level chats (store_id IS NULL)
     *  - neither           → only chats where user_id matches (legacy)
     */
    public function listChats(
        string $organizationId,
        ?string $storeId,
        string $userId,
        ?array $accessibleStoreIds = null,
        int $perPage = 20,
    ): mixed {
        $q = AIChat::where('organization_id', $organizationId)
            ->with(['llmModel:id,provider,model_id,display_name', 'store:id,name'])
            ->orderByDesc('last_message_at');

        if ($storeId) {
            $q->where('store_id', $storeId)->where('user_id', $userId);
        } elseif (!empty($accessibleStoreIds)) {
            // Org-scope: see chats across all accessible stores (any user) + own org-level chats
            $q->where(function ($w) use ($accessibleStoreIds, $userId) {
                $w->whereIn('store_id', $accessibleStoreIds)
                  ->orWhere(function ($ww) use ($userId) {
                      $ww->whereNull('store_id')->where('user_id', $userId);
                  });
            });
        } else {
            $q->where('user_id', $userId);
        }

        return $q->paginate($perPage);
    }

    /**
     * Get chat with messages. Allows access if the chat belongs to the user OR
     * if the user is org-scoped and the chat belongs to a store they can access.
     */
    public function getChat(string $chatId, string $userId, ?array $accessibleStoreIds = null): ?AIChat
    {
        $q = AIChat::where('id', $chatId)
            ->with(['messages' => fn ($qq) => $qq->orderBy('created_at'), 'llmModel', 'store:id,name']);

        if (!empty($accessibleStoreIds)) {
            $q->where(function ($w) use ($accessibleStoreIds, $userId) {
                $w->whereIn('store_id', $accessibleStoreIds)
                  ->orWhere('user_id', $userId);
            });
        } else {
            $q->where('user_id', $userId);
        }

        return $q->first();
    }

    /**
     * Send a message in a chat and get AI response.
     */
    public function sendMessage(
        AIChat $chat,
        string $userMessage,
        ?string $featureSlug = null,
        ?array $featureData = null,
        ?string $imageBase64 = null,
        ?array $attachments = null,
    ): ?AIChatMessage {
        // 1. Save user message (denormalize org/store for filtering)
        $userMsg = $chat->messages()->create([
            'organization_id' => $chat->organization_id,
            'store_id' => $chat->store_id,
            'role' => 'user',
            'content' => $userMessage,
            'feature_slug' => $featureSlug,
            'feature_data' => $featureData,
            'attachments' => $attachments,
            'model_used' => null,
            'input_tokens' => 0,
            'output_tokens' => 0,
            'cost_usd' => 0,
            'latency_ms' => 0,
        ]);

        // 2. Build conversation context
        $systemPrompt = $this->buildSystemPrompt($chat, $featureSlug);
        $conversationMessages = $this->buildConversationHistory($chat);

        // Add image if provided
        if ($imageBase64) {
            $lastIdx = count($conversationMessages) - 1;
            $conversationMessages[$lastIdx]['content'] = [
                ['type' => 'text', 'text' => $conversationMessages[$lastIdx]['content']],
                ['type' => 'image_url', 'image_url' => [
                    'url' => "data:image/jpeg;base64,{$imageBase64}",
                    'detail' => 'high',
                ]],
            ];
        }

        // 3. Call AI gateway with chat context
        $response = $this->gateway->chatCall(
            chat: $chat,
            conversationMessages: $conversationMessages,
            systemPrompt: $systemPrompt,
            imageBase64: null, // Already embedded in messages
            featureSlug: $featureSlug,
        );

        if (!$response) {
            // Save error message
            return $chat->messages()->create([
                'organization_id' => $chat->organization_id,
                'store_id' => $chat->store_id,
                'role' => 'assistant',
                'content' => 'I apologize, but I encountered an error processing your request. Please try again.',
                'model_used' => $chat->llmModel?->model_id ?? 'unknown',
                'input_tokens' => 0,
                'output_tokens' => 0,
                'cost_usd' => 0,
                'latency_ms' => 0,
            ]);
        }

        // 4. Save assistant message
        $assistantMsg = $chat->messages()->create([
            'organization_id' => $chat->organization_id,
            'store_id' => $chat->store_id,
            'role' => 'assistant',
            'content' => $response['content'],
            'feature_slug' => $featureSlug,
            'model_used' => $response['model'],
            'input_tokens' => $response['input_tokens'],
            'output_tokens' => $response['output_tokens'],
            'cost_usd' => $response['cost'],
            'latency_ms' => $response['latency_ms'],
        ]);

        // 5. Update chat stats (use query builder to avoid Eloquent cast issues with DB::raw)
        DB::table('ai_chats')->where('id', $chat->id)->update([
            'message_count' => DB::raw('message_count + 2'),
            'total_tokens' => DB::raw("total_tokens + {$response['input_tokens']} + {$response['output_tokens']}"),
            'total_cost_usd' => DB::raw("total_cost_usd + {$response['cost']}"),
            'last_message_at' => now(),
        ]);
        $chat->refresh();

        // 6. Auto-title on first message
        if ($chat->message_count <= 2 && $chat->title === 'New Chat') {
            $this->autoTitleChat($chat, $userMessage);
        }

        return $assistantMsg;
    }

    /**
     * Invoke a feature within chat context.
     */
    public function invokeFeatureInChat(
        AIChat $chat,
        string $featureSlug,
        array $featureParams,
    ): ?AIChatMessage {
        $feature = AIFeatureDefinition::where('slug', $featureSlug)->where('is_enabled', true)->first();
        if (!$feature) {
            return null;
        }

        $featureDescription = $feature->name ?? $featureSlug;
        $userMessage = "Use the {$featureDescription} feature with these parameters: " . json_encode($featureParams);

        return $this->sendMessage(
            chat: $chat,
            userMessage: $userMessage,
            featureSlug: $featureSlug,
            featureData: $featureParams,
        );
    }

    /**
     * Change the LLM model for a chat.
     */
    public function changeModel(AIChat $chat, string $llmModelId): bool
    {
        $model = AILlmModel::enabled()->where('id', $llmModelId)->first();
        if (!$model) return false;

        $chat->update(['llm_model_id' => $model->id]);
        return true;
    }

    /**
     * Delete (soft-delete) a chat.
     */
    public function deleteChat(AIChat $chat): bool
    {
        return $chat->delete();
    }

    /**
     * Get available LLM models.
     */
    public function getAvailableModels(): mixed
    {
        return AILlmModel::enabled()
            ->orderBy('sort_order')
            ->get(['id', 'provider', 'model_id', 'display_name', 'description', 'supports_vision', 'supports_json_mode', 'max_context_tokens', 'max_output_tokens', 'is_default']);
    }

    // ─── Private Helpers ─────────────────────────────────────

    private function buildSystemPrompt(AIChat $chat, ?string $featureSlug = null): string
    {
        try {
            return $this->buildEnrichedSystemPrompt($chat, $featureSlug);
        } catch (\Throwable $e) {
            Log::warning("buildSystemPrompt enrichment failed, using fallback: {$e->getMessage()}");
            return $this->buildFallbackSystemPrompt($chat, $featureSlug);
        }
    }

    private function buildFallbackSystemPrompt(AIChat $chat, ?string $featureSlug): string
    {
        $storeName = 'your organization';
        $currency = 'SAR';
        try {
            if ($chat->store_id) {
                $store = DB::selectOne("SELECT name, currency FROM stores WHERE id = ?", [$chat->store_id]);
                $storeName = $store->name ?? $storeName;
                $currency = $store->currency ?? $currency;
            } elseif ($chat->organization_id) {
                $org = DB::selectOne("SELECT name, currency FROM organizations WHERE id = ?", [$chat->organization_id]);
                $storeName = $org->name ?? $storeName;
                $currency = $org->currency ?? $currency;
            }
        } catch (\Throwable) {}

        $prompt = "You are Wameed AI, an intelligent POS assistant for \"{$storeName}\". Currency: {$currency}.\n"
            . "Respond in the same language the user writes in. Be concise and provide actionable recommendations.";

        if ($featureSlug) {
            $feature = AIFeatureDefinition::where('slug', $featureSlug)->first();
            if ($feature) {
                $prompt .= "\n\nThe user is using the '{$feature->name}' feature.";
            }
        }

        return $prompt;
    }

    private function buildEnrichedSystemPrompt(AIChat $chat, ?string $featureSlug): string
    {
        // Org-level chats (no store) fall back to the lightweight prompt — the
        // store-context aggregator requires a specific store_id.
        if (!$chat->store_id) {
            return $this->buildFallbackSystemPrompt($chat, $featureSlug);
        }

        $prompt = $this->storeDataService->buildStoreContextPrompt($chat->store_id, $chat->organization_id);

        if ($featureSlug) {
            $feature = AIFeatureDefinition::where('slug', $featureSlug)->first();
            if ($feature) {
                $prompt .= "\n\nThe user is using the '{$feature->name}' feature. Prioritize analysis related to this area.";
            }
        }

        return $prompt;
    }

    private function buildConversationHistory(AIChat $chat): array
    {
        $messages = $chat->messages()
            ->orderBy('created_at')
            ->select(['role', 'content'])
            ->limit(self::MAX_CONTEXT_MESSAGES)
            ->get();

        return $messages->map(fn ($m) => [
            'role' => $m->role,
            'content' => $m->content,
        ])->toArray();
    }

    private function autoTitleChat(AIChat $chat, string $firstMessage): void
    {
        try {
            // Generate a short title from the first message
            $title = Str::limit($firstMessage, 50);
            $chat->update(['title' => $title]);
        } catch (\Throwable $e) {
            Log::warning("Failed to auto-title chat: {$e->getMessage()}");
        }
    }
}
