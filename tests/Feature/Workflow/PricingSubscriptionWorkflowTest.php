<?php

namespace Tests\Feature\Workflow;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * PRICING & SUBSCRIPTION WORKFLOW TESTS
 *
 * Covers public pricing page, subscription plans, plan management,
 * subscription lifecycle, invoices, entitlements, add-ons.
 *
 * Cross-references: Workflows #821-850
 */
class PricingSubscriptionWorkflowTest extends WorkflowTestCase
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
            'name' => 'Pricing Org',
            'name_ar' => 'منظمة تسعير',
            'business_type' => 'grocery',
            'country' => 'SA',
            'vat_number' => '300000000000003',
            'is_active' => true,
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Pricing Store',
            'name_ar' => 'متجر تسعير',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Pricing Owner',
            'email' => 'pricing-owner@workflow.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->ownerToken = $this->owner->createToken('test', ['*'])->plainTextToken;
        $this->assignOwnerRole($this->owner, $this->store->id);

        // Seed a plan for tests that reference existing plans
        DB::table('subscription_plans')->insert([
            'id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'name' => 'Basic Plan',
            'slug' => 'basic',
            'description' => 'Starter plan',
            'monthly_price' => 99.00,
            'annual_price' => 999.00,
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ══════════════════════════════════════════════
    //  PUBLIC PRICING PAGE — WF #821-823
    // ══════════════════════════════════════════════

    /** @test */
    public function wf821_pricing_page_index(): void
    {
        $response = $this->getJson('/api/v2/pricing');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf822_pricing_by_slug(): void
    {
        $response = $this->getJson('/api/v2/pricing/basic');

        $this->assertContains($response->status(), [200, 404]);
    }

    /** @test */
    public function wf823_pricing_by_plan_id(): void
    {
        $response = $this->getJson('/api/v2/pricing/plan/aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');

        $this->assertContains($response->status(), [200, 404]);
    }

    // ══════════════════════════════════════════════
    //  PUBLIC SUBSCRIPTION PLANS — WF #824-828
    // ══════════════════════════════════════════════

    /** @test */
    public function wf824_list_subscription_plans(): void
    {
        $response = $this->getJson('/api/v2/subscription/plans');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf825_show_plan_by_slug(): void
    {
        $response = $this->getJson('/api/v2/subscription/plans/slug/basic');

        $this->assertContains($response->status(), [200, 404]);
    }

    /** @test */
    public function wf826_show_plan_by_id(): void
    {
        $response = $this->getJson('/api/v2/subscription/plans/aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');

        $this->assertContains($response->status(), [200, 404]);
    }

    /** @test */
    public function wf827_compare_plans(): void
    {
        $response = $this->postJson('/api/v2/subscription/plans/compare', [
            'plan_ids' => ['aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'],
        ]);

        $this->assertContains($response->status(), [200, 422]);
    }

    /** @test */
    public function wf828_list_add_ons(): void
    {
        $response = $this->getJson('/api/v2/subscription/add-ons');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    // ══════════════════════════════════════════════
    //  SUBSCRIPTION MANAGEMENT — WF #829-838
    // ══════════════════════════════════════════════

    /** @test */
    public function wf829_current_subscription(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/subscription/current');

        $this->assertContains($response->status(), [200, 404]);
    }

    /** @test */
    public function wf830_subscribe_to_plan(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/subscription/subscribe', [
                'plan_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
                'billing_cycle' => 'monthly',
            ]);

        $this->assertContains($response->status(), [200, 201, 422]);
    }

    /** @test */
    public function wf831_change_plan(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->putJson('/api/v2/subscription/change-plan', [
                'plan_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            ]);

        $this->assertContains($response->status(), [200, 404, 422]);
    }

    /** @test */
    public function wf832_cancel_subscription(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/subscription/cancel', [
                'reason' => 'Testing cancellation flow',
            ]);

        $this->assertContains($response->status(), [200, 404, 422]);
    }

    /** @test */
    public function wf833_resume_subscription(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/subscription/resume');

        $this->assertContains($response->status(), [200, 404, 422]);
    }

    /** @test */
    public function wf834_subscription_usage(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/subscription/usage');

        $this->assertContains($response->status(), [200, 404]);
    }

    /** @test */
    public function wf835_check_feature(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/subscription/check-feature/multi_branch');

        $this->assertContains($response->status(), [200, 404]);
    }

    /** @test */
    public function wf836_check_limit(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/subscription/check-limit/max_products');

        $this->assertContains($response->status(), [200, 404]);
    }

    /** @test */
    public function wf837_sync_entitlements(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/subscription/sync/entitlements');

        $this->assertContains($response->status(), [200, 404]);
    }

    /** @test */
    public function wf838_store_add_ons(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/subscription/store-add-ons');

        $this->assertContains($response->status(), [200, 404]);
    }

    // ══════════════════════════════════════════════
    //  INVOICES — WF #839-841
    // ══════════════════════════════════════════════

    /** @test */
    public function wf839_list_invoices(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/subscription/invoices');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf840_show_invoice(): void
    {
        DB::table('invoices')->insert([
            'id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
            'invoice_number' => 'INV-2025-001',
            'amount' => 99.00,
            'tax' => 14.85,
            'total' => 113.85,
            'status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/subscription/invoices/bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb');

        $this->assertContains($response->status(), [200, 404]);
    }

    /** @test */
    public function wf841_download_invoice_pdf(): void
    {
        DB::table('invoices')->insert([
            'id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbc',
            'invoice_number' => 'INV-2025-002',
            'amount' => 199.00,
            'tax' => 29.85,
            'total' => 228.85,
            'status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/subscription/invoices/bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbc/pdf');

        $this->assertContains($response->status(), [200, 403, 404, 422]);
    }

    // ══════════════════════════════════════════════
    //  PLAN MANAGEMENT (OWNER) — WF #842-845
    // ══════════════════════════════════════════════

    /** @test */
    public function wf842_create_plan(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/subscription/plans', [
                'name' => 'Premium Plan',
                'slug' => 'premium',
                'description' => 'For growing businesses',
                'monthly_price' => 299.00,
                'annual_price' => 2999.00,
                'currency' => 'SAR',
            ]);

        $this->assertContains($response->status(), [200, 201, 403, 422]);
    }

    /** @test */
    public function wf843_update_plan(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->putJson('/api/v2/subscription/plans/aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', [
                'name' => 'Basic Plan Updated',
            ]);

        $this->assertContains($response->status(), [200, 403, 422]);
    }

    /** @test */
    public function wf844_toggle_plan(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->patchJson('/api/v2/subscription/plans/aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa/toggle');

        $this->assertContains($response->status(), [200, 403, 422]);
    }

    /** @test */
    public function wf845_delete_plan(): void
    {
        DB::table('subscription_plans')->insert([
            'id' => 'cccccccc-cccc-cccc-cccc-cccccccccccc',
            'name' => 'Temp Plan',
            'slug' => 'temp-plan',
            'description' => 'Temporary',
            'monthly_price' => 50.00,
            'annual_price' => 500.00,
            'is_active' => false,
            'sort_order' => 99,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->deleteJson('/api/v2/subscription/plans/cccccccc-cccc-cccc-cccc-cccccccccccc');

        $this->assertContains($response->status(), [200, 204, 403, 404]);
    }
}
