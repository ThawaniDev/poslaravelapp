<?php

namespace Tests\Feature\Industry;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IndustryWorkflowApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private string $storeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createIndustryTables();

        $org = Organization::create([
            'name' => 'Test Org',
            'slug' => 'test-org-' . uniqid(),
            'is_active' => true,
        ]);

        $store = Store::create([
            'organization_id' => $org->id,
            'name' => 'Industry Store',
            'slug' => 'industry-store-' . uniqid(),
            'is_active' => true,
        ]);
        $this->storeId = $store->id;

        $user = User::create([
            'name' => 'Industry Tester',
            'email' => 'industry-' . uniqid() . '@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $store->id,
        ]);

        $this->token = $user->createToken('test', ['*'])->plainTextToken;
    }

    private function createIndustryTables(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        }
        // Pharmacy
        DB::statement('DROP TABLE IF EXISTS prescriptions CASCADE');
        DB::statement('CREATE TABLE prescriptions (
            id VARCHAR(36) PRIMARY KEY,
            store_id VARCHAR(36) NOT NULL,
            order_id VARCHAR(36),
            prescription_number VARCHAR(100) NOT NULL,
            patient_name VARCHAR(255) NOT NULL,
            patient_id VARCHAR(100),
            doctor_name VARCHAR(255) NOT NULL,
            doctor_license VARCHAR(100) NOT NULL,
            insurance_provider VARCHAR(255),
            insurance_claim_amount DECIMAL(10,2),
            notes TEXT,
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )');

        DB::statement('DROP TABLE IF EXISTS drug_schedules CASCADE');
        DB::statement('CREATE TABLE drug_schedules (
            id VARCHAR(36) PRIMARY KEY,
            product_id VARCHAR(36) NOT NULL,
            schedule_type VARCHAR(30) NOT NULL,
            active_ingredient VARCHAR(255),
            dosage_form VARCHAR(100),
            strength VARCHAR(100),
            manufacturer VARCHAR(255),
            requires_prescription BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )');

        // Jewelry
        DB::statement('DROP TABLE IF EXISTS daily_metal_rates CASCADE');
        DB::statement('CREATE TABLE daily_metal_rates (
            id VARCHAR(36) PRIMARY KEY,
            store_id VARCHAR(36) NOT NULL,
            metal_type VARCHAR(20) NOT NULL,
            karat VARCHAR(20),
            rate_per_gram DECIMAL(12,2) NOT NULL,
            buyback_rate_per_gram DECIMAL(12,2),
            effective_date DATE NOT NULL,
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )');

        DB::statement('DROP TABLE IF EXISTS jewelry_product_details CASCADE');
        DB::statement('CREATE TABLE jewelry_product_details (
            id VARCHAR(36) PRIMARY KEY,
            product_id VARCHAR(36) NOT NULL,
            metal_type VARCHAR(20) NOT NULL,
            karat VARCHAR(20),
            gross_weight_g DECIMAL(10,2) NOT NULL,
            net_weight_g DECIMAL(10,2),
            making_charges_type VARCHAR(20) NOT NULL,
            making_charges_value DECIMAL(10,2) NOT NULL,
            stone_type VARCHAR(100),
            stone_weight_carat DECIMAL(8,2),
            stone_count INTEGER,
            certificate_number VARCHAR(100),
            certificate_url VARCHAR(500),
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )');

        DB::statement('DROP TABLE IF EXISTS buyback_transactions CASCADE');
        DB::statement('CREATE TABLE buyback_transactions (
            id VARCHAR(36) PRIMARY KEY,
            store_id VARCHAR(36) NOT NULL,
            customer_id VARCHAR(36) NOT NULL,
            metal_type VARCHAR(20) NOT NULL,
            karat VARCHAR(20),
            weight_g DECIMAL(10,2) NOT NULL,
            rate_per_gram DECIMAL(12,2) NOT NULL,
            total_amount DECIMAL(12,2) NOT NULL,
            payment_method VARCHAR(30) NOT NULL,
            staff_user_id VARCHAR(36),
            notes TEXT,
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )');

        // Electronics
        DB::statement('DROP TABLE IF EXISTS device_imei_records CASCADE');
        DB::statement('CREATE TABLE device_imei_records (
            id VARCHAR(36) PRIMARY KEY,
            product_id VARCHAR(36) NOT NULL,
            store_id VARCHAR(36) NOT NULL,
            imei VARCHAR(20) NOT NULL,
            imei2 VARCHAR(20),
            serial_number VARCHAR(100),
            condition_grade VARCHAR(5) NOT NULL,
            purchase_price DECIMAL(10,2),
            status VARCHAR(20) NOT NULL,
            warranty_end_date DATE,
            store_warranty_end_date DATE,
            sold_order_id VARCHAR(36),
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )');

        DB::statement('DROP TABLE IF EXISTS repair_jobs CASCADE');
        DB::statement('CREATE TABLE repair_jobs (
            id VARCHAR(36) PRIMARY KEY,
            store_id VARCHAR(36) NOT NULL,
            customer_id VARCHAR(36) NOT NULL,
            device_description VARCHAR(500) NOT NULL,
            imei VARCHAR(20),
            issue_description TEXT NOT NULL,
            status VARCHAR(20) NOT NULL,
            diagnosis_notes TEXT,
            repair_notes TEXT,
            estimated_cost DECIMAL(10,2),
            final_cost DECIMAL(10,2),
            parts_used TEXT,
            staff_user_id VARCHAR(36),
            received_at TIMESTAMP,
            estimated_ready_at TIMESTAMP,
            completed_at TIMESTAMP,
            collected_at TIMESTAMP,
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )');

        DB::statement('DROP TABLE IF EXISTS trade_in_records CASCADE');
        DB::statement('CREATE TABLE trade_in_records (
            id VARCHAR(36) PRIMARY KEY,
            store_id VARCHAR(36) NOT NULL,
            customer_id VARCHAR(36) NOT NULL,
            device_description VARCHAR(500) NOT NULL,
            imei VARCHAR(20),
            condition_grade VARCHAR(5) NOT NULL,
            assessed_value DECIMAL(10,2) NOT NULL,
            applied_to_order_id VARCHAR(36),
            staff_user_id VARCHAR(36),
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )');

        // Florist
        DB::statement('DROP TABLE IF EXISTS flower_arrangements CASCADE');
        DB::statement('CREATE TABLE flower_arrangements (
            id VARCHAR(36) PRIMARY KEY,
            store_id VARCHAR(36) NOT NULL,
            name VARCHAR(255) NOT NULL,
            occasion VARCHAR(100),
            items_json TEXT,
            total_price DECIMAL(10,2) NOT NULL,
            is_template BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )');

        DB::statement('DROP TABLE IF EXISTS flower_freshness_log CASCADE');
        DB::statement('CREATE TABLE flower_freshness_log (
            id VARCHAR(36) PRIMARY KEY,
            product_id VARCHAR(36) NOT NULL,
            store_id VARCHAR(36) NOT NULL,
            received_date DATE NOT NULL,
            expected_vase_life_days INTEGER NOT NULL,
            markdown_date DATE,
            dispose_date DATE,
            quantity INTEGER NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'fresh\',
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )');

        DB::statement('DROP TABLE IF EXISTS flower_subscriptions CASCADE');
        DB::statement('CREATE TABLE flower_subscriptions (
            id VARCHAR(36) PRIMARY KEY,
            store_id VARCHAR(36) NOT NULL,
            customer_id VARCHAR(36) NOT NULL,
            arrangement_template_id VARCHAR(36),
            frequency VARCHAR(20) NOT NULL,
            delivery_day VARCHAR(20) NOT NULL,
            delivery_address VARCHAR(500) NOT NULL,
            price_per_delivery DECIMAL(10,2) NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            next_delivery_date DATE NOT NULL,
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )');

        // Bakery
        DB::statement('DROP TABLE IF EXISTS bakery_recipes CASCADE');
        DB::statement('CREATE TABLE bakery_recipes (
            id VARCHAR(36) PRIMARY KEY,
            store_id VARCHAR(36) NOT NULL,
            product_id VARCHAR(36),
            name VARCHAR(255) NOT NULL,
            expected_yield INTEGER,
            prep_time_minutes INTEGER,
            bake_time_minutes INTEGER,
            bake_temperature_c INTEGER,
            instructions TEXT,
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )');

        DB::statement('DROP TABLE IF EXISTS production_schedules CASCADE');
        DB::statement('CREATE TABLE production_schedules (
            id VARCHAR(36) PRIMARY KEY,
            store_id VARCHAR(36) NOT NULL,
            recipe_id VARCHAR(36) NOT NULL,
            schedule_date DATE NOT NULL,
            planned_batches INTEGER NOT NULL,
            actual_batches INTEGER,
            planned_yield INTEGER,
            actual_yield INTEGER,
            status VARCHAR(20) NOT NULL DEFAULT \'planned\',
            notes TEXT,
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )');

        DB::statement('DROP TABLE IF EXISTS custom_cake_orders CASCADE');
        DB::statement('CREATE TABLE custom_cake_orders (
            id VARCHAR(36) PRIMARY KEY,
            store_id VARCHAR(36) NOT NULL,
            customer_id VARCHAR(36) NOT NULL,
            order_id VARCHAR(36),
            description TEXT NOT NULL,
            size VARCHAR(50) NOT NULL,
            flavor VARCHAR(100) NOT NULL,
            decoration_notes TEXT,
            delivery_date DATE NOT NULL,
            delivery_time VARCHAR(10),
            price DECIMAL(10,2) NOT NULL,
            deposit_paid DECIMAL(10,2),
            status VARCHAR(20) NOT NULL DEFAULT \'ordered\',
            reference_image_url VARCHAR(500),
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )');

        // Restaurant
        DB::statement('DROP TABLE IF EXISTS restaurant_tables CASCADE');
        DB::statement('CREATE TABLE restaurant_tables (
            id VARCHAR(36) PRIMARY KEY,
            store_id VARCHAR(36) NOT NULL,
            table_number VARCHAR(20) NOT NULL,
            display_name VARCHAR(100),
            seats INTEGER NOT NULL,
            zone VARCHAR(50),
            position_x REAL,
            position_y REAL,
            status VARCHAR(20) NOT NULL DEFAULT \'available\',
            current_order_id VARCHAR(36),
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )');

        DB::statement('DROP TABLE IF EXISTS kitchen_tickets CASCADE');
        DB::statement('CREATE TABLE kitchen_tickets (
            id VARCHAR(36) PRIMARY KEY,
            store_id VARCHAR(36) NOT NULL,
            order_id VARCHAR(36),
            table_id VARCHAR(36),
            ticket_number VARCHAR(50) NOT NULL,
            items_json TEXT NOT NULL,
            station VARCHAR(50),
            status VARCHAR(20) NOT NULL DEFAULT \'pending\',
            course_number INTEGER,
            fire_at TIMESTAMP,
            completed_at TIMESTAMP,
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )');

        DB::statement('DROP TABLE IF EXISTS table_reservations CASCADE');
        DB::statement('CREATE TABLE table_reservations (
            id VARCHAR(36) PRIMARY KEY,
            store_id VARCHAR(36) NOT NULL,
            table_id VARCHAR(36),
            customer_name VARCHAR(255) NOT NULL,
            customer_phone VARCHAR(20),
            party_size INTEGER NOT NULL,
            reservation_date DATE NOT NULL,
            reservation_time VARCHAR(10) NOT NULL,
            duration_minutes INTEGER,
            status VARCHAR(20) NOT NULL DEFAULT \'confirmed\',
            notes TEXT,
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )');

        DB::statement('DROP TABLE IF EXISTS open_tabs CASCADE');
        DB::statement('CREATE TABLE open_tabs (
            id VARCHAR(36) PRIMARY KEY,
            store_id VARCHAR(36) NOT NULL,
            order_id VARCHAR(36),
            transaction_id VARCHAR(36),
            customer_name VARCHAR(255),
            table_id VARCHAR(36),
            opened_at TIMESTAMP,
            closed_at TIMESTAMP,
            status VARCHAR(10) NOT NULL DEFAULT \'open\',
            running_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )');
    }

    private function authHeader(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    // ════════════════════════════════════════════════════════
    // PHARMACY
    // ════════════════════════════════════════════════════════

    public function test_list_prescriptions(): void
    {
        $res = $this->getJson('/api/v2/industry/pharmacy/prescriptions', $this->authHeader());
        $res->assertOk();
    }

    public function test_create_prescription(): void
    {
        $res = $this->postJson('/api/v2/industry/pharmacy/prescriptions', [
            'order_id' => fake()->uuid(),
            'prescription_number' => 'RX-001',
            'patient_name' => 'John Doe',
            'doctor_name' => 'Dr Smith',
            'doctor_license' => 'LIC-001',
        ], $this->authHeader());
        $res->assertCreated();
    }

    public function test_update_prescription(): void
    {
        $create = $this->postJson('/api/v2/industry/pharmacy/prescriptions', [
            'order_id' => fake()->uuid(),
            'prescription_number' => 'RX-002',
            'patient_name' => 'Jane Doe',
            'doctor_name' => 'Dr Smith',
            'doctor_license' => 'LIC-001',
        ], $this->authHeader());
        $id = $create->json('data.id');

        $res = $this->putJson("/api/v2/industry/pharmacy/prescriptions/{$id}", [
            'insurance_provider' => 'Al Ahlia',
            'notes' => 'Half dispensed',
        ], $this->authHeader());
        $res->assertOk();
    }

    public function test_list_drug_schedules(): void
    {
        $res = $this->getJson('/api/v2/industry/pharmacy/drug-schedules', $this->authHeader());
        $res->assertOk();
    }

    public function test_create_drug_schedule(): void
    {
        $res = $this->postJson('/api/v2/industry/pharmacy/drug-schedules', [
            'product_id' => fake()->uuid(),
            'schedule_type' => 'controlled',
            'active_ingredient' => 'Codeine',
            'dosage_form' => 'tablet',
            'strength' => '30mg',
            'requires_prescription' => true,
        ], $this->authHeader());
        $res->assertCreated();
    }

    public function test_update_drug_schedule(): void
    {
        $create = $this->postJson('/api/v2/industry/pharmacy/drug-schedules', [
            'product_id' => fake()->uuid(),
            'schedule_type' => 'otc',
            'active_ingredient' => 'Ibuprofen',
            'dosage_form' => 'tablet',
            'strength' => '200mg',
            'requires_prescription' => false,
        ], $this->authHeader());
        $id = $create->json('data.id');

        $res = $this->putJson("/api/v2/industry/pharmacy/drug-schedules/{$id}", [
            'schedule_type' => 'prescription_only',
            'requires_prescription' => true,
        ], $this->authHeader());
        $res->assertOk();
    }

    // ════════════════════════════════════════════════════════
    // JEWELRY
    // ════════════════════════════════════════════════════════

    public function test_list_metal_rates(): void
    {
        $res = $this->getJson('/api/v2/industry/jewelry/metal-rates', $this->authHeader());
        $res->assertOk();
    }

    public function test_upsert_metal_rate(): void
    {
        $res = $this->postJson('/api/v2/industry/jewelry/metal-rates', [
            'metal_type' => 'gold',
            'karat' => 24,
            'rate_per_gram' => 62.50,
            'buyback_rate_per_gram' => 58.00,
            'effective_date' => '2027-06-01',
        ], $this->authHeader());
        $res->assertOk();
    }

    public function test_list_product_details(): void
    {
        $res = $this->getJson('/api/v2/industry/jewelry/product-details', $this->authHeader());
        $res->assertOk();
    }

    public function test_create_product_detail(): void
    {
        $res = $this->postJson('/api/v2/industry/jewelry/product-details', [
            'product_id' => fake()->uuid(),
            'metal_type' => 'gold',
            'karat' => '22',
            'gross_weight_g' => 10.5,
            'net_weight_g' => 9.8,
            'making_charges_type' => 'per_gram',
            'making_charges_value' => 3.50,
            'certificate_number' => 'CERT-001',
        ], $this->authHeader());
        $res->assertCreated();
    }

    public function test_update_product_detail(): void
    {
        $create = $this->postJson('/api/v2/industry/jewelry/product-details', [
            'product_id' => fake()->uuid(),
            'metal_type' => 'silver',
            'karat' => '9',
            'gross_weight_g' => 25.0,
            'net_weight_g' => 24.5,
            'making_charges_type' => 'flat',
            'making_charges_value' => 15.00,
        ], $this->authHeader());
        $id = $create->json('data.id');

        $res = $this->putJson("/api/v2/industry/jewelry/product-details/{$id}", [
            'certificate_url' => 'https://example.com/cert.pdf',
        ], $this->authHeader());
        $res->assertOk();
    }

    public function test_list_buybacks(): void
    {
        $res = $this->getJson('/api/v2/industry/jewelry/buybacks', $this->authHeader());
        $res->assertOk();
    }

    public function test_create_buyback(): void
    {
        $res = $this->postJson('/api/v2/industry/jewelry/buybacks', [
            'customer_id' => fake()->uuid(),
            'metal_type' => 'gold',
            'karat' => 22,
            'weight_g' => 5.0,
            'rate_per_gram' => 58.00,
            'total_amount' => 290.00,
            'payment_method' => 'cash',
        ], $this->authHeader());
        $res->assertCreated();
    }

    // ════════════════════════════════════════════════════════
    // ELECTRONICS
    // ════════════════════════════════════════════════════════

    public function test_list_imei_records(): void
    {
        $res = $this->getJson('/api/v2/industry/electronics/imei-records', $this->authHeader());
        $res->assertOk();
    }

    public function test_create_imei_record(): void
    {
        $res = $this->postJson('/api/v2/industry/electronics/imei-records', [
            'product_id' => fake()->uuid(),
            'imei' => '123456789012345',
            'serial_number' => 'SN-001',
            'condition_grade' => 'A',
            'purchase_price' => 999.99,
        ], $this->authHeader());
        $res->assertCreated();
    }

    public function test_update_imei_record(): void
    {
        $create = $this->postJson('/api/v2/industry/electronics/imei-records', [
            'product_id' => fake()->uuid(),
            'imei' => '987654321098765',
            'condition_grade' => 'B',
        ], $this->authHeader());
        $id = $create->json('data.id');

        $res = $this->putJson("/api/v2/industry/electronics/imei-records/{$id}", [
            'sold_order_id' => fake()->uuid(),
        ], $this->authHeader());
        $res->assertOk();
    }

    public function test_list_repair_jobs(): void
    {
        $res = $this->getJson('/api/v2/industry/electronics/repair-jobs', $this->authHeader());
        $res->assertOk();
    }

    public function test_create_repair_job(): void
    {
        $res = $this->postJson('/api/v2/industry/electronics/repair-jobs', [
            'customer_id' => fake()->uuid(),
            'device_description' => 'Apple iPhone 15 Pro',
            'imei' => '111222333444555',
            'issue_description' => 'Cracked screen',
        ], $this->authHeader());
        $res->assertCreated();
    }

    public function test_update_repair_job(): void
    {
        $create = $this->postJson('/api/v2/industry/electronics/repair-jobs', [
            'customer_id' => fake()->uuid(),
            'device_description' => 'Dell XPS 15 Laptop',
            'issue_description' => 'Not charging',
        ], $this->authHeader());
        $id = $create->json('data.id');

        $res = $this->putJson("/api/v2/industry/electronics/repair-jobs/{$id}", [
            'diagnosis_notes' => 'Faulty charging port',
            'estimated_cost' => 150.00,
        ], $this->authHeader());
        $res->assertOk();
    }

    public function test_update_repair_job_status(): void
    {
        $create = $this->postJson('/api/v2/industry/electronics/repair-jobs', [
            'customer_id' => fake()->uuid(),
            'device_description' => 'Samsung Galaxy Tab S9',
            'issue_description' => 'Battery drain',
        ], $this->authHeader());
        $id = $create->json('data.id');

        $res = $this->patchJson("/api/v2/industry/electronics/repair-jobs/{$id}/status", [
            'status' => 'diagnosing',
        ], $this->authHeader());
        $res->assertOk();
    }

    public function test_list_trade_ins(): void
    {
        $res = $this->getJson('/api/v2/industry/electronics/trade-ins', $this->authHeader());
        $res->assertOk();
    }

    public function test_create_trade_in(): void
    {
        $res = $this->postJson('/api/v2/industry/electronics/trade-ins', [
            'customer_id' => fake()->uuid(),
            'device_description' => 'iPhone 13 Pro 256GB Silver',
            'imei' => '111222333444555',
            'condition_grade' => 'B',
            'assessed_value' => 450.00,
        ], $this->authHeader());
        $res->assertCreated();
    }

    // ════════════════════════════════════════════════════════
    // FLORIST
    // ════════════════════════════════════════════════════════

    public function test_list_arrangements(): void
    {
        $res = $this->getJson('/api/v2/industry/florist/arrangements', $this->authHeader());
        $res->assertOk();
    }

    public function test_create_arrangement(): void
    {
        $res = $this->postJson('/api/v2/industry/florist/arrangements', [
            'name' => 'Wedding Bouquet',
            'occasion' => 'wedding',
            'items_json' => [['product_id' => fake()->uuid(), 'quantity' => 12]],
            'total_price' => 85.00,
            'is_template' => true,
        ], $this->authHeader());
        $res->assertCreated();
    }

    public function test_update_arrangement(): void
    {
        $create = $this->postJson('/api/v2/industry/florist/arrangements', [
            'name' => 'Birthday Mix',
            'items_json' => [['product_id' => fake()->uuid(), 'quantity' => 6]],
            'total_price' => 45.00,
        ], $this->authHeader());
        $id = $create->json('data.id');

        $res = $this->putJson("/api/v2/industry/florist/arrangements/{$id}", [
            'name' => 'Birthday Deluxe Mix',
            'total_price' => 65.00,
        ], $this->authHeader());
        $res->assertOk();
    }

    public function test_delete_arrangement(): void
    {
        $create = $this->postJson('/api/v2/industry/florist/arrangements', [
            'name' => 'To Delete',
            'items_json' => [['product_id' => fake()->uuid(), 'quantity' => 3]],
            'total_price' => 20.00,
        ], $this->authHeader());
        $id = $create->json('data.id');

        $res = $this->deleteJson("/api/v2/industry/florist/arrangements/{$id}", [], $this->authHeader());
        $res->assertOk();
    }

    public function test_list_freshness_logs(): void
    {
        $res = $this->getJson('/api/v2/industry/florist/freshness-logs', $this->authHeader());
        $res->assertOk();
    }

    public function test_create_freshness_log(): void
    {
        $res = $this->postJson('/api/v2/industry/florist/freshness-logs', [
            'product_id' => fake()->uuid(),
            'received_date' => '2025-06-01',
            'expected_vase_life_days' => 7,
            'quantity' => 50,
        ], $this->authHeader());
        $res->assertCreated();
    }

    public function test_update_freshness_log_status(): void
    {
        $create = $this->postJson('/api/v2/industry/florist/freshness-logs', [
            'product_id' => fake()->uuid(),
            'received_date' => '2025-05-28',
            'expected_vase_life_days' => 5,
            'quantity' => 30,
        ], $this->authHeader());
        $id = $create->json('data.id');

        $res = $this->patchJson("/api/v2/industry/florist/freshness-logs/{$id}/status", [
            'status' => 'marked_down',
        ], $this->authHeader());
        $res->assertOk();
    }

    public function test_list_subscriptions(): void
    {
        $res = $this->getJson('/api/v2/industry/florist/subscriptions', $this->authHeader());
        $res->assertOk();
    }

    public function test_create_subscription(): void
    {
        $res = $this->postJson('/api/v2/industry/florist/subscriptions', [
            'customer_id' => fake()->uuid(),
            'arrangement_template_id' => fake()->uuid(),
            'frequency' => 'weekly',
            'delivery_day' => 'monday',
            'delivery_address' => '123 Flower St',
            'price_per_delivery' => 35.00,
            'next_delivery_date' => '2027-06-09',
        ], $this->authHeader());
        $res->assertCreated();
    }

    public function test_update_subscription(): void
    {
        $create = $this->postJson('/api/v2/industry/florist/subscriptions', [
            'customer_id' => fake()->uuid(),
            'arrangement_template_id' => fake()->uuid(),
            'frequency' => 'monthly',
            'delivery_day' => 'friday',
            'delivery_address' => '456 Garden Ave',
            'price_per_delivery' => 50.00,
            'next_delivery_date' => '2027-07-01',
        ], $this->authHeader());
        $id = $create->json('data.id');

        $res = $this->putJson("/api/v2/industry/florist/subscriptions/{$id}", [
            'price_per_delivery' => 55.00,
        ], $this->authHeader());
        $res->assertOk();
    }

    public function test_toggle_subscription(): void
    {
        $create = $this->postJson('/api/v2/industry/florist/subscriptions', [
            'customer_id' => fake()->uuid(),
            'arrangement_template_id' => fake()->uuid(),
            'frequency' => 'biweekly',
            'delivery_day' => 'wednesday',
            'delivery_address' => '789 Bloom Rd',
            'price_per_delivery' => 42.00,
            'next_delivery_date' => '2027-06-15',
        ], $this->authHeader());
        $id = $create->json('data.id');

        $res = $this->patchJson("/api/v2/industry/florist/subscriptions/{$id}/toggle", [], $this->authHeader());
        $res->assertOk();
    }

    // ════════════════════════════════════════════════════════
    // BAKERY
    // ════════════════════════════════════════════════════════

    public function test_list_recipes(): void
    {
        $res = $this->getJson('/api/v2/industry/bakery/recipes', $this->authHeader());
        $res->assertOk();
    }

    public function test_create_recipe(): void
    {
        $res = $this->postJson('/api/v2/industry/bakery/recipes', [
            'name' => 'Sourdough Bread',
            'expected_yield' => '10',
            'prep_time_minutes' => 30,
            'bake_time_minutes' => 45,
            'bake_temperature_c' => 230,
            'instructions' => 'Mix, proof, bake.',
        ], $this->authHeader());
        $res->assertCreated();
    }

    public function test_update_recipe(): void
    {
        $create = $this->postJson('/api/v2/industry/bakery/recipes', [
            'name' => 'Croissant',
            'prep_time_minutes' => 60,
            'bake_time_minutes' => 20,
            'bake_temperature_c' => 200,
        ], $this->authHeader());
        $id = $create->json('data.id');

        $res = $this->putJson("/api/v2/industry/bakery/recipes/{$id}", [
            'name' => 'Butter Croissant',
            'prep_time_minutes' => 120,
        ], $this->authHeader());
        $res->assertOk();
    }

    public function test_delete_recipe(): void
    {
        $create = $this->postJson('/api/v2/industry/bakery/recipes', [
            'name' => 'To Delete Recipe',
            'prep_time_minutes' => 10,
            'bake_time_minutes' => 15,
        ], $this->authHeader());
        $id = $create->json('data.id');

        $res = $this->deleteJson("/api/v2/industry/bakery/recipes/{$id}", [], $this->authHeader());
        $res->assertOk();
    }

    public function test_list_production_schedules(): void
    {
        $res = $this->getJson('/api/v2/industry/bakery/production-schedules', $this->authHeader());
        $res->assertOk();
    }

    public function test_create_production_schedule(): void
    {
        $recipe = $this->postJson('/api/v2/industry/bakery/recipes', [
            'name' => 'Baguette',
            'prep_time_minutes' => 20,
            'bake_time_minutes' => 25,
        ], $this->authHeader());
        $recipeId = $recipe->json('data.id');

        $res = $this->postJson('/api/v2/industry/bakery/production-schedules', [
            'recipe_id' => $recipeId,
            'schedule_date' => '2027-06-10',
            'planned_batches' => 5,
            'planned_yield' => 50,
        ], $this->authHeader());
        $res->assertCreated();
    }

    public function test_update_production_schedule(): void
    {
        $recipe = $this->postJson('/api/v2/industry/bakery/recipes', [
            'name' => 'Baguette 2',
            'prep_time_minutes' => 20,
            'bake_time_minutes' => 25,
        ], $this->authHeader());
        $recipeId = $recipe->json('data.id');

        $create = $this->postJson('/api/v2/industry/bakery/production-schedules', [
            'recipe_id' => $recipeId,
            'schedule_date' => '2027-06-11',
            'planned_batches' => 3,
        ], $this->authHeader());
        $id = $create->json('data.id');

        $res = $this->putJson("/api/v2/industry/bakery/production-schedules/{$id}", [
            'actual_batches' => 3,
            'actual_yield' => 28,
        ], $this->authHeader());
        $res->assertOk();
    }

    public function test_update_production_schedule_status(): void
    {
        $recipe = $this->postJson('/api/v2/industry/bakery/recipes', [
            'name' => 'Baguette 3',
            'prep_time_minutes' => 20,
            'bake_time_minutes' => 25,
        ], $this->authHeader());
        $recipeId = $recipe->json('data.id');

        $create = $this->postJson('/api/v2/industry/bakery/production-schedules', [
            'recipe_id' => $recipeId,
            'schedule_date' => '2027-06-12',
            'planned_batches' => 4,
        ], $this->authHeader());
        $id = $create->json('data.id');

        $res = $this->patchJson("/api/v2/industry/bakery/production-schedules/{$id}/status", [
            'status' => 'in_progress',
        ], $this->authHeader());
        $res->assertOk();
    }

    public function test_list_custom_cake_orders(): void
    {
        $res = $this->getJson('/api/v2/industry/bakery/cake-orders', $this->authHeader());
        $res->assertOk();
    }

    public function test_create_custom_cake_order(): void
    {
        $res = $this->postJson('/api/v2/industry/bakery/cake-orders', [
            'customer_id' => fake()->uuid(),
            'description' => 'Two-tier chocolate cake with roses',
            'size' => '10 inch',
            'flavor' => 'Chocolate',
            'delivery_date' => '2027-06-20',
            'price' => 120.00,
            'deposit_paid' => 50.00,
        ], $this->authHeader());
        $res->assertCreated();
    }

    public function test_update_custom_cake_order(): void
    {
        $create = $this->postJson('/api/v2/industry/bakery/cake-orders', [
            'customer_id' => fake()->uuid(),
            'description' => 'Vanilla birthday cake',
            'size' => '8 inch',
            'flavor' => 'Vanilla',
            'delivery_date' => '2027-06-25',
            'price' => 80.00,
        ], $this->authHeader());
        $id = $create->json('data.id');

        $res = $this->putJson("/api/v2/industry/bakery/cake-orders/{$id}", [
            'deposit_paid' => 40.00,
        ], $this->authHeader());
        $res->assertOk();
    }

    public function test_update_custom_cake_order_status(): void
    {
        $create = $this->postJson('/api/v2/industry/bakery/cake-orders', [
            'customer_id' => fake()->uuid(),
            'description' => 'Red velvet cake',
            'size' => '12 inch',
            'flavor' => 'Red Velvet',
            'delivery_date' => '2027-07-01',
            'price' => 150.00,
        ], $this->authHeader());
        $id = $create->json('data.id');

        $res = $this->patchJson("/api/v2/industry/bakery/cake-orders/{$id}/status", [
            'status' => 'in_production',
        ], $this->authHeader());
        $res->assertOk();
    }

    // ════════════════════════════════════════════════════════
    // RESTAURANT
    // ════════════════════════════════════════════════════════

    public function test_list_tables(): void
    {
        $res = $this->getJson('/api/v2/industry/restaurant/tables', $this->authHeader());
        $res->assertOk();
    }

    public function test_create_table(): void
    {
        $res = $this->postJson('/api/v2/industry/restaurant/tables', [
            'table_number' => 'T1',
            'display_name' => 'Window Table 1',
            'seats' => 4,
            'zone' => 'Main Hall',
        ], $this->authHeader());
        $res->assertCreated();
    }

    public function test_update_table(): void
    {
        $create = $this->postJson('/api/v2/industry/restaurant/tables', [
            'table_number' => 'T2',
            'seats' => 2,
        ], $this->authHeader());
        $id = $create->json('data.id');

        $res = $this->putJson("/api/v2/industry/restaurant/tables/{$id}", [
            'display_name' => 'Corner Booth',
            'seats' => 6,
        ], $this->authHeader());
        $res->assertOk();
    }

    public function test_update_table_status(): void
    {
        $create = $this->postJson('/api/v2/industry/restaurant/tables', [
            'table_number' => 'T3',
            'seats' => 4,
        ], $this->authHeader());
        $id = $create->json('data.id');

        $res = $this->patchJson("/api/v2/industry/restaurant/tables/{$id}/status", [
            'status' => 'occupied',
        ], $this->authHeader());
        $res->assertOk();
    }

    public function test_list_kitchen_tickets(): void
    {
        $res = $this->getJson('/api/v2/industry/restaurant/kitchen-tickets', $this->authHeader());
        $res->assertOk();
    }

    public function test_create_kitchen_ticket(): void
    {
        $res = $this->postJson('/api/v2/industry/restaurant/kitchen-tickets', [
            'ticket_number' => 'KT-001',
            'items_json' => [['name' => 'Burger', 'qty' => 2]],
            'station' => 'Grill',
        ], $this->authHeader());
        $res->assertCreated();
    }

    public function test_update_kitchen_ticket_status(): void
    {
        $create = $this->postJson('/api/v2/industry/restaurant/kitchen-tickets', [
            'ticket_number' => 'KT-002',
            'items_json' => [['name' => 'Pasta', 'qty' => 1]],
            'station' => 'Hot',
        ], $this->authHeader());
        $id = $create->json('data.id');

        $res = $this->patchJson("/api/v2/industry/restaurant/kitchen-tickets/{$id}/status", [
            'status' => 'preparing',
        ], $this->authHeader());
        $res->assertOk();
    }

    public function test_list_reservations(): void
    {
        $res = $this->getJson('/api/v2/industry/restaurant/reservations', $this->authHeader());
        $res->assertOk();
    }

    public function test_create_reservation(): void
    {
        $res = $this->postJson('/api/v2/industry/restaurant/reservations', [
            'table_id' => fake()->uuid(),
            'customer_name' => 'John Doe',
            'customer_phone' => '+96812345678',
            'party_size' => 4,
            'reservation_date' => '2027-06-15',
            'reservation_time' => '19:30',
            'duration_minutes' => 90,
        ], $this->authHeader());
        $res->assertCreated();
    }

    public function test_update_reservation(): void
    {
        $create = $this->postJson('/api/v2/industry/restaurant/reservations', [
            'table_id' => fake()->uuid(),
            'customer_name' => 'Jane Smith',
            'customer_phone' => '+96812345679',
            'party_size' => 2,
            'reservation_date' => '2027-06-16',
            'reservation_time' => '20:00',
        ], $this->authHeader());
        $id = $create->json('data.id');

        $res = $this->putJson("/api/v2/industry/restaurant/reservations/{$id}", [
            'party_size' => 3,
            'notes' => 'High chair needed',
        ], $this->authHeader());
        $res->assertOk();
    }

    public function test_update_reservation_status(): void
    {
        $create = $this->postJson('/api/v2/industry/restaurant/reservations', [
            'table_id' => fake()->uuid(),
            'customer_name' => 'Bob Johnson',
            'customer_phone' => '+96812345680',
            'party_size' => 6,
            'reservation_date' => '2027-06-17',
            'reservation_time' => '18:00',
        ], $this->authHeader());
        $id = $create->json('data.id');

        $res = $this->patchJson("/api/v2/industry/restaurant/reservations/{$id}/status", [
            'status' => 'seated',
        ], $this->authHeader());
        $res->assertOk();
    }

    public function test_list_open_tabs(): void
    {
        $res = $this->getJson('/api/v2/industry/restaurant/tabs', $this->authHeader());
        $res->assertOk();
    }

    public function test_open_tab(): void
    {
        $res = $this->postJson('/api/v2/industry/restaurant/tabs', [
            'customer_name' => 'VIP Guest',
        ], $this->authHeader());
        $res->assertCreated();
    }

    public function test_close_tab(): void
    {
        $create = $this->postJson('/api/v2/industry/restaurant/tabs', [
            'customer_name' => 'Bar Customer',
        ], $this->authHeader());
        $id = $create->json('data.id');

        $res = $this->patchJson("/api/v2/industry/restaurant/tabs/{$id}/close", [], $this->authHeader());
        $res->assertOk();
    }
}
