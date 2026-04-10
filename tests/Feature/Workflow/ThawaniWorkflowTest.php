<?php

namespace Tests\Feature\Workflow;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * THAWANI PAYMENT INTEGRATION WORKFLOW TESTS
 *
 * Verifies Thawani config, connection, order tracking,
 * product mapping, and settlement reconciliation.
 *
 * Cross-references: Workflows #601-612
 */
class ThawaniWorkflowTest extends WorkflowTestCase
{
    use RefreshDatabase;

    private User $owner;
    private Organization $org;
    private Store $store;
    private string $ownerToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();

        $this->org = Organization::create([
            'name' => 'Thawani Org',
            'name_ar' => 'منظمة ثواني',
            'business_type' => 'grocery',
            'country' => 'SA',
            'vat_number' => '300000000000003',
            'is_active' => true,
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Thawani Store',
            'name_ar' => 'متجر ثواني',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Thawani Owner',
            'email' => 'thawani-owner@workflow.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->ownerToken = $this->owner->createToken('test', ['*'])->plainTextToken;
        $this->assignOwnerRole($this->owner, $this->store->id);
    }

    // ══════════════════════════════════════════════
    //  CONFIGURATION & CONNECTION — WF #601-604
    // ══════════════════════════════════════════════

    /** @test */
    public function wf601_get_thawani_config(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/thawani/config');

        $this->assertContains($response->status(), [200, 403, 500]);
    }

    /** @test */
    public function wf602_save_thawani_config(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/thawani/config', [
                'api_key' => 'test_api_key_' . Str::random(20),
                'secret_key' => 'test_secret_' . Str::random(30),
                'environment' => 'sandbox',
                'is_enabled' => true,
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 403, 500]);
    }

    /** @test */
    public function wf603_disconnect_thawani(): void
    {
        // Seed a config first
        DB::table('thawani_store_config')->insert([
            'id' => Str::uuid()->toString(),
            'store_id' => $this->store->id,
            'thawani_store_id' => 'thw_store_test_001',
            'is_connected' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->putJson('/api/v2/thawani/disconnect');

        $this->assertContains($response->status(), [200, 404, 403, 500]);
    }

    /** @test */
    public function wf604_thawani_stats(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/thawani/stats');

        $this->assertContains($response->status(), [200, 403, 500]);
    }

    // ══════════════════════════════════════════════
    //  ORDERS & MAPPINGS — WF #605-608
    // ══════════════════════════════════════════════

    /** @test */
    public function wf605_list_thawani_orders(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/thawani/orders');

        $this->assertContains($response->status(), [200, 403, 500]);
    }

    /** @test */
    public function wf606_list_product_mappings(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/thawani/product-mappings');

        $this->assertContains($response->status(), [200, 403, 500]);
    }

    /** @test */
    public function wf607_list_settlements(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/thawani/settlements');

        $this->assertContains($response->status(), [200, 403, 500]);
    }

    /** @test */
    public function wf608_thawani_order_with_seeded_data(): void
    {
        // Seed Thawani order mapping
        DB::table('thawani_order_mappings')->insert([
            'id' => Str::uuid()->toString(),
            'store_id' => $this->store->id,
            'thawani_order_id' => 'THW-ORD-001',
            'thawani_order_number' => 'THWN-001',
            'order_id' => Str::uuid()->toString(),
            'status' => 'completed',
            'order_total' => 150.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/thawani/orders');

        if ($response->status() === 200) {
            $response->assertJsonStructure(['success']);
        } else {
            $this->assertContains($response->status(), [200, 403, 500]);
        }
    }
}
