<?php

namespace Tests\Feature\Promotion;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Promotion\Models\BundleProduct;
use App\Domain\Promotion\Models\CouponCode;
use App\Domain\Promotion\Models\Promotion;
use App\Domain\Promotion\Models\PromotionCategory;
use App\Domain\Promotion\Models\PromotionProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionAdvancedApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'AdvPromo Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);
        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Main',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);
        $this->user = User::create([
            'name' => 'Adv User',
            'email' => 'adv@promo.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;
    }

    private function makeCategory(string $name = 'Cat'): Category
    {
        return Category::create([
            'organization_id' => $this->org->id,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function makeProduct(string $name, float $price, ?string $categoryId = null): Product
    {
        return Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $categoryId,
            'name' => $name,
            'sell_price' => $price,
            'is_active' => true,
        ]);
    }

    private function makePromotion(array $overrides = []): Promotion
    {
        return Promotion::create(array_merge([
            'organization_id' => $this->org->id,
            'name' => 'Test Promo',
            'type' => 'percentage',
            'discount_value' => 10,
            'is_active' => true,
        ], $overrides));
    }

    // ─── Duplicate ──────────────────────────────────────────

    public function test_can_duplicate_promotion(): void
    {
        $promo = Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'Source Promo',
            'type' => 'percentage',
            'discount_value' => 10,
            'is_active' => true,
        ]);
        $product = $this->makeProduct('P1', 10);
        PromotionProduct::create(['promotion_id' => $promo->id, 'product_id' => $product->id]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/promotions/{$promo->id}/duplicate");

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Source Promo (Copy)')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseCount('promotions', 2);
        $copy = Promotion::where('name', 'Source Promo (Copy)')->first();
        $this->assertNotNull($copy);
        $this->assertDatabaseHas('promotion_products', [
            'promotion_id' => $copy->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_duplicate_cross_org_blocked(): void
    {
        $otherOrg = Organization::create(['name' => 'X', 'business_type' => 'grocery', 'country' => 'OM']);
        $promo = Promotion::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Foreign',
            'type' => 'percentage',
            'discount_value' => 5,
            'is_active' => true,
        ]);
        $this->withToken($this->token)
            ->postJson("/api/v2/promotions/{$promo->id}/duplicate")
            ->assertNotFound();
    }

    // ─── List Coupons ───────────────────────────────────────

    public function test_can_list_coupons_for_promotion(): void
    {
        $promo = Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'Coupon Promo',
            'type' => 'percentage',
            'discount_value' => 10,
            'is_coupon' => true,
            'is_active' => true,
        ]);
        for ($i = 0; $i < 3; $i++) {
            CouponCode::create([
                'promotion_id' => $promo->id,
                'code' => 'CODE' . $i,
                'max_uses' => 1,
                'usage_count' => 0,
                'is_active' => true,
            ]);
        }

        $this->withToken($this->token)
            ->getJson("/api/v2/promotions/{$promo->id}/coupons")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data.data');
    }

    public function test_list_coupons_search_filter(): void
    {
        $promo = Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'Coupon Promo',
            'type' => 'percentage',
            'discount_value' => 10,
            'is_coupon' => true,
            'is_active' => true,
        ]);
        CouponCode::create(['promotion_id' => $promo->id, 'code' => 'SUMMER10', 'max_uses' => 1, 'is_active' => true]);
        CouponCode::create(['promotion_id' => $promo->id, 'code' => 'WINTER20', 'max_uses' => 1, 'is_active' => true]);

        $this->withToken($this->token)
            ->getJson("/api/v2/promotions/{$promo->id}/coupons?search=SUMMER")
            ->assertOk()
            ->assertJsonCount(1, 'data.data');
    }

    // ─── Delete Coupon ──────────────────────────────────────

    public function test_can_delete_coupon(): void
    {
        $promo = Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'X',
            'type' => 'percentage',
            'discount_value' => 10,
            'is_coupon' => true,
            'is_active' => true,
        ]);
        $c = CouponCode::create([
            'promotion_id' => $promo->id,
            'code' => 'DELME',
            'max_uses' => 1,
            'is_active' => true,
        ]);
        $this->withToken($this->token)
            ->deleteJson("/api/v2/coupons/{$c->id}")
            ->assertOk();
        $this->assertDatabaseMissing('coupon_codes', ['id' => $c->id]);
    }

    // ─── Batch Generate ─────────────────────────────────────

    public function test_batch_generate_coupons(): void
    {
        $promo = Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'X',
            'type' => 'percentage',
            'discount_value' => 10,
            'is_coupon' => true,
            'is_active' => true,
        ]);
        $this->withToken($this->token)
            ->postJson('/api/v2/coupons/batch-generate', [
                'promotion_id' => $promo->id,
                'count' => 5,
                'prefix' => 'BATCH',
                'max_uses' => 1,
            ])
            ->assertCreated();
        $this->assertEquals(5, CouponCode::where('promotion_id', $promo->id)->count());
        $this->assertTrue(CouponCode::where('promotion_id', $promo->id)->where('code', 'like', 'BATCH%')->exists());
    }

    // ─── POS Sync ───────────────────────────────────────────

    public function test_pos_sync_returns_all_when_no_since(): void
    {
        Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'A', 'type' => 'percentage', 'discount_value' => 10, 'is_active' => true,
        ]);
        Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'B', 'type' => 'fixed_amount', 'discount_value' => 5, 'is_active' => true,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v2/pos/promotions/sync')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.promotions');
    }

    public function test_pos_sync_delta_respects_since(): void
    {
        $old = Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'Old', 'type' => 'percentage', 'discount_value' => 10, 'is_active' => true,
        ]);
        $old->updated_at = now()->subHour();
        $old->save();

        $threshold = now()->subMinutes(30)->toIso8601String();

        Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'New', 'type' => 'percentage', 'discount_value' => 15, 'is_active' => true,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v2/pos/promotions/sync?since=' . urlencode($threshold))
            ->assertOk()
            ->assertJsonCount(1, 'data.promotions')
            ->assertJsonPath('data.promotions.0.name', 'New');
    }

    public function test_pos_sync_isolates_org(): void
    {
        $otherOrg = Organization::create(['name' => 'Y', 'business_type' => 'grocery', 'country' => 'OM']);
        Promotion::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Foreign', 'type' => 'percentage', 'discount_value' => 10, 'is_active' => true,
        ]);
        Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'Mine', 'type' => 'percentage', 'discount_value' => 10, 'is_active' => true,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v2/pos/promotions/sync')
            ->assertOk()
            ->assertJsonCount(1, 'data.promotions')
            ->assertJsonPath('data.promotions.0.name', 'Mine');
    }

    // ─── Evaluate Cart ──────────────────────────────────────

    public function test_evaluate_percentage_promotion(): void
    {
        $cat = $this->makeCategory('Drinks');
        $product = $this->makeProduct('Cola', 10, $cat->id);

        $promo = Promotion::create([
            'organization_id' => $this->org->id,
            'name' => '10% off all',
            'type' => 'percentage',
            'discount_value' => 10,
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/promotions/evaluate', [
                'items' => [[
                    'product_id' => $product->id,
                    'category_id' => $cat->id,
                    'unit_price' => 10.00,
                    'quantity' => 2,
                ]],
            ])
            ->assertOk();

        $response->assertJsonPath('data.subtotal', 20);
        $response->assertJsonPath('data.total_discount', 2);
        $response->assertJsonCount(1, 'data.applied');
    }

    public function test_evaluate_fixed_amount_promotion(): void
    {
        $product = $this->makeProduct('Item', 50);
        Promotion::create([
            'organization_id' => $this->org->id,
            'name' => '5 off',
            'type' => 'fixed_amount',
            'discount_value' => 5,
            'is_active' => true,
        ]);
        $this->withToken($this->token)
            ->postJson('/api/v2/promotions/evaluate', [
                'items' => [[
                    'product_id' => $product->id,
                    'unit_price' => 50,
                    'quantity' => 1,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.total_discount', 5);
    }

    public function test_evaluate_bogo_discounts_cheapest(): void
    {
        $p1 = $this->makeProduct('A', 10);
        $p2 = $this->makeProduct('B', 20);

        // Buy 1 get 1 free (get_discount_percent = 100) on p1 & p2
        $promo = Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'BOGO',
            'type' => 'bogo',
            'buy_quantity' => 1,
            'get_quantity' => 1,
            'get_discount_percent' => 100,
            'is_active' => true,
        ]);
        PromotionProduct::create(['promotion_id' => $promo->id, 'product_id' => $p1->id]);
        PromotionProduct::create(['promotion_id' => $promo->id, 'product_id' => $p2->id]);

        $res = $this->withToken($this->token)
            ->postJson('/api/v2/promotions/evaluate', [
                'items' => [
                    ['product_id' => $p1->id, 'unit_price' => 10, 'quantity' => 1],
                    ['product_id' => $p2->id, 'unit_price' => 20, 'quantity' => 1],
                ],
            ])
            ->assertOk();

        // Cheapest (10) is free
        $res->assertJsonPath('data.total_discount', 10);
    }

    public function test_evaluate_bundle_discount(): void
    {
        $a = $this->makeProduct('Bun-A', 15);
        $b = $this->makeProduct('Bun-B', 25);

        $promo = Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'Meal Bundle',
            'type' => 'bundle',
            'bundle_price' => 30,
            'is_active' => true,
        ]);
        BundleProduct::create(['promotion_id' => $promo->id, 'product_id' => $a->id, 'quantity' => 1]);
        BundleProduct::create(['promotion_id' => $promo->id, 'product_id' => $b->id, 'quantity' => 1]);

        // Cart has the full bundle: discount = (15+25) - 30 = 10
        $this->withToken($this->token)
            ->postJson('/api/v2/promotions/evaluate', [
                'items' => [
                    ['product_id' => $a->id, 'unit_price' => 15, 'quantity' => 1],
                    ['product_id' => $b->id, 'unit_price' => 25, 'quantity' => 1],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.total_discount', 10);
    }

    public function test_evaluate_bundle_returns_no_discount_when_incomplete(): void
    {
        $a = $this->makeProduct('Bun-A', 15);
        $b = $this->makeProduct('Bun-B', 25);

        $promo = Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'Meal Bundle',
            'type' => 'bundle',
            'bundle_price' => 30,
            'is_active' => true,
        ]);
        BundleProduct::create(['promotion_id' => $promo->id, 'product_id' => $a->id, 'quantity' => 1]);
        BundleProduct::create(['promotion_id' => $promo->id, 'product_id' => $b->id, 'quantity' => 1]);

        $this->withToken($this->token)
            ->postJson('/api/v2/promotions/evaluate', [
                'items' => [
                    ['product_id' => $a->id, 'unit_price' => 15, 'quantity' => 1],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.total_discount', 0);
    }

    public function test_evaluate_respects_min_order_total(): void
    {
        $p = $this->makeProduct('P', 5);
        Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'BigOrder10',
            'type' => 'percentage',
            'discount_value' => 10,
            'min_order_total' => 100,
            'is_active' => true,
        ]);
        $this->withToken($this->token)
            ->postJson('/api/v2/promotions/evaluate', [
                'items' => [['product_id' => $p->id, 'unit_price' => 5, 'quantity' => 2]],
            ])
            ->assertOk()
            ->assertJsonPath('data.total_discount', 0);
    }

    public function test_evaluate_non_stackable_picks_single_promotion(): void
    {
        $p = $this->makeProduct('P', 100);
        // Two non-stackable promos on everything
        Promotion::create([
            'organization_id' => $this->org->id,
            'name' => '10% off',
            'type' => 'percentage',
            'discount_value' => 10,
            'is_active' => true,
            'is_stackable' => false,
        ]);
        Promotion::create([
            'organization_id' => $this->org->id,
            'name' => '5 off',
            'type' => 'fixed_amount',
            'discount_value' => 5,
            'is_active' => true,
            'is_stackable' => false,
        ]);
        $res = $this->withToken($this->token)
            ->postJson('/api/v2/promotions/evaluate', [
                'items' => [['product_id' => $p->id, 'unit_price' => 100, 'quantity' => 1]],
            ])
            ->assertOk();
        // Only 1 applied
        $res->assertJsonCount(1, 'data.applied');
    }

    public function test_evaluate_applies_coupon(): void
    {
        $p = $this->makeProduct('P', 40);
        $promo = Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'CouponPromo',
            'type' => 'percentage',
            'discount_value' => 25,
            'is_coupon' => true,
            'is_active' => true,
        ]);
        CouponCode::create([
            'promotion_id' => $promo->id,
            'code' => 'SAVE25',
            'max_uses' => 10,
            'is_active' => true,
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v2/promotions/evaluate', [
                'items' => [['product_id' => $p->id, 'unit_price' => 40, 'quantity' => 1]],
                'coupon_code' => 'SAVE25',
            ])
            ->assertOk()
            ->assertJsonPath('data.total_discount', 10);
    }

    public function test_evaluate_ignores_unknown_coupon(): void
    {
        $p = $this->makeProduct('P', 40);
        $this->withToken($this->token)
            ->postJson('/api/v2/promotions/evaluate', [
                'items' => [['product_id' => $p->id, 'unit_price' => 40, 'quantity' => 1]],
                'coupon_code' => 'BOGUS',
            ])
            ->assertOk()
            ->assertJsonPath('data.total_discount', 0);
    }

    public function test_evaluate_validates_payload(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/promotions/evaluate', [])
            ->assertStatus(422);
    }

    // ─── Usage Log Tests ──────────────────────────────────────────────────────────

    public function test_usage_log_returns_empty_for_new_promotion(): void
    {
        $promo = $this->makePromotion(['type' => 'percentage', 'discount_value' => 10]);

        $this->withToken($this->token)
            ->getJson("/api/v2/promotions/{$promo->id}/usage-log")
            ->assertOk()
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.data', []);
    }

    public function test_usage_log_requires_analytics_permission(): void
    {
        $promo = $this->makePromotion(['type' => 'percentage', 'discount_value' => 10]);

        // A user without any permissions (no token) should be unauthorized
        $this->getJson("/api/v2/promotions/{$promo->id}/usage-log")
            ->assertUnauthorized();
    }

    public function test_analytics_includes_daily_usage(): void
    {
        $promo = $this->makePromotion([
            'type' => 'percentage',
            'discount_value' => 10,
        ]);

        $this->withToken($this->token)
            ->getJson("/api/v2/promotions/{$promo->id}/analytics")
            ->assertOk()
            ->assertJsonPath('data.promotion_id', $promo->id)
            ->assertJsonStructure(['data' => ['daily_usage', 'coupon_uses', 'auto_uses']]);
    }

    public function test_analytics_daily_usage_is_array(): void
    {
        $promo = $this->makePromotion(['type' => 'percentage', 'discount_value' => 10]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/promotions/{$promo->id}/analytics")
            ->assertOk();

        $this->assertIsArray($response->json('data.daily_usage'));
    }

    public function test_usage_log_rejects_nonexistent_promotion(): void
    {
        $nonExistentId = (string) \Illuminate\Support\Str::uuid();

        $this->withToken($this->token)
            ->getJson("/api/v2/promotions/{$nonExistentId}/usage-log")
            ->assertNotFound();
    }
}
