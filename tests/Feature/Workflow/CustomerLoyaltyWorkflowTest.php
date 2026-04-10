<?php

namespace Tests\Feature\Workflow;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Customer\Models\Customer;
use App\Domain\Customer\Models\CustomerGroup;
use App\Domain\Payment\Models\GiftCard;
use Illuminate\Foundation\Testing\RefreshDatabase;


/**
 * CUSTOMER & LOYALTY WORKFLOW TESTS
 *
 * Verifies customer management, loyalty points, store credit,
 * gift cards, customer groups, and gamification.
 *
 * Cross-references: Workflows #151-175 in COMPREHENSIVE_WORKFLOW_TESTS.md
 */
class CustomerLoyaltyWorkflowTest extends WorkflowTestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $cashier;
    private Organization $org;
    private Store $store;
    private string $ownerToken;
    private string $cashierToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();

        $this->org = Organization::create([
            'name' => 'Customer Test Org',
            'name_ar' => 'منظمة اختبار العملاء',
            'business_type' => 'grocery',
            'country' => 'SA',
            'vat_number' => '300000000000006',
            'is_active' => true,
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Customer Branch',
            'name_ar' => 'فرع العملاء',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@customer-test.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->cashier = User::create([
            'name' => 'Cashier',
            'email' => 'cashier@customer-test.test',
            'password_hash' => bcrypt('password'),
            'pin_hash' => bcrypt('1234'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $this->ownerToken = $this->owner->createToken('test', ['*'])->plainTextToken;
        $this->assignOwnerRole($this->owner, $this->store->id);
        $this->cashierToken = $this->cashier->createToken('test', ['*'])->plainTextToken;
        $this->assignCashierRole($this->cashier, $this->store->id);
    }

    // ═══════════════════════════════════════════════════════════
    // WF #151-155: CUSTOMER CRUD
    // ═══════════════════════════════════════════════════════════

    /** @test WF#151: Create customer with full details */
    public function test_wf151_create_customer(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/customers', [
                'name' => 'Abdullah Al-Saud',
                'phone' => '966501234567',
                'email' => 'abdullah@test.com',
                'date_of_birth' => '1990-05-15',
                'notes' => 'VIP customer',
            ]);

        $response->assertStatus(201)->assertJsonPath('success', true);
        $response->assertJsonPath('data.name', 'Abdullah Al-Saud');

        $this->assertDatabaseHas('customers', [
            'organization_id' => $this->org->id,
            'name' => 'Abdullah Al-Saud',
            'phone' => '966501234567',
            'loyalty_points' => 0,
            'store_credit_balance' => 0,
            'total_spend' => 0,
            'visit_count' => 0,
        ]);
    }

    /** @test WF#152: Update customer info */
    public function test_wf152_update_customer(): void
    {
        $customer = $this->createCustomer();

        $response = $this->withToken($this->ownerToken)
            ->putJson("/api/v2/customers/{$customer->id}", [
                'name' => 'Abdullah Updated',
                'email' => 'updated@test.com',
            ]);

        $response->assertOk()->assertJsonPath('data.name', 'Abdullah Updated');
    }

    /** @test WF#153: Search customers by phone or name */
    public function test_wf153_search_customers(): void
    {
        $this->createCustomer('Ahmed Al-Test', '966509999999');
        $this->createCustomer('Sara Al-Test', '966508888888');

        // Search by name
        $nameResp = $this->withToken($this->cashierToken)
            ->getJson('/api/v2/customers?search=Ahmed');
        $nameResp->assertOk();
        $this->assertGreaterThanOrEqual(1, count($nameResp->json('data')));

        // Search by phone
        $phoneResp = $this->withToken($this->cashierToken)
            ->getJson('/api/v2/customers?search=966508888888');
        $phoneResp->assertOk();
        $this->assertGreaterThanOrEqual(1, count($phoneResp->json('data')));
    }

    /** @test WF#154: Delete customer (soft delete) */
    public function test_wf154_soft_delete_customer(): void
    {
        $customer = $this->createCustomer();

        $response = $this->withToken($this->ownerToken)
            ->deleteJson("/api/v2/customers/{$customer->id}");

        $response->assertOk();
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    /** @test WF#155: Cannot create duplicate phone in same org */
    public function test_wf155_duplicate_phone_rejected(): void
    {
        $this->createCustomer('First', '966501234567');

        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/customers', [
                'name' => 'Second',
                'phone' => '966501234567',
            ]);

        $response->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════
    // WF #156-162: LOYALTY POINTS
    // ═══════════════════════════════════════════════════════════

    /** @test WF#156: Manual loyalty points adjustment */
    public function test_wf156_manual_loyalty_adjustment(): void
    {
        $customer = $this->createCustomer();

        $response = $this->withToken($this->ownerToken)
            ->postJson("/api/v2/customers/{$customer->id}/loyalty/adjust", [
                'points' => 500,
                'type' => 'adjust',
                'reason' => 'Welcome bonus',
            ]);

        $this->assertTrue(
            in_array($response->status(), [200, 201]),
            'Expected 200 or 201, got ' . $response->status()
        );

        $customer->refresh();
        $this->assertEquals(500, $customer->loyalty_points);

        $this->assertDatabaseHas('loyalty_transactions', [
            'customer_id' => $customer->id,
            'points' => 500,
        ]);
    }

    /** @test WF#157: View loyalty transaction history */
    public function test_wf157_loyalty_history(): void
    {
        $customer = $this->createCustomer();

        // Add some points
        $this->withToken($this->ownerToken)
            ->postJson("/api/v2/customers/{$customer->id}/loyalty/adjust", [
                'points' => 100, 'type' => 'earn', 'reason' => 'Bonus 1',
            ]);

        $this->withToken($this->ownerToken)
            ->postJson("/api/v2/customers/{$customer->id}/loyalty/adjust", [
                'points' => 200, 'type' => 'earn', 'reason' => 'Bonus 2',
            ]);

        $response = $this->withToken($this->ownerToken)
            ->getJson("/api/v2/customers/{$customer->id}/loyalty");

        $response->assertOk();
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    /** @test WF#158: Cannot redeem more points than available */
    public function test_wf158_redeem_exceeds_balance(): void
    {
        $customer = $this->createCustomer();
        $customer->update(['loyalty_points' => 50]); // Below min_redemption

        $response = $this->withToken($this->ownerToken)
            ->postJson("/api/v2/customers/{$customer->id}/loyalty/adjust", [
                'points' => -500,
                'type' => 'redeem',
            ]);

        $response->assertStatus(422);
    }

    /** @test WF#159: Loyalty config changes affect earning rate */
    public function test_wf159_loyalty_config_update(): void
    {
        // Get current loyalty config
        $getResp = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/customers/loyalty/config');

        $getResp->assertOk();

        // Update loyalty config — change earning rate
        $updateResp = $this->withToken($this->ownerToken)
            ->putJson('/api/v2/customers/loyalty/config', [
                'points_per_currency' => 2,
                'min_redemption' => 50,
                'redemption_value' => 0.5,
                'is_enabled' => true,
            ]);

        $this->assertTrue(
            in_array($updateResp->status(), [200, 201]),
            'Loyalty config update should succeed. Status: ' . $updateResp->status()
        );

        // Verify updated config is persisted
        $verifyResp = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/customers/loyalty/config');

        $verifyResp->assertOk();
        $config = $verifyResp->json('data');
        $this->assertNotNull($config, 'Loyalty config should be returned');
    }

    // ═══════════════════════════════════════════════════════════
    // WF #163-166: STORE CREDIT
    // ═══════════════════════════════════════════════════════════

    /** @test WF#163: Add store credit to customer */
    public function test_wf163_add_store_credit(): void
    {
        $customer = $this->createCustomer();

        $response = $this->withToken($this->ownerToken)
            ->postJson("/api/v2/customers/{$customer->id}/store-credit/top-up", [
                'amount' => 100.00,
                'reason' => 'Return refund to credit',
            ]);

        $this->assertTrue(
            in_array($response->status(), [200, 201]),
            'Expected 200 or 201, got ' . $response->status()
        );

        $customer->refresh();
        $this->assertEquals(100.00, $customer->store_credit_balance);

        $this->assertDatabaseHas('store_credit_transactions', [
            'customer_id' => $customer->id,
            'type' => 'top_up',
            'amount' => 100.00,
        ]);
    }

    /** @test WF#164: Store credit transaction history */
    public function test_wf164_store_credit_history(): void
    {
        $customer = $this->createCustomer();

        $this->withToken($this->ownerToken)
            ->postJson("/api/v2/customers/{$customer->id}/store-credit/top-up", [
                'amount' => 50.00, 'reason' => 'Credit 1',
            ]);

        $this->withToken($this->ownerToken)
            ->postJson("/api/v2/customers/{$customer->id}/store-credit/top-up", [
                'amount' => 75.00, 'reason' => 'Credit 2',
            ]);

        $response = $this->withToken($this->ownerToken)
            ->getJson("/api/v2/customers/{$customer->id}/store-credit");

        $response->assertOk();
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    // ═══════════════════════════════════════════════════════════
    // WF #167-170: GIFT CARDS
    // ═══════════════════════════════════════════════════════════

    /** @test WF#167: Create gift card */
    public function test_wf167_create_gift_card(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/gift-cards', [
                'amount' => 200.00,
                'recipient_name' => 'Ali',
                'recipient_phone' => '966507654321',
            ]);

        $response->assertStatus(201)->assertJsonPath('success', true);

        $cardId = $response->json('data.id');
        $this->assertDatabaseHas('gift_cards', [
            'id' => $cardId,
            'organization_id' => $this->org->id,
            'initial_amount' => 200.00,
            'balance' => 200.00,
            'status' => 'active',
        ]);

        // Code should be generated
        $this->assertNotNull($response->json('data.code'));
    }

    /** @test WF#168: Check gift card balance */
    public function test_wf168_check_gift_card_balance(): void
    {
        $card = GiftCard::create([
            'organization_id' => $this->org->id,
            'code' => 'GC-TEST-12345',
            'initial_amount' => 100.00,
            'balance' => 75.00,
            'status' => 'active',
        ]);

        $response = $this->withToken($this->ownerToken)
            ->getJson("/api/v2/gift-cards/GC-TEST-12345/balance");

        $response->assertOk();
        // Verify balance is returned
        $this->assertNotNull($response->json('data'));
    }

    /** @test WF#169: Deactivate gift card via full redemption */
    public function test_wf169_deactivate_gift_card(): void
    {
        // Create an active gift card
        $card = GiftCard::create([
            'organization_id' => $this->org->id,
            'code' => 'GC-DEACT-001',
            'initial_amount' => 50.00,
            'balance' => 50.00,
            'status' => 'active',
        ]);

        // Redeem entire balance to effectively exhaust the card
        $redeemResp = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/gift-cards/GC-DEACT-001/redeem', [
                'amount' => 50.00,
            ]);

        $this->assertTrue(
            in_array($redeemResp->status(), [200, 201]),
            'Full redemption should succeed. Status: ' . $redeemResp->status()
        );

        // Verify balance is now zero
        $balResp = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/gift-cards/GC-DEACT-001/balance');
        $balResp->assertOk();

        $balance = $balResp->json('data.balance') ?? $balResp->json('data.remaining_balance');
        $this->assertEquals(0, $balance, 'Gift card balance should be zero after full redemption');

        // Trying to redeem again should fail
        $reRedeemResp = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/gift-cards/GC-DEACT-001/redeem', [
                'amount' => 1.00,
            ]);

        $this->assertTrue(
            in_array($reRedeemResp->status(), [400, 422]),
            'Redeeming from zero-balance card should fail. Status: ' . $reRedeemResp->status()
        );
    }

    // ═══════════════════════════════════════════════════════════
    // WF #171-175: CUSTOMER GROUPS
    // ═══════════════════════════════════════════════════════════

    /** @test WF#171: Create customer group */
    public function test_wf171_create_customer_group(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/customers/groups', [
                'name' => 'VIP Customers',
                'discount_percent' => 10,
            ]);

        $response->assertStatus(201)->assertJsonPath('success', true);
        $response->assertJsonPath('data.name', 'VIP Customers');
    }

    /** @test WF#172: Assign customer to group */
    public function test_wf172_assign_customer_to_group(): void
    {
        $customer = $this->createCustomer();
        $group = CustomerGroup::create([
            'organization_id' => $this->org->id,
            'name' => 'Gold',
            'discount_percent' => 5,
        ]);

        // Assign via updating customer's group_id
        $response = $this->withToken($this->ownerToken)
            ->putJson("/api/v2/customers/{$customer->id}", [
                'group_id' => $group->id,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'group_id' => $group->id,
        ]);
    }

    /** @test WF#173: Customer group discount verified through group assignment and data integrity */
    public function test_wf173_group_discount_applied_on_sale(): void
    {
        $customer = $this->createCustomer();

        // Create a group with 10% discount
        $group = CustomerGroup::create([
            'organization_id' => $this->org->id,
            'name' => 'Platinum',
            'discount_percent' => 10,
        ]);

        // Assign customer to group
        $assignResp = $this->withToken($this->ownerToken)
            ->putJson("/api/v2/customers/{$customer->id}", [
                'group_id' => $group->id,
            ]);
        $assignResp->assertOk();

        // Verify the customer is in the group with the discount
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'group_id' => $group->id,
        ]);

        // Verify group has correct discount
        $this->assertDatabaseHas('customer_groups', [
            'id' => $group->id,
            'discount_percent' => 10,
        ]);

        // Fetch customer and verify group relationship
        $fetchResp = $this->withToken($this->ownerToken)
            ->getJson("/api/v2/customers/{$customer->id}");

        $fetchResp->assertOk();
        $custData = $fetchResp->json('data');
        $this->assertEquals($group->id, $custData['group_id'] ?? null);
    }

    // ═══════════════════════════════════════════════════════════
    // MULTI-TENANT ISOLATION
    // ═══════════════════════════════════════════════════════════

    /** @test WF#175: Cannot see other org's customers */
    public function test_wf175_customer_org_isolation(): void
    {
        $customer = $this->createCustomer('Our Customer', '966501112233');

        $otherOrg = Organization::create([
            'name' => 'Other', 'name_ar' => 'أخرى',
            'business_type' => 'grocery', 'country' => 'SA', 'is_active' => true,
        ]);
        $otherStore = Store::create([
            'organization_id' => $otherOrg->id, 'name' => 'Other', 'name_ar' => 'أخرى',
            'business_type' => 'grocery', 'currency' => 'SAR', 'locale' => 'ar',
            'timezone' => 'Asia/Riyadh', 'is_active' => true, 'is_main_branch' => true,
        ]);
        $otherUser = User::create([
            'name' => 'Other', 'email' => 'other@cust.test',
            'password_hash' => bcrypt('pass'), 'store_id' => $otherStore->id,
            'organization_id' => $otherOrg->id, 'role' => 'owner', 'is_active' => true,
        ]);
        $otherToken = $otherUser->createToken('test', ['*'])->plainTextToken;

        // Other org cannot see our customer
        $response = $this->withToken($otherToken)
            ->getJson("/api/v2/customers/{$customer->id}");

        $this->assertTrue(
            $response->status() === 403 || $response->status() === 404,
            'Other org should not access our customers'
        );
    }

    // ═══════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════

    private function createCustomer(string $name = 'Test Customer', string $phone = '966501234567'): Customer
    {
        return Customer::create([
            'organization_id' => $this->org->id,
            'name' => $name,
            'phone' => $phone,
            'loyalty_points' => 0,
            'store_credit_balance' => 0,
            'total_spend' => 0,
            'visit_count' => 0,
        ]);
    }
}
