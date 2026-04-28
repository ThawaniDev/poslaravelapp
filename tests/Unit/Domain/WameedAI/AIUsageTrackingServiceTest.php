<?php

namespace Tests\Unit\Domain\WameedAI;

use App\Domain\WameedAI\Models\AIFeatureDefinition;
use App\Domain\WameedAI\Models\AIStoreFeatureConfig;
use App\Domain\WameedAI\Models\AIUsageLog;
use App\Domain\WameedAI\Services\AIUsageTrackingService;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AIUsageTrackingServiceTest extends TestCase
{
    use RefreshDatabase;

    private AIUsageTrackingService $service;
    private Organization $org;
    private Store $store;
    private AIFeatureDefinition $feature;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAITablesIfNeeded();
        $this->service = new AIUsageTrackingService();

        $this->org = Organization::create([
            'name' => 'Test Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->feature = AIFeatureDefinition::create([
            'slug' => 'smart_reorder',
            'name' => 'Smart Reorder',
            'name_ar' => 'إعادة الطلب الذكي',
            'category' => 'inventory',
            'is_enabled' => true,
            'default_model' => 'gpt-4o-mini',
        ]);
    }

    private function createAITablesIfNeeded(): void
    {
        if (Schema::hasTable('ai_feature_definitions')) {
            return;
        }

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

        Schema::create('ai_monthly_usage_summaries', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('store_id');
            $table->date('month');
            $table->integer('total_requests')->default(0);
            $table->integer('cached_requests')->default(0);
            $table->integer('failed_requests')->default(0);
            $table->bigInteger('total_input_tokens')->default(0);
            $table->bigInteger('total_output_tokens')->default(0);
            $table->decimal('total_estimated_cost_usd', 12, 6)->default(0);
            $table->json('feature_breakdown_json')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['store_id', 'month']);
        });

        Schema::create('ai_platform_daily_summaries', function ($table) {
            $table->uuid('id')->primary();
            $table->date('date')->unique();
            $table->integer('total_stores_active')->default(0);
            $table->integer('total_requests')->default(0);
            $table->bigInteger('total_tokens')->default(0);
            $table->decimal('total_estimated_cost_usd', 12, 6)->default(0);
            $table->json('feature_breakdown_json')->nullable();
            $table->json('top_stores_json')->nullable();
            $table->decimal('error_rate', 5, 2)->default(0);
            $table->integer('avg_latency_ms')->default(0);
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
    }

    private function createUsageLog(array $overrides = []): AIUsageLog
    {
        return AIUsageLog::create(array_merge([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'ai_feature_definition_id' => $this->feature->id,
            'feature_slug' => 'smart_reorder',
            'model_used' => 'gpt-4o-mini',
            'input_tokens' => 100,
            'output_tokens' => 200,
            'total_tokens' => 300,
            'estimated_cost_usd' => 0.000045,
            'latency_ms' => 800,
            'status' => 'success',
            'response_cached' => false,
            'created_at' => now(),
        ], $overrides));
    }

    // ─── getTodayUsage Tests ─────────────────────────────────

    public function test_get_today_usage_returns_zero_with_no_logs(): void
    {
        $result = $this->service->getTodayUsage($this->store->id);

        $this->assertEquals(0, $result['total_requests']);
        $this->assertEquals(0, $result['cached_requests']);
        $this->assertEquals(0, $result['failed_requests']);
        $this->assertEquals(0, $result['total_tokens']);
        $this->assertEquals(0.0, $result['total_cost_usd']);
    }

    public function test_get_today_usage_counts_logs_correctly(): void
    {
        $this->createUsageLog();
        $this->createUsageLog(['response_cached' => true, 'status' => 'cached']);
        $this->createUsageLog(['status' => 'error', 'error_message' => 'timeout']);

        $result = $this->service->getTodayUsage($this->store->id);

        $this->assertEquals(3, $result['total_requests']);
        $this->assertEquals(1, $result['cached_requests']);
        $this->assertEquals(1, $result['failed_requests']);
    }

    public function test_get_today_usage_aggregates_tokens_and_cost(): void
    {
        $this->createUsageLog(['total_tokens' => 300, 'estimated_cost_usd' => 0.001]);
        $this->createUsageLog(['total_tokens' => 500, 'estimated_cost_usd' => 0.002]);

        $result = $this->service->getTodayUsage($this->store->id);

        $this->assertEquals(800, $result['total_tokens']);
        $this->assertEquals(0.003, $result['total_cost_usd']);
    }

    public function test_get_today_usage_groups_by_feature(): void
    {
        $this->createUsageLog(['feature_slug' => 'smart_reorder']);
        $this->createUsageLog(['feature_slug' => 'smart_reorder']);
        $this->createUsageLog(['feature_slug' => 'dead_stock']);

        $result = $this->service->getTodayUsage($this->store->id);

        $this->assertArrayHasKey('smart_reorder', $result['by_feature']);
        $this->assertArrayHasKey('dead_stock', $result['by_feature']);
    }

    // ─── getUsageByFeature Tests ─────────────────────────────

    public function test_get_usage_by_feature_returns_aggregated_data(): void
    {
        $this->createUsageLog(['feature_slug' => 'smart_reorder']);
        $this->createUsageLog(['feature_slug' => 'smart_reorder']);
        $this->createUsageLog(['feature_slug' => 'dead_stock']);

        $result = $this->service->getUsageByFeature($this->store->id);

        $this->assertCount(2, $result);
        $smartReorder = collect($result)->firstWhere('feature_slug', 'smart_reorder');
        $this->assertEquals(2, $smartReorder['total_requests']);
    }

    // ─── aggregateDaily Tests ────────────────────────────────

    public function test_aggregate_daily_creates_summary(): void
    {
        $yesterday = now()->subDay()->toDateString();
        $this->createUsageLog([
            'created_at' => now()->subDay(),
            'total_tokens' => 500,
            'input_tokens' => 200,
            'output_tokens' => 300,
            'estimated_cost_usd' => 0.001,
        ]);

        $this->service->aggregateDaily($yesterday);

        $this->assertDatabaseHas('ai_daily_usage_summaries', [
            'store_id' => $this->store->id,
            'date' => $yesterday,
            'total_requests' => 1,
        ]);
    }

    // ─── cleanupExpiredCache Tests ───────────────────────────

    public function test_cleanup_expired_cache_removes_old_entries(): void
    {
        \App\Domain\WameedAI\Models\AICache::create([
            'cache_key' => 'test_key_expired',
            'feature_slug' => 'smart_reorder',
            'store_id' => $this->store->id,
            'response_text' => '{"test": true}',
            'tokens_used' => 100,
            'expires_at' => now()->subHour(),
        ]);

        \App\Domain\WameedAI\Models\AICache::create([
            'cache_key' => 'test_key_valid',
            'feature_slug' => 'smart_reorder',
            'store_id' => $this->store->id,
            'response_text' => '{"test": true}',
            'tokens_used' => 100,
            'expires_at' => now()->addHour(),
        ]);

        $deleted = $this->service->cleanupExpiredCache();

        $this->assertEquals(1, $deleted);
        $this->assertDatabaseMissing('ai_cache', ['cache_key' => 'test_key_expired']);
        $this->assertDatabaseHas('ai_cache', ['cache_key' => 'test_key_valid']);
    }

    // ─── Model Tests ─────────────────────────────────────────

    public function test_feature_definition_has_correct_casts(): void
    {
        $feature = AIFeatureDefinition::create([
            'slug' => 'test_cast',
            'name' => 'Test',
            'name_ar' => 'اختبار',
            'category' => 'inventory',
            'is_enabled' => true,
            'is_premium' => false,
            'daily_limit' => 50,
            'monthly_limit' => 500,
        ]);

        $loaded = AIFeatureDefinition::find($feature->id);
        $this->assertIsBool($loaded->is_enabled);
        $this->assertIsBool($loaded->is_premium);
        $this->assertIsInt($loaded->daily_limit);
        $this->assertIsInt($loaded->monthly_limit);
    }

    public function test_store_feature_config_unique_constraint(): void
    {
        AIStoreFeatureConfig::create([
            'store_id' => $this->store->id,
            'ai_feature_definition_id' => $this->feature->id,
            'is_enabled' => true,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        AIStoreFeatureConfig::create([
            'store_id' => $this->store->id,
            'ai_feature_definition_id' => $this->feature->id,
            'is_enabled' => false,
        ]);
    }

    public function test_usage_log_tracks_all_statuses(): void
    {
        foreach (['success', 'cached', 'error', 'rate_limited'] as $status) {
            $this->createUsageLog(['status' => $status]);
        }

        $count = AIUsageLog::where('store_id', $this->store->id)->count();
        $this->assertEquals(4, $count);
    }
}
