<?php

namespace Tests\Feature\Customer;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Customer\Models\Customer;
use App\Domain\Customer\Models\CustomerGroup;
use App\Domain\Customer\Models\LoyaltyConfig;
use App\Domain\Order\Models\Order;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Models\PlanFeatureToggle;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;
    private SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Customer Org',
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
            'name' => 'Owner',
            'email' => 'owner@cust.com',
            'password_hash' => bcrypt('secret'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('t', ['*'])->plainTextToken;

        $this->plan = SubscriptionPlan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'monthly_price' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        PlanFeatureToggle::create([
            'subscription_plan_id' => $this->plan->id,
            'feature_key' => 'customer_management',
            'is_enabled' => true,
        ]);
        PlanFeatureToggle::create([
            'subscription_plan_id' => $this->plan->id,
            'feature_key' => 'customer_loyalty',
            'is_enabled' => true,
        ]);
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);
    }

    private function makeCustomer(array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'organization_id' => $this->org->id,
            'name' => 'Ali',
            'phone' => '500'.random_int(10000, 99999),
            'loyalty_code' => Str::upper(Str::random(8)),
            'loyalty_points' => 0,
            'store_credit_balance' => 0,
            'sync_version' => 1,
        ], $overrides));
    }

    // ─── CRUD ──────────────────────────────────────────────

    public function test_can_list_customers(): void
    {
        $this->makeCustomer(['name' => 'Ali']);
        $this->makeCustomer(['name' => 'Sara']);

        $r = $this->withToken($this->token)->getJson('/api/v2/customers');
        $r->assertOk()->assertJsonPath('success', true);
        $this->assertCount(2, $r->json('data.data'));
    }

    public function test_can_create_customer_and_loyalty_code_is_generated(): void
    {
        $r = $this->withToken($this->token)->postJson('/api/v2/customers', [
            'name' => 'New Cust',
            'phone' => '5012345678',
            'email' => 'new@cust.com',
        ]);

        $r->assertStatus(201)
            ->assertJsonPath('data.name', 'New Cust');

        $this->assertNotEmpty($r->json('data.loyalty_code'));
        $this->assertEquals(8, strlen($r->json('data.loyalty_code')));
    }

    public function test_create_requires_phone(): void
    {
        $r = $this->withToken($this->token)->postJson('/api/v2/customers', [
            'name' => 'NoPhone',
        ]);
        $r->assertStatus(422);
    }

    public function test_create_rejects_duplicate_phone(): void
    {
        $this->makeCustomer(['phone' => '500111222']);
        $r = $this->withToken($this->token)->postJson('/api/v2/customers', [
            'name' => 'Dup',
            'phone' => '500111222',
        ]);
        $r->assertStatus(422);
    }

    public function test_can_update_customer(): void
    {
        $c = $this->makeCustomer(['name' => 'Old']);
        $r = $this->withToken($this->token)->putJson('/api/v2/customers/'.$c->id, [
            'name' => 'New Name',
            'notes' => 'VIP',
        ]);
        $r->assertOk()->assertJsonPath('data.name', 'New Name');
    }

    public function test_can_soft_delete_customer(): void
    {
        $c = $this->makeCustomer();
        $r = $this->withToken($this->token)->deleteJson('/api/v2/customers/'.$c->id);
        $r->assertOk();
        $this->assertSoftDeleted('customers', ['id' => $c->id]);
    }

    // ─── Search & Sync ─────────────────────────────────────

    public function test_quick_search_matches_by_phone_and_name(): void
    {
        $this->makeCustomer(['name' => 'Hassan', 'phone' => '500777888']);
        $this->makeCustomer(['name' => 'Khalid', 'phone' => '500999000']);

        $r = $this->withToken($this->token)->getJson('/api/v2/customers/search?q=Hass');
        $r->assertOk();
        $this->assertCount(1, $r->json('data'));
        $this->assertEquals('Hassan', $r->json('data.0.name'));

        $r2 = $this->withToken($this->token)->getJson('/api/v2/customers/search?q=999000');
        $this->assertCount(1, $r2->json('data'));
    }

    public function test_sync_returns_delta_since_timestamp(): void
    {
        $old = $this->makeCustomer(['name' => 'Old', 'phone' => '500001']);
        \DB::table('customers')->where('id', $old->id)->update(['updated_at' => now()->subDays(2)]);
        $cutoff = now()->subDay()->toIso8601String();
        $this->makeCustomer(['name' => 'Recent', 'phone' => '500002']);

        $r = $this->withToken($this->token)->getJson('/api/v2/pos/customers/sync?since='.urlencode($cutoff));
        $r->assertOk();
        $names = collect($r->json('data.data'))->pluck('name')->all();
        $this->assertContains('Recent', $names);
        $this->assertNotContains('Old', $names);
    }

    public function test_sync_includes_soft_deleted_records(): void
    {
        $c = $this->makeCustomer(['name' => 'ToDelete', 'phone' => '500003']);
        $c->delete();
        $cutoff = now()->subMinute()->toIso8601String();
        $r = $this->withToken($this->token)->getJson('/api/v2/pos/customers/sync?since='.urlencode($cutoff));
        $r->assertOk();
        $ids = collect($r->json('data.data'))->pluck('id')->all();
        $this->assertContains($c->id, $ids);
    }

    // ─── Orders history ────────────────────────────────────

    public function test_can_list_customer_orders(): void
    {
        $c = $this->makeCustomer();
        Order::create([
            'store_id' => $this->store->id,
            'customer_id' => $c->id,
            'order_number' => 'O-1',
            'status' => 'completed',
            'total' => 50,
        ]);
        $r = $this->withToken($this->token)->getJson('/api/v2/customers/'.$c->id.'/orders');
        $r->assertOk();
        $this->assertEquals(1, $r->json('data.total'));
    }

    // ─── Groups ────────────────────────────────────────────

    public function test_can_create_and_list_groups(): void
    {
        $this->withToken($this->token)->postJson('/api/v2/customers/groups', [
            'name' => 'VIP',
            'discount_percent' => 10,
        ])->assertStatus(201);

        $r = $this->withToken($this->token)->getJson('/api/v2/customers/groups/list');
        $r->assertOk();
        $this->assertCount(1, $r->json('data'));
    }

    public function test_cannot_delete_group_with_customers(): void
    {
        $g = CustomerGroup::create([
            'organization_id' => $this->org->id,
            'name' => 'Reg',
            'discount_percent' => 5,
        ]);
        $this->makeCustomer(['group_id' => $g->id]);

        $r = $this->withToken($this->token)->deleteJson('/api/v2/customers/groups/'.$g->id);
        $r->assertStatus(422);
    }

    public function test_can_delete_empty_group(): void
    {
        $g = CustomerGroup::create([
            'organization_id' => $this->org->id,
            'name' => 'Empty',
            'discount_percent' => 5,
        ]);
        $this->withToken($this->token)->deleteJson('/api/v2/customers/groups/'.$g->id)->assertOk();
    }

    // ─── Loyalty ───────────────────────────────────────────

    public function test_can_save_loyalty_config(): void
    {
        $r = $this->withToken($this->token)->putJson('/api/v2/customers/loyalty/config', [
            'points_per_sar' => 1,
            'sar_per_point' => 0.05,
            'min_redemption_points' => 100,
            'points_expiry_months' => 12,
            'is_active' => true,
        ]);
        $r->assertOk();
        $this->assertDatabaseHas('loyalty_config', ['organization_id' => $this->org->id]);
    }

    public function test_can_adjust_and_redeem_loyalty_points(): void
    {
        LoyaltyConfig::create([
            'organization_id' => $this->org->id,
            'points_per_sar' => 1,
            'sar_per_point' => 0.05,
            'min_redemption_points' => 50,
            'points_expiry_months' => 12,
            'is_active' => true,
        ]);
        $c = $this->makeCustomer();

        $this->withToken($this->token)->postJson("/api/v2/customers/{$c->id}/loyalty/adjust", [
            'points' => 200, 'type' => 'earn',
        ])->assertStatus(201);
        $this->assertEquals(200, $c->fresh()->loyalty_points);

        $this->withToken($this->token)->postJson("/api/v2/customers/{$c->id}/loyalty/redeem", [
            'points' => 80,
        ])->assertStatus(201);
        $this->assertEquals(120, $c->fresh()->loyalty_points);

        $this->withToken($this->token)->postJson("/api/v2/customers/{$c->id}/loyalty/redeem", [
            'points' => 10,
        ])->assertStatus(422);

        $this->withToken($this->token)->postJson("/api/v2/customers/{$c->id}/loyalty/redeem", [
            'points' => 9999,
        ])->assertStatus(422);
    }

    // ─── Store credit ──────────────────────────────────────

    public function test_store_credit_topup_and_spend(): void
    {
        $c = $this->makeCustomer();

        $this->withToken($this->token)->postJson("/api/v2/customers/{$c->id}/store-credit/top-up", [
            'amount' => 100,
        ])->assertStatus(201);
        $this->assertEquals(100, (float) $c->fresh()->store_credit_balance);

        $this->withToken($this->token)->postJson("/api/v2/customers/{$c->id}/store-credit/adjust", [
            'amount' => -30,
            'notes' => 'manual',
        ])->assertStatus(201);
        $this->assertEquals(70, (float) $c->fresh()->store_credit_balance);

        $this->withToken($this->token)->postJson("/api/v2/customers/{$c->id}/store-credit/adjust", [
            'amount' => -500,
        ])->assertStatus(422);
    }

    // ─── Digital receipt ───────────────────────────────────

    public function test_can_send_digital_receipt(): void
    {
        $c = $this->makeCustomer(['email' => 'a@b.com']);
        $order = Order::create([
            'store_id' => $this->store->id,
            'customer_id' => $c->id,
            'order_number' => 'O-9',
            'status' => 'completed',
            'total' => 25,
        ]);

        $r = $this->withToken($this->token)->postJson("/api/v2/customers/{$c->id}/receipt", [
            'order_id' => $order->id,
            'channel' => 'email',
        ]);
        $r->assertStatus(201)->assertJsonPath('data.channel', 'email');
        $this->assertDatabaseHas('digital_receipt_log', ['order_id' => $order->id]);
    }

    public function test_cannot_send_receipt_for_other_customer_order(): void
    {
        $c = $this->makeCustomer();
        $other = $this->makeCustomer();
        $order = Order::create([
            'store_id' => $this->store->id,
            'customer_id' => $other->id,
            'order_number' => 'O-8',
            'status' => 'completed',
            'total' => 10,
        ]);
        $r = $this->withToken($this->token)->postJson("/api/v2/customers/{$c->id}/receipt", [
            'order_id' => $order->id,
            'channel' => 'email',
        ]);
        $r->assertStatus(422);
    }

    public function test_receipt_requires_destination(): void
    {
        $c = $this->makeCustomer(['email' => null, 'phone' => '500111']);
        $order = Order::create([
            'store_id' => $this->store->id,
            'customer_id' => $c->id,
            'order_number' => 'O-7',
            'status' => 'completed',
            'total' => 10,
        ]);
        // override empty destination explicitly
        $c->email = null;
        $c->save();
        $r = $this->withToken($this->token)->postJson("/api/v2/customers/{$c->id}/receipt", [
            'order_id' => $order->id,
            'channel' => 'email',
            'destination' => '',
        ]);
        $r->assertStatus(422);
    }

    // ─── Cross-org isolation ───────────────────────────────

    public function test_cannot_access_other_orgs_customer(): void
    {
        $other = Organization::create([
            'name' => 'Other',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);
        $c = Customer::create([
            'organization_id' => $other->id,
            'name' => 'Foreigner',
            'phone' => '500ext',
            'loyalty_code' => 'XXXXXXXX',
            'sync_version' => 1,
        ]);
        $this->withToken($this->token)->getJson('/api/v2/customers/'.$c->id)->assertStatus(404);
    }

    // ─── New behaviour: bulk + filters + anonymisation + cron ─────────

    public function test_bulk_assign_group_updates_many_customers(): void
    {
        $g = CustomerGroup::create([
            'organization_id' => $this->org->id,
            'name' => 'VIP',
            'discount_percent' => 10,
        ]);
        $c1 = $this->makeCustomer(['name' => 'A']);
        $c2 = $this->makeCustomer(['name' => 'B']);
        $c3 = $this->makeCustomer(['name' => 'C']);

        $r = $this->withToken($this->token)->postJson('/api/v2/customers/bulk/assign-group', [
            'customer_ids' => [$c1->id, $c2->id, $c3->id],
            'group_id' => $g->id,
        ]);
        $r->assertOk();
        $this->assertSame(3, $r->json('data.updated'));
        $this->assertSame($g->id, $c1->refresh()->group_id);
        $this->assertSame($g->id, $c2->refresh()->group_id);
        $this->assertSame($g->id, $c3->refresh()->group_id);

        // null group_id removes
        $this->withToken($this->token)->postJson('/api/v2/customers/bulk/assign-group', [
            'customer_ids' => [$c1->id],
            'group_id' => null,
        ])->assertOk();
        $this->assertNull($c1->refresh()->group_id);
    }

    public function test_index_filters_by_has_loyalty_and_date_range(): void
    {
        $with = $this->makeCustomer(['name' => 'WithPoints', 'loyalty_points' => 50, 'last_visit_at' => now()->subDays(2)]);
        $without = $this->makeCustomer(['name' => 'NoPoints', 'loyalty_points' => 0, 'last_visit_at' => now()->subYears(2)]);

        $r1 = $this->withToken($this->token)->getJson('/api/v2/customers?has_loyalty=true');
        $r1->assertOk();
        $names = array_column($r1->json('data.data'), 'name');
        $this->assertContains('WithPoints', $names);
        $this->assertNotContains('NoPoints', $names);

        $r2 = $this->withToken($this->token)->getJson(
            '/api/v2/customers?last_visit_from='.now()->subDays(7)->toDateString()
                .'&last_visit_to='.now()->toDateString()
        );
        $r2->assertOk();
        $names2 = array_column($r2->json('data.data'), 'name');
        $this->assertContains('WithPoints', $names2);
        $this->assertNotContains('NoPoints', $names2);

        // Suppress unused warning
        $this->assertNotNull($with->id);
        $this->assertNotNull($without->id);
    }

    public function test_delete_anonymises_pii(): void
    {
        $c = $this->makeCustomer([
            'name' => 'Real Name',
            'email' => 'real@x.com',
            'address' => '123 St',
            'notes' => 'sensitive',
        ]);
        $this->withToken($this->token)->deleteJson('/api/v2/customers/'.$c->id)->assertOk();
        $row = \DB::table('customers')->where('id', $c->id)->first();
        $this->assertNotNull($row, 'row should still exist (soft-delete)');
        $this->assertSame('ANONYMISED', $row->name);
        $this->assertNull($row->email);
        $this->assertNull($row->address);
        $this->assertNull($row->notes);
        $this->assertNotNull($row->deleted_at);
    }

    public function test_loyalty_expire_points_command_runs(): void
    {
        // Just ensure the command exists and exits 0.
        $exit = \Artisan::call('loyalty:expire-points');
        $this->assertSame(0, $exit);
    }

    public function test_loyalty_config_accepts_double_points_days_and_excluded_categories(): void
    {
        $r = $this->withToken($this->token)->putJson('/api/v2/customers/loyalty/config', [
            'points_per_sar' => 1,
            'sar_per_point' => 0.05,
            'min_redemption_points' => 50,
            'points_expiry_months' => 12,
            'excluded_category_ids' => ['cat-1', 'cat-2'],
            'double_points_days' => [1, 5],
            'is_active' => true,
        ]);
        $r->assertOk();
        $cfg = LoyaltyConfig::where('organization_id', $this->org->id)->first();
        $this->assertSame(['cat-1', 'cat-2'], $cfg->excluded_category_ids);
        $this->assertSame([1, 5], $cfg->double_points_days);
    }

    public function test_loyalty_config_rejects_invalid_double_points_day(): void
    {
        $this->withToken($this->token)->putJson('/api/v2/customers/loyalty/config', [
            'points_per_sar' => 1,
            'double_points_days' => [9], // out of range
        ])->assertStatus(422);
    }
}
