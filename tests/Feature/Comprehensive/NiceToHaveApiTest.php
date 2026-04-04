<?php

namespace Tests\Feature\Comprehensive;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Customer\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NiceToHaveApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private Customer $customer;
    private Product $product;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        auth()->forgetGuards();

        $this->org = Organization::create([
            'name' => 'N2H Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'N2H Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Owner',
            'email' => 'n2h-owner@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Customer',
            'phone' => '+966500000001',
        ]);

        $this->product = Product::forceCreate([
            'organization_id' => $this->org->id,
            'name' => 'Test Product',
            'sell_price' => 25.00,
            'cost_price' => 15.00,
            'sku' => 'TST-001',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;
    }

    // ─── Wishlist ────────────────────────────────────────────

    public function test_can_get_wishlist(): void
    {
        $response = $this->withToken($this->token)
            ->getJson("/api/v2/wishlist?customer_id={$this->customer->id}");

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_can_add_to_wishlist(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/wishlist', [
                'customer_id' => $this->customer->id,
                'product_id' => $this->product->id,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('wishlists', [
            'customer_id' => $this->customer->id,
            'product_id' => $this->product->id,
        ]);
    }

    public function test_can_remove_from_wishlist(): void
    {
        // Add first
        DB::table('wishlists')->insert([
            'id' => Str::uuid()->toString(),
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'product_id' => $this->product->id,
            'added_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v2/wishlist', [
                'customer_id' => $this->customer->id,
                'product_id' => $this->product->id,
            ]);

        $response->assertOk();
    }

    public function test_add_to_wishlist_requires_customer_and_product(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/wishlist', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['customer_id', 'product_id']);
    }

    // ─── Appointments ────────────────────────────────────────

    public function test_can_list_appointments(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/appointments');

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_can_create_appointment(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/appointments', [
                'customer_id' => $this->customer->id,
                'appointment_date' => now()->addDays(3)->toDateString(),
                'start_time' => '10:00',
                'end_time' => '11:00',
                'notes' => 'Consultation visit',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
    }

    public function test_create_appointment_requires_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/appointments', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['customer_id', 'appointment_date', 'start_time', 'end_time']);
    }

    public function test_can_update_appointment(): void
    {
        $apptId = Str::uuid()->toString();
        DB::table('appointments')->insert([
            'id' => $apptId,
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'appointment_date' => now()->addDays(3)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/appointments/{$apptId}", [
                'status' => 'confirmed',
                'notes' => 'Confirmed by phone',
            ]);

        $response->assertOk();
    }

    public function test_can_cancel_appointment(): void
    {
        $apptId = Str::uuid()->toString();
        DB::table('appointments')->insert([
            'id' => $apptId,
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'appointment_date' => now()->addDays(1)->toDateString(),
            'start_time' => '14:00',
            'end_time' => '15:00',
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/appointments/{$apptId}/cancel");

        $response->assertOk();
        $this->assertDatabaseHas('appointments', [
            'id' => $apptId,
            'status' => 'cancelled',
        ]);
    }

    public function test_can_filter_appointments_by_date(): void
    {
        $date = now()->addDays(5)->toDateString();

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/appointments?date={$date}");

        $response->assertOk();
    }

    // ─── CFD Config ──────────────────────────────────────────

    public function test_can_get_cfd_config(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/cfd/config');

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_can_update_cfd_config(): void
    {
        $response = $this->withToken($this->token)
            ->putJson('/api/v2/cfd/config', [
                'is_enabled' => true,
                'target_monitor' => 'secondary',
                'idle_rotation_seconds' => 15,
            ]);

        $response->assertOk();
    }

    public function test_cfd_idle_rotation_validates_range(): void
    {
        $response = $this->withToken($this->token)
            ->putJson('/api/v2/cfd/config', [
                'idle_rotation_seconds' => 1, // min is 3
            ]);

        $response->assertUnprocessable();
    }

    // ─── Gift Registry ───────────────────────────────────────

    public function test_can_list_registries(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/gift-registry');

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_can_create_registry(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/gift-registry', [
                'customer_id' => $this->customer->id,
                'name' => 'Wedding Registry',
                'event_type' => 'wedding',
                'event_date' => now()->addMonths(3)->toDateString(),
            ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
    }

    public function test_create_registry_requires_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/gift-registry', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['customer_id', 'name', 'event_type', 'event_date']);
    }

    public function test_can_get_registry_by_share_code(): void
    {
        $regId = Str::uuid()->toString();
        $shareCode = 'ABCD1234';
        DB::table('gift_registries')->insert([
            'id' => $regId,
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'share_code' => $shareCode,
            'name' => 'Baby Shower',
            'event_type' => 'baby_shower',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/gift-registry/share/{$shareCode}");

        $response->assertOk();
    }

    public function test_share_code_returns_404_for_unknown(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/gift-registry/share/ZZZZZZZZ');

        $response->assertNotFound();
    }

    public function test_can_add_item_to_registry(): void
    {
        $regId = Str::uuid()->toString();
        DB::table('gift_registries')->insert([
            'id' => $regId,
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'share_code' => 'REG-' . Str::random(4),
            'name' => 'Birthday',
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/gift-registry/{$regId}/items", [
                'product_id' => $this->product->id,
                'quantity_desired' => 2,
            ]);

        $response->assertCreated();
    }

    public function test_can_list_registry_items(): void
    {
        $regId = Str::uuid()->toString();
        DB::table('gift_registries')->insert([
            'id' => $regId,
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'share_code' => 'REG-' . Str::random(4),
            'name' => 'Housewarming',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/gift-registry/{$regId}/items");

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    // ─── Digital Signage ─────────────────────────────────────

    public function test_can_list_playlists(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/signage/playlists');

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_can_create_playlist(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/signage/playlists', [
                'name' => 'Morning Promotions',
                'slides' => [
                    ['type' => 'image', 'url' => 'https://example.com/slide1.jpg', 'duration' => 10],
                    ['type' => 'image', 'url' => 'https://example.com/slide2.jpg', 'duration' => 10],
                ],
            ]);

        $response->assertCreated();
    }

    public function test_create_playlist_requires_name_and_slides(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/signage/playlists', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name', 'slides']);
    }

    public function test_can_update_playlist(): void
    {
        $plId = Str::uuid()->toString();
        DB::table('signage_playlists')->insert([
            'id' => $plId,
            'store_id' => $this->store->id,
            'name' => 'Old Name',
            'slides' => json_encode([]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/signage/playlists/{$plId}", [
                'name' => 'Updated Promotions',
                'is_active' => false,
            ]);

        $response->assertOk();
    }

    public function test_can_delete_playlist(): void
    {
        $plId = Str::uuid()->toString();
        DB::table('signage_playlists')->insert([
            'id' => $plId,
            'store_id' => $this->store->id,
            'name' => 'To Delete',
            'slides' => json_encode([]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/signage/playlists/{$plId}");

        $response->assertOk();
    }

    // ─── Gamification ────────────────────────────────────────

    public function test_can_get_challenges(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/gamification/challenges');

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_can_get_badges(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/gamification/badges');

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_can_get_tiers(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/gamification/tiers');

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_can_get_customer_progress(): void
    {
        $response = $this->withToken($this->token)
            ->getJson("/api/v2/gamification/customer/{$this->customer->id}/progress");

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_can_get_customer_badges(): void
    {
        $response = $this->withToken($this->token)
            ->getJson("/api/v2/gamification/customer/{$this->customer->id}/badges");

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    // ─── Auth Required ───────────────────────────────────────

    public function test_nice_to_have_endpoints_require_auth(): void
    {
        $response = $this->getJson('/api/v2/wishlist');
        $response->assertUnauthorized();

        $response = $this->getJson('/api/v2/appointments');
        $response->assertUnauthorized();

        $response = $this->getJson('/api/v2/cfd/config');
        $response->assertUnauthorized();

        $response = $this->getJson('/api/v2/gamification/challenges');
        $response->assertUnauthorized();
    }
}
