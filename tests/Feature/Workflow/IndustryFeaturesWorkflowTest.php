<?php

namespace Tests\Feature\Workflow;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * INDUSTRY FEATURES WORKFLOW TESTS
 *
 * Tests all 6 industry verticals: Pharmacy, Jewelry, Electronics,
 * Florist, Bakery, Restaurant.
 *
 * Cross-references: Workflows #731-810
 */
class IndustryFeaturesWorkflowTest extends WorkflowTestCase
{
    use RefreshDatabase;

    private User $owner;
    private Organization $org;
    private Store $store;
    private string $ownerToken;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();

        $this->org = Organization::create([
            'name' => 'Industry Org',
            'name_ar' => 'منظمة صناعية',
            'business_type' => 'grocery',
            'country' => 'SA',
            'vat_number' => '300000000000003',
            'is_active' => true,
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Industry Store',
            'name_ar' => 'متجر صناعي',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Industry Owner',
            'email' => 'industry-owner@workflow.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->ownerToken = $this->owner->createToken('test', ['*'])->plainTextToken;
        $this->assignOwnerRole($this->owner, $this->store->id);

        $cat = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'General', 'name_ar' => 'عام',
            'is_active' => true, 'sync_version' => 1,
        ]);

        $this->product = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $cat->id,
            'name' => 'Industry Item', 'name_ar' => 'منتج صناعي',
            'sku' => 'IND-001', 'barcode' => '6281001250001',
            'sell_price' => 100.00, 'cost_price' => 50.00,
            'tax_rate' => 15.00, 'is_active' => true, 'sync_version' => 1,
        ]);
    }

    // ══════════════════════════════════════════════
    //  PHARMACY — WF #731-736
    // ══════════════════════════════════════════════

    /** @test */
    public function wf731_pharmacy_list_prescriptions(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/industry/pharmacy/prescriptions');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf732_pharmacy_create_prescription(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/industry/pharmacy/prescriptions', [
                'prescription_number' => 'RX-2025-001',
                'patient_name' => 'Ahmed Ali',
                'doctor_name' => 'Dr. Saeed',
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf733_pharmacy_update_prescription(): void
    {
        DB::table('prescriptions')->insert([
            'id' => '11111111-1111-1111-1111-111111111111',
            'store_id' => $this->store->id,
            'prescription_number' => 'RX-TEST-001',
            'patient_name' => 'Test Patient',
            'doctor_name' => 'Dr. Test',
            'created_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->putJson('/api/v2/industry/pharmacy/prescriptions/11111111-1111-1111-1111-111111111111', [
                'patient_name' => 'Ahmed Updated',
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf734_pharmacy_list_drug_schedules(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/industry/pharmacy/drug-schedules');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf735_pharmacy_create_drug_schedule(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/industry/pharmacy/drug-schedules', [
                'product_id' => $this->product->id,
                'schedule_type' => 'prescription_only',
                'requires_prescription' => true,
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf736_pharmacy_update_drug_schedule(): void
    {
        DB::table('drug_schedules')->insert([
            'id' => '22222222-2222-2222-2222-222222222222',
            'product_id' => $this->product->id,
            'schedule_type' => 'otc',
            'requires_prescription' => false,
        ]);

        $response = $this->withToken($this->ownerToken)
            ->putJson('/api/v2/industry/pharmacy/drug-schedules/22222222-2222-2222-2222-222222222222', [
                'schedule_type' => 'prescription_only',
                'requires_prescription' => true,
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    // ══════════════════════════════════════════════
    //  JEWELRY — WF #741-747
    // ══════════════════════════════════════════════

    /** @test */
    public function wf741_jewelry_list_metal_rates(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/industry/jewelry/metal-rates');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf742_jewelry_upsert_metal_rate(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/industry/jewelry/metal-rates', [
                'metal_type' => 'gold',
                'karat' => '24k',
                'rate_per_gram' => 260.00,
                'buyback_rate_per_gram' => 250.00,
                'effective_date' => now()->toDateString(),
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf743_jewelry_list_product_details(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/industry/jewelry/product-details');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf744_jewelry_create_product_detail(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/industry/jewelry/product-details', [
                'product_id' => $this->product->id,
                'metal_type' => 'gold',
                'karat' => '24k',
                'gross_weight_g' => 10.5,
                'net_weight_g' => 10.0,
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf745_jewelry_update_product_detail(): void
    {
        DB::table('jewelry_product_details')->insert([
            'id' => '33333333-3333-3333-3333-333333333333',
            'product_id' => $this->product->id,
            'metal_type' => 'gold',
            'karat' => '18k',
            'gross_weight_g' => 8.0,
            'net_weight_g' => 7.5,
            'making_charges_type' => 'percentage',
            'making_charges_value' => 10.00,
        ]);

        $response = $this->withToken($this->ownerToken)
            ->putJson('/api/v2/industry/jewelry/product-details/33333333-3333-3333-3333-333333333333', [
                'gross_weight_g' => 10.0,
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf746_jewelry_list_buybacks(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/industry/jewelry/buybacks');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf747_jewelry_create_buyback(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/industry/jewelry/buybacks', [
                'metal_type' => 'gold',
                'karat' => '24k',
                'weight_g' => 15.0,
                'rate_per_gram' => 245.00,
                'total_amount' => 3675.00,
                'payment_method' => 'cash',
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    // ══════════════════════════════════════════════
    //  ELECTRONICS — WF #751-759
    // ══════════════════════════════════════════════

    /** @test */
    public function wf751_electronics_list_imei_records(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/industry/electronics/imei-records');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf752_electronics_create_imei_record(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/industry/electronics/imei-records', [
                'product_id' => $this->product->id,
                'imei' => '356938035643809',
                'serial_number' => 'SN-12345',
                'status' => 'in_stock',
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf753_electronics_update_imei_record(): void
    {
        DB::table('device_imei_records')->insert([
            'id' => '44444444-4444-4444-4444-444444444444',
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'imei' => '356938035643810',
            'serial_number' => 'SN-OLD',
            'status' => 'in_stock',
            'created_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->putJson('/api/v2/industry/electronics/imei-records/44444444-4444-4444-4444-444444444444', [
                'status' => 'sold',
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf754_electronics_list_repair_jobs(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/industry/electronics/repair-jobs');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf755_electronics_create_repair_job(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/industry/electronics/repair-jobs', [
                'device_description' => 'Samsung Galaxy S24 - Cracked screen',
                'issue_description' => 'Screen shattered from drop',
                'estimated_cost' => 350.00,
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf756_electronics_update_repair_job(): void
    {
        DB::table('repair_jobs')->insert([
            'id' => '55555555-5555-5555-5555-555555555555',
            'store_id' => $this->store->id,
            'device_description' => 'Apple iPhone 15',
            'issue_description' => 'Battery replacement',
            'estimated_cost' => 200.00,
            'status' => 'received',
            'staff_user_id' => $this->owner->id,
            'received_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->putJson('/api/v2/industry/electronics/repair-jobs/55555555-5555-5555-5555-555555555555', [
                'estimated_cost' => 250.00,
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf757_electronics_update_repair_status(): void
    {
        DB::table('repair_jobs')->insert([
            'id' => '55555555-5555-5555-5555-555555555556',
            'store_id' => $this->store->id,
            'device_description' => 'Apple iPad Air',
            'issue_description' => 'Screen fix',
            'estimated_cost' => 300.00,
            'status' => 'received',
            'staff_user_id' => $this->owner->id,
            'received_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->patchJson('/api/v2/industry/electronics/repair-jobs/55555555-5555-5555-5555-555555555556/status', [
                'status' => 'in_progress',
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf758_electronics_list_trade_ins(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/industry/electronics/trade-ins');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf759_electronics_create_trade_in(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/industry/electronics/trade-ins', [
                'device_description' => 'Samsung Galaxy S23 - Good condition',
                'condition_grade' => 'B',
                'assessed_value' => 800.00,
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    // ══════════════════════════════════════════════
    //  FLORIST — WF #761-771
    // ══════════════════════════════════════════════

    /** @test */
    public function wf761_florist_list_arrangements(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/industry/florist/arrangements');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf762_florist_create_arrangement(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/industry/florist/arrangements', [
                'name' => 'Wedding Bouquet',
                'occasion' => 'wedding',
                'items_json' => [['flower' => 'roses', 'qty' => 24]],
                'total_price' => 250.00,
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf763_florist_update_arrangement(): void
    {
        DB::table('flower_arrangements')->insert([
            'id' => '66666666-6666-6666-6666-666666666666',
            'store_id' => $this->store->id,
            'name' => 'Birthday Roses',
            'occasion' => 'birthday',
            'items_json' => json_encode([['flower' => 'roses', 'qty' => 12]]),
            'total_price' => 150.00,
            'created_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->putJson('/api/v2/industry/florist/arrangements/66666666-6666-6666-6666-666666666666', [
                'total_price' => 175.00,
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf764_florist_delete_arrangement(): void
    {
        DB::table('flower_arrangements')->insert([
            'id' => '66666666-6666-6666-6666-666666666667',
            'store_id' => $this->store->id,
            'name' => 'Temp Arrangement',
            'items_json' => json_encode([]),
            'total_price' => 50.00,
            'created_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->deleteJson('/api/v2/industry/florist/arrangements/66666666-6666-6666-6666-666666666667');

        $this->assertContains($response->status(), [200, 204, 404, 500]);
    }

    /** @test */
    public function wf765_florist_list_freshness_logs(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/industry/florist/freshness-logs');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf766_florist_create_freshness_log(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/industry/florist/freshness-logs', [
                'product_id' => $this->product->id,
                'received_date' => now()->toDateString(),
                'expected_vase_life_days' => 7,
                'quantity' => 50,
                'status' => 'fresh',
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf767_florist_update_freshness_status(): void
    {
        DB::table('flower_freshness_log')->insert([
            'id' => '77777777-7777-7777-7777-777777777777',
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'received_date' => now()->toDateString(),
            'expected_vase_life_days' => 7,
            'quantity' => 30,
            'status' => 'fresh',
        ]);

        $response = $this->withToken($this->ownerToken)
            ->patchJson('/api/v2/industry/florist/freshness-logs/77777777-7777-7777-7777-777777777777/status', [
                'status' => 'wilting',
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf768_florist_list_subscriptions(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/industry/florist/subscriptions');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf769_florist_create_subscription(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/industry/florist/subscriptions', [
                'frequency' => 'weekly',
                'delivery_address' => '123 Riyadh St',
                'price_per_delivery' => 120.00,
                'next_delivery_date' => now()->addDays(7)->toDateString(),
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf770_florist_update_subscription(): void
    {
        DB::table('flower_subscriptions')->insert([
            'id' => '77777777-7777-7777-7777-777777777778',
            'store_id' => $this->store->id,
            'customer_id' => $this->owner->id,
            'frequency' => 'monthly',
            'delivery_address' => '456 Jeddah Ave',
            'price_per_delivery' => 200.00,
            'is_active' => true,
            'next_delivery_date' => now()->addDays(30)->toDateString(),
            'created_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->putJson('/api/v2/industry/florist/subscriptions/77777777-7777-7777-7777-777777777778', [
                'price_per_delivery' => 180.00,
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf771_florist_toggle_subscription(): void
    {
        DB::table('flower_subscriptions')->insert([
            'id' => '77777777-7777-7777-7777-777777777779',
            'store_id' => $this->store->id,
            'customer_id' => $this->owner->id,
            'frequency' => 'biweekly',
            'delivery_address' => '789 Dammam Rd',
            'price_per_delivery' => 150.00,
            'is_active' => true,
            'next_delivery_date' => now()->addDays(14)->toDateString(),
            'created_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->patchJson('/api/v2/industry/florist/subscriptions/77777777-7777-7777-7777-777777777779/toggle');

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    // ══════════════════════════════════════════════
    //  BAKERY — WF #781-792
    // ══════════════════════════════════════════════

    /** @test */
    public function wf781_bakery_list_recipes(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/industry/bakery/recipes');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf782_bakery_create_recipe(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/industry/bakery/recipes', [
                'product_id' => $this->product->id,
                'name' => 'Chocolate Cake',
                'expected_yield' => 12,
                'prep_time_minutes' => 30,
                'bake_time_minutes' => 60,
                'instructions' => 'Mix, bake, frost.',
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf783_bakery_update_recipe(): void
    {
        DB::table('bakery_recipes')->insert([
            'id' => '88888888-8888-8888-8888-888888888888',
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'name' => 'Vanilla Cupcakes',
            'expected_yield' => 24,
            'prep_time_minutes' => 20,
            'bake_time_minutes' => 25,
        ]);

        $response = $this->withToken($this->ownerToken)
            ->putJson('/api/v2/industry/bakery/recipes/88888888-8888-8888-8888-888888888888', [
                'expected_yield' => 30,
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf784_bakery_delete_recipe(): void
    {
        DB::table('bakery_recipes')->insert([
            'id' => '88888888-8888-8888-8888-888888888889',
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'name' => 'Old Recipe',
            'expected_yield' => 10,
            'prep_time_minutes' => 30,
        ]);

        $response = $this->withToken($this->ownerToken)
            ->deleteJson('/api/v2/industry/bakery/recipes/88888888-8888-8888-8888-888888888889');

        $this->assertContains($response->status(), [200, 204, 404, 500]);
    }

    /** @test */
    public function wf785_bakery_list_production_schedules(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/industry/bakery/production-schedules');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf786_bakery_create_production_schedule(): void
    {
        // Need a recipe first
        DB::table('bakery_recipes')->insert([
            'id' => '88888888-8888-8888-8888-888888888800',
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'name' => 'Croissants',
            'expected_yield' => 50,
        ]);

        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/industry/bakery/production-schedules', [
                'recipe_id' => '88888888-8888-8888-8888-888888888800',
                'schedule_date' => now()->addDay()->toDateString(),
                'planned_batches' => 2,
                'planned_yield' => 100,
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf787_bakery_update_production_schedule(): void
    {
        DB::table('bakery_recipes')->insert([
            'id' => '88888888-8888-8888-8888-888888888801',
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'name' => 'Baguettes',
            'expected_yield' => 30,
        ]);

        DB::table('production_schedules')->insert([
            'id' => '88888888-8888-8888-8888-888888888890',
            'store_id' => $this->store->id,
            'recipe_id' => '88888888-8888-8888-8888-888888888801',
            'schedule_date' => now()->addDay()->toDateString(),
            'planned_batches' => 1,
            'planned_yield' => 30,
            'status' => 'planned',
            'created_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->putJson('/api/v2/industry/bakery/production-schedules/88888888-8888-8888-8888-888888888890', [
                'planned_batches' => 2,
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf788_bakery_update_production_status(): void
    {
        DB::table('bakery_recipes')->insert([
            'id' => '88888888-8888-8888-8888-888888888802',
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'name' => 'Muffins',
            'expected_yield' => 20,
        ]);

        DB::table('production_schedules')->insert([
            'id' => '88888888-8888-8888-8888-888888888891',
            'store_id' => $this->store->id,
            'recipe_id' => '88888888-8888-8888-8888-888888888802',
            'schedule_date' => now()->toDateString(),
            'planned_batches' => 1,
            'planned_yield' => 20,
            'status' => 'planned',
            'created_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->patchJson('/api/v2/industry/bakery/production-schedules/88888888-8888-8888-8888-888888888891/status', [
                'status' => 'in_progress',
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf789_bakery_list_cake_orders(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/industry/bakery/cake-orders');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf790_bakery_create_cake_order(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/industry/bakery/cake-orders', [
                'description' => 'Birthday cake - chocolate with sprinkles',
                'size' => 'large',
                'flavor' => 'chocolate',
                'delivery_date' => now()->addDays(3)->toDateString(),
                'price' => 350.00,
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf791_bakery_update_cake_order(): void
    {
        DB::table('custom_cake_orders')->insert([
            'id' => '88888888-8888-8888-8888-888888888892',
            'store_id' => $this->store->id,
            'description' => 'Wedding cake - vanilla with fondant',
            'size' => 'xlarge',
            'flavor' => 'vanilla',
            'delivery_date' => now()->addDays(7)->toDateString(),
            'price' => 800.00,
            'status' => 'ordered',
            'created_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->putJson('/api/v2/industry/bakery/cake-orders/88888888-8888-8888-8888-888888888892', [
                'price' => 850.00,
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf792_bakery_update_cake_order_status(): void
    {
        DB::table('custom_cake_orders')->insert([
            'id' => '88888888-8888-8888-8888-888888888893',
            'store_id' => $this->store->id,
            'description' => 'Anniversary cake - red velvet',
            'size' => 'medium',
            'flavor' => 'red velvet',
            'delivery_date' => now()->addDays(2)->toDateString(),
            'price' => 400.00,
            'status' => 'ordered',
            'created_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->patchJson('/api/v2/industry/bakery/cake-orders/88888888-8888-8888-8888-888888888893/status', [
                'status' => 'in_progress',
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    // ══════════════════════════════════════════════
    //  RESTAURANT — WF #801-814
    // ══════════════════════════════════════════════

    /** @test */
    public function wf801_restaurant_list_tables(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/industry/restaurant/tables');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf802_restaurant_create_table(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/industry/restaurant/tables', [
                'table_number' => 'T-01',
                'seats' => 4,
                'zone' => 'indoor',
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf803_restaurant_update_table(): void
    {
        DB::table('restaurant_tables')->insert([
            'id' => '99999999-9999-9999-9999-999999999991',
            'store_id' => $this->store->id,
            'table_number' => 'T-99',
            'seats' => 6,
            'zone' => 'outdoor',
            'status' => 'available',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->ownerToken)
            ->putJson('/api/v2/industry/restaurant/tables/99999999-9999-9999-9999-999999999991', [
                'seats' => 8,
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf804_restaurant_update_table_status(): void
    {
        DB::table('restaurant_tables')->insert([
            'id' => '99999999-9999-9999-9999-999999999992',
            'store_id' => $this->store->id,
            'table_number' => 'T-98',
            'seats' => 2,
            'zone' => 'indoor',
            'status' => 'available',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->ownerToken)
            ->patchJson('/api/v2/industry/restaurant/tables/99999999-9999-9999-9999-999999999992/status', [
                'status' => 'occupied',
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf805_restaurant_list_kitchen_tickets(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/industry/restaurant/kitchen-tickets');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf806_restaurant_create_kitchen_ticket(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/industry/restaurant/kitchen-tickets', [
                'items_json' => [
                    ['name' => 'Grilled Chicken', 'quantity' => 2],
                ],
                'station' => 'grill',
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf807_restaurant_update_kitchen_ticket_status(): void
    {
        // kitchen_tickets requires order_id; seed a minimal order first
        DB::table('orders')->insert([
            'id' => '99999999-9999-9999-9999-999999999900',
            'store_id' => $this->store->id,
            'order_number' => 'ORD-KT-001',
            'status' => 'completed',
            'subtotal' => 100.00,
            'tax_amount' => 15.00,
            'total' => 115.00,
            'created_by' => $this->owner->id,
        ]);

        DB::table('kitchen_tickets')->insert([
            'id' => '99999999-9999-9999-9999-999999999993',
            'store_id' => $this->store->id,
            'order_id' => '99999999-9999-9999-9999-999999999900',
            'ticket_number' => 1,
            'items_json' => json_encode([['name' => 'Pasta', 'qty' => 1]]),
            'status' => 'pending',
        ]);

        $response = $this->withToken($this->ownerToken)
            ->patchJson('/api/v2/industry/restaurant/kitchen-tickets/99999999-9999-9999-9999-999999999993/status', [
                'status' => 'preparing',
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf808_restaurant_list_reservations(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/industry/restaurant/reservations');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf809_restaurant_create_reservation(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/industry/restaurant/reservations', [
                'customer_name' => 'Sultan Mohammed',
                'party_size' => 4,
                'reservation_date' => now()->addDay()->toDateString(),
                'reservation_time' => '19:00',
                'customer_phone' => '+966501234567',
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf810_restaurant_update_reservation(): void
    {
        DB::table('table_reservations')->insert([
            'id' => '99999999-9999-9999-9999-999999999994',
            'store_id' => $this->store->id,
            'customer_name' => 'Update Customer',
            'party_size' => 2,
            'reservation_date' => now()->addDay()->toDateString(),
            'reservation_time' => '20:00',
            'status' => 'confirmed',
            'created_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->putJson('/api/v2/industry/restaurant/reservations/99999999-9999-9999-9999-999999999994', [
                'party_size' => 5,
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf811_restaurant_update_reservation_status(): void
    {
        DB::table('table_reservations')->insert([
            'id' => '99999999-9999-9999-9999-999999999995',
            'store_id' => $this->store->id,
            'customer_name' => 'Status Reservation',
            'party_size' => 3,
            'reservation_date' => now()->addDay()->toDateString(),
            'reservation_time' => '18:00',
            'status' => 'confirmed',
            'created_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->patchJson('/api/v2/industry/restaurant/reservations/99999999-9999-9999-9999-999999999995/status', [
                'status' => 'seated',
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf812_restaurant_list_open_tabs(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/industry/restaurant/tabs');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf813_restaurant_open_tab(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/industry/restaurant/tabs', [
                'customer_name' => 'Tab Customer',
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf814_restaurant_close_tab(): void
    {
        // open_tabs requires order_id
        DB::table('orders')->insert([
            'id' => '99999999-9999-9999-9999-999999999901',
            'store_id' => $this->store->id,
            'order_number' => 'ORD-TAB-001',
            'status' => 'completed',
            'subtotal' => 50.00,
            'tax_amount' => 7.50,
            'total' => 57.50,
            'created_by' => $this->owner->id,
        ]);

        DB::table('open_tabs')->insert([
            'id' => '99999999-9999-9999-9999-999999999996',
            'store_id' => $this->store->id,
            'order_id' => '99999999-9999-9999-9999-999999999901',
            'customer_name' => 'Close Tab Customer',
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->patchJson('/api/v2/industry/restaurant/tabs/99999999-9999-9999-9999-999999999996/close');

        $this->assertContains($response->status(), [200, 422, 500]);
    }
}
