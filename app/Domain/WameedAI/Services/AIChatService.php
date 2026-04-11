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
    ) {}

    /**
     * Create a new chat.
     */
    public function createChat(
        string $organizationId,
        string $storeId,
        string $userId,
        ?string $llmModelId = null,
        ?string $title = null,
    ): AIChat {
        $model = $llmModelId
            ? AILlmModel::enabled()->where('id', $llmModelId)->first()
            : AILlmModel::enabled()->where('is_default', true)->first();

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
     * List chats for a user/store sorted by last message.
     */
    public function listChats(string $storeId, string $userId, int $perPage = 20): mixed
    {
        return AIChat::where('store_id', $storeId)
            ->where('user_id', $userId)
            ->with('llmModel:id,provider,model_id,display_name')
            ->orderByDesc('last_message_at')
            ->paginate($perPage);
    }

    /**
     * Get chat with messages.
     */
    public function getChat(string $chatId, string $userId): ?AIChat
    {
        return AIChat::where('id', $chatId)
            ->where('user_id', $userId)
            ->with(['messages' => fn ($q) => $q->orderBy('created_at'), 'llmModel'])
            ->first();
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
        // 1. Save user message
        $userMsg = $chat->messages()->create([
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
            'role' => 'assistant',
            'content' => $response['content'],
            'feature_slug' => $featureSlug,
            'model_used' => $response['model'],
            'input_tokens' => $response['input_tokens'],
            'output_tokens' => $response['output_tokens'],
            'cost_usd' => $response['cost'],
            'latency_ms' => $response['latency_ms'],
        ]);

        // 5. Update chat stats
        $chat->update([
            'message_count' => DB::raw('message_count + 2'),
            'total_tokens' => DB::raw("total_tokens + {$response['input_tokens']} + {$response['output_tokens']}"),
            'total_cost_usd' => DB::raw("total_cost_usd + {$response['cost']}"),
            'last_message_at' => now(),
        ]);

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

        $featureDescription = $feature->display_name ?? $featureSlug;
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
        $storeName = DB::selectOne("SELECT name FROM stores WHERE id = ?", [$chat->store_id])?->name ?? 'your store';
        $currency = DB::selectOne("SELECT currency FROM stores WHERE id = ?", [$chat->store_id])?->currency ?? 'OMR';

        $prompt = <<<PROMPT
You are Wameed AI, an intelligent assistant for "{$storeName}" POS system. You help store owners and managers with business insights, inventory management, sales analytics, customer intelligence, and operational efficiency.

Key guidelines:
- Always respond in the same language the user writes in (Arabic or English).
- Use {$currency} as the currency for all monetary values.
- Be concise but thorough. Use tables, bullet points, and structured formatting when appropriate.
- When analyzing data, provide actionable recommendations.
- If you don't have enough data to answer, say so clearly.
- You have access to the store's real-time data including transactions, inventory, customers, and staff metrics.
PROMPT;

        if ($featureSlug) {
            $feature = AIFeatureDefinition::where('slug', $featureSlug)->first();
            if ($feature) {
                $prompt .= "\n\nThe user is currently using the '{$feature->display_name}' feature. Focus your responses on this area.";
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
