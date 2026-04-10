<?php

namespace Tests\Feature\WameedAI;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\WameedAI\Models\AIFeatureDefinition;
use App\Domain\WameedAI\Models\AIStoreFeatureConfig;
use App\Domain\WameedAI\Models\AISuggestion;
use App\Domain\WameedAI\Models\AIUsageLog;
use App\Domain\WameedAI\Models\AIFeedback;
use App\Domain\WameedAI\Models\AIDailyUsageSummary;
use App\Domain\WameedAI\Models\AIProviderConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WameedAIApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;
    private AIFeatureDefinition $feature;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAITables();

        $this->org = Organization::create([
            'name' => 'Test Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Main Store',
            'business_type' => 'grocery',
            'currency' => 'OMR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'AI Test User',
            'email' => 'ai@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;
        $this->user->givePermissionTo(['wameed_ai.view', 'wameed_ai.manage', 'wameed_ai.use']);

        $this->feature = AIFeatureDefinition::create([
            'slug' => 'smart_reorder',
            'name' => 'Smart Reorder',
            'name_ar' => 'إعادة الطلب الذكي',
            'description' => 'AI-powered reorder suggestions',
            'description_ar' => 'اقتراحات ذكية لإعادة الطلب',
            'category' => 'inventory',
            'icon' => 'refresh_rounded',
            'is_enabled' => true,
            'default_model' => 'gpt-4o-mini',
            'daily_limit' => 50,
            'monthly_limit' => 500,
        ]);
    }

    private function createAITables(): void
    {
        if (Schema::hasTable('ai_provider_configs')) {
            return;
        }

        Schema::create('ai_provider_configs', function ($table) {
            $table->uuid('id')->primary()->default(\Illuminate\Support\Facades\DB::raw("(lower(hex(randomblob(4))) || '-' || lower(hex(randomblob(2))) || '-4' || substr(lower(hex(randomblob(2))),2) || '-' || substr('89ab',abs(random()) % 4 + 1, 1) || substr(lower(hex(randomblob(2))),2) || '-' || lower(hex(randomblob(6))))"));
            $table->string('provider', 50)->default('openai');
            $table->text('api_key_encrypted');
            $table->string('default_model', 100)->default('gpt-4o-mini');
            $table->integer('max_tokens_per_request')->default(4096);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

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
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('ai_daily_usage_summaries', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('store_id');
            $table->date('date');
            $table->integer('total_requests')->default(0);
            $table->integer('cached_requests')->default(0);
            $table->integer('failed_requests')->default(0);
            $table->bigInteger('total_input_tokens')->default(0);
            $table->bigInteger('total_output_tokens')->default(0);
            $table->decimal('total_estimated_cost_usd', 12, 6)->default(0);
            $table->json('feature_breakdown_json')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['store_id', 'date']);
        });

        Schema::create('ai_suggestions', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->string('feature_slug', 100);
            $table->string('suggestion_type', 100);
            $table->string('title', 500);
            $table->string('title_ar', 500)->nullable();
            $table->json('content_json');
            $table->string('priority', 20)->default('medium');
            $table->string('status', 20)->default('pending');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('ai_feedback', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('ai_usage_log_id');
            $table->uuid('store_id');
            $table->uuid('user_id')->nullable();
            $table->smallInteger('rating');
            $table->text('feedback_text')->nullable();
            $table->boolean('is_helpful')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('ai_cache', function ($table) {
            $table->uuid('id')->primary();
            $table->string('cache_key', 255)->unique();
            $table->string('feature_slug', 100);
            $table->uuid('store_id')->nullable();
            $table->text('response_text');
            $table->integer('tokens_used')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('ai_prompts', function ($table) {
            $table->uuid('id')->primary();
            $table->string('feature_slug', 100);
            $table->integer('version')->default(1);
            $table->text('system_prompt');
            $table->text('user_prompt_template');
            $table->string('model', 100)->default('gpt-4o-mini');
            $table->integer('max_tokens')->default(2048);
            $table->decimal('temperature', 3, 2)->default(0.7);
            $table->string('response_format', 20)->default('json_object');
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['feature_slug', 'version']);
        });
    }

    // ─── Features Endpoint Tests ─────────────────────────────

    public function test_can_list_features(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/wameed-ai/features');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertEquals('smart_reorder', $data[0]['slug']);
        $this->assertEquals('Smart Reorder', $data[0]['name']);
    }

    public function test_features_include_store_configs_when_available(): void
    {
        AIStoreFeatureConfig::create([
            'store_id' => $this->store->id,
            'ai_feature_definition_id' => $this->feature->id,
            'is_enabled' => false,
            'daily_limit' => 10,
            'monthly_limit' => 100,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/wameed-ai/features');

        $response->assertOk();
        $features = $response->json('data');
        $this->assertNotEmpty($features);
    }

    public function test_features_requires_authentication(): void
    {
        $response = $this->getJson('/api/v2/wameed-ai/features');

        $response->assertUnauthorized();
    }

    // ─── Store Config Tests ──────────────────────────────────

    public function test_can_get_store_config(): void
    {
        AIStoreFeatureConfig::create([
            'store_id' => $this->store->id,
            'ai_feature_definition_id' => $this->feature->id,
            'is_enabled' => true,
            'daily_limit' => 25,
            'monthly_limit' => 250,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/wameed-ai/config');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $configs = $response->json('data');
        $this->assertCount(1, $configs);
        $this->assertEquals($this->feature->id, $configs[0]['ai_feature_definition_id']);
    }

    public function test_can_update_store_config(): void
    {
        $response = $this->withToken($this->token)
            ->putJson("/api/v2/wameed-ai/config/{$this->feature->id}", [
                'is_enabled' => false,
                'daily_limit' => 10,
                'monthly_limit' => 50,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_enabled', false);

        $this->assertDatabaseHas('ai_store_feature_configs', [
            'store_id' => $this->store->id,
            'ai_feature_definition_id' => $this->feature->id,
            'is_enabled' => false,
        ]);
    }

    public function test_update_config_validates_is_enabled_required(): void
    {
        $response = $this->withToken($this->token)
            ->putJson("/api/v2/wameed-ai/config/{$this->feature->id}", [
                'daily_limit' => 10,
            ]);

        $response->assertStatus(422);
    }

    // ─── Suggestions Tests ───────────────────────────────────

    public function test_can_list_suggestions(): void
    {
        AISuggestion::create([
            'store_id' => $this->store->id,
            'feature_slug' => 'smart_reorder',
            'suggestion_type' => 'reorder_alert',
            'title' => 'Low stock on Item A',
            'title_ar' => 'مخزون منخفض للمنتج أ',
            'content_json' => json_encode(['product_id' => 'abc', 'recommended_qty' => 50]),
            'priority' => 'high',
            'status' => 'pending',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/wameed-ai/suggestions');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
    }

    public function test_can_update_suggestion_status(): void
    {
        $suggestion = AISuggestion::create([
            'store_id' => $this->store->id,
            'feature_slug' => 'smart_reorder',
            'suggestion_type' => 'reorder_alert',
            'title' => 'Reorder Item B',
            'content_json' => json_encode(['qty' => 30]),
            'priority' => 'medium',
            'status' => 'pending',
        ]);

        $response = $this->withToken($this->token)
            ->patchJson("/api/v2/wameed-ai/suggestions/{$suggestion->id}/status", [
                'status' => 'accepted',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    // ─── Feedback Tests ──────────────────────────────────────

    public function test_can_submit_feedback(): void
    {
        $usageLog = AIUsageLog::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'ai_feature_definition_id' => $this->feature->id,
            'feature_slug' => 'smart_reorder',
            'model_used' => 'gpt-4o-mini',
            'input_tokens' => 100,
            'output_tokens' => 200,
            'total_tokens' => 300,
            'estimated_cost_usd' => 0.0003,
            'latency_ms' => 1200,
            'status' => 'success',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/wameed-ai/feedback', [
                'ai_usage_log_id' => $usageLog->id,
                'rating' => 5,
                'feedback_text' => 'Very helpful suggestions!',
                'is_helpful' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('ai_feedback', [
            'ai_usage_log_id' => $usageLog->id,
            'rating' => 5,
        ]);
    }

    public function test_feedback_validates_rating_range(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/wameed-ai/feedback', [
                'ai_usage_log_id' => 'some-uuid',
                'rating' => 10,
            ]);

        $response->assertStatus(422);
    }

    public function test_feedback_requires_ai_usage_log_id(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/wameed-ai/feedback', [
                'rating' => 3,
            ]);

        $response->assertStatus(422);
    }

    // ─── Usage Tests ─────────────────────────────────────────

    public function test_can_get_usage_summary(): void
    {
        AIUsageLog::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'ai_feature_definition_id' => $this->feature->id,
            'feature_slug' => 'smart_reorder',
            'model_used' => 'gpt-4o-mini',
            'input_tokens' => 100,
            'output_tokens' => 200,
            'total_tokens' => 300,
            'estimated_cost_usd' => 0.0003,
            'latency_ms' => 1200,
            'status' => 'success',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/wameed-ai/usage');

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_can_get_usage_history(): void
    {
        AIDailyUsageSummary::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'date' => now()->subDay()->toDateString(),
            'total_requests' => 15,
            'cached_requests' => 3,
            'failed_requests' => 1,
            'total_input_tokens' => 5000,
            'total_output_tokens' => 8000,
            'total_estimated_cost_usd' => 0.05,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/wameed-ai/usage/history');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertEquals(15, $data[0]['total_requests']);
    }

    public function test_can_get_usage_logs(): void
    {
        AIUsageLog::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'ai_feature_definition_id' => $this->feature->id,
            'feature_slug' => 'smart_reorder',
            'model_used' => 'gpt-4o-mini',
            'input_tokens' => 100,
            'output_tokens' => 200,
            'total_tokens' => 300,
            'estimated_cost_usd' => 0.0003,
            'latency_ms' => 500,
            'status' => 'success',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/wameed-ai/usage/logs');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertEquals('smart_reorder', $data[0]['feature_slug']);
        $this->assertEquals(0.0003, $data[0]['estimated_cost_usd']);
        $this->assertEquals(500, $data[0]['latency_ms']);
    }

    // ─── Feature Definition Model Tests ──────────────────────

    public function test_feature_definition_relationships(): void
    {
        $config = AIStoreFeatureConfig::create([
            'store_id' => $this->store->id,
            'ai_feature_definition_id' => $this->feature->id,
            'is_enabled' => true,
        ]);

        $loaded = AIFeatureDefinition::with('storeConfigs')->find($this->feature->id);
        $this->assertCount(1, $loaded->storeConfigs);
        $this->assertEquals($this->store->id, $loaded->storeConfigs->first()->store_id);
    }

    public function test_store_config_belongs_to_feature_definition(): void
    {
        $config = AIStoreFeatureConfig::create([
            'store_id' => $this->store->id,
            'ai_feature_definition_id' => $this->feature->id,
            'is_enabled' => false,
        ]);

        $loaded = AIStoreFeatureConfig::with('featureDefinition')->find($config->id);
        $this->assertEquals('smart_reorder', $loaded->featureDefinition->slug);
    }

    // ─── Multiple Features Test ──────────────────────────────

    public function test_features_sorted_by_category_and_sort_order(): void
    {
        AIFeatureDefinition::create([
            'slug' => 'daily_summary',
            'name' => 'Daily Summary',
            'name_ar' => 'الملخص اليومي',
            'category' => 'sales',
            'icon' => 'summarize_outlined',
            'is_enabled' => true,
            'default_model' => 'gpt-4o-mini',
            'sort_order' => 0,
        ]);

        AIFeatureDefinition::create([
            'slug' => 'dead_stock',
            'name' => 'Dead Stock Detector',
            'name_ar' => 'كاشف المخزون الراكد',
            'category' => 'inventory',
            'icon' => 'inventory_2_outlined',
            'is_enabled' => true,
            'default_model' => 'gpt-4o-mini',
            'sort_order' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/wameed-ai/features');

        $response->assertOk();
        $slugs = collect($response->json('data'))->pluck('slug')->toArray();
        // inventory comes before sales alphabetically
        $inventoryIdx = array_search('smart_reorder', $slugs);
        $salesIdx = array_search('daily_summary', $slugs);
        $this->assertLessThan($salesIdx, $inventoryIdx);
    }

    public function test_disabled_features_not_listed(): void
    {
        AIFeatureDefinition::create([
            'slug' => 'disabled_feature',
            'name' => 'Disabled',
            'name_ar' => 'معطل',
            'category' => 'test',
            'is_enabled' => false,
            'default_model' => 'gpt-4o-mini',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/wameed-ai/features');

        $response->assertOk();
        $slugs = collect($response->json('data'))->pluck('slug')->toArray();
        $this->assertNotContains('disabled_feature', $slugs);
    }

    // ─── Resource Field Integrity Tests ──────────────────────

    public function test_usage_log_resource_has_correct_fields(): void
    {
        AIUsageLog::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'ai_feature_definition_id' => $this->feature->id,
            'feature_slug' => 'smart_reorder',
            'model_used' => 'gpt-4o-mini',
            'input_tokens' => 150,
            'output_tokens' => 250,
            'total_tokens' => 400,
            'estimated_cost_usd' => 0.0004,
            'response_cached' => true,
            'latency_ms' => 800,
            'status' => 'success',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/wameed-ai/usage/logs');

        $response->assertOk();
        $log = $response->json('data.0');
        $this->assertArrayHasKey('estimated_cost_usd', $log);
        $this->assertArrayHasKey('latency_ms', $log);
        $this->assertArrayHasKey('response_cached', $log);
        $this->assertArrayHasKey('total_tokens', $log);
        $this->assertTrue($log['response_cached']);
        $this->assertEquals(800, $log['latency_ms']);
    }

    public function test_suggestion_resource_has_correct_fields(): void
    {
        AISuggestion::create([
            'store_id' => $this->store->id,
            'feature_slug' => 'smart_reorder',
            'suggestion_type' => 'reorder_alert',
            'title' => 'Test suggestion',
            'content_json' => json_encode(['key' => 'value']),
            'priority' => 'high',
            'status' => 'pending',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/wameed-ai/suggestions');

        $response->assertOk();
        $suggestion = $response->json('data.0');

        // Verify new field names are present
        $this->assertArrayHasKey('content_json', $suggestion);
        $this->assertArrayHasKey('suggestion_type', $suggestion);
        // Verify old field names are NOT present
        $this->assertArrayNotHasKey('body', $suggestion);
        $this->assertArrayNotHasKey('body_ar', $suggestion);
        $this->assertArrayNotHasKey('metadata', $suggestion);
    }

    public function test_feature_definition_resource_has_correct_fields(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/wameed-ai/features');

        $response->assertOk();
        $feature = $response->json('data.0');
        $this->assertArrayHasKey('is_enabled', $feature);
        $this->assertArrayHasKey('is_premium', $feature);
        $this->assertArrayHasKey('daily_limit', $feature);
        $this->assertArrayHasKey('monthly_limit', $feature);
        $this->assertArrayHasKey('sort_order', $feature);
        // Verify old field name is NOT present
        $this->assertArrayNotHasKey('is_active', $feature);
    }

    // ─── Edge Cases ──────────────────────────────────────────

    public function test_empty_suggestions_returns_empty_array(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/wameed-ai/suggestions');

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_usage_with_no_logs_returns_summary(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/wameed-ai/usage');

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_config_upsert_creates_new_config(): void
    {
        $this->assertDatabaseMissing('ai_store_feature_configs', [
            'store_id' => $this->store->id,
            'ai_feature_definition_id' => $this->feature->id,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/wameed-ai/config/{$this->feature->id}", [
                'is_enabled' => true,
                'daily_limit' => 75,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('ai_store_feature_configs', [
            'store_id' => $this->store->id,
            'ai_feature_definition_id' => $this->feature->id,
            'daily_limit' => 75,
        ]);
    }

    public function test_config_upsert_updates_existing_config(): void
    {
        AIStoreFeatureConfig::create([
            'store_id' => $this->store->id,
            'ai_feature_definition_id' => $this->feature->id,
            'is_enabled' => true,
            'daily_limit' => 100,
            'monthly_limit' => 3000,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/wameed-ai/config/{$this->feature->id}", [
                'is_enabled' => false,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('ai_store_feature_configs', [
            'store_id' => $this->store->id,
            'ai_feature_definition_id' => $this->feature->id,
            'is_enabled' => false,
        ]);
    }

    public function test_daily_usage_summary_resource_fields(): void
    {
        AIDailyUsageSummary::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'date' => now()->subDay()->toDateString(),
            'total_requests' => 25,
            'cached_requests' => 5,
            'failed_requests' => 2,
            'total_input_tokens' => 10000,
            'total_output_tokens' => 15000,
            'total_estimated_cost_usd' => 0.08,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/wameed-ai/usage/history');

        $response->assertOk();
        $summary = $response->json('data.0');
        $this->assertArrayHasKey('total_requests', $summary);
        $this->assertArrayHasKey('cached_requests', $summary);
        $this->assertArrayHasKey('failed_requests', $summary);
        $this->assertArrayHasKey('total_input_tokens', $summary);
        $this->assertArrayHasKey('total_output_tokens', $summary);
        $this->assertArrayHasKey('total_estimated_cost_usd', $summary);
        // Old field names should NOT be present
        $this->assertArrayNotHasKey('request_count', $summary);
        $this->assertArrayNotHasKey('total_cost', $summary);
        $this->assertArrayNotHasKey('avg_response_ms', $summary);
    }
}
