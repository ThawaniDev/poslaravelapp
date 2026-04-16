<?php

namespace Tests\Feature\WameedAI;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\WameedAI\Models\AIChat;
use App\Domain\WameedAI\Models\AIChatMessage;
use App\Domain\WameedAI\Models\AIFeatureDefinition;
use App\Domain\WameedAI\Models\AILlmModel;
use App\Domain\WameedAI\Services\AIGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Comprehensive tests for AI Chat API endpoints.
 *
 * Tests the full contract between the Laravel backend and Flutter frontend,
 * ensuring every field is present, every type matches, and every edge case
 * is handled for production readiness.
 */
class AIChatApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;
    private Organization $org;
    private Store $store;
    private string $token;
    private string $otherToken;
    private AILlmModel $defaultModel;
    private AILlmModel $anthropicModel;
    private AILlmModel $disabledModel;
    private AIFeatureDefinition $feature;
    private AIFeatureDefinition $disabledFeature;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();
        $this->seedData();
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 1: AVAILABLE MODELS ENDPOINT
    // GET /api/v2/wameed-ai/models
    // ═══════════════════════════════════════════════════════════

    public function test_available_models_returns_only_enabled(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/wameed-ai/models');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success', 'message', 'data' => [
                    'models' => [
                        '*' => [
                            'id', 'provider', 'model_id', 'display_name', 'description',
                            'supports_vision', 'supports_json_mode',
                            'max_context_tokens', 'max_output_tokens', 'is_default',
                        ],
                    ],
                ],
            ]);

        $models = $response->json('data.models');
        // Should include 2 enabled models, exclude the disabled one
        $this->assertCount(2, $models);
        $modelIds = collect($models)->pluck('model_id')->toArray();
        $this->assertContains('gpt-4o-mini', $modelIds);
        $this->assertContains('claude-3-5-sonnet-20241022', $modelIds);
        $this->assertNotContains('disabled-model', $modelIds);
    }

    public function test_model_response_field_types_match_flutter(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/wameed-ai/models');

        $model = $response->json('data.models.0');

        // Flutter expects: id (String), provider (String), model_id (String),
        // display_name (String), description (String?), supports_vision (bool),
        // supports_json_mode (bool), max_context_tokens (int), max_output_tokens (int),
        // is_default (bool)
        $this->assertIsString($model['id']);
        $this->assertIsString($model['provider']);
        $this->assertMatchesRegularExpression('/^(openai|anthropic|google)$/', $model['provider']);
        $this->assertIsString($model['model_id']);
        $this->assertIsString($model['display_name']);
        $this->assertTrue(is_string($model['description']) || is_null($model['description']));
        $this->assertIsBool($model['supports_vision']);
        $this->assertIsBool($model['supports_json_mode']);
        $this->assertIsInt($model['max_context_tokens']);
        $this->assertIsInt($model['max_output_tokens']);
        $this->assertIsBool($model['is_default']);
    }

    public function test_api_key_encrypted_is_hidden_from_models_response(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/wameed-ai/models');

        $model = $response->json('data.models.0');
        $this->assertArrayNotHasKey('api_key_encrypted', $model);
    }

    public function test_models_are_sorted_by_sort_order(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/wameed-ai/models');

        $models = $response->json('data.models');
        $sortOrders = collect($models)->pluck('sort_order')->toArray();
        $sorted = $sortOrders;
        sort($sorted);
        $this->assertEquals($sorted, $sortOrders);
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 2: FEATURE CARDS ENDPOINT
    // GET /api/v2/wameed-ai/features/cards
    // ═══════════════════════════════════════════════════════════

    public function test_feature_cards_returns_grouped_categories(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/wameed-ai/features/cards');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success', 'message', 'data' => [
                    'categories' => [
                        '*' => [
                            'category',
                            'features' => [
                                '*' => ['id', 'slug', 'display_name', 'description', 'category', 'icon'],
                            ],
                        ],
                    ],
                ],
            ]);
    }

    public function test_feature_cards_excludes_disabled_features(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/wameed-ai/features/cards');

        $allSlugs = [];
        foreach ($response->json('data.categories') as $cat) {
            foreach ($cat['features'] as $f) {
                $allSlugs[] = $f['slug'];
            }
        }
        $this->assertContains('smart_reorder', $allSlugs);
        $this->assertNotContains('disabled_feature', $allSlugs);
    }

    public function test_feature_card_field_types_match_flutter(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/wameed-ai/features/cards');

        $category = $response->json('data.categories.0');
        $this->assertIsString($category['category']);

        $feature = $category['features'][0];
        // Flutter AIFeatureCard.fromJson expects:
        // id (String), slug (String), display_name (String),
        // description (String?), category (String), icon (String?)
        $this->assertIsString($feature['id']);
        $this->assertIsString($feature['slug']);
        $this->assertIsString($feature['display_name']); // aliased from 'name'
        $this->assertTrue(is_string($feature['description']) || is_null($feature['description']));
        $this->assertIsString($feature['category']);
        $this->assertTrue(is_string($feature['icon']) || is_null($feature['icon']));
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 3: CREATE CHAT ENDPOINT
    // POST /api/v2/wameed-ai/chats
    // ═══════════════════════════════════════════════════════════

    public function test_create_chat_with_default_model(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/wameed-ai/chats', []);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Created');

        $data = $response->json('data');
        $this->assertIsString($data['id']);
        $this->assertEquals($this->org->id, $data['organization_id']);
        $this->assertEquals($this->store->id, $data['store_id']);
        $this->assertEquals($this->user->id, $data['user_id']);
        $this->assertEquals('New Chat', $data['title']);
        $this->assertEquals($this->defaultModel->id, $data['llm_model_id']);
        $this->assertNotNull($data['llm_model']);
    }

    public function test_create_chat_with_custom_title(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/wameed-ai/chats', ['title' => 'Marketing Generator']);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Marketing Generator');
    }

    public function test_create_chat_with_specific_model(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/wameed-ai/chats', ['llm_model_id' => $this->anthropicModel->id]);

        $response->assertStatus(201);
        $this->assertEquals($this->anthropicModel->id, $response->json('data.llm_model_id'));
        $this->assertEquals('claude-3-5-sonnet-20241022', $response->json('data.llm_model.model_id'));
    }

    public function test_create_chat_response_types_match_flutter(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/wameed-ai/chats', []);

        $data = $response->json('data');

        // Flutter AIChat.fromJson expects:
        $this->assertIsString($data['id']);
        $this->assertIsString($data['organization_id']);
        $this->assertIsString($data['store_id']);
        $this->assertIsString($data['user_id']);
        $this->assertIsString($data['title']);
        $this->assertTrue(is_string($data['llm_model_id']) || is_null($data['llm_model_id']));
        // message_count and total_tokens: int or int-as-string both work via _toInt
        $this->assertTrue(is_int($data['message_count']) || is_string($data['message_count']));
        $this->assertTrue(is_int($data['total_tokens']) || is_string($data['total_tokens']));
        // total_cost_usd: decimal comes as string from Laravel decimal:6 cast
        $this->assertTrue(is_string($data['total_cost_usd']) || is_numeric($data['total_cost_usd']));
        $this->assertIsString($data['last_message_at']); // datetime string
        $this->assertIsArray($data['llm_model']);
    }

    public function test_create_chat_rejects_disabled_model(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/wameed-ai/chats', ['llm_model_id' => $this->disabledModel->id]);

        // Model validation passes (exists:ai_llm_models,id) but service falls back to default
        $response->assertStatus(201);
        // Since disabled model is not found by enabled() scope, it uses default
        $this->assertEquals($this->defaultModel->id, $response->json('data.llm_model_id'));
    }

    public function test_create_chat_validates_title_length(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/wameed-ai/chats', ['title' => str_repeat('a', 300)]);

        $response->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 4: LIST CHATS ENDPOINT
    // GET /api/v2/wameed-ai/chats
    // ═══════════════════════════════════════════════════════════

    public function test_list_chats_returns_paginated_structure(): void
    {
        // Create 3 chats
        for ($i = 0; $i < 3; $i++) {
            $this->createChat("Chat $i");
        }

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/wameed-ai/chats');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['chats', 'total', 'current_page', 'last_page'],
            ]);

        $this->assertCount(3, $response->json('data.chats'));
        $this->assertEquals(3, $response->json('data.total'));
    }

    public function test_list_chats_ordered_by_last_message_desc(): void
    {
        $chat1 = $this->createChat('Old Chat');
        $chat1->update(['last_message_at' => now()->subHours(2)]);

        $chat2 = $this->createChat('New Chat');
        $chat2->update(['last_message_at' => now()]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/wameed-ai/chats');

        $chats = $response->json('data.chats');
        $this->assertEquals('New Chat', $chats[0]['title']);
        $this->assertEquals('Old Chat', $chats[1]['title']);
    }

    public function test_list_chats_includes_llm_model_subset(): void
    {
        $this->createChat('Test');

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/wameed-ai/chats');

        $chat = $response->json('data.chats.0');
        $this->assertArrayHasKey('llm_model', $chat);
        // llmModel in list should have at least id, provider, model_id, display_name
        $llmModel = $chat['llm_model'];
        $this->assertIsString($llmModel['id']);
        $this->assertIsString($llmModel['provider']);
        $this->assertIsString($llmModel['model_id']);
        $this->assertIsString($llmModel['display_name']);
    }

    public function test_list_chats_isolated_per_user(): void
    {
        $this->createChat('User 1 Chat');

        // Create chat for other user
        AIChat::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'user_id' => $this->otherUser->id,
            'title' => 'Other User Chat',
            'llm_model_id' => $this->defaultModel->id,
            'message_count' => 0,
            'total_tokens' => 0,
            'total_cost_usd' => 0,
            'last_message_at' => now(),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/wameed-ai/chats');

        $this->assertCount(1, $response->json('data.chats'));
        $this->assertEquals('User 1 Chat', $response->json('data.chats.0.title'));
    }

    public function test_list_chats_respects_pagination(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createChat("Chat $i");
        }

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/wameed-ai/chats?per_page=2');

        $this->assertCount(2, $response->json('data.chats'));
        $this->assertEquals(5, $response->json('data.total'));
        $this->assertEquals(3, $response->json('data.last_page'));
    }

    public function test_list_chats_each_item_types_match_flutter(): void
    {
        $this->createChat('Type Check');

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/wameed-ai/chats');

        $chat = $response->json('data.chats.0');

        // Flutter AIChat.fromJson contract
        $this->assertIsString($chat['id']);
        $this->assertIsString($chat['organization_id']);
        $this->assertIsString($chat['store_id']);
        $this->assertIsString($chat['user_id']);
        $this->assertIsString($chat['title']);
        $this->assertTrue(is_int($chat['message_count']) || is_string($chat['message_count']));
        $this->assertTrue(is_int($chat['total_tokens']) || is_string($chat['total_tokens']));
        $this->assertTrue(is_string($chat['total_cost_usd']) || is_numeric($chat['total_cost_usd']));
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 5: GET CHAT (WITH MESSAGES) ENDPOINT
    // GET /api/v2/wameed-ai/chats/{chatId}
    // ═══════════════════════════════════════════════════════════

    public function test_get_chat_returns_chat_with_messages(): void
    {
        $chat = $this->createChat('Test Chat');
        $this->createMessage($chat, 'user', 'Hello');
        $this->createMessage($chat, 'assistant', 'Hi there!');

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v2/wameed-ai/chats/{$chat->id}");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertEquals($chat->id, $data['id']);
        $this->assertCount(2, $data['messages']);
        $this->assertArrayHasKey('llm_model', $data);
    }

    public function test_get_chat_messages_ordered_by_created_at(): void
    {
        $chat = $this->createChat('Ordering Test');
        $this->createMessage($chat, 'user', 'First', now()->subMinutes(2));
        $this->createMessage($chat, 'assistant', 'Second', now()->subMinutes(1));
        $this->createMessage($chat, 'user', 'Third', now());

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v2/wameed-ai/chats/{$chat->id}");

        $messages = $response->json('data.messages');
        $this->assertEquals('First', $messages[0]['content']);
        $this->assertEquals('Second', $messages[1]['content']);
        $this->assertEquals('Third', $messages[2]['content']);
    }

    public function test_get_chat_returns_404_for_other_user(): void
    {
        $chat = $this->createChat('Secret');

        $response = $this->withHeaders($this->otherAuthHeaders())
            ->getJson("/api/v2/wameed-ai/chats/{$chat->id}");

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_get_chat_returns_404_for_nonexistent(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/wameed-ai/chats/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404);
    }

    public function test_get_chat_message_field_types_match_flutter(): void
    {
        $chat = $this->createChat('Type Test');
        $this->createMessage($chat, 'user', 'Hello', null, 'smart_reorder', ['key' => 'val']);
        $this->createMessage($chat, 'assistant', 'Response', null, null, null, 'gpt-4o-mini', 150, 50, 0.000045, 420);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v2/wameed-ai/chats/{$chat->id}");

        $userMsg = $response->json('data.messages.0');
        $assistantMsg = $response->json('data.messages.1');

        // Flutter AIChatMessage.fromJson contract
        // Required fields (must always be present)
        $this->assertIsString($userMsg['id']);
        $this->assertIsString($userMsg['chat_id']);
        $this->assertEquals('user', $userMsg['role']);
        $this->assertIsString($userMsg['content']);
        $this->assertEquals('smart_reorder', $userMsg['feature_slug']);
        $this->assertIsArray($userMsg['feature_data']);
        $this->assertIsString($userMsg['created_at']);

        // Assistant message with token data
        $this->assertEquals('assistant', $assistantMsg['role']);
        $this->assertEquals('gpt-4o-mini', $assistantMsg['model_used']);
        // Token fields: int or string (both parsed by _toInt in Flutter)
        $this->assertTrue(is_int($assistantMsg['input_tokens']) || is_string($assistantMsg['input_tokens']));
        $this->assertTrue(is_int($assistantMsg['output_tokens']) || is_string($assistantMsg['output_tokens']));
        // cost_usd: decimal:6 cast returns string like "0.000045"
        $this->assertTrue(is_string($assistantMsg['cost_usd']) || is_numeric($assistantMsg['cost_usd']));
        $this->assertTrue(is_int($assistantMsg['latency_ms']) || is_string($assistantMsg['latency_ms']));
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 6: SEND MESSAGE ENDPOINT
    // POST /api/v2/wameed-ai/chats/{chatId}/messages
    // ═══════════════════════════════════════════════════════════

    public function test_send_message_returns_messages_and_updated_chat(): void
    {
        $chat = $this->createChat('Send Test');

        // Mock the gateway so we don't call OpenAI
        $this->mockGateway('Hello! How can I help?', 100, 50);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v2/wameed-ai/chats/{$chat->id}/messages", [
                'message' => 'What are my top products?',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'messages' => [
                        '*' => ['id', 'chat_id', 'role', 'content', 'model_used', 'input_tokens', 'output_tokens', 'cost_usd', 'latency_ms', 'created_at'],
                    ],
                    'chat' => ['id', 'title', 'message_count', 'total_tokens', 'total_cost_usd', 'llm_model'],
                ],
            ]);

        $messages = $response->json('data.messages');
        $this->assertCount(2, $messages);
        $this->assertEquals('user', $messages[0]['role']);
        $this->assertEquals('What are my top products?', $messages[0]['content']);
        $this->assertEquals('assistant', $messages[1]['role']);
        $this->assertEquals('Hello! How can I help?', $messages[1]['content']);

        // Chat stats should be updated
        $updatedChat = $response->json('data.chat');
        $this->assertEquals(2, (int) $updatedChat['message_count']);
        $this->assertGreaterThan(0, (int) $updatedChat['total_tokens']);
    }

    public function test_send_message_auto_titles_on_first_message(): void
    {
        $chat = $this->createChat('New Chat');
        $this->mockGateway('Response');

        $this->withHeaders($this->authHeaders())
            ->postJson("/api/v2/wameed-ai/chats/{$chat->id}/messages", [
                'message' => 'Analyze my monthly revenue trends please',
            ]);

        $chat->refresh();
        // Auto-title truncates to 50 chars
        $this->assertNotEquals('New Chat', $chat->title);
        $this->assertStringStartsWith('Analyze my monthly revenue trends', $chat->title);
    }

    public function test_send_message_with_feature_slug(): void
    {
        $chat = $this->createChat('Feature Test');
        $this->mockGateway('Reorder suggestions...');

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v2/wameed-ai/chats/{$chat->id}/messages", [
                'message' => 'Show me reorder suggestions',
                'feature_slug' => 'smart_reorder',
                'feature_data' => ['category' => 'beverages'],
            ]);

        $response->assertOk();
        $userMsg = $response->json('data.messages.0');
        $this->assertEquals('smart_reorder', $userMsg['feature_slug']);
        $this->assertIsArray($userMsg['feature_data']);
    }

    public function test_send_message_validates_required_message(): void
    {
        $chat = $this->createChat('Validation Test');

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v2/wameed-ai/chats/{$chat->id}/messages", []);

        $response->assertStatus(422);
    }

    public function test_send_message_validates_max_length(): void
    {
        $chat = $this->createChat('Max Length');

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v2/wameed-ai/chats/{$chat->id}/messages", [
                'message' => str_repeat('a', 10001),
            ]);

        $response->assertStatus(422);
    }

    public function test_send_message_returns_404_for_other_users_chat(): void
    {
        $chat = $this->createChat('Private');

        $response = $this->withHeaders($this->otherAuthHeaders())
            ->postJson("/api/v2/wameed-ai/chats/{$chat->id}/messages", [
                'message' => 'Hack attempt',
            ]);

        $response->assertStatus(404);
    }

    public function test_send_message_response_types_match_flutter_parser(): void
    {
        $chat = $this->createChat('Type Match');
        $this->mockGateway('AI Response', 200, 80);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v2/wameed-ai/chats/{$chat->id}/messages", [
                'message' => 'Test',
            ]);

        // This tests the exact contract Flutter's sendMessage() parses:
        // response['data']['messages'] -> List<AIChatMessage>
        // response['data']['chat'] -> AIChat

        $data = $response->json('data');
        $this->assertArrayHasKey('messages', $data);
        $this->assertArrayHasKey('chat', $data);
        $this->assertIsArray($data['messages']);

        foreach ($data['messages'] as $msg) {
            $this->assertIsString($msg['id']);
            $this->assertIsString($msg['chat_id']);
            $this->assertContains($msg['role'], ['user', 'assistant']);
            $this->assertIsString($msg['content']);
            $this->assertArrayHasKey('feature_slug', $msg);
            $this->assertArrayHasKey('feature_data', $msg);
            $this->assertArrayHasKey('model_used', $msg);
            $this->assertArrayHasKey('input_tokens', $msg);
            $this->assertArrayHasKey('output_tokens', $msg);
            $this->assertArrayHasKey('cost_usd', $msg);
            $this->assertArrayHasKey('latency_ms', $msg);
            $this->assertArrayHasKey('created_at', $msg);
        }

        $chatData = $data['chat'];
        $this->assertArrayHasKey('llm_model', $chatData);
        $this->assertArrayHasKey('message_count', $chatData);
        $this->assertArrayHasKey('total_tokens', $chatData);
        $this->assertArrayHasKey('total_cost_usd', $chatData);
    }

    public function test_send_message_increments_stats_correctly(): void
    {
        $chat = $this->createChat('Stats Test');

        // Use a single mock with two sequential returns
        $this->mock(AIGatewayService::class, function ($mock) {
            $mock->shouldReceive('chatCall')->twice()->andReturn(
                [
                    'content' => 'First',
                    'model' => 'gpt-4o-mini',
                    'input_tokens' => 100,
                    'output_tokens' => 50,
                    'cost' => (100 * 0.15 + 50 * 0.6) / 1_000_000,
                    'latency_ms' => 420,
                ],
                [
                    'content' => 'Second',
                    'model' => 'gpt-4o-mini',
                    'input_tokens' => 80,
                    'output_tokens' => 40,
                    'cost' => (80 * 0.15 + 40 * 0.6) / 1_000_000,
                    'latency_ms' => 420,
                ],
            );
        });

        $this->withHeaders($this->authHeaders())
            ->postJson("/api/v2/wameed-ai/chats/{$chat->id}/messages", ['message' => 'First']);

        $chat->refresh();
        $this->assertEquals(2, $chat->message_count);
        $this->assertEquals(150, $chat->total_tokens);

        $this->withHeaders($this->authHeaders())
            ->postJson("/api/v2/wameed-ai/chats/{$chat->id}/messages", ['message' => 'Second']);

        $chat->refresh();
        $this->assertEquals(4, $chat->message_count);
        $this->assertEquals(270, $chat->total_tokens);
    }

    public function test_send_message_handles_gateway_failure_gracefully(): void
    {
        $chat = $this->createChat('Failure Test');

        // Mock gateway to return null (failure)
        $this->mock(AIGatewayService::class, function ($mock) {
            $mock->shouldReceive('chatCall')->once()->andReturn(null);
        });

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v2/wameed-ai/chats/{$chat->id}/messages", [
                'message' => 'This will fail',
            ]);

        // Should still return 200 with error message in assistant response
        $response->assertOk();
        $messages = $response->json('data.messages');
        $this->assertCount(2, $messages);
        $assistantMsg = collect($messages)->firstWhere('role', 'assistant');
        $this->assertStringContains('error', strtolower($assistantMsg['content']));
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 7: INVOKE FEATURE ENDPOINT
    // POST /api/v2/wameed-ai/chats/{chatId}/feature
    // ═══════════════════════════════════════════════════════════

    public function test_invoke_feature_sends_prefixed_message(): void
    {
        $chat = $this->createChat('Feature Invoke');
        $this->mockGateway('Reorder analysis ready');

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v2/wameed-ai/chats/{$chat->id}/feature", [
                'feature_slug' => 'smart_reorder',
                'params' => ['category' => 'food'],
            ]);

        $response->assertOk();
        $userMsg = $response->json('data.messages.0');
        $this->assertStringContains('Smart Reorder', $userMsg['content']);
        $this->assertEquals('smart_reorder', $userMsg['feature_slug']);
    }

    public function test_invoke_feature_validates_required_slug(): void
    {
        $chat = $this->createChat('Validation');

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v2/wameed-ai/chats/{$chat->id}/feature", []);

        $response->assertStatus(422);
    }

    public function test_invoke_disabled_feature_returns_503(): void
    {
        $chat = $this->createChat('Disabled Feature');

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v2/wameed-ai/chats/{$chat->id}/feature", [
                'feature_slug' => 'disabled_feature',
            ]);

        $response->assertStatus(503);
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 8: CHANGE MODEL ENDPOINT
    // PUT /api/v2/wameed-ai/chats/{chatId}/model
    // ═══════════════════════════════════════════════════════════

    public function test_change_model_updates_chat(): void
    {
        $chat = $this->createChat('Model Switch');

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/v2/wameed-ai/chats/{$chat->id}/model", [
                'llm_model_id' => $this->anthropicModel->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertEquals($this->anthropicModel->id, $data['llm_model_id']);
        $this->assertEquals('claude-3-5-sonnet-20241022', $data['llm_model']['model_id']);
    }

    public function test_change_model_validates_uuid(): void
    {
        $chat = $this->createChat('Validation');

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/v2/wameed-ai/chats/{$chat->id}/model", [
                'llm_model_id' => 'not-a-uuid',
            ]);

        $response->assertStatus(422);
    }

    public function test_change_model_validates_existence(): void
    {
        $chat = $this->createChat('Existence');

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/v2/wameed-ai/chats/{$chat->id}/model", [
                'llm_model_id' => '00000000-0000-0000-0000-000000000000',
            ]);

        $response->assertStatus(422);
    }

    public function test_change_model_rejects_disabled_model(): void
    {
        $chat = $this->createChat('Disabled');

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/v2/wameed-ai/chats/{$chat->id}/model", [
                'llm_model_id' => $this->disabledModel->id,
            ]);

        // Validation passes (exists) but service says model is not available
        $response->assertStatus(400);
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 9: DELETE CHAT ENDPOINT
    // DELETE /api/v2/wameed-ai/chats/{chatId}
    // ═══════════════════════════════════════════════════════════

    public function test_delete_chat_soft_deletes(): void
    {
        $chat = $this->createChat('Delete Me');
        $this->createMessage($chat, 'user', 'Message');

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/v2/wameed-ai/chats/{$chat->id}");

        $response->assertOk()
            ->assertJsonPath('success', true);

        // Soft deleted
        $this->assertSoftDeleted('ai_chats', ['id' => $chat->id]);
        // Messages still exist
        $this->assertDatabaseHas('ai_chat_messages', ['chat_id' => $chat->id]);
    }

    public function test_delete_chat_returns_404_for_other_user(): void
    {
        $chat = $this->createChat('Private');

        $response = $this->withHeaders($this->otherAuthHeaders())
            ->deleteJson("/api/v2/wameed-ai/chats/{$chat->id}");

        $response->assertStatus(404);
    }

    public function test_deleted_chat_not_visible_in_list(): void
    {
        $chat = $this->createChat('Visible');
        $deleted = $this->createChat('Deleted');
        $deleted->delete();

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/wameed-ai/chats');

        $this->assertCount(1, $response->json('data.chats'));
        $this->assertEquals('Visible', $response->json('data.chats.0.title'));
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 10: AUTHENTICATION & ISOLATION
    // ═══════════════════════════════════════════════════════════

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v2/wameed-ai/chats')->assertStatus(401);
        $this->getJson('/api/v2/wameed-ai/models')->assertStatus(401);
        $this->getJson('/api/v2/wameed-ai/features/cards')->assertStatus(401);
        $this->postJson('/api/v2/wameed-ai/chats', [])->assertStatus(401);
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 11: FULL WORKFLOW — END-TO-END SCENARIO
    // ═══════════════════════════════════════════════════════════

    public function test_full_chat_workflow(): void
    {
        // 1. List models
        $modelsResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/wameed-ai/models');
        $modelsResponse->assertOk();
        $models = $modelsResponse->json('data.models');
        $this->assertGreaterThanOrEqual(2, count($models));

        // 2. Get feature cards
        $cardsResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/wameed-ai/features/cards');
        $cardsResponse->assertOk();

        // 3. Create a chat
        $createResponse = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/wameed-ai/chats', ['title' => 'Workflow Test']);
        $createResponse->assertStatus(201);
        $chatId = $createResponse->json('data.id');

        // 4. Send first message
        $this->mock(AIGatewayService::class, function ($mock) {
            $mock->shouldReceive('chatCall')->twice()->andReturn(
                [
                    'content' => 'Here are your top products: ...',
                    'model' => 'gpt-4o-mini',
                    'input_tokens' => 100,
                    'output_tokens' => 50,
                    'cost' => (100 * 0.15 + 50 * 0.6) / 1_000_000,
                    'latency_ms' => 420,
                ],
                [
                    'content' => 'For last month, the breakdown is: ...',
                    'model' => 'gpt-4o-mini',
                    'input_tokens' => 100,
                    'output_tokens' => 50,
                    'cost' => (100 * 0.15 + 50 * 0.6) / 1_000_000,
                    'latency_ms' => 420,
                ],
            );
        });
        $msgResponse = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v2/wameed-ai/chats/{$chatId}/messages", [
                'message' => 'What are my top selling products?',
            ]);
        $msgResponse->assertOk();
        $this->assertCount(2, $msgResponse->json('data.messages'));

        // 5. Send follow-up
        $msgResponse2 = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v2/wameed-ai/chats/{$chatId}/messages", [
                'message' => 'Break it down by month',
            ]);
        $msgResponse2->assertOk();
        $this->assertEquals(4, (int) $msgResponse2->json('data.chat.message_count'));

        // 6. Switch model
        $switchResponse = $this->withHeaders($this->authHeaders())
            ->putJson("/api/v2/wameed-ai/chats/{$chatId}/model", [
                'llm_model_id' => $this->anthropicModel->id,
            ]);
        $switchResponse->assertOk();
        $this->assertEquals('claude-3-5-sonnet-20241022', $switchResponse->json('data.llm_model.model_id'));

        // 7. Get chat with all messages
        $showResponse = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v2/wameed-ai/chats/{$chatId}");
        $showResponse->assertOk();
        $this->assertCount(4, $showResponse->json('data.messages'));

        // 8. List chats — should show our chat
        $listResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/wameed-ai/chats');
        $listResponse->assertOk();
        $this->assertGreaterThanOrEqual(1, count($listResponse->json('data.chats')));

        // 9. Delete chat
        $deleteResponse = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/v2/wameed-ai/chats/{$chatId}");
        $deleteResponse->assertOk();

        // 10. Verify deleted
        $listAfter = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/wameed-ai/chats');
        $chatIds = collect($listAfter->json('data.chats'))->pluck('id')->toArray();
        $this->assertNotContains($chatId, $chatIds);
    }

    // ═══════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════

    private function authHeaders(): array
    {
        return [
            'Authorization' => "Bearer {$this->token}",
            'X-Store-Id' => $this->store->id,
            'Accept' => 'application/json',
        ];
    }

    private function otherAuthHeaders(): array
    {
        return [
            'Authorization' => "Bearer {$this->otherToken}",
            'X-Store-Id' => $this->store->id,
            'Accept' => 'application/json',
        ];
    }

    private function createChat(string $title): AIChat
    {
        return AIChat::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'title' => $title,
            'llm_model_id' => $this->defaultModel->id,
            'message_count' => 0,
            'total_tokens' => 0,
            'total_cost_usd' => 0,
            'last_message_at' => now(),
        ]);
    }

    private function createMessage(
        AIChat $chat,
        string $role,
        string $content,
        ?\DateTimeInterface $createdAt = null,
        ?string $featureSlug = null,
        ?array $featureData = null,
        ?string $modelUsed = null,
        int $inputTokens = 0,
        int $outputTokens = 0,
        float $costUsd = 0,
        int $latencyMs = 0,
    ): AIChatMessage {
        $msg = AIChatMessage::create([
            'chat_id' => $chat->id,
            'role' => $role,
            'content' => $content,
            'feature_slug' => $featureSlug,
            'feature_data' => $featureData,
            'model_used' => $modelUsed,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_usd' => $costUsd,
            'latency_ms' => $latencyMs,
        ]);

        if ($createdAt) {
            $msg->newQuery()->where('id', $msg->id)->update(['created_at' => $createdAt]);
            $msg->refresh();
        }

        return $msg;
    }

    private function mockGateway(string $content, int $inputTokens = 100, int $outputTokens = 50): void
    {
        $this->mock(AIGatewayService::class, function ($mock) use ($content, $inputTokens, $outputTokens) {
            $mock->shouldReceive('chatCall')->once()->andReturn([
                'content' => $content,
                'model' => 'gpt-4o-mini',
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost' => ($inputTokens * 0.15 + $outputTokens * 0.6) / 1_000_000,
                'latency_ms' => 420,
            ]);
        });
    }

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'"
        );
    }

    private function seedData(): void
    {
        $this->org = Organization::create([
            'name' => 'Test AI Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'AI Test Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Chat Test User',
            'email' => 'chat@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;
        $this->otherToken = $this->otherUser->createToken('test', ['*'])->plainTextToken;

        // Create Spatie role with store_id so isOwner() check passes
        $ownerRole = Role::create([
            'name' => 'owner',
            'guard_name' => 'sanctum',
            'store_id' => $this->store->id,
        ]);
        $cashierRole = Role::create([
            'name' => 'cashier',
            'guard_name' => 'sanctum',
            'store_id' => $this->store->id,
        ]);

        // Create permissions
        foreach (['wameed_ai.view', 'wameed_ai.manage', 'wameed_ai.use'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'sanctum']);
        }
        $cashierRole->givePermissionTo(['wameed_ai.view', 'wameed_ai.use']);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Assign roles
        $this->user->assignRole($ownerRole);
        $this->otherUser->assignRole($cashierRole);

        // LLM Models
        $this->defaultModel = AILlmModel::create([
            'provider' => 'openai',
            'model_id' => 'gpt-4o-mini',
            'display_name' => 'GPT-4o Mini',
            'description' => 'Fast and affordable',
            'supports_vision' => true,
            'supports_json_mode' => true,
            'max_context_tokens' => 128000,
            'max_output_tokens' => 16384,
            'input_price_per_1m' => 0.15,
            'output_price_per_1m' => 0.60,
            'is_enabled' => true,
            'is_default' => true,
            'sort_order' => 1,
        ]);

        $this->anthropicModel = AILlmModel::create([
            'provider' => 'anthropic',
            'model_id' => 'claude-3-5-sonnet-20241022',
            'display_name' => 'Claude 3.5 Sonnet',
            'description' => 'Excellent at analysis',
            'supports_vision' => true,
            'supports_json_mode' => true,
            'max_context_tokens' => 200000,
            'max_output_tokens' => 8192,
            'input_price_per_1m' => 3.0,
            'output_price_per_1m' => 15.0,
            'is_enabled' => true,
            'is_default' => false,
            'sort_order' => 10,
        ]);

        $this->disabledModel = AILlmModel::create([
            'provider' => 'openai',
            'model_id' => 'disabled-model',
            'display_name' => 'Disabled Model',
            'description' => 'Should not appear',
            'supports_vision' => false,
            'supports_json_mode' => false,
            'max_context_tokens' => 8000,
            'max_output_tokens' => 2048,
            'input_price_per_1m' => 0,
            'output_price_per_1m' => 0,
            'is_enabled' => false,
            'is_default' => false,
            'sort_order' => 99,
        ]);

        // Features
        $this->feature = AIFeatureDefinition::create([
            'slug' => 'smart_reorder',
            'name' => 'Smart Reorder',
            'name_ar' => 'إعادة الطلب الذكي',
            'description' => 'AI-powered reorder suggestions',
            'description_ar' => 'اقتراحات إعادة الطلب',
            'category' => 'inventory',
            'icon' => 'refresh_rounded',
            'is_enabled' => true,
            'default_model' => 'gpt-4o-mini',
            'daily_limit' => 50,
            'monthly_limit' => 500,
        ]);

        $this->disabledFeature = AIFeatureDefinition::create([
            'slug' => 'disabled_feature',
            'name' => 'Disabled Feature',
            'name_ar' => 'ميزة معطلة',
            'description' => 'This is disabled',
            'description_ar' => 'معطل',
            'category' => 'sales',
            'icon' => 'block',
            'is_enabled' => false,
            'default_model' => 'gpt-4o-mini',
            'daily_limit' => 50,
            'monthly_limit' => 500,
        ]);
    }

    private function createTables(): void
    {
        // Existing AI tables (from WameedAIApiTest)
        if (!Schema::hasTable('ai_provider_configs')) {
            Schema::create('ai_provider_configs', function ($table) {
                $table->uuid('id')->primary();
                $table->string('provider', 50)->default('openai');
                $table->text('api_key_encrypted');
                $table->string('default_model', 100)->default('gpt-4o-mini');
                $table->integer('max_tokens_per_request')->default(4096);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ai_feature_definitions')) {
            Schema::create('ai_feature_definitions', function ($table) {
                $table->uuid('id')->primary();
                $table->string('slug', 100)->unique();
                $table->string('name', 255);
                $table->string('name_ar', 255)->nullable();
                $table->text('description')->nullable();
                $table->text('description_ar')->nullable();
                $table->string('category', 50);
                $table->string('icon', 100)->nullable();
                $table->boolean('is_enabled')->default(true);
                $table->boolean('is_premium')->default(false);
                $table->string('default_model', 100)->default('gpt-4o-mini');
                $table->integer('default_max_tokens')->default(2048);
                $table->decimal('cost_per_request_estimate', 10, 6)->default(0.001);
                $table->integer('daily_limit')->default(50);
                $table->integer('monthly_limit')->default(500);
                $table->json('requires_subscription_plan')->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ai_store_feature_configs')) {
            Schema::create('ai_store_feature_configs', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id');
                $table->uuid('ai_feature_definition_id');
                $table->boolean('is_enabled')->default(true);
                $table->integer('daily_limit')->default(100);
                $table->integer('monthly_limit')->default(3000);
                $table->text('custom_prompt_override')->nullable();
                $table->json('settings_json')->nullable();
                $table->timestamps();
                $table->unique(['store_id', 'ai_feature_definition_id']);
            });
        }

        if (!Schema::hasTable('ai_usage_logs')) {
            Schema::create('ai_usage_logs', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('organization_id');
                $table->uuid('store_id');
                $table->uuid('user_id')->nullable();
                $table->uuid('ai_feature_definition_id')->nullable();
                $table->string('feature_slug', 100);
                $table->string('model_used', 100);
                $table->integer('input_tokens')->default(0);
                $table->integer('output_tokens')->default(0);
                $table->integer('total_tokens')->default(0);
                $table->decimal('estimated_cost_usd', 10, 6)->default(0);
                $table->string('request_payload_hash', 255)->nullable();
                $table->boolean('response_cached')->default(false);
                $table->integer('latency_ms')->default(0);
                $table->string('status', 20)->default('success');
                $table->text('error_message')->nullable();
                $table->json('metadata_json')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        // New chat tables
        if (!Schema::hasTable('ai_llm_models')) {
            Schema::create('ai_llm_models', function ($table) {
                $table->uuid('id')->primary();
                $table->string('provider', 20)->index();
                $table->string('model_id', 80);
                $table->string('display_name', 120);
                $table->string('description', 500)->nullable();
                $table->string('api_key_encrypted', 500)->nullable();
                $table->boolean('supports_vision')->default(false);
                $table->boolean('supports_json_mode')->default(true);
                $table->integer('max_context_tokens')->default(128000);
                $table->integer('max_output_tokens')->default(4096);
                $table->decimal('input_price_per_1m', 10, 4)->default(0);
                $table->decimal('output_price_per_1m', 10, 4)->default(0);
                $table->boolean('is_enabled')->default(true);
                $table->boolean('is_default')->default(false);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['provider', 'model_id']);
            });
        }

        if (!Schema::hasTable('ai_chats')) {
            Schema::create('ai_chats', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('organization_id')->index();
                $table->uuid('store_id')->index();
                $table->uuid('user_id')->index();
                $table->string('title', 200)->nullable();
                $table->uuid('llm_model_id')->nullable();
                $table->integer('message_count')->default(0);
                $table->integer('total_tokens')->default(0);
                $table->decimal('total_cost_usd', 10, 6)->default(0);
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('ai_chat_messages')) {
            Schema::create('ai_chat_messages', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('chat_id')->index();
                $table->string('role', 20);
                $table->text('content');
                $table->string('feature_slug', 80)->nullable();
                $table->json('feature_data')->nullable();
                $table->json('attachments')->nullable();
                $table->string('model_used', 80)->nullable();
                $table->integer('input_tokens')->default(0);
                $table->integer('output_tokens')->default(0);
                $table->decimal('cost_usd', 10, 6)->default(0);
                $table->integer('latency_ms')->default(0);
                $table->timestamps();
            });
        }
    }
}
