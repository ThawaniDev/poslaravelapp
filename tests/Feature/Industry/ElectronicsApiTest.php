<?php

namespace Tests\Feature\Industry;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ElectronicsApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private string $storeId;
    private string $otherToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();

        $org = Organization::create(['name' => 'Elec Org', 'slug' => 'elec-org-' . uniqid(), 'is_active' => true]);
        $store = Store::create(['organization_id' => $org->id, 'name' => 'Elec Store', 'slug' => 'elec-store-' . uniqid(), 'is_active' => true]);
        $this->storeId = $store->id;
        $user = User::create(['name' => 'Elec User', 'email' => 'elec-' . uniqid() . '@test.com', 'password_hash' => bcrypt('password'), 'store_id' => $store->id]);
        $this->token = $user->createToken('test', ['*'])->plainTextToken;

        $otherStore = Store::create(['organization_id' => $org->id, 'name' => 'Other Elec', 'slug' => 'other-elec-' . uniqid(), 'is_active' => true]);
        $otherUser = User::create(['name' => 'Other Elec', 'email' => 'other-elec-' . uniqid() . '@test.com', 'password_hash' => bcrypt('password'), 'store_id' => $otherStore->id]);
        $this->otherToken = $otherUser->createToken('test', ['*'])->plainTextToken;
    }

    private function createTables(): void
    {
        DB::statement('DROP TABLE IF EXISTS device_imei_records CASCADE');
        DB::statement('CREATE TABLE device_imei_records (id VARCHAR(36) PRIMARY KEY, product_id VARCHAR(36) NOT NULL, store_id VARCHAR(36) NOT NULL, imei VARCHAR(20) NOT NULL, imei2 VARCHAR(20), serial_number VARCHAR(100), condition_grade VARCHAR(5) NOT NULL, purchase_price DECIMAL(10,2), status VARCHAR(20) NOT NULL, warranty_end_date DATE, store_warranty_end_date DATE, sold_order_id VARCHAR(36), created_at TIMESTAMP, updated_at TIMESTAMP)');

        DB::statement('DROP TABLE IF EXISTS repair_jobs CASCADE');
        DB::statement('CREATE TABLE repair_jobs (id VARCHAR(36) PRIMARY KEY, store_id VARCHAR(36) NOT NULL, customer_id VARCHAR(36) NOT NULL, device_description VARCHAR(500) NOT NULL, imei VARCHAR(20), issue_description TEXT NOT NULL, status VARCHAR(20) NOT NULL, diagnosis_notes TEXT, repair_notes TEXT, estimated_cost DECIMAL(10,2), final_cost DECIMAL(10,2), parts_used TEXT, staff_user_id VARCHAR(36), received_at TIMESTAMP, estimated_ready_at TIMESTAMP, completed_at TIMESTAMP, collected_at TIMESTAMP, created_at TIMESTAMP, updated_at TIMESTAMP)');

        DB::statement('DROP TABLE IF EXISTS trade_in_records CASCADE');
        DB::statement('CREATE TABLE trade_in_records (id VARCHAR(36) PRIMARY KEY, store_id VARCHAR(36) NOT NULL, customer_id VARCHAR(36) NOT NULL, device_description VARCHAR(500) NOT NULL, imei VARCHAR(20), condition_grade VARCHAR(5) NOT NULL, assessed_value DECIMAL(10,2) NOT NULL, applied_to_order_id VARCHAR(36), staff_user_id VARCHAR(36), created_at TIMESTAMP, updated_at TIMESTAMP)');
    }

    private function h(?string $token = null): array
    {
        auth()->forgetGuards();
        return ['Authorization' => 'Bearer ' . ($token ?? $this->token)];
    }

    // ── AUTHENTICATION ──────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v2/industry/electronics/imei-records')->assertUnauthorized();
        $this->getJson('/api/v2/industry/electronics/repair-jobs')->assertUnauthorized();
        $this->getJson('/api/v2/industry/electronics/trade-ins')->assertUnauthorized();
    }

    // ── IMEI RECORDS ────────────────────────────────────

    public function test_list_imei_records(): void
    {
        $this->postJson('/api/v2/industry/electronics/imei-records', [
            'product_id' => fake()->uuid(), 'imei' => '111111111111111', 'condition_grade' => 'A',
        ], $this->h());

        $this->getJson('/api/v2/industry/electronics/imei-records', $this->h())
            ->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_create_imei_record(): void
    {
        $res = $this->postJson('/api/v2/industry/electronics/imei-records', [
            'product_id' => fake()->uuid(), 'imei' => '123456789012345', 'serial_number' => 'SN-001',
            'condition_grade' => 'A', 'purchase_price' => 999.99,
            'warranty_end_date' => '2026-06-01',
        ], $this->h());
        $res->assertCreated()->assertJsonPath('data.imei', '123456789012345');
    }

    public function test_create_imei_requires_fields(): void
    {
        $res = $this->postJson('/api/v2/industry/electronics/imei-records', [], $this->h());
        $res->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id', 'imei']);
    }

    public function test_update_imei_record(): void
    {
        $create = $this->postJson('/api/v2/industry/electronics/imei-records', [
            'product_id' => fake()->uuid(), 'imei' => '987654321098765', 'condition_grade' => 'B',
        ], $this->h());
        $id = $create->json('data.id');

        $res = $this->putJson("/api/v2/industry/electronics/imei-records/{$id}", [
            'status' => 'sold', 'sold_order_id' => fake()->uuid(),
        ], $this->h());
        $res->assertOk()->assertJsonPath('data.status', 'sold');
    }

    public function test_cannot_update_imei_from_other_store(): void
    {
        $create = $this->postJson('/api/v2/industry/electronics/imei-records', [
            'product_id' => fake()->uuid(), 'imei' => '555666777888999', 'condition_grade' => 'A',
        ], $this->h());
        $id = $create->json('data.id');

        $this->putJson("/api/v2/industry/electronics/imei-records/{$id}", ['status' => 'sold'], $this->h($this->otherToken))
            ->assertNotFound();
    }

    public function test_filter_imei_by_status(): void
    {
        $first = $this->postJson('/api/v2/industry/electronics/imei-records', [
            'product_id' => fake()->uuid(), 'imei' => '111000111000111', 'condition_grade' => 'A',
        ], $this->h());
        $this->postJson('/api/v2/industry/electronics/imei-records', [
            'product_id' => fake()->uuid(), 'imei' => '222000222000222', 'condition_grade' => 'B',
        ], $this->h());

        $id = $first->json('data.id');
        $this->putJson("/api/v2/industry/electronics/imei-records/{$id}", [
            'status' => 'sold', 'sold_order_id' => fake()->uuid(),
        ], $this->h());

        $this->getJson('/api/v2/industry/electronics/imei-records?status=sold', $this->h())
            ->assertOk()->assertJsonCount(1, 'data.data');
    }

    // ── REPAIR JOBS ─────────────────────────────────────

    public function test_create_repair_job(): void
    {
        $res = $this->postJson('/api/v2/industry/electronics/repair-jobs', [
            'customer_id' => fake()->uuid(),
            'device_description' => 'iPhone 15 Pro Max',
            'imei' => '111222333444555',
            'issue_description' => 'Cracked screen and battery drain',
        ], $this->h());
        $res->assertCreated()->assertJsonPath('data.device_description', 'iPhone 15 Pro Max');
    }

    public function test_create_repair_job_requires_fields(): void
    {
        $res = $this->postJson('/api/v2/industry/electronics/repair-jobs', [], $this->h());
        $res->assertUnprocessable()
            ->assertJsonValidationErrors(['device_description', 'issue_description']);
    }

    public function test_update_repair_job(): void
    {
        $create = $this->postJson('/api/v2/industry/electronics/repair-jobs', [
            'customer_id' => fake()->uuid(), 'device_description' => 'MacBook Pro', 'issue_description' => 'Won\'t boot',
        ], $this->h());
        $id = $create->json('data.id');

        $res = $this->putJson("/api/v2/industry/electronics/repair-jobs/{$id}", [
            'diagnosis_notes' => 'Logic board failure', 'estimated_cost' => 500.00,
        ], $this->h());
        $res->assertOk();
        $this->assertEquals(500.0, (float) $res->json('data.estimated_cost'));
    }

    public function test_update_repair_job_status(): void
    {
        $create = $this->postJson('/api/v2/industry/electronics/repair-jobs', [
            'customer_id' => fake()->uuid(), 'device_description' => 'Galaxy S24', 'issue_description' => 'Water damage',
        ], $this->h());
        $id = $create->json('data.id');

        $this->patchJson("/api/v2/industry/electronics/repair-jobs/{$id}/status", ['status' => 'diagnosing'], $this->h())->assertOk();
        $this->patchJson("/api/v2/industry/electronics/repair-jobs/{$id}/status", ['status' => 'repairing'], $this->h())->assertOk();
    }

    public function test_repair_job_status_must_be_valid(): void
    {
        $create = $this->postJson('/api/v2/industry/electronics/repair-jobs', [
            'customer_id' => fake()->uuid(), 'device_description' => 'iPad', 'issue_description' => 'Cracked',
        ], $this->h());
        $id = $create->json('data.id');

        $this->patchJson("/api/v2/industry/electronics/repair-jobs/{$id}/status", ['status' => 'invalid'], $this->h())
            ->assertUnprocessable();
    }

    public function test_cannot_update_repair_from_other_store(): void
    {
        $create = $this->postJson('/api/v2/industry/electronics/repair-jobs', [
            'customer_id' => fake()->uuid(), 'device_description' => 'Laptop', 'issue_description' => 'Broken',
        ], $this->h());
        $id = $create->json('data.id');

        $this->putJson("/api/v2/industry/electronics/repair-jobs/{$id}", ['diagnosis_notes' => 'Hijack'], $this->h($this->otherToken))
            ->assertNotFound();
    }

    // ── TRADE-INS ───────────────────────────────────────

    public function test_create_trade_in(): void
    {
        $res = $this->postJson('/api/v2/industry/electronics/trade-ins', [
            'customer_id' => fake()->uuid(), 'device_description' => 'iPhone 13 256GB',
            'imei' => '999888777666555', 'condition_grade' => 'B', 'assessed_value' => 450.00,
        ], $this->h());
        $res->assertCreated();
        $this->assertEquals(450.0, (float) $res->json('data.assessed_value'));
    }

    public function test_create_trade_in_requires_fields(): void
    {
        $res = $this->postJson('/api/v2/industry/electronics/trade-ins', [], $this->h());
        $res->assertUnprocessable()
            ->assertJsonValidationErrors(['device_description', 'condition_grade', 'assessed_value']);
    }

    public function test_list_trade_ins_filters(): void
    {
        $this->postJson('/api/v2/industry/electronics/trade-ins', [
            'customer_id' => fake()->uuid(), 'device_description' => 'Samsung S23', 'condition_grade' => 'A', 'assessed_value' => 600,
        ], $this->h());

        $this->getJson('/api/v2/industry/electronics/trade-ins', $this->h())
            ->assertOk()->assertJsonCount(1, 'data.data');
    }
}
