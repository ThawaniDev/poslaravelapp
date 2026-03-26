<?php

namespace Tests\Feature\Promotion;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Customer\Models\CustomerGroup;
use App\Domain\Order\Models\Order;
use App\Domain\Promotion\Models\CouponCode;
use App\Domain\Promotion\Models\Promotion;
use App\Domain\Promotion\Models\PromotionUsageLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;

    // Second org for cross-org isolation tests
    private Organization $otherOrg;
    private User $otherUser;
    private string $otherToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Promo Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Main Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Promo User',
            'email' => 'promo@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;

        // Second org
        $this->otherOrg = Organization::create([
            'name' => 'Other Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);
        $otherStore = Store::create([
            'organization_id' => $this->otherOrg->id,
            'name' => 'Other Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);
        $this->otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other@promo.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $otherStore->id,
            'organization_id' => $this->otherOrg->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $this->otherToken = $this->otherUser->createToken('test', ['*'])->plainTextToken;
    }

    // ─── Auth ────────────────────────────────────────────────

    public function test_requires_auth(): void
    {
        $this->getJson('/api/v2/promotions')->assertUnauthorized();
        $this->postJson('/api/v2/promotions')->assertUnauthorized();
    }

    // ─── List ────────────────────────────────────────────────

    public function test_can_list_promotions(): void
    {
        Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'Summer Sale',
            'type' => 'percentage',
            'discount_value' => 15,
            'is_active' => true,
        ]);
        Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'Winter Bundle',
            'type' => 'bundle',
            'bundle_price' => 99.99,
            'is_active' => false,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/promotions');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data.data'));
    }

    public function test_list_filters_by_active_status(): void
    {
        Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'Active Promo',
            'type' => 'percentage',
            'discount_value' => 10,
            'is_active' => true,
        ]);
        Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'Inactive Promo',
            'type' => 'fixed_amount',
            'discount_value' => 5,
            'is_active' => false,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/promotions?is_active=true');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('Active Promo', $response->json('data.data.0.name'));
    }

    public function test_list_filters_by_type(): void
    {
        Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'Percent Off',
            'type' => 'percentage',
            'discount_value' => 10,
        ]);
        Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'BOGO Deal',
            'type' => 'bogo',
            'buy_quantity' => 2,
            'get_quantity' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/promotions?type=bogo');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('BOGO Deal', $response->json('data.data.0.name'));
    }

    public function test_list_filters_by_coupon_flag(): void
    {
        Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'Auto Promo',
            'type' => 'percentage',
            'discount_value' => 10,
            'is_coupon' => false,
        ]);
        Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'Coupon Promo',
            'type' => 'fixed_amount',
            'discount_value' => 5,
            'is_coupon' => true,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/promotions?is_coupon=true');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('Coupon Promo', $response->json('data.data.0.name'));
    }

    public function test_list_search(): void
    {
        Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'Summer Sale',
            'type' => 'percentage',
            'discount_value' => 15,
        ]);
        Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'Winter Bundle',
            'type' => 'bundle',
            'bundle_price' => 50,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/promotions?search=summer');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_list_only_shows_own_org(): void
    {
        Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'My Promo',
            'type' => 'percentage',
            'discount_value' => 10,
        ]);
        Promotion::create([
            'organization_id' => $this->otherOrg->id,
            'name' => 'Their Promo',
            'type' => 'percentage',
            'discount_value' => 20,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/promotions');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('My Promo', $response->json('data.data.0.name'));
    }

    // ─── Create ──────────────────────────────────────────────

    public function test_can_create_percentage_promotion(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/promotions', [
                'name' => 'Summer 20% Off',
                'description' => 'Summer discount',
                'type' => 'percentage',
                'discount_value' => 20,
                'valid_from' => '2025-06-01',
                'valid_to' => '2025-09-01',
                'max_uses' => 100,
                'max_uses_per_customer' => 2,
                'is_active' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Summer 20% Off')
            ->assertJsonPath('data.type', 'percentage')
            ->assertJsonPath('data.is_active', true);

        $this->assertEquals(20, $response->json('data.discount_value'));

        $this->assertDatabaseHas('promotions', [
            'name' => 'Summer 20% Off',
            'organization_id' => $this->org->id,
        ]);
    }

    public function test_can_create_bogo_promotion(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/promotions', [
                'name' => 'Buy 2 Get 1 Free',
                'type' => 'bogo',
                'buy_quantity' => 2,
                'get_quantity' => 1,
                'get_discount_percent' => 100,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'bogo')
            ->assertJsonPath('data.buy_quantity', 2)
            ->assertJsonPath('data.get_quantity', 1);

        $this->assertEquals(100, $response->json('data.get_discount_percent'));
    }

    public function test_can_create_bundle_promotion(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/promotions', [
                'name' => 'Phone + Case Bundle',
                'type' => 'bundle',
                'bundle_price' => 199.99,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'bundle')
            ->assertJsonPath('data.bundle_price', 199.99);
    }

    public function test_can_create_coupon_promotion(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/promotions', [
                'name' => 'Welcome Coupon',
                'type' => 'fixed_amount',
                'discount_value' => 5,
                'is_coupon' => true,
                'max_uses' => 50,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.is_coupon', true);

        // Should auto-generate a coupon code
        $promoId = $response->json('data.id');
        $this->assertDatabaseHas('coupon_codes', ['promotion_id' => $promoId]);
    }

    public function test_can_create_happy_hour_promotion(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/promotions', [
                'name' => 'Happy Hour',
                'type' => 'happy_hour',
                'discount_value' => 25,
                'active_days' => ['monday', 'wednesday', 'friday'],
                'active_time_from' => '14:00',
                'active_time_to' => '16:00',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'happy_hour')
            ->assertJsonPath('data.active_days', ['monday', 'wednesday', 'friday']);
    }

    public function test_create_validation_requires_name_and_type(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/promotions', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'type']);
    }

    public function test_create_rejects_invalid_type(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/promotions', [
                'name' => 'Bad Type',
                'type' => 'invalid_type',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_create_rejects_valid_to_before_valid_from(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/promotions', [
                'name' => 'Bad Dates',
                'type' => 'percentage',
                'discount_value' => 10,
                'valid_from' => '2025-12-01',
                'valid_to' => '2025-01-01',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['valid_to']);
    }

    public function test_create_with_product_ids(): void
    {
        $cat = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Electronics',
        ]);
        $product = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $cat->id,
            'name' => 'Widget',
            'sku' => 'WDG1',
            'sell_price' => 10,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/promotions', [
                'name' => 'Product Promo',
                'type' => 'percentage',
                'discount_value' => 15,
                'product_ids' => [$product->id],
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('promotion_products', [
            'promotion_id' => $response->json('data.id'),
            'product_id' => $product->id,
        ]);
    }

    public function test_create_with_category_ids(): void
    {
        $cat = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Food',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/promotions', [
                'name' => 'Category Promo',
                'type' => 'percentage',
                'discount_value' => 10,
                'category_ids' => [$cat->id],
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('promotion_categories', [
            'promotion_id' => $response->json('data.id'),
            'category_id' => $cat->id,
        ]);
    }

    public function test_create_with_customer_group_ids(): void
    {
        $group = CustomerGroup::create([
            'organization_id' => $this->org->id,
            'name' => 'VIP',
            'discount_percent' => 10,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/promotions', [
                'name' => 'VIP Only',
                'type' => 'percentage',
                'discount_value' => 20,
                'customer_group_ids' => [$group->id],
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('promotion_customer_groups', [
            'promotion_id' => $response->json('data.id'),
            'customer_group_id' => $group->id,
        ]);
    }

    // ─── Show ────────────────────────────────────────────────

    public function test_can_show_promotion(): void
    {
        $promo = Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'Detail Promo',
            'type' => 'percentage',
            'discount_value' => 10,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/promotions/{$promo->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Detail Promo')
            ->assertJsonPath('data.type', 'percentage');
    }

    public function test_cannot_show_other_org_promotion(): void
    {
        $promo = Promotion::create([
            'organization_id' => $this->otherOrg->id,
            'name' => 'Secret',
            'type' => 'percentage',
            'discount_value' => 10,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/promotions/{$promo->id}");

        $response->assertNotFound();
    }

    // ─── Update ──────────────────────────────────────────────

    public function test_can_update_promotion(): void
    {
        $promo = Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'Old Name',
            'type' => 'percentage',
            'discount_value' => 10,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/promotions/{$promo->id}", [
                'name' => 'New Name',
                'discount_value' => 25,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertEquals(25, $response->json('data.discount_value'));
    }

    public function test_update_increments_sync_version(): void
    {
        $promo = Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'Sync Test',
            'type' => 'percentage',
            'discount_value' => 10,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/promotions/{$promo->id}", ['name' => 'Updated']);

        $response->assertOk()
            ->assertJsonPath('data.sync_version', 2);
    }

    public function test_cannot_update_other_org_promotion(): void
    {
        $promo = Promotion::create([
            'organization_id' => $this->otherOrg->id,
            'name' => 'Theirs',
            'type' => 'percentage',
            'discount_value' => 10,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/promotions/{$promo->id}", ['name' => 'Hacked']);

        $response->assertNotFound();
    }

    // ─── Delete ──────────────────────────────────────────────

    public function test_can_delete_promotion(): void
    {
        $promo = Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'Delete Me',
            'type' => 'percentage',
            'discount_value' => 10,
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/promotions/{$promo->id}");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('promotions', ['id' => $promo->id]);
    }

    public function test_cannot_delete_other_org_promotion(): void
    {
        $promo = Promotion::create([
            'organization_id' => $this->otherOrg->id,
            'name' => 'Not Yours',
            'type' => 'percentage',
            'discount_value' => 10,
        ]);

        $this->withToken($this->token)
            ->deleteJson("/api/v2/promotions/{$promo->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('promotions', ['id' => $promo->id]);
    }

    // ─── Toggle ──────────────────────────────────────────────

    public function test_can_toggle_promotion_active(): void
    {
        $promo = Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'Toggle Me',
            'type' => 'percentage',
            'discount_value' => 10,
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/promotions/{$promo->id}/toggle");

        $response->assertOk()
            ->assertJsonPath('data.is_active', false);

        // Toggle back
        $response2 = $this->withToken($this->token)
            ->postJson("/api/v2/promotions/{$promo->id}/toggle");

        $response2->assertOk()
            ->assertJsonPath('data.is_active', true);
    }

    // ─── Coupon Validation ──────────────────────────────────

    public function test_can_validate_coupon(): void
    {
        $promo = Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'Coupon Deal',
            'type' => 'fixed_amount',
            'discount_value' => 5,
            'is_coupon' => true,
            'is_active' => true,
        ]);

        CouponCode::create([
            'promotion_id' => $promo->id,
            'code' => 'SAVE5',
            'max_uses' => 100,
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/coupons/validate', [
                'code' => 'SAVE5',
                'order_total' => 50,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.promotion_name', 'Coupon Deal');

        $this->assertEquals(5, $response->json('data.discount_amount'));
    }

    public function test_validate_returns_error_for_unknown_code(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/coupons/validate', [
                'code' => 'DOESNOTEXIST',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.error', 'coupon_not_found');
    }

    public function test_validate_returns_error_for_inactive_coupon(): void
    {
        $promo = Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'Dead Coupon',
            'type' => 'percentage',
            'discount_value' => 10,
            'is_coupon' => true,
            'is_active' => true,
        ]);

        CouponCode::create([
            'promotion_id' => $promo->id,
            'code' => 'DEAD10',
            'max_uses' => 1,
            'is_active' => false,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/coupons/validate', ['code' => 'DEAD10']);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.error', 'coupon_inactive');
    }

    public function test_validate_returns_error_for_expired_promotion(): void
    {
        $promo = Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'Expired',
            'type' => 'percentage',
            'discount_value' => 10,
            'is_coupon' => true,
            'is_active' => true,
            'valid_to' => now()->subDay(),
        ]);

        CouponCode::create([
            'promotion_id' => $promo->id,
            'code' => 'EXPIRED10',
            'max_uses' => 100,
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/coupons/validate', ['code' => 'EXPIRED10']);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.error', 'expired');
    }

    public function test_validate_returns_error_when_coupon_exhausted(): void
    {
        $promo = Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'Used Up',
            'type' => 'percentage',
            'discount_value' => 10,
            'is_coupon' => true,
            'is_active' => true,
        ]);

        CouponCode::create([
            'promotion_id' => $promo->id,
            'code' => 'USEDUP',
            'max_uses' => 1,
            'usage_count' => 1,
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/coupons/validate', ['code' => 'USEDUP']);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.error', 'coupon_exhausted');
    }

    public function test_validate_checks_min_order_total(): void
    {
        $promo = Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'Min Order',
            'type' => 'fixed_amount',
            'discount_value' => 10,
            'min_order_total' => 50,
            'is_coupon' => true,
            'is_active' => true,
        ]);

        CouponCode::create([
            'promotion_id' => $promo->id,
            'code' => 'MINORDER',
            'max_uses' => 100,
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/coupons/validate', [
                'code' => 'MINORDER',
                'order_total' => 30,
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.error', 'min_order_not_met');
    }

    public function test_validate_coupon_applies_percentage_discount(): void
    {
        $promo = Promotion::create([
            'organization_id' => $this->org->id,
            'name' => '20% Off',
            'type' => 'percentage',
            'discount_value' => 20,
            'is_coupon' => true,
            'is_active' => true,
        ]);

        CouponCode::create([
            'promotion_id' => $promo->id,
            'code' => 'TAKE20',
            'max_uses' => 100,
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/coupons/validate', [
                'code' => 'TAKE20',
                'order_total' => 100,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.valid', true);

        $this->assertEquals(20, $response->json('data.discount_amount'));
    }

    // ─── Coupon Redemption ──────────────────────────────────

    public function test_can_redeem_coupon(): void
    {
        $promo = Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'Redeem Test',
            'type' => 'fixed_amount',
            'discount_value' => 5,
            'is_coupon' => true,
            'is_active' => true,
            'usage_count' => 0,
        ]);

        $coupon = CouponCode::create([
            'promotion_id' => $promo->id,
            'code' => 'REDEEM5',
            'max_uses' => 10,
            'usage_count' => 0,
            'is_active' => true,
        ]);

        $order = Order::create([
            'store_id' => $this->store->id,
            'order_number' => 'ORD-001',
            'status' => 'completed',
            'subtotal' => 50,
            'tax_amount' => 5,
            'total' => 55,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/coupons/redeem', [
                'coupon_code_id' => $coupon->id,
                'order_id' => $order->id,
                'discount_amount' => 5,
            ]);

        $response->assertCreated();

        $this->assertEquals(5, $response->json('data.discount_amount'));

        // Verify usage counts incremented
        $this->assertEquals(1, $coupon->fresh()->usage_count);
        $this->assertEquals(1, $promo->fresh()->usage_count);
    }

    // ─── Batch Generate Coupons ─────────────────────────────

    public function test_can_batch_generate_coupons(): void
    {
        $promo = Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'Batch Gen',
            'type' => 'percentage',
            'discount_value' => 10,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/promotions/{$promo->id}/generate-coupons", [
                'count' => 5,
                'max_uses' => 3,
                'prefix' => 'VIP',
            ]);

        $response->assertCreated();
        $this->assertCount(5, $response->json('data'));

        // All codes start with VIP-
        foreach ($response->json('data') as $c) {
            $this->assertStringStartsWith('VIP-', $c['code']);
            $this->assertEquals(3, $c['max_uses']);
        }
    }

    public function test_generate_coupons_validation(): void
    {
        $promo = Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'Validate Gen',
            'type' => 'percentage',
            'discount_value' => 10,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/promotions/{$promo->id}/generate-coupons", [
                'count' => 0,
            ]);

        $response->assertUnprocessable();
    }

    public function test_cannot_generate_coupons_for_other_org(): void
    {
        $promo = Promotion::create([
            'organization_id' => $this->otherOrg->id,
            'name' => 'Other Gen',
            'type' => 'percentage',
            'discount_value' => 10,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/promotions/{$promo->id}/generate-coupons", [
                'count' => 5,
            ]);

        $response->assertNotFound();
    }

    // ─── Analytics ──────────────────────────────────────────

    public function test_can_get_promotion_analytics(): void
    {
        $promo = Promotion::create([
            'organization_id' => $this->org->id,
            'name' => 'Analytics Test',
            'type' => 'percentage',
            'discount_value' => 10,
            'usage_count' => 5,
            'max_uses' => 100,
            'is_active' => true,
        ]);

        CouponCode::create([
            'promotion_id' => $promo->id,
            'code' => 'ANA1',
            'max_uses' => 50,
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/promotions/{$promo->id}/analytics");

        $response->assertOk()
            ->assertJsonPath('data.promotion_id', $promo->id)
            ->assertJsonPath('data.usage_count', 5)
            ->assertJsonPath('data.active_coupons', 1)
            ->assertJsonPath('data.total_coupons', 1)
            ->assertJsonPath('data.is_active', true);
    }

    public function test_cannot_get_analytics_for_other_org(): void
    {
        $promo = Promotion::create([
            'organization_id' => $this->otherOrg->id,
            'name' => 'Secret Analytics',
            'type' => 'percentage',
            'discount_value' => 10,
        ]);

        $this->withToken($this->token)
            ->getJson("/api/v2/promotions/{$promo->id}/analytics")
            ->assertNotFound();
    }
}
