<?php

namespace Tests\Feature\Industry;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FloristApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private string $storeId;
    private string $otherToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();

        $org = Organization::create(['name' => 'Florist Org', 'slug' => 'florist-org-' . uniqid(), 'is_active' => true]);
        $store = Store::create(['organization_id' => $org->id, 'name' => 'Bloom Store', 'slug' => 'bloom-store-' . uniqid(), 'is_active' => true]);
        $this->storeId = $store->id;
        $user = User::create(['name' => 'Florist', 'email' => 'florist-' . uniqid() . '@test.com', 'password_hash' => bcrypt('password'), 'store_id' => $store->id]);
        $this->token = $user->createToken('test', ['*'])->plainTextToken;

        $otherStore = Store::create(['organization_id' => $org->id, 'name' => 'Other Bloom', 'slug' => 'other-bloom-' . uniqid(), 'is_active' => true]);
        $otherUser = User::create(['name' => 'Other Florist', 'email' => 'other-florist-' . uniqid() . '@test.com', 'password_hash' => bcrypt('password'), 'store_id' => $otherStore->id]);
        $this->otherToken = $otherUser->createToken('test', ['*'])->plainTextToken;
    }

    private function createTables(): void
    {
        DB::statement('DROP TABLE IF EXISTS flower_arrangements CASCADE');
        DB::statement('CREATE TABLE flower_arrangements (id VARCHAR(36) PRIMARY KEY, store_id VARCHAR(36) NOT NULL, name VARCHAR(255) NOT NULL, occasion VARCHAR(100), items_json TEXT, total_price DECIMAL(10,2) NOT NULL, is_template BOOLEAN DEFAULT FALSE, created_at TIMESTAMP, updated_at TIMESTAMP)');

        DB::statement('DROP TABLE IF EXISTS flower_freshness_log CASCADE');
        DB::statement("CREATE TABLE flower_freshness_log (id VARCHAR(36) PRIMARY KEY, product_id VARCHAR(36) NOT NULL, store_id VARCHAR(36) NOT NULL, received_date DATE NOT NULL, expected_vase_life_days INTEGER NOT NULL, markdown_date DATE, dispose_date DATE, quantity INTEGER NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'fresh', created_at TIMESTAMP, updated_at TIMESTAMP)");

        DB::statement('DROP TABLE IF EXISTS flower_subscriptions CASCADE');
        DB::statement('CREATE TABLE flower_subscriptions (id VARCHAR(36) PRIMARY KEY, store_id VARCHAR(36) NOT NULL, customer_id VARCHAR(36) NOT NULL, arrangement_template_id VARCHAR(36), frequency VARCHAR(20) NOT NULL, delivery_day VARCHAR(20) NOT NULL, delivery_address VARCHAR(500) NOT NULL, price_per_delivery DECIMAL(10,2) NOT NULL, is_active BOOLEAN DEFAULT TRUE, next_delivery_date DATE NOT NULL, created_at TIMESTAMP, updated_at TIMESTAMP)');
    }

    private function h(?string $token = null): array
    {
        auth()->forgetGuards();
        return ['Authorization' => 'Bearer ' . ($token ?? $this->token)];
    }

    // ── AUTHENTICATION ──────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v2/industry/florist/arrangements')->assertUnauthorized();
    }

    // ── ARRANGEMENTS ────────────────────────────────────

    public function test_create_arrangement(): void
    {
        $res = $this->postJson('/api/v2/industry/florist/arrangements', [
            'name' => 'Wedding Bouquet',
            'occasion' => 'wedding',
            'items_json' => [['product_id' => fake()->uuid(), 'quantity' => 24]],
            'total_price' => 120.00,
            'is_template' => true,
        ], $this->h());
        $res->assertCreated()->assertJsonPath('data.name', 'Wedding Bouquet');
    }

    public function test_create_arrangement_requires_fields(): void
    {
        $this->postJson('/api/v2/industry/florist/arrangements', [], $this->h())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'items_json', 'total_price']);
    }

    public function test_update_arrangement(): void
    {
        $create = $this->postJson('/api/v2/industry/florist/arrangements', [
            'name' => 'Birthday', 'items_json' => [['product_id' => fake()->uuid(), 'quantity' => 6]], 'total_price' => 45.00,
        ], $this->h());
        $id = $create->json('data.id');

        $res = $this->putJson("/api/v2/industry/florist/arrangements/{$id}", [
            'name' => 'Birthday Deluxe', 'total_price' => 65.00,
        ], $this->h());
        $res->assertOk()->assertJsonPath('data.name', 'Birthday Deluxe');
    }

    public function test_delete_arrangement(): void
    {
        $create = $this->postJson('/api/v2/industry/florist/arrangements', [
            'name' => 'To Delete', 'items_json' => [['product_id' => fake()->uuid(), 'quantity' => 3]], 'total_price' => 20.00,
        ], $this->h());
        $id = $create->json('data.id');

        $this->deleteJson("/api/v2/industry/florist/arrangements/{$id}", [], $this->h())->assertOk();
        $this->assertDatabaseMissing('flower_arrangements', ['id' => $id]);
    }

    public function test_cannot_update_arrangement_from_other_store(): void
    {
        $create = $this->postJson('/api/v2/industry/florist/arrangements', [
            'name' => 'Mine', 'items_json' => [['product_id' => fake()->uuid(), 'quantity' => 5]], 'total_price' => 50.00,
        ], $this->h());
        $id = $create->json('data.id');

        $this->putJson("/api/v2/industry/florist/arrangements/{$id}", ['name' => 'Hijacked'], $this->h($this->otherToken))
            ->assertNotFound();
    }

    public function test_cannot_delete_arrangement_from_other_store(): void
    {
        $create = $this->postJson('/api/v2/industry/florist/arrangements', [
            'name' => 'Protected', 'items_json' => [['product_id' => fake()->uuid(), 'quantity' => 1]], 'total_price' => 80.00,
        ], $this->h());
        $id = $create->json('data.id');

        $this->deleteJson("/api/v2/industry/florist/arrangements/{$id}", [], $this->h($this->otherToken))->assertNotFound();
    }

    public function test_filter_arrangements_by_occasion(): void
    {
        $this->postJson('/api/v2/industry/florist/arrangements', [
            'name' => 'Wedding', 'occasion' => 'wedding', 'items_json' => [['product_id' => fake()->uuid(), 'quantity' => 12]], 'total_price' => 100,
        ], $this->h());
        $this->postJson('/api/v2/industry/florist/arrangements', [
            'name' => 'Funeral', 'occasion' => 'sympathy', 'items_json' => [['product_id' => fake()->uuid(), 'quantity' => 12]], 'total_price' => 80,
        ], $this->h());

        $this->getJson('/api/v2/industry/florist/arrangements?occasion=wedding', $this->h())
            ->assertOk()->assertJsonCount(1, 'data.data');
    }

    // ── FRESHNESS LOGS ──────────────────────────────────

    public function test_create_freshness_log(): void
    {
        $res = $this->postJson('/api/v2/industry/florist/freshness-logs', [
            'product_id' => fake()->uuid(), 'received_date' => '2025-06-01', 'expected_vase_life_days' => 7, 'quantity' => 50,
        ], $this->h());
        $res->assertCreated()->assertJsonPath('data.quantity', 50);
    }

    public function test_create_freshness_log_requires_fields(): void
    {
        $this->postJson('/api/v2/industry/florist/freshness-logs', [], $this->h())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id', 'received_date', 'expected_vase_life_days', 'quantity']);
    }

    public function test_update_freshness_log_status(): void
    {
        $create = $this->postJson('/api/v2/industry/florist/freshness-logs', [
            'product_id' => fake()->uuid(), 'received_date' => '2025-05-28', 'expected_vase_life_days' => 5, 'quantity' => 30,
        ], $this->h());
        $id = $create->json('data.id');

        $this->patchJson("/api/v2/industry/florist/freshness-logs/{$id}/status", ['status' => 'marked_down'], $this->h())->assertOk();
    }

    public function test_freshness_log_status_must_be_valid(): void
    {
        $create = $this->postJson('/api/v2/industry/florist/freshness-logs', [
            'product_id' => fake()->uuid(), 'received_date' => '2025-05-30', 'expected_vase_life_days' => 6, 'quantity' => 20,
        ], $this->h());
        $id = $create->json('data.id');

        $this->patchJson("/api/v2/industry/florist/freshness-logs/{$id}/status", ['status' => 'invalid'], $this->h())
            ->assertUnprocessable();
    }

    // ── SUBSCRIPTIONS ───────────────────────────────────

    public function test_create_subscription(): void
    {
        $res = $this->postJson('/api/v2/industry/florist/subscriptions', [
            'customer_id' => fake()->uuid(), 'arrangement_template_id' => fake()->uuid(), 'frequency' => 'weekly', 'delivery_day' => 'monday',
            'delivery_address' => '123 Flower St', 'price_per_delivery' => 35.00, 'next_delivery_date' => '2027-06-09',
        ], $this->h());
        $res->assertCreated()->assertJsonPath('data.frequency', 'weekly');
    }

    public function test_create_subscription_requires_fields(): void
    {
        $this->postJson('/api/v2/industry/florist/subscriptions', [], $this->h())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id', 'arrangement_template_id', 'frequency', 'delivery_day', 'delivery_address', 'price_per_delivery', 'next_delivery_date']);
    }

    public function test_update_subscription(): void
    {
        $create = $this->postJson('/api/v2/industry/florist/subscriptions', [
            'customer_id' => fake()->uuid(), 'arrangement_template_id' => fake()->uuid(), 'frequency' => 'monthly', 'delivery_day' => 'friday',
            'delivery_address' => '456 Garden Ave', 'price_per_delivery' => 50.00, 'next_delivery_date' => '2027-07-01',
        ], $this->h());
        $id = $create->json('data.id');

        $res = $this->putJson("/api/v2/industry/florist/subscriptions/{$id}", ['price_per_delivery' => 55.00], $this->h());
        $res->assertOk();
    }

    public function test_toggle_subscription(): void
    {
        $create = $this->postJson('/api/v2/industry/florist/subscriptions', [
            'customer_id' => fake()->uuid(), 'arrangement_template_id' => fake()->uuid(), 'frequency' => 'biweekly', 'delivery_day' => 'wednesday',
            'delivery_address' => '789 Bloom Rd', 'price_per_delivery' => 42.00, 'next_delivery_date' => '2027-06-15',
        ], $this->h());
        $id = $create->json('data.id');

        $this->patchJson("/api/v2/industry/florist/subscriptions/{$id}/toggle", [], $this->h())->assertOk();
    }

    public function test_cannot_update_subscription_from_other_store(): void
    {
        $create = $this->postJson('/api/v2/industry/florist/subscriptions', [
            'customer_id' => fake()->uuid(), 'arrangement_template_id' => fake()->uuid(), 'frequency' => 'weekly', 'delivery_day' => 'monday',
            'delivery_address' => '100 My St', 'price_per_delivery' => 30.00, 'next_delivery_date' => '2027-06-16',
        ], $this->h());
        $id = $create->json('data.id');

        $this->putJson("/api/v2/industry/florist/subscriptions/{$id}", ['price_per_delivery' => 10.00], $this->h($this->otherToken))
            ->assertNotFound();
    }
}
