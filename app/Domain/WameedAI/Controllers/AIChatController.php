<?php

namespace App\Domain\WameedAI\Controllers;

use App\Domain\WameedAI\Models\AIChat;
use App\Domain\WameedAI\Services\AIChatService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AIChatController extends BaseApiController
{
    public function __construct(
        private readonly AIChatService $chatService,
    ) {}

    /**
     * Resolve a chat the current request is allowed to act on.
     * Accepts the chat if (a) it belongs to the user, OR (b) the request is
     * org-scoped (no specific store) and the chat belongs to a store the
     * user can access.
     */
    private function findAccessibleChat(Request $request, string $chatId): ?AIChat
    {
        $userId = $request->user()->id;
        $orgId = $this->resolveOrganizationId($request);

        $q = AIChat::where('id', $chatId)->where('organization_id', $orgId);

        if ($this->resolveStoreId($request)) {
            $q->where('user_id', $userId);
        } else {
            $accessible = $this->resolveAccessibleStoreIds($request);
            $q->where(function ($w) use ($accessible, $userId) {
                $w->where('user_id', $userId);
                if (!empty($accessible)) {
                    $w->orWhereIn('store_id', $accessible);
                }
            });
        }

        return $q->first();
    }

    /**
     * GET /wameed-ai/chats — List user's chats.
     */
    public function index(Request $request): JsonResponse
    {
        $storeId = $this->resolveStoreId($request);
        $orgId = $this->resolveOrganizationId($request);
        $userId = $request->user()->id;
        $perPage = (int) $request->query('per_page', 20);
        // Org-scoped users (no store selected) see chats across all accessible stores.
        $accessibleStoreIds = $storeId ? null : $this->resolveAccessibleStoreIds($request);

        $chats = $this->chatService->listChats($orgId, $storeId, $userId, $accessibleStoreIds, $perPage);

        return $this->success([
            'chats' => $chats->items(),
            'total' => $chats->total(),
            'current_page' => $chats->currentPage(),
            'last_page' => $chats->lastPage(),
        ]);
    }

    /**
     * POST /wameed-ai/chats — Create a new chat.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'llm_model_id' => 'nullable|uuid|exists:ai_llm_models,id',
            'title' => 'nullable|string|max:255',
        ]);

        $chat = $this->chatService->createChat(
            organizationId: $this->resolveOrganizationId($request),
            storeId: $this->resolveStoreId($request),
            userId: $request->user()->id,
            llmModelId: $request->input('llm_model_id'),
            title: $request->input('title'),
        );

        return $this->created($chat->load(['llmModel', 'store:id,name']));
    }

    /**
     * GET /wameed-ai/chats/{chatId} — Get chat with messages.
     */
    public function show(Request $request, string $chatId): JsonResponse
    {
        $accessibleStoreIds = $this->resolveStoreId($request)
            ? null
            : $this->resolveAccessibleStoreIds($request);
        $chat = $this->chatService->getChat($chatId, $request->user()->id, $accessibleStoreIds);

        if (!$chat) {
            return $this->notFound('Chat not found');
        }

        return $this->success($chat);
    }

    /**
     * POST /wameed-ai/chats/{chatId}/messages — Send a message.
     */
    public function sendMessage(Request $request, string $chatId): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:10000',
            'feature_slug' => 'nullable|string|max:100',
            'feature_data' => 'nullable|array',
            'image' => 'nullable|string',
        ]);

        $chat = $this->findAccessibleChat($request, $chatId);

        if (!$chat) {
            return $this->notFound('Chat not found');
        }

        try {
            $response = $this->chatService->sendMessage(
                chat: $chat,
                userMessage: $request->input('message'),
                featureSlug: $request->input('feature_slug'),
                featureData: $request->input('feature_data'),
                imageBase64: $request->input('image'),
            );

            if (!$response) {
                return $this->error('Failed to process message', 503);
            }

            // Return both the user's message and the AI response
            $lastMessages = $chat->messages()
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(2)
                ->get()
                ->reverse()
                ->values();

            return $this->success([
                'messages' => $lastMessages,
                'chat' => $chat->fresh(['llmModel']),
            ]);
        } catch (\Throwable $e) {
            Log::error("AIChatController::sendMessage error: {$e->getMessage()}", [
                'chat_id' => $chatId,
                'exception' => $e,
            ]);
            return $this->error('An error occurred while processing your message.', 500);
        }
    }

    /**
     * POST /wameed-ai/chats/{chatId}/feature — Invoke a feature in chat.
     */
    public function invokeFeature(Request $request, string $chatId): JsonResponse
    {
        $request->validate([
            'feature_slug' => 'required|string|max:100',
            'params' => 'nullable|array',
        ]);

        $chat = $this->findAccessibleChat($request, $chatId);

        if (!$chat) {
            return $this->notFound('Chat not found');
        }

        try {
            $response = $this->chatService->invokeFeatureInChat(
                chat: $chat,
                featureSlug: $request->input('feature_slug'),
                featureParams: $request->input('params', []),
            );

            if (!$response) {
                return $this->error('Feature is not available', 503);
            }

            $lastMessages = $chat->messages()
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(2)
                ->get()
                ->reverse()
                ->values();

            return $this->success([
                'messages' => $lastMessages,
                'chat' => $chat->fresh(['llmModel']),
            ]);
        } catch (\Throwable $e) {
            Log::error("AIChatController::invokeFeature error: {$e->getMessage()}");
            return $this->error('An error occurred while invoking the feature.', 500);
        }
    }

    /**
     * PUT /wameed-ai/chats/{chatId}/model — Change chat model.
     */
    public function changeModel(Request $request, string $chatId): JsonResponse
    {
        $request->validate([
            'llm_model_id' => 'required|uuid|exists:ai_llm_models,id',
        ]);

        $chat = $this->findAccessibleChat($request, $chatId);

        if (!$chat) {
            return $this->notFound('Chat not found');
        }

        $success = $this->chatService->changeModel($chat, $request->input('llm_model_id'));

        return $success
            ? $this->success($chat->fresh(['llmModel']))
            : $this->error('Model not available');
    }

    /**
     * DELETE /wameed-ai/chats/{chatId} — Delete a chat.
     */
    public function destroy(Request $request, string $chatId): JsonResponse
    {
        $chat = $this->findAccessibleChat($request, $chatId);

        if (!$chat) {
            return $this->notFound('Chat not found');
        }

        $this->chatService->deleteChat($chat);

        return $this->success(null, 'Chat deleted');
    }

    /**
     * PUT /wameed-ai/chats/{chatId}/title — Rename a chat.
     */
    public function renameChat(Request $request, string $chatId): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $chat = $this->findAccessibleChat($request, $chatId);

        if (!$chat) {
            return $this->notFound('Chat not found');
        }

        $chat->update(['title' => $request->input('title')]);

        return $this->success($chat->fresh(['llmModel']));
    }

    /**
     * GET /wameed-ai/models — List available LLM models.
     */
    public function availableModels(): JsonResponse
    {
        $models = $this->chatService->getAvailableModels();

        return $this->success(['models' => $models]);
    }

    /**
     * GET /wameed-ai/features/cards — Get feature cards for chat overlay.
     */
    public function featureCards(Request $request): JsonResponse
    {
        $features = \App\Domain\WameedAI\Models\AIFeatureDefinition::where('is_enabled', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get(['id', 'slug', 'name as display_name', 'description', 'category', 'icon']);

        $grouped = $features->groupBy('category')->map(function ($group, $category) {
            return [
                'category' => $category,
                'features' => $group->values(),
            ];
        })->values();

        return $this->success(['categories' => $grouped]);
    }
}
