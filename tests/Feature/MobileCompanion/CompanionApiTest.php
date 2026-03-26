<?php

namespace Tests\Feature\MobileCompanion;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CompanionApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private string $storeId;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('organizations')) {
            Schema::create('organizations', function ($t) {
                $t->uuid('id')->primary();
                $t->string('name');
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('stores')) {
            Schema::create('stores', function ($t) {
                $t->uuid('id')->primary();
                $t->foreignUuid('organization_id')->nullable();
                $t->string('name');
                $t->string('name_ar')->nullable();
                $t->string('slug')->nullable();
                $t->string('branch_code')->nullable();
                $t->text('address')->nullable();
                $t->string('city')->nullable();
                $t->decimal('latitude', 10, 7)->nullable();
                $t->decimal('longitude', 10, 7)->nullable();
                $t->string('phone')->nullable();
                $t->string('email')->nullable();
                $t->string('timezone')->default('UTC');
                $t->string('currency')->default('SAR');
                $t->string('locale')->default('en');
                $t->string('business_type')->nullable();
                $t->boolean('is_active')->default(true);
                $t->boolean('is_main_branch')->default(false);
                $t->decimal('storage_used_mb', 10, 2)->default(0);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('users')) {
            Schema::create('users', function ($t) {
                $t->uuid('id')->primary();
                $t->string('name');
                $t->string('email')->unique();
                $t->foreignUuid('store_id')->nullable();
                $t->string('password_hash')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function ($t) {
                $t->id();
                $t->uuidMorphs('tokenable');
                $t->string('name');
                $t->string('token', 64)->unique();
                $t->text('abilities')->nullable();
                $t->timestamp('last_used_at')->nullable();
                $t->timestamp('expires_at')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('sync_logs')) {
            Schema::create('sync_logs', function ($t) {
                $t->uuid('id')->primary();
                $t->foreignUuid('store_id');
                $t->string('terminal_id');
                $t->string('direction');
                $t->integer('records_count')->default(0);
                $t->integer('duration_ms')->default(0);
                $t->string('status');
                $t->text('error_message')->nullable();
                $t->timestamp('started_at')->nullable();
                $t->timestamp('completed_at')->nullable();
            });
        }

        $org = Organization::create(['name' => 'Companion Org']);
        $store = Store::create([
            'organization_id' => $org->id,
            'name' => 'Companion Store',
            'currency' => 'SAR',
        ]);
        $this->storeId = $store->id;

        $user = User::create([
            'name' => 'Companion User',
            'email' => 'companion@test.com',
            'store_id' => $store->id,
            'password_hash' => bcrypt('password'),
        ]);
        $this->token = $user->createToken('test', ['*'])->plainTextToken;
    }

    private function auth(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    // ── Quick Stats ──────────────────────────────────────────

    public function test_quick_stats(): void
    {
        $res = $this->getJson('/api/v2/companion/quick-stats', $this->auth());

        $res->assertOk();
        $body = json_decode($res->getContent(), true);
        $this->assertArrayHasKey('today_revenue', $body['data']);
        $this->assertArrayHasKey('today_transactions', $body['data']);
        $this->assertArrayHasKey('pending_orders', $body['data']);
        $this->assertEquals('SAR', $body['data']['currency']);
    }

    public function test_quick_stats_unauthenticated(): void
    {
        $res = $this->getJson('/api/v2/companion/quick-stats');
        $res->assertStatus(401);
    }

    // ── Sessions ─────────────────────────────────────────────

    public function test_register_session(): void
    {
        $res = $this->postJson('/api/v2/companion/sessions', [
            'device_name' => 'iPhone 15 Pro',
            'device_os' => 'iOS 17.5',
            'app_version' => '2.1.0',
        ], $this->auth());

        $res->assertStatus(201);
        $body = json_decode($res->getContent(), true);
        $this->assertNotEmpty($body['data']['session_id']);
        $this->assertEquals('iPhone 15 Pro', $body['data']['device_name']);
    }

    public function test_register_session_validation(): void
    {
        $res = $this->postJson('/api/v2/companion/sessions', [], $this->auth());
        $res->assertStatus(422);
    }

    public function test_end_session(): void
    {
        $res = $this->postJson('/api/v2/companion/sessions/' . fake()->uuid() . '/end', [], $this->auth());

        $res->assertOk();
        $body = json_decode($res->getContent(), true);
        $this->assertNotEmpty($body['data']['ended_at']);
    }

    public function test_list_sessions(): void
    {
        $res = $this->getJson('/api/v2/companion/sessions', $this->auth());

        $res->assertOk();
        $body = json_decode($res->getContent(), true);
        $this->assertArrayHasKey('sessions', $body['data']);
        $this->assertEquals(0, $body['data']['total']);
    }

    // ── Preferences ──────────────────────────────────────────

    public function test_get_preferences(): void
    {
        $res = $this->getJson('/api/v2/companion/preferences', $this->auth());

        $res->assertOk();
        $body = json_decode($res->getContent(), true);
        $this->assertArrayHasKey('theme', $body['data']);
        $this->assertArrayHasKey('language', $body['data']);
        $this->assertEquals('system', $body['data']['theme']);
    }

    public function test_update_preferences(): void
    {
        $res = $this->putJson('/api/v2/companion/preferences', [
            'theme' => 'dark',
            'language' => 'ar',
            'compact_mode' => true,
        ], $this->auth());

        $res->assertOk();
        $body = json_decode($res->getContent(), true);
        $this->assertEquals('dark', $body['data']['theme']);
        $this->assertEquals('ar', $body['data']['language']);
        $this->assertTrue($body['data']['compact_mode']);
    }

    public function test_update_preferences_invalid_theme(): void
    {
        $res = $this->putJson('/api/v2/companion/preferences', [
            'theme' => 'neon',
        ], $this->auth());

        $res->assertStatus(422);
    }

    // ── Quick Actions ────────────────────────────────────────

    public function test_get_quick_actions(): void
    {
        $res = $this->getJson('/api/v2/companion/quick-actions', $this->auth());

        $res->assertOk();
        $body = json_decode($res->getContent(), true);
        $this->assertArrayHasKey('actions', $body['data']);
        $this->assertGreaterThan(0, count($body['data']['actions']));
    }

    public function test_update_quick_actions(): void
    {
        $res = $this->putJson('/api/v2/companion/quick-actions', [
            'actions' => [
                ['id' => 'new_sale', 'label' => 'New Sale', 'icon' => 'cart', 'enabled' => true, 'order' => 1],
                ['id' => 'reports', 'label' => 'Reports', 'icon' => 'chart', 'enabled' => true, 'order' => 2],
            ],
        ], $this->auth());

        $res->assertOk();
        $body = json_decode($res->getContent(), true);
        $this->assertCount(2, $body['data']['actions']);
    }

    public function test_update_quick_actions_validation(): void
    {
        $res = $this->putJson('/api/v2/companion/quick-actions', [
            'actions' => [],
        ], $this->auth());

        $res->assertStatus(422);
    }

    // ── Summary ──────────────────────────────────────────────

    public function test_mobile_summary(): void
    {
        $res = $this->getJson('/api/v2/companion/summary', $this->auth());

        $res->assertOk();
        $body = json_decode($res->getContent(), true);
        $this->assertArrayHasKey('quick_stats', $body['data']);
        $this->assertArrayHasKey('recent_syncs', $body['data']);
        $this->assertArrayHasKey('tips', $body['data']);
    }

    // ── Events ───────────────────────────────────────────────

    public function test_log_event(): void
    {
        $res = $this->postJson('/api/v2/companion/events', [
            'event_type' => 'page_view',
            'event_data' => ['page' => 'dashboard'],
        ], $this->auth());

        $res->assertStatus(201);
        $body = json_decode($res->getContent(), true);
        $this->assertEquals('page_view', $body['data']['event_type']);
    }

    public function test_log_event_validation(): void
    {
        $res = $this->postJson('/api/v2/companion/events', [], $this->auth());
        $res->assertStatus(422);
    }

    public function test_log_event_minimal(): void
    {
        $res = $this->postJson('/api/v2/companion/events', [
            'event_type' => 'app_open',
        ], $this->auth());

        $res->assertStatus(201);
    }
}
