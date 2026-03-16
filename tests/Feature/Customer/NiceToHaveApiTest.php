<?php

namespace Tests\Feature\Customer;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NiceToHaveApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private string $storeId;
    private string $customerId;
    private string $productId;

    protected function setUp(): void
    {
        parent::setUp();

        if (! \Schema::hasTable('organizations')) {
            \Schema::create('organizations', function ($t) {
                $t->uuid('id')->primary();
                $t->string('name');
                $t->string('slug')->unique();
                $t->timestamps();
            });
        }
        if (! \Schema::hasTable('stores')) {
            \Schema::create('stores', function ($t) {
                $t->uuid('id')->primary();
                $t->foreignUuid('organization_id')->constrained('organizations');
                $t->string('name');
                $t->string('name_ar')->nullable();
                $t->string('slug')->unique();
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }
        if (! \Schema::hasTable('users')) {
            \Schema::create('users', function ($t) {
                $t->uuid('id')->primary();
                $t->foreignUuid('store_id')->constrained('stores');
                $t->string('name');
                $t->string('email')->unique();
                $t->string('password_hash');
                $t->timestamps();
            });
        }
        if (! \Schema::hasTable('personal_access_tokens')) {
            \Schema::create('personal_access_tokens', function ($t) {
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

        // Nice-to-have tables
        \Schema::dropIfExists('customer_badges');
        \Schema::dropIfExists('customer_challenge_progress');
        \Schema::dropIfExists('loyalty_tiers');
        \Schema::dropIfExists('loyalty_badges');
        \Schema::dropIfExists('loyalty_challenges');
        \Schema::dropIfExists('wishlists');
        \Schema::dropIfExists('gift_registry_items');
        \Schema::dropIfExists('gift_registries');
        \Schema::dropIfExists('appointments');
        \Schema::dropIfExists('signage_playlists');
        \Schema::dropIfExists('cfd_configurations');

        \Schema::create('cfd_configurations', function ($t) {
            $t->uuid('id')->primary();
            $t->uuid('store_id');
            $t->boolean('is_enabled')->default(false);
            $t->string('target_monitor')->default('secondary');
            $t->json('theme_config')->nullable();
            $t->json('idle_content')->nullable();
            $t->integer('idle_rotation_seconds')->default(10);
        });
        \Schema::create('signage_playlists', function ($t) {
            $t->uuid('id')->primary();
            $t->uuid('store_id');
            $t->string('name');
            $t->json('slides')->nullable();
            $t->json('schedule')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        \Schema::create('appointments', function ($t) {
            $t->uuid('id')->primary();
            $t->uuid('store_id');
            $t->uuid('customer_id');
            $t->uuid('staff_id')->nullable();
            $t->uuid('service_product_id')->nullable();
            $t->date('appointment_date');
            $t->string('start_time');
            $t->string('end_time');
            $t->string('status')->default('scheduled');
            $t->text('notes')->nullable();
            $t->boolean('reminder_sent')->default(false);
            $t->timestamps();
        });
        \Schema::create('gift_registries', function ($t) {
            $t->uuid('id')->primary();
            $t->uuid('store_id');
            $t->uuid('customer_id');
            $t->string('name');
            $t->string('event_type');
            $t->date('event_date');
            $t->string('share_code')->unique();
            $t->boolean('is_active')->default(true);
        });
        \Schema::create('gift_registry_items', function ($t) {
            $t->uuid('id')->primary();
            $t->uuid('registry_id');
            $t->uuid('product_id');
            $t->integer('quantity_desired')->default(1);
            $t->integer('quantity_purchased')->default(0);
            $t->string('purchased_by_name')->nullable();
        });
        \Schema::create('wishlists', function ($t) {
            $t->uuid('id')->primary();
            $t->uuid('store_id');
            $t->uuid('customer_id');
            $t->uuid('product_id');
            $t->timestamp('added_at')->nullable();
        });
        \Schema::create('loyalty_challenges', function ($t) {
            $t->uuid('id')->primary();
            $t->uuid('store_id');
            $t->string('name_ar')->nullable();
            $t->string('name_en');
            $t->text('description_ar')->nullable();
            $t->text('description_en')->nullable();
            $t->string('challenge_type');
            $t->decimal('target_value')->default(0);
            $t->string('reward_type')->nullable();
            $t->decimal('reward_value')->default(0);
            $t->uuid('reward_badge_id')->nullable();
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->boolean('is_active')->default(true);
        });
        \Schema::create('loyalty_badges', function ($t) {
            $t->uuid('id')->primary();
            $t->uuid('store_id');
            $t->string('name_ar')->nullable();
            $t->string('name_en');
            $t->string('icon_url')->nullable();
            $t->text('description_ar')->nullable();
            $t->text('description_en')->nullable();
        });
        \Schema::create('loyalty_tiers', function ($t) {
            $t->uuid('id')->primary();
            $t->uuid('store_id');
            $t->string('tier_name_ar')->nullable();
            $t->string('tier_name_en');
            $t->integer('tier_order')->default(0);
            $t->integer('min_points')->default(0);
            $t->json('benefits')->nullable();
            $t->string('icon_url')->nullable();
        });
        \Schema::create('customer_challenge_progress', function ($t) {
            $t->uuid('id')->primary();
            $t->uuid('customer_id');
            $t->uuid('challenge_id');
            $t->decimal('current_value')->default(0);
            $t->boolean('is_completed')->default(false);
            $t->timestamp('completed_at')->nullable();
            $t->boolean('reward_claimed')->default(false);
            $t->timestamps();
        });
        \Schema::create('customer_badges', function ($t) {
            $t->uuid('id')->primary();
            $t->uuid('customer_id');
            $t->uuid('badge_id');
            $t->timestamp('earned_at')->nullable();
        });

        $org = Organization::create(['name' => 'Test Org', 'slug' => 'test-org']);
        $store = Store::create([
            'organization_id' => $org->id,
            'name' => 'Test Store',
            'slug' => 'test-store',
        ]);
        $this->storeId = $store->id;
        $this->customerId = (string) Str::uuid();
        $this->productId = (string) Str::uuid();

        $user = User::create([
            'name' => 'Test User',
            'email' => 'nicetohave@test.com',
            'store_id' => $store->id,
            'password_hash' => bcrypt('password'),
        ]);
        $this->token = $user->createToken('test', ['*'])->plainTextToken;
    }

    private function authGet(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->getJson("/api/v2/{$uri}", ['Authorization' => "Bearer {$this->token}"]);
    }

    private function authPost(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/v2/{$uri}", $data, ['Authorization' => "Bearer {$this->token}"]);
    }

    private function authPut(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->putJson("/api/v2/{$uri}", $data, ['Authorization' => "Bearer {$this->token}"]);
    }

    private function authDelete(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->deleteJson("/api/v2/{$uri}", $data, ['Authorization' => "Bearer {$this->token}"]);
    }

    // ═══════════════ Wishlist ═══════════════

    public function test_wishlist_empty(): void
    {
        $res = $this->authGet("wishlist?customer_id={$this->customerId}");
        $res->assertOk();
        $this->assertEmpty(json_decode($res->getContent(), true)['data']);
    }

    public function test_add_to_wishlist(): void
    {
        $res = $this->authPost('wishlist', [
            'customer_id' => $this->customerId,
            'product_id' => $this->productId,
        ]);
        $res->assertCreated();
    }

    public function test_remove_from_wishlist(): void
    {
        $this->authPost('wishlist', [
            'customer_id' => $this->customerId,
            'product_id' => $this->productId,
        ]);
        $res = $this->authDelete('wishlist', [
            'customer_id' => $this->customerId,
            'product_id' => $this->productId,
        ]);
        $res->assertOk();
    }

    // ═══════════════ Appointments ═══════════════

    public function test_appointments_empty(): void
    {
        $res = $this->authGet('appointments');
        $res->assertOk();
        $this->assertEmpty(json_decode($res->getContent(), true)['data']);
    }

    public function test_create_appointment(): void
    {
        $res = $this->authPost('appointments', [
            'customer_id' => $this->customerId,
            'appointment_date' => '2026-06-15',
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);
        $res->assertCreated();
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertEquals('scheduled', $data['status']);
    }

    public function test_update_appointment(): void
    {
        $res = $this->authPost('appointments', [
            'customer_id' => $this->customerId,
            'appointment_date' => '2026-06-15',
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);
        $id = json_decode($res->getContent(), true)['data']['id'];

        $res = $this->authPut("appointments/{$id}", ['status' => 'confirmed']);
        $res->assertOk();
        $this->assertEquals('confirmed', json_decode($res->getContent(), true)['data']['status']);
    }

    public function test_cancel_appointment(): void
    {
        $res = $this->authPost('appointments', [
            'customer_id' => $this->customerId,
            'appointment_date' => '2026-06-15',
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);
        $id = json_decode($res->getContent(), true)['data']['id'];

        $res = $this->authPost("appointments/{$id}/cancel");
        $res->assertOk();
        $this->assertEquals('cancelled', json_decode($res->getContent(), true)['data']['status']);
    }

    // ═══════════════ CFD ═══════════════

    public function test_cfd_defaults(): void
    {
        $res = $this->authGet('cfd/config');
        $res->assertOk();
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertFalse($data['is_enabled']);
    }

    public function test_update_cfd(): void
    {
        $res = $this->authPut('cfd/config', [
            'is_enabled' => true,
            'idle_rotation_seconds' => 15,
        ]);
        $res->assertOk();
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertTrue((bool) $data['is_enabled']);
        $this->assertEquals(15, $data['idle_rotation_seconds']);
    }

    // ═══════════════ Gift Registry ═══════════════

    public function test_registries_empty(): void
    {
        $res = $this->authGet('gift-registry');
        $res->assertOk();
        $this->assertEmpty(json_decode($res->getContent(), true)['data']);
    }

    public function test_create_registry(): void
    {
        $res = $this->authPost('gift-registry', [
            'customer_id' => $this->customerId,
            'name' => 'Wedding Registry',
            'event_type' => 'wedding',
            'event_date' => '2026-12-25',
        ]);
        $res->assertCreated();
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertNotEmpty($data['share_code']);
    }

    public function test_registry_share_code(): void
    {
        $res = $this->authPost('gift-registry', [
            'customer_id' => $this->customerId,
            'name' => 'Baby Shower',
            'event_type' => 'baby_shower',
            'event_date' => '2026-09-01',
        ]);
        $code = json_decode($res->getContent(), true)['data']['share_code'];

        $res = $this->authGet("gift-registry/share/{$code}");
        $res->assertOk();
        $this->assertEquals('Baby Shower', json_decode($res->getContent(), true)['data']['name']);
    }

    public function test_registry_items(): void
    {
        $res = $this->authPost('gift-registry', [
            'customer_id' => $this->customerId,
            'name' => 'Test Registry',
            'event_type' => 'birthday',
            'event_date' => '2026-08-01',
        ]);
        $regId = json_decode($res->getContent(), true)['data']['id'];

        $this->authPost("gift-registry/{$regId}/items", [
            'product_id' => $this->productId,
            'quantity_desired' => 2,
        ])->assertCreated();

        $res = $this->authGet("gift-registry/{$regId}/items");
        $items = json_decode($res->getContent(), true)['data'];
        $this->assertCount(1, $items);
    }

    // ═══════════════ Digital Signage ═══════════════

    public function test_playlists_empty(): void
    {
        $res = $this->authGet('signage/playlists');
        $res->assertOk();
        $this->assertEmpty(json_decode($res->getContent(), true)['data']);
    }

    public function test_create_playlist(): void
    {
        $res = $this->authPost('signage/playlists', [
            'name' => 'Promo Slides',
            'slides' => [['type' => 'image', 'url' => 'https://example.com/promo.jpg']],
        ]);
        $res->assertCreated();
    }

    public function test_update_playlist(): void
    {
        $res = $this->authPost('signage/playlists', [
            'name' => 'Original',
            'slides' => [['type' => 'text', 'content' => 'Hello']],
        ]);
        $id = json_decode($res->getContent(), true)['data']['id'];

        $res = $this->authPut("signage/playlists/{$id}", ['name' => 'Updated']);
        $res->assertOk();
        $this->assertEquals('Updated', json_decode($res->getContent(), true)['data']['name']);
    }

    public function test_delete_playlist(): void
    {
        $res = $this->authPost('signage/playlists', [
            'name' => 'To Delete',
            'slides' => [['type' => 'text', 'content' => 'Bye']],
        ]);
        $id = json_decode($res->getContent(), true)['data']['id'];

        $this->deleteJson("/api/v2/signage/playlists/{$id}", [], ['Authorization' => "Bearer {$this->token}"])
            ->assertOk();
    }

    // ═══════════════ Gamification ═══════════════

    public function test_challenges(): void
    {
        \App\Domain\Customer\Models\LoyaltyChallenge::create([
            'store_id' => $this->storeId,
            'name_en' => 'Spend 100',
            'challenge_type' => 'spend_amount',
            'target_value' => 100,
            'is_active' => true,
        ]);
        $res = $this->authGet('gamification/challenges');
        $res->assertOk();
        $this->assertCount(1, json_decode($res->getContent(), true)['data']);
    }

    public function test_badges(): void
    {
        \App\Domain\Customer\Models\LoyaltyBadge::create([
            'store_id' => $this->storeId,
            'name_en' => 'First Purchase',
        ]);
        $res = $this->authGet('gamification/badges');
        $res->assertOk();
        $this->assertCount(1, json_decode($res->getContent(), true)['data']);
    }

    public function test_tiers(): void
    {
        \App\Domain\Customer\Models\LoyaltyTier::create([
            'store_id' => $this->storeId,
            'tier_name_en' => 'Gold',
            'min_points' => 1000,
        ]);
        $res = $this->authGet('gamification/tiers');
        $res->assertOk();
        $this->assertCount(1, json_decode($res->getContent(), true)['data']);
    }

    public function test_customer_progress(): void
    {
        $res = $this->authGet("gamification/customer/{$this->customerId}/progress");
        $res->assertOk();
        $this->assertEmpty(json_decode($res->getContent(), true)['data']);
    }

    public function test_customer_badges(): void
    {
        $res = $this->authGet("gamification/customer/{$this->customerId}/badges");
        $res->assertOk();
        $this->assertEmpty(json_decode($res->getContent(), true)['data']);
    }

    // ═══════════════ Auth ═══════════════

    public function test_unauthenticated(): void
    {
        $this->getJson('/api/v2/wishlist?customer_id=x')->assertUnauthorized();
    }
}
