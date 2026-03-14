<?php

namespace Tests\Feature\Admin;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderSubscription\Models\Invoice;
use App\Domain\ProviderSubscription\Models\InvoiceLineItem;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Models\PlanAddOn;
use App\Domain\Subscription\Models\PlanFeatureToggle;
use App\Domain\Subscription\Models\PlanLimit;
use App\Domain\Subscription\Models\SubscriptionDiscount;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PackageSubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;
    private SubscriptionPlan $basicPlan;
    private SubscriptionPlan $premiumPlan;
    private Store $store1;
    private Store $store2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = AdminUser::forceCreate([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->admin, ['*'], 'admin-api');

        $this->store1 = Store::forceCreate([
            'name' => 'Store One',
            'is_active' => true,
        ]);

        $this->store2 = Store::forceCreate([
            'name' => 'Store Two',
            'is_active' => true,
        ]);

        $this->basicPlan = SubscriptionPlan::forceCreate([
            'name' => 'Basic',
            'name_ar' => 'أساسي',
            'slug' => 'basic',
            'description' => 'Basic plan',
            'monthly_price' => 10.00,
            'annual_price' => 100.00,
            'trial_days' => 14,
            'grace_period_days' => 7,
            'is_active' => true,
            'is_highlighted' => false,
            'sort_order' => 1,
        ]);

        $this->premiumPlan = SubscriptionPlan::forceCreate([
            'name' => 'Premium',
            'name_ar' => 'مميز',
            'slug' => 'premium',
            'description' => 'Premium plan',
            'monthly_price' => 50.00,
            'annual_price' => 500.00,
            'trial_days' => 0,
            'grace_period_days' => 14,
            'is_active' => true,
            'is_highlighted' => true,
            'sort_order' => 2,
        ]);

        PlanFeatureToggle::forceCreate([
            'subscription_plan_id' => $this->basicPlan->id,
            'feature_key' => 'pos_basic',
            'is_enabled' => true,
        ]);

        PlanFeatureToggle::forceCreate([
            'subscription_plan_id' => $this->premiumPlan->id,
            'feature_key' => 'pos_basic',
            'is_enabled' => true,
        ]);

        PlanFeatureToggle::forceCreate([
            'subscription_plan_id' => $this->premiumPlan->id,
            'feature_key' => 'delivery_integration',
            'is_enabled' => true,
        ]);

        PlanLimit::forceCreate([
            'subscription_plan_id' => $this->basicPlan->id,
            'limit_key' => 'products',
            'limit_value' => 100,
            'price_per_extra_unit' => 0.50,
        ]);

        PlanLimit::forceCreate([
            'subscription_plan_id' => $this->premiumPlan->id,
            'limit_key' => 'products',
            'limit_value' => 10000,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // Plan List
    // ═══════════════════════════════════════════════════════════

    public function test_list_plans_returns_all_plans(): void
    {
        $response = $this->getJson('/api/v2/admin/plans');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_list_plans_with_active_only_filter(): void
    {
        $this->premiumPlan->update(['is_active' => false]);

        $response = $this->getJson('/api/v2/admin/plans?active_only=1');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_list_plans_includes_features_and_limits(): void
    {
        $response = $this->getJson('/api/v2/admin/plans');

        $response->assertOk();
        $data = $response->json('data');
        $basic = collect($data)->firstWhere('slug', 'basic');
        $this->assertNotNull($basic);
        $this->assertArrayHasKey('features', $basic);
        $this->assertArrayHasKey('limits', $basic);
    }

    // ═══════════════════════════════════════════════════════════
    // Plan Show
    // ═══════════════════════════════════════════════════════════

    public function test_show_plan_returns_plan_with_details(): void
    {
        $response = $this->getJson("/api/v2/admin/plans/{$this->basicPlan->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Basic')
            ->assertJsonPath('data.slug', 'basic');

        $this->assertEquals(10.0, $response->json('data.monthly_price'));
    }

    public function test_show_plan_returns_404_for_invalid_id(): void
    {
        $response = $this->getJson('/api/v2/admin/plans/nonexistent');

        $response->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════
    // Plan Create
    // ═══════════════════════════════════════════════════════════

    public function test_create_plan_with_minimal_data(): void
    {
        $response = $this->postJson('/api/v2/admin/plans', [
            'name' => 'Starter',
            'monthly_price' => 5.00,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Starter');

        $this->assertEquals(5.0, $response->json('data.monthly_price'));

        $this->assertDatabaseHas('subscription_plans', ['name' => 'Starter']);
    }

    public function test_create_plan_with_features_and_limits(): void
    {
        $response = $this->postJson('/api/v2/admin/plans', [
            'name' => 'Pro',
            'monthly_price' => 30.00,
            'annual_price' => 300.00,
            'trial_days' => 7,
            'features' => [
                ['feature_key' => 'pos_basic', 'is_enabled' => true],
                ['feature_key' => 'inventory_management', 'is_enabled' => true],
            ],
            'limits' => [
                ['limit_key' => 'products', 'limit_value' => 500],
                ['limit_key' => 'staff', 'limit_value' => 10, 'price_per_extra_unit' => 2.00],
            ],
        ]);

        $response->assertCreated();
        $planId = $response->json('data.id');

        $this->assertDatabaseCount('plan_feature_toggles', 5); // 2 existing + 1 existing + 2 new
        $this->assertDatabaseHas('plan_feature_toggles', [
            'subscription_plan_id' => $planId,
            'feature_key' => 'inventory_management',
        ]);
        $this->assertDatabaseHas('plan_limits', [
            'subscription_plan_id' => $planId,
            'limit_key' => 'staff',
            'limit_value' => 10,
        ]);
    }

    public function test_create_plan_validation_requires_name(): void
    {
        $response = $this->postJson('/api/v2/admin/plans', [
            'monthly_price' => 10.00,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_plan_validation_requires_monthly_price(): void
    {
        $response = $this->postJson('/api/v2/admin/plans', [
            'name' => 'Test',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['monthly_price']);
    }

    // ═══════════════════════════════════════════════════════════
    // Plan Update
    // ═══════════════════════════════════════════════════════════

    public function test_update_plan_name(): void
    {
        $response = $this->putJson("/api/v2/admin/plans/{$this->basicPlan->id}", [
            'name' => 'Basic Updated',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Basic Updated');
    }

    public function test_update_plan_features_replaces_them(): void
    {
        $response = $this->putJson("/api/v2/admin/plans/{$this->basicPlan->id}", [
            'features' => [
                ['feature_key' => 'new_feature', 'is_enabled' => true],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseMissing('plan_feature_toggles', [
            'subscription_plan_id' => $this->basicPlan->id,
            'feature_key' => 'pos_basic',
        ]);
        $this->assertDatabaseHas('plan_feature_toggles', [
            'subscription_plan_id' => $this->basicPlan->id,
            'feature_key' => 'new_feature',
        ]);
    }

    public function test_update_plan_limits_replaces_them(): void
    {
        $response = $this->putJson("/api/v2/admin/plans/{$this->basicPlan->id}", [
            'limits' => [
                ['limit_key' => 'staff', 'limit_value' => 5],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseMissing('plan_limits', [
            'subscription_plan_id' => $this->basicPlan->id,
            'limit_key' => 'products',
        ]);
        $this->assertDatabaseHas('plan_limits', [
            'subscription_plan_id' => $this->basicPlan->id,
            'limit_key' => 'staff',
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // Plan Toggle
    // ═══════════════════════════════════════════════════════════

    public function test_toggle_plan_deactivates_active_plan(): void
    {
        $response = $this->postJson("/api/v2/admin/plans/{$this->basicPlan->id}/toggle");

        $response->assertOk();
        $this->assertDatabaseHas('subscription_plans', [
            'id' => $this->basicPlan->id,
            'is_active' => false,
        ]);
    }

    public function test_toggle_plan_activates_inactive_plan(): void
    {
        $this->basicPlan->update(['is_active' => false]);

        $response = $this->postJson("/api/v2/admin/plans/{$this->basicPlan->id}/toggle");

        $response->assertOk();
        $this->assertDatabaseHas('subscription_plans', [
            'id' => $this->basicPlan->id,
            'is_active' => true,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // Plan Delete
    // ═══════════════════════════════════════════════════════════

    public function test_delete_plan_with_no_subscribers(): void
    {
        $response = $this->deleteJson("/api/v2/admin/plans/{$this->basicPlan->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('subscription_plans', ['id' => $this->basicPlan->id]);
    }

    public function test_delete_plan_with_active_subscribers_fails(): void
    {
        StoreSubscription::forceCreate([
            'store_id' => $this->store1->id,
            'subscription_plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->deleteJson("/api/v2/admin/plans/{$this->basicPlan->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('subscription_plans', ['id' => $this->basicPlan->id]);
    }

    // ═══════════════════════════════════════════════════════════
    // Plan Compare
    // ═══════════════════════════════════════════════════════════

    public function test_compare_plans_returns_feature_matrix(): void
    {
        $response = $this->getJson('/api/v2/admin/plans/compare');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['plans', 'features', 'limits'],
            ]);
    }

    public function test_compare_specific_plans(): void
    {
        $response = $this->getJson('/api/v2/admin/plans/compare?plan_ids[]=' . $this->basicPlan->id);

        $response->assertOk();
        $plans = $response->json('data.plans');
        $this->assertCount(1, $plans);
    }

    // ═══════════════════════════════════════════════════════════
    // Add-Ons
    // ═══════════════════════════════════════════════════════════

    public function test_list_add_ons(): void
    {
        PlanAddOn::forceCreate([
            'name' => 'SMS Pack',
            'slug' => 'sms-pack',
            'monthly_price' => 5.00,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v2/admin/add-ons');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_create_add_on(): void
    {
        $response = $this->postJson('/api/v2/admin/add-ons', [
            'name' => 'Delivery Integration',
            'monthly_price' => 15.00,
            'description' => 'Third-party delivery integration',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Delivery Integration');
    }

    public function test_show_add_on(): void
    {
        $addOn = PlanAddOn::forceCreate([
            'name' => 'Extra Storage',
            'slug' => 'extra-storage',
            'monthly_price' => 3.00,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v2/admin/add-ons/{$addOn->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Extra Storage');
    }

    public function test_update_add_on(): void
    {
        $addOn = PlanAddOn::forceCreate([
            'name' => 'Old Name',
            'slug' => 'old-name',
            'monthly_price' => 5.00,
            'is_active' => true,
        ]);

        $response = $this->putJson("/api/v2/admin/add-ons/{$addOn->id}", [
            'name' => 'New Name',
            'monthly_price' => 10.00,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name');
    }

    public function test_delete_add_on(): void
    {
        $addOn = PlanAddOn::forceCreate([
            'name' => 'Temp',
            'slug' => 'temp',
            'monthly_price' => 1.00,
            'is_active' => true,
        ]);

        $response = $this->deleteJson("/api/v2/admin/add-ons/{$addOn->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('plan_add_ons', ['id' => $addOn->id]);
    }

    public function test_create_add_on_validation(): void
    {
        $response = $this->postJson('/api/v2/admin/add-ons', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'monthly_price']);
    }

    // ═══════════════════════════════════════════════════════════
    // Discounts
    // ═══════════════════════════════════════════════════════════

    public function test_list_discounts(): void
    {
        SubscriptionDiscount::forceCreate([
            'code' => 'SAVE20',
            'type' => 'percentage',
            'value' => 20.00,
            'max_uses' => 100,
            'times_used' => 0,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addMonth(),
        ]);

        $response = $this->getJson('/api/v2/admin/discounts');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['discounts', 'pagination']]);
    }

    public function test_create_discount(): void
    {
        $response = $this->postJson('/api/v2/admin/discounts', [
            'code' => 'LAUNCH50',
            'type' => 'fixed',
            'value' => 50.00,
            'max_uses' => 50,
            'valid_from' => now()->toDateTimeString(),
            'valid_to' => now()->addMonths(3)->toDateTimeString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'LAUNCH50')
            ->assertJsonPath('data.type', 'fixed');
    }

    public function test_show_discount(): void
    {
        $discount = SubscriptionDiscount::forceCreate([
            'code' => 'TEST10',
            'type' => 'percentage',
            'value' => 10.00,
            'valid_from' => now(),
            'valid_to' => now()->addMonth(),
        ]);

        $response = $this->getJson("/api/v2/admin/discounts/{$discount->id}");

        $response->assertOk()
            ->assertJsonPath('data.code', 'TEST10');
    }

    public function test_update_discount(): void
    {
        $discount = SubscriptionDiscount::forceCreate([
            'code' => 'OLD',
            'type' => 'fixed',
            'value' => 5.00,
            'valid_from' => now(),
            'valid_to' => now()->addMonth(),
        ]);

        $response = $this->putJson("/api/v2/admin/discounts/{$discount->id}", [
            'value' => 25.00,
        ]);

        $response->assertOk();
    }

    public function test_delete_discount(): void
    {
        $discount = SubscriptionDiscount::forceCreate([
            'code' => 'DEL',
            'type' => 'percentage',
            'value' => 15.00,
            'valid_from' => now(),
            'valid_to' => now()->addMonth(),
        ]);

        $response = $this->deleteJson("/api/v2/admin/discounts/{$discount->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('subscription_discounts', ['id' => $discount->id]);
    }

    public function test_create_discount_validation(): void
    {
        $response = $this->postJson('/api/v2/admin/discounts', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'type', 'value', 'valid_from', 'valid_to']);
    }

    public function test_create_discount_with_applicable_plans(): void
    {
        $response = $this->postJson('/api/v2/admin/discounts', [
            'code' => 'BASIC_ONLY',
            'type' => 'percentage',
            'value' => 10.00,
            'valid_from' => now()->toDateTimeString(),
            'valid_to' => now()->addMonth()->toDateTimeString(),
            'applicable_plan_ids' => [$this->basicPlan->id],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('subscription_discounts', ['code' => 'BASIC_ONLY']);
    }

    // ═══════════════════════════════════════════════════════════
    // Subscriptions (Admin Overview)
    // ═══════════════════════════════════════════════════════════

    public function test_list_subscriptions(): void
    {
        StoreSubscription::forceCreate([
            'store_id' => $this->store1->id,
            'subscription_plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->getJson('/api/v2/admin/subscriptions');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['subscriptions', 'pagination']]);
    }

    public function test_list_subscriptions_filter_by_status(): void
    {
        StoreSubscription::forceCreate([
            'store_id' => $this->store1->id,
            'subscription_plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        StoreSubscription::forceCreate([
            'store_id' => $this->store2->id,
            'subscription_plan_id' => $this->premiumPlan->id,
            'status' => 'trial',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addDays(14),
        ]);

        $response = $this->getJson('/api/v2/admin/subscriptions?status=active');

        $response->assertOk();
        $subs = $response->json('data.subscriptions');
        $this->assertCount(1, $subs);
    }

    public function test_show_subscription_with_plan_and_invoices(): void
    {
        $sub = StoreSubscription::forceCreate([
            'store_id' => $this->store1->id,
            'subscription_plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->getJson("/api/v2/admin/subscriptions/{$sub->id}");

        $response->assertOk()
            ->assertJsonPath('data.store_id', $this->store1->id)
            ->assertJsonPath('data.status', 'active');
    }

    // ═══════════════════════════════════════════════════════════
    // Invoices
    // ═══════════════════════════════════════════════════════════

    public function test_list_invoices(): void
    {
        $sub = StoreSubscription::forceCreate([
            'store_id' => $this->store1->id,
            'subscription_plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        Invoice::forceCreate([
            'store_subscription_id' => $sub->id,
            'invoice_number' => 'INV-001',
            'amount' => 10.00,
            'tax' => 1.50,
            'total' => 11.50,
            'status' => 'pending',
            'due_date' => now()->addDays(7),
        ]);

        $response = $this->getJson('/api/v2/admin/invoices');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['invoices', 'pagination']]);
    }

    public function test_show_invoice_with_line_items(): void
    {
        $sub = StoreSubscription::forceCreate([
            'store_id' => $this->store1->id,
            'subscription_plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $invoice = Invoice::forceCreate([
            'store_subscription_id' => $sub->id,
            'invoice_number' => 'INV-002',
            'amount' => 50.00,
            'tax' => 7.50,
            'total' => 57.50,
            'status' => 'paid',
            'due_date' => now()->addDays(7),
            'paid_at' => now(),
        ]);

        InvoiceLineItem::forceCreate([
            'invoice_id' => $invoice->id,
            'description' => 'Premium - monthly subscription',
            'quantity' => 1,
            'unit_price' => 50.00,
            'total' => 50.00,
        ]);

        $response = $this->getJson("/api/v2/admin/invoices/{$invoice->id}");

        $response->assertOk()
            ->assertJsonPath('data.invoice_number', 'INV-002')
            ->assertJsonPath('data.total', 57.5);
    }

    // ═══════════════════════════════════════════════════════════
    // Revenue Dashboard
    // ═══════════════════════════════════════════════════════════

    public function test_revenue_dashboard_returns_subscription_stats(): void
    {
        StoreSubscription::forceCreate([
            'store_id' => $this->store1->id,
            'subscription_plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        StoreSubscription::forceCreate([
            'store_id' => $this->store2->id,
            'subscription_plan_id' => $this->premiumPlan->id,
            'status' => 'trial',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addDays(14),
        ]);

        $response = $this->getJson('/api/v2/admin/revenue-dashboard');

        $response->assertOk()
            ->assertJsonPath('data.subscriptions.active', 1)
            ->assertJsonPath('data.subscriptions.trial', 1);
    }

    // ═══════════════════════════════════════════════════════════
    // Auth & Unauthenticated Access
    // ═══════════════════════════════════════════════════════════

    public function test_unauthenticated_access_to_plans_returns_401(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v2/admin/plans');

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_access_to_subscriptions_returns_401(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v2/admin/subscriptions');

        $response->assertUnauthorized();
    }

    // ═══════════════════════════════════════════════════════════
    // Edge Cases
    // ═══════════════════════════════════════════════════════════

    public function test_create_plan_with_arabic_name(): void
    {
        $response = $this->postJson('/api/v2/admin/plans', [
            'name' => 'Enterprise',
            'name_ar' => 'مؤسسي',
            'monthly_price' => 99.00,
            'description_ar' => 'خطة مؤسسية متكاملة',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name_ar', 'مؤسسي');
    }

    public function test_create_plan_with_zero_trial_days(): void
    {
        $response = $this->postJson('/api/v2/admin/plans', [
            'name' => 'No Trial',
            'monthly_price' => 20.00,
            'trial_days' => 0,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.trial_days', 0);
    }

    public function test_discount_unique_code_constraint(): void
    {
        SubscriptionDiscount::forceCreate([
            'code' => 'UNIQUE',
            'type' => 'percentage',
            'value' => 10.00,
            'valid_from' => now(),
            'valid_to' => now()->addMonth(),
        ]);

        $response = $this->postJson('/api/v2/admin/discounts', [
            'code' => 'UNIQUE',
            'type' => 'percentage',
            'value' => 20.00,
            'valid_from' => now()->toDateTimeString(),
            'valid_to' => now()->addMonth()->toDateTimeString(),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_list_invoices_filter_by_status(): void
    {
        $sub = StoreSubscription::forceCreate([
            'store_id' => $this->store1->id,
            'subscription_plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        Invoice::forceCreate([
            'store_subscription_id' => $sub->id,
            'invoice_number' => 'INV-A',
            'amount' => 10.00,
            'tax' => 1.50,
            'total' => 11.50,
            'status' => 'pending',
            'due_date' => now()->addDays(7),
        ]);

        Invoice::forceCreate([
            'store_subscription_id' => $sub->id,
            'invoice_number' => 'INV-B',
            'amount' => 10.00,
            'tax' => 1.50,
            'total' => 11.50,
            'status' => 'paid',
            'due_date' => now()->addDays(7),
            'paid_at' => now(),
        ]);

        $response = $this->getJson('/api/v2/admin/invoices?status=pending');

        $response->assertOk();
        $invoices = $response->json('data.invoices');
        $this->assertCount(1, $invoices);
    }
}
