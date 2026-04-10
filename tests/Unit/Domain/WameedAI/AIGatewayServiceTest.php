<?php

namespace Tests\Unit\Domain\WameedAI;

use App\Domain\WameedAI\Models\AICache;
use App\Domain\WameedAI\Models\AIFeatureDefinition;
use App\Domain\WameedAI\Models\AIPrompt;
use App\Domain\WameedAI\Models\AIStoreFeatureConfig;
use App\Domain\WameedAI\Models\AIUsageLog;
use App\Domain\WameedAI\Services\AIGatewayService;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

class AIGatewayServiceTest extends TestCase
{
    use RefreshDatabase;

    private AIGatewayService $gateway;
    private Organization $org;
    private Store $store;
    private AIFeatureDefinition $feature;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAllAITables();

        $this->gateway = new AIGatewayService();

        $this->org = Organization::create([
            'name' => 'Test Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Store',
            'business_type' => 'grocery',
            'currency' => 'OMR',
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
            'daily_limit' => 50,
            'monthly_limit' => 500,
        ]);
    }

    private function createAllAITables(): void
    {
        if (Schema::hasTable('ai_feature_definitions')) {
            return;
        }

        Schema::create('ai_feature_definitions', function ($t) {
            $t->uuid('id')->primary();
            $t->string('slug', 100)->unique();
            $t->string('name', 255);
            $t->string('name_ar', 255);
            $t->text('description')->nullable();
            $t->text('description_ar')->nullable();
            $t->string('category', 50);
            $t->string('icon', 100)->nullable();
            $t->boolean('is_enabled')->default(true);
            $t->boolean('is_premium')->default(false);
            $t->string('default_model', 100)->default('gpt-4o-mini');
            $t->integer('default_max_tokens')->default(2048);
            $t->decimal('cost_per_request_estimate', 10, 6)->default(0.001);
            $t->integer('daily_limit')->default(50);
            $t->integer('monthly_limit')->default(500);
            $t->json('requires_subscription_plan')->nullable();
            $t->integer('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('ai_store_feature_configs', function ($t) {
            $t->uuid('id')->primary();
            $t->uuid('store_id');
            $t->uuid('ai_feature_definition_id');
            $t->boolean('is_enabled')->default(true);
            $t->integer('daily_limit')->default(100);
            $t->integer('monthly_limit')->default(3000);
            $t->text('custom_prompt_override')->nullable();
            $t->json('settings_json')->nullable();
            $t->timestamps();
            $t->unique(['store_id', 'ai_feature_definition_id']);
        });

        Schema::create('ai_usage_logs', function ($t) {
            $t->uuid('id')->primary();
            $t->uuid('organization_id');
            $t->uuid('store_id');
            $t->uuid('user_id')->nullable();
            $t->uuid('ai_feature_definition_id');
            $t->string('feature_slug', 100);
            $t->string('model_used', 100);
            $t->integer('input_tokens')->default(0);
            $t->integer('output_tokens')->default(0);
            $t->integer('total_tokens')->default(0);
            $t->decimal('estimated_cost_usd', 10, 6)->default(0);
            $t->string('request_payload_hash', 255)->nullable();
            $t->boolean('response_cached')->default(false);
            $t->integer('latency_ms')->default(0);
            $t->string('status', 20)->default('success');
            $t->text('error_message')->nullable();
            $t->json('metadata_json')->nullable();
            $t->timestamp('created_at')->nullable();
        });

        Schema::create('ai_prompts', function ($t) {
            $t->uuid('id')->primary();
            $t->string('feature_slug', 100);
            $t->integer('version')->default(1);
            $t->text('system_prompt');
            $t->text('user_prompt_template');
            $t->string('model', 100)->default('gpt-4o-mini');
            $t->integer('max_tokens')->default(2048);
            $t->decimal('temperature', 3, 2)->default(0.7);
            $t->string('response_format', 20)->default('json_object');
            $t->boolean('is_active')->default(true);
            $t->uuid('created_by')->nullable();
            $t->timestamps();
            $t->unique(['feature_slug', 'version']);
        });

        Schema::create('ai_cache', function ($t) {
            $t->uuid('id')->primary();
            $t->string('cache_key', 255)->unique();
            $t->string('feature_slug', 100);
            $t->uuid('store_id')->nullable();
            $t->text('response_text');
            $t->integer('tokens_used')->default(0);
            $t->timestamp('expires_at');
            $t->timestamp('created_at')->nullable();
        });
    }

    private function createPrompt(string $featureSlug = 'smart_reorder'): AIPrompt
    {
        return AIPrompt::create([
            'feature_slug' => $featureSlug,
            'version' => 1,
            'system_prompt' => 'You are an inventory analyst.',
            'user_prompt_template' => 'Analyze this store data: {data}',
            'model' => 'gpt-4o-mini',
            'max_tokens' => 2048,
            'temperature' => 0.7,
            'response_format' => 'json_object',
            'is_active' => true,
        ]);
    }

    // ─── Feature Gating Tests ────────────────────────────────

    public function test_returns_null_when_feature_disabled(): void
    {
        $this->feature->update(['is_enabled' => false]);

        $result = $this->gateway->call(
            'smart_reorder',
            $this->store->id,
            $this->org->id,
            ['data' => 'test'],
        );

        $this->assertNull($result);
    }

    public function test_returns_null_when_store_feature_disabled(): void
    {
        AIStoreFeatureConfig::create([
            'store_id' => $this->store->id,
            'ai_feature_definition_id' => $this->feature->id,
            'is_enabled' => false,
        ]);

        $result = $this->gateway->call(
            'smart_reorder',
            $this->store->id,
            $this->org->id,
            ['data' => 'test'],
        );

        $this->assertNull($result);
    }

    public function test_returns_null_when_feature_not_found(): void
    {
        $result = $this->gateway->call(
            'nonexistent_feature',
            $this->store->id,
            $this->org->id,
            ['data' => 'test'],
        );

        $this->assertNull($result);
    }

    // ─── Rate Limiting Tests ─────────────────────────────────

    public function test_rate_limits_on_daily_limit(): void
    {
        $config = AIStoreFeatureConfig::create([
            'store_id' => $this->store->id,
            'ai_feature_definition_id' => $this->feature->id,
            'is_enabled' => true,
            'daily_limit' => 2,
            'monthly_limit' => 100,
        ]);

        // Create 2 usage logs for today (at limit)
        for ($i = 0; $i < 2; $i++) {
            AIUsageLog::create([
                'organization_id' => $this->org->id,
                'store_id' => $this->store->id,
                'ai_feature_definition_id' => $this->feature->id,
                'feature_slug' => 'smart_reorder',
                'model_used' => 'gpt-4o-mini',
                'status' => 'success',
                'created_at' => now(),
            ]);
        }

        $this->createPrompt();

        $result = $this->gateway->call(
            'smart_reorder',
            $this->store->id,
            $this->org->id,
            ['data' => 'test'],
        );

        $this->assertNull($result);

        // Verify rate_limited log was created
        $this->assertDatabaseHas('ai_usage_logs', [
            'store_id' => $this->store->id,
            'feature_slug' => 'smart_reorder',
            'status' => 'rate_limited',
        ]);
    }

    // ─── Cache Tests ─────────────────────────────────────────

    public function test_returns_cached_result_when_available(): void
    {
        $this->createPrompt();

        $cacheData = ['suggestions' => [['product' => 'A', 'qty' => 50]]];
        AICache::create([
            'cache_key' => 'wameed_ai:smart_reorder:' . $this->store->id . ':' . md5(json_encode(['data' => 'test'])),
            'feature_slug' => 'smart_reorder',
            'store_id' => $this->store->id,
            'response_text' => json_encode($cacheData),
            'tokens_used' => 300,
            'expires_at' => now()->addHour(),
        ]);

        $result = $this->gateway->call(
            'smart_reorder',
            $this->store->id,
            $this->org->id,
            ['data' => 'test'],
        );

        $this->assertNotNull($result);
        $this->assertArrayHasKey('suggestions', $result);

        // Verify cached log was created
        $this->assertDatabaseHas('ai_usage_logs', [
            'store_id' => $this->store->id,
            'feature_slug' => 'smart_reorder',
            'status' => 'cached',
        ]);
    }

    public function test_does_not_use_expired_cache(): void
    {
        $this->createPrompt();

        AICache::create([
            'cache_key' => 'wameed_ai:smart_reorder:' . $this->store->id . ':' . md5(json_encode(['data' => 'test'])),
            'feature_slug' => 'smart_reorder',
            'store_id' => $this->store->id,
            'response_text' => '{"old": true}',
            'tokens_used' => 100,
            'expires_at' => now()->subHour(),
        ]);

        // Since expired cache won't be found and there's no OpenAI mock,
        // the call will attempt to call OpenAI through loadPrompt → prompt lookup
        // If no prompt, it returns null
        // Here we're just verifying expired cache is not returned
        $result = $this->gateway->call(
            'smart_reorder',
            $this->store->id,
            $this->org->id,
            ['data' => 'test'],
        );

        // Result should not be the cached {'old': true} value
        // Either null (no OpenAI) or a fresh response
        if ($result !== null) {
            $this->assertArrayNotHasKey('old', $result);
        }
    }

    // ─── Prompt Tests ────────────────────────────────────────

    public function test_returns_null_when_no_prompt_available(): void
    {
        // No prompt created, feature enabled, no cache
        $result = $this->gateway->call(
            'smart_reorder',
            $this->store->id,
            $this->org->id,
            ['data' => 'test'],
        );

        $this->assertNull($result);
    }

    // ─── Usage Logging Tests ─────────────────────────────────

    public function test_logs_usage_on_cache_hit(): void
    {
        $this->createPrompt();

        AICache::create([
            'cache_key' => 'wameed_ai:smart_reorder:' . $this->store->id . ':' . md5(json_encode(['x' => 1])),
            'feature_slug' => 'smart_reorder',
            'store_id' => $this->store->id,
            'response_text' => '{"cached": true}',
            'tokens_used' => 200,
            'expires_at' => now()->addHour(),
        ]);

        $this->gateway->call(
            'smart_reorder',
            $this->store->id,
            $this->org->id,
            ['x' => 1],
            'user-123',
        );

        $log = AIUsageLog::where('store_id', $this->store->id)
            ->where('status', 'cached')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('user-123', $log->user_id);
        $this->assertTrue($log->response_cached);
    }

    // ─── Cost Estimation Tests ───────────────────────────────

    public function test_cost_estimation_is_reasonable(): void
    {
        // GPT-4o-mini: input=$0.15/1M, output=$0.60/1M
        // 1000 input + 2000 output = (1000*0.15/1M) + (2000*0.60/1M) = 0.00015 + 0.0012 = 0.00135
        $method = new \ReflectionMethod(AIGatewayService::class, 'estimateCost');
        $method->setAccessible(true);

        $cost = $method->invoke($this->gateway, 'gpt-4o-mini', 1000, 2000);

        $this->assertGreaterThan(0, $cost);
        $this->assertLessThan(0.01, $cost);
        $this->assertEqualsWithDelta(0.00135, $cost, 0.00001);
    }

    public function test_cost_estimation_gpt4o(): void
    {
        $method = new \ReflectionMethod(AIGatewayService::class, 'estimateCost');
        $method->setAccessible(true);

        $costMini = $method->invoke($this->gateway, 'gpt-4o-mini', 1000, 1000);
        $costFull = $method->invoke($this->gateway, 'gpt-4o', 1000, 1000);

        // GPT-4o should be significantly more expensive than mini
        $this->assertGreaterThan($costMini, $costFull);
    }
}
