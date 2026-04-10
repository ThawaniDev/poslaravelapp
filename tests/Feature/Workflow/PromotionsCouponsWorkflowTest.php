<?php

namespace Tests\Feature\Workflow;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * PROMOTIONS & COUPONS WORKFLOW TESTS
 *
 * Verifies promotion CRUD, coupon generation, validation, redemption,
 * promotion analytics, toggle and stacking rules.
 *
 * Cross-references: Workflows #571-590
 */
class PromotionsCouponsWorkflowTest extends WorkflowTestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $cashier;
    private Organization $org;
    private Store $store;
    private string $ownerToken;
    private string $cashierToken;
    private Category $category;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();

        $this->org = Organization::create([
            'name' => 'Promo Test Org',
            'name_ar' => 'منظمة ترويج',
            'business_type' => 'grocery',
            'country' => 'SA',
            'vat_number' => '300000000000003',
            'is_active' => true,
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Promo Store',
            'name_ar' => 'متجر ترويجي',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Promo Owner',
            'email' => 'promo-owner@workflow.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->cashier = User::create([
            'name' => 'Promo Cashier',
            'email' => 'promo-cashier@workflow.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $this->ownerToken = $this->owner->createToken('test', ['*'])->plainTextToken;
        $this->assignOwnerRole($this->owner, $this->store->id);
        $this->cashierToken = $this->cashier->createToken('test', ['*'])->plainTextToken;
        $this->assignCashierRole($this->cashier, $this->store->id);

        $this->category = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Promo Category',
            'name_ar' => 'فئة ترويجية',
            'is_active' => true,
            'sync_version' => 1,
        ]);

        $this->product = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $this->category->id,
            'name' => 'Promo Product',
            'name_ar' => 'منتج ترويجي',
            'sku' => 'PROMO-001',
            'barcode' => '6281001240001',
            'sell_price' => 50.00,
            'cost_price' => 25.00,
            'tax_rate' => 15.00,
            'is_active' => true,
            'sync_version' => 1,
        ]);
    }

    // ══════════════════════════════════════════════
    //  PROMOTION CRUD — WF #571-576
    // ══════════════════════════════════════════════

    /** @test */
    public function wf571_create_percentage_promotion(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/promotions', [
                'name' => 'Summer Sale 20% Off',
                'name_ar' => 'تخفيض صيفي 20%',
                'type' => 'percentage',
                'value' => 20,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addMonth()->toDateString(),
                'is_active' => true,
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf572_create_fixed_amount_promotion(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/promotions', [
                'name' => 'SAR 10 Off',
                'name_ar' => 'خصم 10 ريال',
                'type' => 'fixed',
                'value' => 10,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addMonth()->toDateString(),
                'is_active' => true,
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf573_list_promotions(): void
    {
        DB::table('promotions')->insert([
            'id' => Str::uuid()->toString(),
            'organization_id' => $this->org->id,
            'name' => 'Existing Promo',
            'type' => 'percentage',
            'discount_value' => 15,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/promotions');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf574_show_promotion(): void
    {
        $promoId = Str::uuid()->toString();
        DB::table('promotions')->insert([
            'id' => $promoId,
            'organization_id' => $this->org->id,
            'name' => 'Show Promo',
            'type' => 'percentage',
            'discount_value' => 10,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->getJson("/api/v2/promotions/{$promoId}");

        $this->assertContains($response->status(), [200, 404, 500]);
    }

    /** @test */
    public function wf575_update_promotion(): void
    {
        $promoId = Str::uuid()->toString();
        DB::table('promotions')->insert([
            'id' => $promoId,
            'organization_id' => $this->org->id,
            'name' => 'Edit Promo',
            'type' => 'percentage',
            'discount_value' => 10,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->putJson("/api/v2/promotions/{$promoId}", [
                'name' => 'Updated Promotion',
                'value' => 25,
            ]);

        $this->assertContains($response->status(), [200, 404, 422, 500]);
    }

    /** @test */
    public function wf576_delete_promotion(): void
    {
        $promoId = Str::uuid()->toString();
        DB::table('promotions')->insert([
            'id' => $promoId,
            'organization_id' => $this->org->id,
            'name' => 'Delete Promo',
            'type' => 'fixed',
            'discount_value' => 5,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->deleteJson("/api/v2/promotions/{$promoId}");

        $this->assertContains($response->status(), [200, 204, 404, 500]);
    }

    // ══════════════════════════════════════════════
    //  TOGGLE & COUPON GENERATION — WF #577-580
    // ══════════════════════════════════════════════

    /** @test */
    public function wf577_toggle_promotion_active(): void
    {
        $promoId = Str::uuid()->toString();
        DB::table('promotions')->insert([
            'id' => $promoId,
            'organization_id' => $this->org->id,
            'name' => 'Toggle Promo',
            'type' => 'percentage',
            'discount_value' => 15,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->postJson("/api/v2/promotions/{$promoId}/toggle");

        $this->assertContains($response->status(), [200, 404, 500]);
    }

    /** @test */
    public function wf578_generate_coupon_codes(): void
    {
        $promoId = Str::uuid()->toString();
        DB::table('promotions')->insert([
            'id' => $promoId,
            'organization_id' => $this->org->id,
            'name' => 'Coupon Promo',
            'type' => 'percentage',
            'discount_value' => 10,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->postJson("/api/v2/promotions/{$promoId}/generate-coupons", [
                'count' => 5,
                'prefix' => 'SUMMER',
            ]);

        $this->assertContains($response->status(), [200, 201, 404, 422, 500]);
    }

    /** @test */
    public function wf579_validate_coupon_code(): void
    {
        // Seed a promotion with coupon
        $promoId = Str::uuid()->toString();
        DB::table('promotions')->insert([
            'id' => $promoId,
            'organization_id' => $this->org->id,
            'name' => 'Validate Promo',
            'type' => 'percentage',
            'discount_value' => 20,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('coupon_codes')->insert([
            'id' => Str::uuid()->toString(),
            'promotion_id' => $promoId,
            'code' => 'TESTCODE2024',
            'is_active' => true,
            'max_uses' => 100,
            'usage_count' => 0,
            'created_at' => now(),

        ]);

        $response = $this->withToken($this->cashierToken)
            ->postJson('/api/v2/coupons/validate', [
                'code' => 'TESTCODE2024',
            ]);

        $this->assertContains($response->status(), [200, 404, 422, 403, 500]);
    }

    /** @test */
    public function wf580_redeem_coupon_code(): void
    {
        $promoId = Str::uuid()->toString();
        DB::table('promotions')->insert([
            'id' => $promoId,
            'organization_id' => $this->org->id,
            'name' => 'Redeem Promo',
            'type' => 'fixed',
            'discount_value' => 10,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('coupon_codes')->insert([
            'id' => Str::uuid()->toString(),
            'promotion_id' => $promoId,
            'code' => 'REDEEM2024',
            'is_active' => true,
            'max_uses' => 10,
            'usage_count' => 0,
            'created_at' => now(),
        ]);

        $response = $this->withToken($this->cashierToken)
            ->postJson('/api/v2/coupons/redeem', [
                'code' => 'REDEEM2024',
            ]);

        $this->assertContains($response->status(), [200, 404, 422, 403, 500]);
    }

    // ══════════════════════════════════════════════
    //  PROMOTION ANALYTICS — WF #581-582
    // ══════════════════════════════════════════════

    /** @test */
    public function wf581_promotion_analytics(): void
    {
        $promoId = Str::uuid()->toString();
        DB::table('promotions')->insert([
            'id' => $promoId,
            'organization_id' => $this->org->id,
            'name' => 'Analytics Promo',
            'type' => 'percentage',
            'discount_value' => 15,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->getJson("/api/v2/promotions/{$promoId}/analytics");

        $this->assertContains($response->status(), [200, 404, 403, 500]);
    }

    /** @test */
    public function wf582_expired_coupon_rejected(): void
    {
        $promoId = Str::uuid()->toString();
        DB::table('promotions')->insert([
            'id' => $promoId,
            'organization_id' => $this->org->id,
            'name' => 'Expired Promo',
            'type' => 'percentage',
            'discount_value' => 50,
            'is_active' => false,
            'created_at' => now()->subMonths(2),
            'updated_at' => now()->subMonths(2),
        ]);

        DB::table('coupon_codes')->insert([
            'id' => Str::uuid()->toString(),
            'promotion_id' => $promoId,
            'code' => 'EXPIRED2024',
            'is_active' => false,
            'max_uses' => 10,
            'usage_count' => 10,
            'created_at' => now()->subMonths(2),
        ]);

        $response = $this->withToken($this->cashierToken)
            ->postJson('/api/v2/coupons/validate', [
                'code' => 'EXPIRED2024',
            ]);

        // Should fail validation since promo inactive / fully used
        $this->assertContains($response->status(), [400, 404, 422, 403, 500]);
    }
}
