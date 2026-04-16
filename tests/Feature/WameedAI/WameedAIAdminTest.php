<?php

namespace Tests\Feature\WameedAI;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\WameedAI\Models\AIFeatureDefinition;
use App\Domain\WameedAI\Models\AIUsageLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WameedAIAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Organization $org;
    private Store $store;
    private Store $store2;
    private string $token;
    private AIFeatureDefinition $feature;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAITables();

        // Ensure admin AI permissions exist in Spatie's permission table
        foreach ([
            'admin.wameed_ai.view', 'admin.wameed_ai.manage',
            'wameed_ai.view', 'wameed_ai.manage', 'wameed_ai.use',
        ] as $perm) {
            Permission::findOrCreate($perm, 'sanctum');
        }

        // Reset Spatie's permission cache
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->org = Organization::create([
            'name' => 'Admin Test Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Alpha Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->store2 = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Beta Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => false,
        ]);

        $this->admin = User::create([
            'name' => 'Platform Admin',
            'email' => 'admin@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->admin->createToken('test', ['*'])->plainTextToken;
        $this->admin->givePermissionTo([
            'admin.wameed_ai.view',
            'admin.wameed_ai.manage',
            'wameed_ai.view',
            'wameed_ai.manage',
        ]);

        // Create 'owner' role in the roles table and link via model_has_roles
        // Required by the custom CheckPermission middleware
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'owner',
            'guard_name' => 'sanctum',
            'store_id' => $this->store->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => get_class($this->admin),
            'model_id' => $this->admin->id,
        ]);

        $this->feature = AIFeatureDefinition::create([
            'slug' => 'smart_reorder',
            'name' => 'Smart Reorder',
            'name_ar' => 'إعادة الطلب الذكي',
            'description' => 'Reorder suggestions',
            'description_ar' => 'اقتراحات إعادة الطلب',
            'category' => 'inventory',
            'icon' => 'refresh_rounded',
            'is_enabled' => true,
            'default_model' => 'gpt-4o-mini',
            'daily_limit' => 50,
            'monthly_limit' => 500,
        ]);
    }

    // ─── Helper: Create AI Tables ──────────────────────────────

    private function createAITables(): void
    {
        $uuidDefault = DB::raw("(lower(hex(randomblob(4))) || '-' || lower(hex(randomblob(2))) || '-4' || substr(lower(hex(randomblob(2))),2) || '-' || substr('89ab',abs(random()) % 4 + 1, 1) || substr(lower(hex(randomblob(2))),2) || '-' || lower(hex(randomblob(6))))");

        if (! Schema::hasTable('ai_feature_definitions')) {
            Schema::create('ai_feature_definitions', function ($table) {
                $table->uuid('id')->primary();
                $table->string('slug', 100)->unique();
                $table->string('name', 255);
                $table->string('name_ar', 255);
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

        if (! Schema::hasTable('ai_usage_logs')) {
            Schema::create('ai_usage_logs', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('organization_id');
                $table->uuid('store_id');
                $table->uuid('user_id')->nullable();
                $table->uuid('ai_feature_definition_id');
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
                $table->text('request_messages')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('ai_llm_models')) {
            Schema::create('ai_llm_models', function ($table) use ($uuidDefault) {
                $table->uuid('id')->primary()->default($uuidDefault);
                $table->string('provider', 50)->default('openai');
                $table->string('model_id', 100);
                $table->string('display_name', 255);
                $table->text('description')->nullable();
                $table->string('api_key_encrypted', 500)->nullable();
                $table->boolean('is_enabled')->default(true);
                $table->boolean('is_default')->default(false);
                $table->decimal('input_price_per_1m', 10, 6)->default(0);
                $table->decimal('output_price_per_1m', 10, 6)->default(0);
                $table->integer('max_context_tokens')->default(128000);
                $table->integer('max_output_tokens')->default(4096);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ai_provider_configs')) {
            Schema::create('ai_provider_configs', function ($table) use ($uuidDefault) {
                $table->uuid('id')->primary()->default($uuidDefault);
                $table->string('provider', 50)->default('openai');
                $table->text('api_key_encrypted');
                $table->string('default_model', 100)->default('gpt-4o-mini');
                $table->integer('max_tokens_per_request')->default(4096);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ai_chats')) {
            Schema::create('ai_chats', function ($table) use ($uuidDefault) {
                $table->uuid('id')->primary()->default($uuidDefault);
                $table->uuid('organization_id');
                $table->uuid('store_id');
                $table->uuid('user_id');
                $table->string('title', 255)->nullable();
                $table->uuid('llm_model_id')->nullable();
                $table->integer('message_count')->default(0);
                $table->integer('total_tokens')->default(0);
                $table->decimal('total_cost_usd', 10, 6)->default(0);
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('ai_chat_messages')) {
            Schema::create('ai_chat_messages', function ($table) use ($uuidDefault) {
                $table->uuid('id')->primary()->default($uuidDefault);
                $table->uuid('chat_id');
                $table->string('role', 20);
                $table->text('content');
                $table->string('feature_slug', 100)->nullable();
                $table->json('feature_data')->nullable();
                $table->json('attachments')->nullable();
                $table->string('model_used', 100)->nullable();
                $table->integer('input_tokens')->default(0);
                $table->integer('output_tokens')->default(0);
                $table->decimal('cost_usd', 10, 6)->default(0);
                $table->integer('latency_ms')->default(0);
                $table->timestamps();
            });
        }
    }

    // ─── Helper: Seed usage logs ───────────────────────────────

    private function createLog(
        string $storeId,
        string $featureSlug = 'smart_reorder',
        string $model = 'gpt-4o-mini',
        string $status = 'success',
        int $tokens = 100,
        float $cost = 0.001,
        int $latency = 500,
        bool $cached = false,
        ?string $userId = null,
    ): AIUsageLog {
        return AIUsageLog::create([
            'organization_id' => $this->org->id,
            'store_id' => $storeId,
            'user_id' => $userId ?? $this->admin->id,
            'ai_feature_definition_id' => $this->feature->id,
            'feature_slug' => $featureSlug,
            'model_used' => $model,
            'input_tokens' => (int) ($tokens * 0.6),
            'output_tokens' => (int) ($tokens * 0.4),
            'total_tokens' => $tokens,
            'estimated_cost_usd' => $cost,
            'response_cached' => $cached,
            'latency_ms' => $latency,
            'status' => $status,
            'error_message' => $status === 'error' ? 'Test error' : null,
            'created_at' => now(),
        ]);
    }

    // ═════════════════════════════════════════════════════════════
    // Platform Logs
    // ═════════════════════════════════════════════════════════════

    public function test_platform_logs_returns_paginated_list(): void
    {
        $this->createLog($this->store->id);
        $this->createLog($this->store2->id);
        $this->createLog($this->store->id, status: 'error');

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/admin/wameed-ai/platform-logs');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id', 'store_id', 'store_name', 'user_id', 'user_name',
                            'feature_slug', 'model_used', 'input_tokens', 'output_tokens',
                            'total_tokens', 'estimated_cost_usd', 'status', 'latency_ms',
                            'response_cached', 'error_message', 'created_at',
                        ],
                    ],
                    'total',
                ],
            ]);

        $this->assertCount(3, $response->json('data.data'));
    }

    public function test_platform_logs_includes_store_name(): void
    {
        $this->createLog($this->store->id);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/admin/wameed-ai/platform-logs');

        $response->assertOk();
        $first = $response->json('data.data.0');
        $this->assertEquals('Alpha Store', $first['store_name']);
    }

    public function test_platform_logs_filter_by_store(): void
    {
        $this->createLog($this->store->id);
        $this->createLog($this->store2->id);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/admin/wameed-ai/platform-logs?store_id=' . $this->store->id);

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals($this->store->id, $response->json('data.data.0.store_id'));
    }

    public function test_platform_logs_filter_by_feature(): void
    {
        $this->createLog($this->store->id, featureSlug: 'smart_reorder');
        $this->createLog($this->store->id, featureSlug: 'price_optimization');

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/admin/wameed-ai/platform-logs?feature=smart_reorder');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('smart_reorder', $response->json('data.data.0.feature_slug'));
    }

    public function test_platform_logs_filter_by_status(): void
    {
        $this->createLog($this->store->id, status: 'success');
        $this->createLog($this->store->id, status: 'error');
        $this->createLog($this->store->id, status: 'error');

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/admin/wameed-ai/platform-logs?status=error');

        $response->assertOk();
        $this->assertCount(2, $response->json('data.data'));
    }

    public function test_platform_logs_filter_by_model(): void
    {
        $this->createLog($this->store->id, model: 'gpt-4o-mini');
        $this->createLog($this->store->id, model: 'claude-3-haiku');

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/admin/wameed-ai/platform-logs?model=claude-3-haiku');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_platform_logs_filter_by_date_range(): void
    {
        $log1 = $this->createLog($this->store->id);
        $log1->update(['created_at' => now()->subDays(10)]);

        $log2 = $this->createLog($this->store->id);
        $log2->update(['created_at' => now()->subDays(2)]);

        $from = now()->subDays(5)->toDateString();
        $to = now()->toDateString();

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/admin/wameed-ai/platform-logs?from={$from}&to={$to}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_platform_logs_search_by_feature_slug(): void
    {
        $this->createLog($this->store->id, featureSlug: 'smart_reorder');
        $this->createLog($this->store->id, featureSlug: 'daily_summary');

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/admin/wameed-ai/platform-logs?search=reorder');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_platform_logs_filter_by_cached(): void
    {
        $this->createLog($this->store->id, cached: true);
        $this->createLog($this->store->id, cached: false);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/admin/wameed-ai/platform-logs?cached=true');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertTrue($response->json('data.data.0.response_cached'));
    }

    public function test_platform_logs_pagination(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createLog($this->store->id);
        }

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/admin/wameed-ai/platform-logs?per_page=2');

        $response->assertOk();
        $this->assertCount(2, $response->json('data.data'));
        $this->assertEquals(5, $response->json('data.total'));
        $this->assertEquals(3, $response->json('data.last_page'));
    }

    // ═════════════════════════════════════════════════════════════
    // Platform Log Stats
    // ═════════════════════════════════════════════════════════════

    public function test_platform_log_stats_returns_all_fields(): void
    {
        $this->createLog($this->store->id, status: 'success', tokens: 200, cost: 0.005, latency: 300);
        $this->createLog($this->store2->id, status: 'success', tokens: 150, cost: 0.003, latency: 400, cached: true);
        $this->createLog($this->store->id, status: 'error', tokens: 50, cost: 0.001, latency: 100);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/admin/wameed-ai/platform-log-stats');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_requests',
                    'success_requests',
                    'error_requests',
                    'cached_requests',
                    'total_tokens',
                    'total_cost_usd',
                    'avg_latency_ms',
                    'unique_stores',
                    'success_rate',
                    'cache_hit_rate',
                    'top_feature',
                    'top_feature_count',
                    'top_model',
                    'top_model_cost',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals(3, $data['total_requests']);
        $this->assertEquals(2, $data['success_requests']);
        $this->assertEquals(1, $data['error_requests']);
        $this->assertEquals(1, $data['cached_requests']);
        $this->assertEquals(400, $data['total_tokens']);
        $this->assertEquals(2, $data['unique_stores']);
    }

    public function test_platform_log_stats_success_rate(): void
    {
        $this->createLog($this->store->id, status: 'success');
        $this->createLog($this->store->id, status: 'success');
        $this->createLog($this->store->id, status: 'error');
        $this->createLog($this->store->id, status: 'error');

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/admin/wameed-ai/platform-log-stats');

        $response->assertOk();
        $this->assertEquals(50.0, $response->json('data.success_rate'));
    }

    public function test_platform_log_stats_cache_hit_rate(): void
    {
        $this->createLog($this->store->id, cached: true);
        $this->createLog($this->store->id, cached: true);
        $this->createLog($this->store->id, cached: false);
        $this->createLog($this->store->id, cached: false);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/admin/wameed-ai/platform-log-stats');

        $response->assertOk();
        $this->assertEquals(50.0, $response->json('data.cache_hit_rate'));
    }

    public function test_platform_log_stats_top_feature(): void
    {
        $this->createLog($this->store->id, featureSlug: 'smart_reorder');
        $this->createLog($this->store->id, featureSlug: 'smart_reorder');
        $this->createLog($this->store->id, featureSlug: 'daily_summary');

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/admin/wameed-ai/platform-log-stats');

        $response->assertOk();
        $this->assertEquals('smart_reorder', $response->json('data.top_feature'));
        $this->assertEquals(2, $response->json('data.top_feature_count'));
    }

    public function test_platform_log_stats_top_model_by_cost(): void
    {
        $this->createLog($this->store->id, model: 'gpt-4o-mini', cost: 0.001);
        $this->createLog($this->store->id, model: 'gpt-4o', cost: 0.05);
        $this->createLog($this->store->id, model: 'gpt-4o', cost: 0.05);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/admin/wameed-ai/platform-log-stats');

        $response->assertOk();
        $this->assertEquals('gpt-4o', $response->json('data.top_model'));
    }

    public function test_platform_log_stats_filter_by_store(): void
    {
        $this->createLog($this->store->id, tokens: 200, cost: 0.01);
        $this->createLog($this->store2->id, tokens: 100, cost: 0.005);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/admin/wameed-ai/platform-log-stats?store_id=' . $this->store->id);

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.total_requests'));
        $this->assertEquals(1, $response->json('data.unique_stores'));
    }

    public function test_platform_log_stats_empty_returns_zeros(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/admin/wameed-ai/platform-log-stats');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals(0, $data['total_requests']);
        $this->assertEquals(0, $data['success_requests']);
        $this->assertEquals(0, $data['total_tokens']);
        $this->assertEquals(0, $data['unique_stores']);
        $this->assertEquals(0, $data['success_rate']);
    }

    // ═════════════════════════════════════════════════════════════
    // Features Endpoint
    // ═════════════════════════════════════════════════════════════

    public function test_admin_can_list_all_features(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/admin/wameed-ai/features');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $features = $response->json('data');
        $this->assertNotEmpty($features);
    }

    public function test_admin_can_toggle_feature(): void
    {
        $this->assertTrue($this->feature->is_enabled);

        $response = $this->withToken($this->token)
            ->patchJson("/api/v2/admin/wameed-ai/features/{$this->feature->id}/toggle");

        $response->assertOk();
        $this->feature->refresh();
        $this->assertFalse($this->feature->is_enabled);
    }

    public function test_admin_can_toggle_feature_back_on(): void
    {
        $this->feature->update(['is_enabled' => false]);

        $response = $this->withToken($this->token)
            ->patchJson("/api/v2/admin/wameed-ai/features/{$this->feature->id}/toggle");

        $response->assertOk();
        $this->feature->refresh();
        $this->assertTrue($this->feature->is_enabled);
    }

    // ═════════════════════════════════════════════════════════════
    // LLM Models
    // ═════════════════════════════════════════════════════════════

    public function test_admin_can_list_llm_models(): void
    {
        DB::table('ai_llm_models')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'provider' => 'openai',
            'model_id' => 'gpt-4o-mini',
            'display_name' => 'GPT-4o Mini',
            'is_enabled' => true,
            'is_default' => true,
            'input_price_per_1m' => 0.00015,
            'output_price_per_1m' => 0.0006,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/admin/wameed-ai/llm-models');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $models = $response->json('data');
        $this->assertNotEmpty($models);
    }

    public function test_admin_can_create_llm_model(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/admin/wameed-ai/llm-models', [
                'provider' => 'anthropic',
                'model_id' => 'claude-3-haiku',
                'display_name' => 'Claude 3 Haiku',
                'description' => 'Fast and affordable',
                'input_price_per_1m' => 0.00025,
                'output_price_per_1m' => 0.00125,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('ai_llm_models', [
            'model_id' => 'claude-3-haiku',
            'provider' => 'anthropic',
        ]);
    }

    public function test_admin_can_update_llm_model(): void
    {
        $modelId = (string) \Illuminate\Support\Str::uuid();
        DB::table('ai_llm_models')->insert([
            'id' => $modelId,
            'provider' => 'openai',
            'model_id' => 'gpt-4o',
            'display_name' => 'GPT-4o',
            'is_enabled' => true,
            'is_default' => false,
            'input_price_per_1m' => 0.005,
            'output_price_per_1m' => 0.015,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/admin/wameed-ai/llm-models/{$modelId}", [
                'display_name' => 'GPT-4o Updated',
                'description' => 'Updated description',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('ai_llm_models', [
            'id' => $modelId,
            'display_name' => 'GPT-4o Updated',
        ]);
    }

    public function test_admin_can_toggle_llm_model(): void
    {
        $modelId = (string) \Illuminate\Support\Str::uuid();
        DB::table('ai_llm_models')->insert([
            'id' => $modelId,
            'provider' => 'openai',
            'model_id' => 'gpt-4o-mini',
            'display_name' => 'GPT-4o Mini',
            'is_enabled' => true,
            'is_default' => false,
            'input_price_per_1m' => 0.00015,
            'output_price_per_1m' => 0.0006,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->patchJson("/api/v2/admin/wameed-ai/llm-models/{$modelId}/toggle");

        $response->assertOk();
        $this->assertDatabaseHas('ai_llm_models', [
            'id' => $modelId,
            'is_enabled' => false,
        ]);
    }

    public function test_admin_can_delete_llm_model(): void
    {
        $modelId = (string) \Illuminate\Support\Str::uuid();
        DB::table('ai_llm_models')->insert([
            'id' => $modelId,
            'provider' => 'google',
            'model_id' => 'gemini-1.5-flash',
            'display_name' => 'Gemini Flash',
            'is_enabled' => true,
            'is_default' => false,
            'input_price_per_1m' => 0.0001,
            'output_price_per_1m' => 0.0004,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/admin/wameed-ai/llm-models/{$modelId}");

        $response->assertOk();
        $this->assertDatabaseMissing('ai_llm_models', ['id' => $modelId]);
    }

    // ═════════════════════════════════════════════════════════════
    // Provider Configs
    // ═════════════════════════════════════════════════════════════

    public function test_admin_can_list_providers(): void
    {
        DB::table('ai_llm_models')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'provider' => 'openai',
            'model_id' => 'gpt-4o-mini',
            'display_name' => 'GPT-4o Mini',
            'is_enabled' => true,
            'is_default' => true,
            'api_key_encrypted' => 'sk-test-key',
            'input_price_per_1m' => 0.00015,
            'output_price_per_1m' => 0.0006,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/admin/wameed-ai/providers');

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    // ═════════════════════════════════════════════════════════════
    // Analytics Dashboard
    // ═════════════════════════════════════════════════════════════

    public function test_admin_can_access_analytics_dashboard(): void
    {
        $this->createLog($this->store->id);
        $this->createLog($this->store2->id);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/admin/wameed-ai/analytics/dashboard');

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    // ═════════════════════════════════════════════════════════════
    // Permission Guard Tests
    // ═════════════════════════════════════════════════════════════

    public function test_unauthenticated_user_cannot_access_admin(): void
    {
        $response = $this->getJson('/api/v2/admin/wameed-ai/platform-logs');
        $response->assertUnauthorized();
    }

    public function test_user_without_permission_cannot_access_admin_endpoints(): void
    {
        $user = User::create([
            'name' => 'Regular User',
            'email' => 'user@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'cashier',
            'organization_id' => $this->org->id,
            'is_active' => true,
        ]);

        $token = $user->createToken('test', ['*'])->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/v2/admin/wameed-ai/platform-logs');

        $response->assertForbidden();
    }
}
