<?php

namespace Tests\Feature\Industry;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RestaurantApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private string $storeId;
    private string $otherToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();

        $org = Organization::create(['name' => 'Restaurant Org', 'slug' => 'rest-org-' . uniqid(), 'is_active' => true]);
        $store = Store::create(['organization_id' => $org->id, 'name' => 'Fine Dining', 'slug' => 'fine-dining-' . uniqid(), 'is_active' => true]);
        $this->storeId = $store->id;
        $user = User::create(['name' => 'Host', 'email' => 'host-' . uniqid() . '@test.com', 'password_hash' => bcrypt('password'), 'store_id' => $store->id]);
        $this->token = $user->createToken('test', ['*'])->plainTextToken;

        $otherStore = Store::create(['organization_id' => $org->id, 'name' => 'Other Rest', 'slug' => 'other-rest-' . uniqid(), 'is_active' => true]);
        $otherUser = User::create(['name' => 'Other Host', 'email' => 'other-host-' . uniqid() . '@test.com', 'password_hash' => bcrypt('password'), 'store_id' => $otherStore->id]);
        $this->otherToken = $otherUser->createToken('test', ['*'])->plainTextToken;
    }

    private function createTables(): void
    {
        DB::statement('DROP TABLE IF EXISTS restaurant_tables');
        DB::statement('CREATE TABLE restaurant_tables (id VARCHAR(36) PRIMARY KEY, store_id VARCHAR(36) NOT NULL, table_number VARCHAR(20) NOT NULL, display_name VARCHAR(100), seats INTEGER NOT NULL, zone VARCHAR(50), position_x REAL, position_y REAL, status VARCHAR(20) NOT NULL DEFAULT "available", current_order_id VARCHAR(36), is_active BOOLEAN DEFAULT 1, created_at TIMESTAMP, updated_at TIMESTAMP)');

        DB::statement('DROP TABLE IF EXISTS kitchen_tickets');
        DB::statement('CREATE TABLE kitchen_tickets (id VARCHAR(36) PRIMARY KEY, store_id VARCHAR(36) NOT NULL, order_id VARCHAR(36), table_id VARCHAR(36), ticket_number VARCHAR(50) NOT NULL, items_json TEXT NOT NULL, station VARCHAR(50), status VARCHAR(20) NOT NULL DEFAULT "pending", course_number INTEGER, fire_at DATETIME, completed_at DATETIME, created_at TIMESTAMP, updated_at TIMESTAMP)');

        DB::statement('DROP TABLE IF EXISTS table_reservations');
        DB::statement('CREATE TABLE table_reservations (id VARCHAR(36) PRIMARY KEY, store_id VARCHAR(36) NOT NULL, table_id VARCHAR(36), customer_name VARCHAR(255) NOT NULL, customer_phone VARCHAR(20), party_size INTEGER NOT NULL, reservation_date DATE NOT NULL, reservation_time VARCHAR(10) NOT NULL, duration_minutes INTEGER, status VARCHAR(20) NOT NULL DEFAULT "confirmed", notes TEXT, created_at TIMESTAMP, updated_at TIMESTAMP)');

        DB::statement('DROP TABLE IF EXISTS open_tabs');
        DB::statement('CREATE TABLE open_tabs (id VARCHAR(36) PRIMARY KEY, store_id VARCHAR(36) NOT NULL, order_id VARCHAR(36), customer_name VARCHAR(255), table_id VARCHAR(36), opened_at DATETIME, closed_at DATETIME, status VARCHAR(10) NOT NULL DEFAULT "open", created_at TIMESTAMP, updated_at TIMESTAMP)');
    }

    private function h(string $token = null): array
    {
        return ['Authorization' => 'Bearer ' . ($token ?? $this->token)];
    }

    // ── AUTHENTICATION ──────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v2/industry/restaurant/tables')->assertUnauthorized();
        $this->getJson('/api/v2/industry/restaurant/kitchen-tickets')->assertUnauthorized();
        $this->getJson('/api/v2/industry/restaurant/reservations')->assertUnauthorized();
        $this->getJson('/api/v2/industry/restaurant/tabs')->assertUnauthorized();
    }

    // ── TABLES ──────────────────────────────────────────

    public function test_create_table(): void
    {
        $res = $this->postJson('/api/v2/industry/restaurant/tables', [
            'table_number' => 'T1', 'display_name' => 'Window Table', 'seats' => 4, 'zone' => 'Main Hall',
        ], $this->h());
        $res->assertCreated()->assertJsonPath('data.table_number', 'T1');
    }

    public function test_create_table_requires_fields(): void
    {
        $this->postJson('/api/v2/industry/restaurant/tables', [], $this->h())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['table_number', 'seats']);
    }

    public function test_list_tables_paginated(): void
    {
        $this->postJson('/api/v2/industry/restaurant/tables', ['table_number' => 'T1', 'seats' => 2], $this->h());
        $this->postJson('/api/v2/industry/restaurant/tables', ['table_number' => 'T2', 'seats' => 4], $this->h());

        $this->getJson('/api/v2/industry/restaurant/tables', $this->h())
            ->assertOk()->assertJsonCount(2, 'data.data');
    }

    public function test_update_table(): void
    {
        $create = $this->postJson('/api/v2/industry/restaurant/tables', ['table_number' => 'T3', 'seats' => 2], $this->h());
        $id = $create->json('data.id');

        $res = $this->putJson("/api/v2/industry/restaurant/tables/{$id}", [
            'display_name' => 'Corner Booth', 'seats' => 6,
        ], $this->h());
        $res->assertOk()->assertJsonPath('data.seats', 6);
    }

    public function test_update_table_status(): void
    {
        $create = $this->postJson('/api/v2/industry/restaurant/tables', ['table_number' => 'T4', 'seats' => 4], $this->h());
        $id = $create->json('data.id');

        $this->patchJson("/api/v2/industry/restaurant/tables/{$id}/status", ['status' => 'occupied'], $this->h())->assertOk();
    }

    public function test_table_status_must_be_valid(): void
    {
        $create = $this->postJson('/api/v2/industry/restaurant/tables', ['table_number' => 'T5', 'seats' => 2], $this->h());
        $id = $create->json('data.id');

        $this->patchJson("/api/v2/industry/restaurant/tables/{$id}/status", ['status' => 'invalid'], $this->h())
            ->assertUnprocessable();
    }

    public function test_cannot_update_table_from_other_store(): void
    {
        $create = $this->postJson('/api/v2/industry/restaurant/tables', ['table_number' => 'T6', 'seats' => 4], $this->h());
        $id = $create->json('data.id');

        $this->putJson("/api/v2/industry/restaurant/tables/{$id}", ['seats' => 10], $this->h($this->otherToken))
            ->assertNotFound();
    }

    public function test_filter_tables_by_zone(): void
    {
        $this->postJson('/api/v2/industry/restaurant/tables', ['table_number' => 'T7', 'seats' => 2, 'zone' => 'Terrace'], $this->h());
        $this->postJson('/api/v2/industry/restaurant/tables', ['table_number' => 'T8', 'seats' => 4, 'zone' => 'Indoor'], $this->h());

        $this->getJson('/api/v2/industry/restaurant/tables?zone=Terrace', $this->h())
            ->assertOk()->assertJsonCount(1, 'data.data');
    }

    // ── KITCHEN TICKETS ─────────────────────────────────

    public function test_create_kitchen_ticket(): void
    {
        $res = $this->postJson('/api/v2/industry/restaurant/kitchen-tickets', [
            'ticket_number' => 'KT-001', 'items_json' => [['name' => 'Burger', 'qty' => 2]], 'station' => 'Grill',
        ], $this->h());
        $res->assertCreated()->assertJsonPath('data.ticket_number', 'KT-001');
    }

    public function test_create_kitchen_ticket_requires_fields(): void
    {
        $this->postJson('/api/v2/industry/restaurant/kitchen-tickets', [], $this->h())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ticket_number', 'items_json']);
    }

    public function test_update_kitchen_ticket_status(): void
    {
        $create = $this->postJson('/api/v2/industry/restaurant/kitchen-tickets', [
            'ticket_number' => 'KT-002', 'items_json' => [['name' => 'Pasta', 'qty' => 1]], 'station' => 'Hot',
        ], $this->h());
        $id = $create->json('data.id');

        $this->patchJson("/api/v2/industry/restaurant/kitchen-tickets/{$id}/status", ['status' => 'preparing'], $this->h())->assertOk();
        $this->patchJson("/api/v2/industry/restaurant/kitchen-tickets/{$id}/status", ['status' => 'ready'], $this->h())->assertOk();
    }

    public function test_kitchen_ticket_status_must_be_valid(): void
    {
        $create = $this->postJson('/api/v2/industry/restaurant/kitchen-tickets', [
            'ticket_number' => 'KT-003', 'items_json' => [['name' => 'Salad', 'qty' => 1]],
        ], $this->h());
        $id = $create->json('data.id');

        $this->patchJson("/api/v2/industry/restaurant/kitchen-tickets/{$id}/status", ['status' => 'invalid'], $this->h())
            ->assertUnprocessable();
    }

    public function test_cannot_update_ticket_from_other_store(): void
    {
        $create = $this->postJson('/api/v2/industry/restaurant/kitchen-tickets', [
            'ticket_number' => 'KT-004', 'items_json' => [['name' => 'Fish', 'qty' => 1]],
        ], $this->h());
        $id = $create->json('data.id');

        $this->patchJson("/api/v2/industry/restaurant/kitchen-tickets/{$id}/status", ['status' => 'ready'], $this->h($this->otherToken))
            ->assertNotFound();
    }

    // ── RESERVATIONS ────────────────────────────────────

    public function test_create_reservation(): void
    {
        $res = $this->postJson('/api/v2/industry/restaurant/reservations', [
            'customer_name' => 'John Doe', 'customer_phone' => '+96812345678',
            'party_size' => 4, 'reservation_date' => '2025-06-15', 'reservation_time' => '19:30',
            'duration_minutes' => 90,
        ], $this->h());
        $res->assertCreated()->assertJsonPath('data.customer_name', 'John Doe');
    }

    public function test_create_reservation_requires_fields(): void
    {
        $this->postJson('/api/v2/industry/restaurant/reservations', [], $this->h())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_name', 'party_size', 'reservation_date', 'reservation_time']);
    }

    public function test_update_reservation(): void
    {
        $create = $this->postJson('/api/v2/industry/restaurant/reservations', [
            'customer_name' => 'Jane', 'party_size' => 2, 'reservation_date' => '2025-06-16', 'reservation_time' => '20:00',
        ], $this->h());
        $id = $create->json('data.id');

        $res = $this->putJson("/api/v2/industry/restaurant/reservations/{$id}", [
            'party_size' => 3, 'notes' => 'High chair needed',
        ], $this->h());
        $res->assertOk();
    }

    public function test_update_reservation_status(): void
    {
        $create = $this->postJson('/api/v2/industry/restaurant/reservations', [
            'customer_name' => 'Bob', 'party_size' => 6, 'reservation_date' => '2025-06-17', 'reservation_time' => '18:00',
        ], $this->h());
        $id = $create->json('data.id');

        $this->patchJson("/api/v2/industry/restaurant/reservations/{$id}/status", ['status' => 'seated'], $this->h())->assertOk();
    }

    public function test_reservation_status_must_be_valid(): void
    {
        $create = $this->postJson('/api/v2/industry/restaurant/reservations', [
            'customer_name' => 'Alice', 'party_size' => 2, 'reservation_date' => '2025-06-18', 'reservation_time' => '19:00',
        ], $this->h());
        $id = $create->json('data.id');

        $this->patchJson("/api/v2/industry/restaurant/reservations/{$id}/status", ['status' => 'invalid'], $this->h())
            ->assertUnprocessable();
    }

    public function test_cannot_update_reservation_from_other_store(): void
    {
        $create = $this->postJson('/api/v2/industry/restaurant/reservations', [
            'customer_name' => 'Charlie', 'party_size' => 3, 'reservation_date' => '2025-06-19', 'reservation_time' => '17:00',
        ], $this->h());
        $id = $create->json('data.id');

        $this->putJson("/api/v2/industry/restaurant/reservations/{$id}", ['notes' => 'Hijacked'], $this->h($this->otherToken))
            ->assertNotFound();
    }

    public function test_filter_reservations_by_date(): void
    {
        $this->postJson('/api/v2/industry/restaurant/reservations', [
            'customer_name' => 'A', 'party_size' => 2, 'reservation_date' => '2025-06-20', 'reservation_time' => '18:00',
        ], $this->h());
        $this->postJson('/api/v2/industry/restaurant/reservations', [
            'customer_name' => 'B', 'party_size' => 2, 'reservation_date' => '2025-06-21', 'reservation_time' => '18:00',
        ], $this->h());

        $this->getJson('/api/v2/industry/restaurant/reservations?reservation_date=2025-06-20', $this->h())
            ->assertOk()->assertJsonCount(1, 'data.data');
    }

    // ── OPEN TABS ───────────────────────────────────────

    public function test_open_tab(): void
    {
        $res = $this->postJson('/api/v2/industry/restaurant/tabs', ['customer_name' => 'VIP Guest'], $this->h());
        $res->assertCreated()->assertJsonPath('data.customer_name', 'VIP Guest');
    }

    public function test_close_tab(): void
    {
        $create = $this->postJson('/api/v2/industry/restaurant/tabs', ['customer_name' => 'Bar Customer'], $this->h());
        $id = $create->json('data.id');

        $res = $this->patchJson("/api/v2/industry/restaurant/tabs/{$id}/close", [], $this->h());
        $res->assertOk()->assertJsonPath('data.status', 'closed');
    }

    public function test_cannot_close_tab_from_other_store(): void
    {
        $create = $this->postJson('/api/v2/industry/restaurant/tabs', ['customer_name' => 'My Customer'], $this->h());
        $id = $create->json('data.id');

        $this->patchJson("/api/v2/industry/restaurant/tabs/{$id}/close", [], $this->h($this->otherToken))
            ->assertNotFound();
    }

    public function test_list_open_tabs(): void
    {
        $this->postJson('/api/v2/industry/restaurant/tabs', ['customer_name' => 'Guest 1'], $this->h());
        $this->postJson('/api/v2/industry/restaurant/tabs', ['customer_name' => 'Guest 2'], $this->h());

        $this->getJson('/api/v2/industry/restaurant/tabs', $this->h())
            ->assertOk()->assertJsonCount(2, 'data.data');
    }

    public function test_tabs_only_shows_own_store(): void
    {
        $this->postJson('/api/v2/industry/restaurant/tabs', ['customer_name' => 'Store 1 Guest'], $this->h());

        $this->getJson('/api/v2/industry/restaurant/tabs', $this->h($this->otherToken))
            ->assertOk()->assertJsonCount(0, 'data.data');
    }
}
