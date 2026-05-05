<?php

namespace Tests\Feature\Report;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\StaffManagement\Models\Permission;
use App\Domain\StaffManagement\Models\Role;
use App\Domain\Subscription\Enums\SubscriptionStatus;
use App\Domain\Subscription\Models\PlanFeatureToggle;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckPlanFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Tests\Support\BypassPermissionMiddleware;
use Tests\TestCase;

/**
 * Permission + Subscription enforcement tests for the Report API.
 *
 * These tests override the middleware aliases to use real middleware,
 * verifying that auth, plan feature, and permission checks all behave correctly.
 */
class ReportPermissionTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Store $store;
    private User $ownerUser;
    private User $staffUser;
    private SubscriptionPlan $plan;
    private StoreSubscription $subscription;

    // ─── Middleware aliases (restored in tearDown) ─────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        // Override the bypassed middleware aliases to real implementations
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('permission', CheckPermission::class);
        $router->aliasMiddleware('plan.feature', CheckPlanFeature::class);

        // Set up org + store
        $this->org = Organization::create([
            'name' => 'Perm Test Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Perm Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        // Owner user — bypasses permission checks
        $this->ownerUser = User::create([
            'name' => 'Owner',
            'email' => 'owner@perm.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        // Staff user — needs explicit role + permission
        $this->staffUser = User::create([
            'name' => 'Staff',
            'email' => 'staff@perm.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        // Active subscription plan with reports_basic enabled
        $this->plan = SubscriptionPlan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'monthly_price' => 99.00,
            'is_active' => true,
        ]);

        $this->subscription = StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::Active->value,
            'billing_cycle' => 'monthly',
        ]);

        PlanFeatureToggle::create([
            'subscription_plan_id' => $this->plan->id,
            'feature_key' => 'reports_basic',
            'name' => 'Reports Basic',
            'is_enabled' => true,
        ]);
    }

    protected function tearDown(): void
    {
        // Restore bypassed middleware so other tests are not affected
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('permission', BypassPermissionMiddleware::class);
        $router->aliasMiddleware('plan.feature', BypassPermissionMiddleware::class);

        parent::tearDown();
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function grantPermission(User $user, string $permissionName): void
    {
        $permission = Permission::firstWhere('name', $permissionName)
            ?? Permission::create([
                'name' => $permissionName,
                'display_name' => $permissionName,
                'module' => 'reports',
                'guard_name' => 'staff',
            ]);

        $role = Role::create([
            'store_id' => $this->store->id,
            'name' => 'role_' . $permissionName,
            'display_name' => $permissionName,
            'guard_name' => 'staff',
        ]);

        $role->permissions()->attach($permission->id);

        DB::table('model_has_roles')->updateOrInsert([
            'role_id' => $role->id,
            'model_id' => $user->id,
            'model_type' => get_class($user),
        ]);
    }

    // ─── Unauthenticated ──────────────────────────────────────────────────────

    /** @test */
    public function unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v2/reports/dashboard')
            ->assertStatus(401);
    }

    /** @test */
    public function unauthenticated_request_to_sales_summary_returns_401(): void
    {
        $this->getJson('/api/v2/reports/sales-summary')
            ->assertStatus(401);
    }

    /** @test */
    public function unauthenticated_post_export_returns_401(): void
    {
        $this->postJson('/api/v2/reports/export', ['report_type' => 'sales_summary'])
            ->assertStatus(401);
    }

    // ─── No Subscription ──────────────────────────────────────────────────────

    /** @test */
    public function owner_without_subscription_gets_403_on_plan_feature_check(): void
    {
        // Delete the subscription so no active subscription exists
        $this->subscription->delete();

        $this->actingAs($this->ownerUser)
            ->getJson('/api/v2/reports/dashboard')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'feature_not_available');
    }

    /** @test */
    public function owner_with_cancelled_subscription_gets_403(): void
    {
        $this->subscription->update(['status' => SubscriptionStatus::Cancelled->value]);

        $this->actingAs($this->ownerUser)
            ->getJson('/api/v2/reports/sales-summary')
            ->assertStatus(403);
    }

    /** @test */
    public function owner_with_expired_subscription_gets_403(): void
    {
        $this->subscription->update(['status' => SubscriptionStatus::Expired->value]);

        $this->actingAs($this->ownerUser)
            ->getJson('/api/v2/reports/sales-summary')
            ->assertStatus(403);
    }

    // ─── Trial and Grace period allowed ───────────────────────────────────────

    /** @test */
    public function owner_with_trial_subscription_can_access_reports(): void
    {
        $this->subscription->update(['status' => SubscriptionStatus::Trial->value]);

        $this->grantPermission($this->ownerUser, 'reports.view');

        // Owner bypasses permission check; trial still allows feature access
        $this->actingAs($this->ownerUser)
            ->getJson('/api/v2/reports/dashboard')
            ->assertStatus(200);
    }

    /** @test */
    public function owner_with_grace_period_subscription_can_access_reports(): void
    {
        $this->subscription->update(['status' => SubscriptionStatus::Grace->value]);

        $this->actingAs($this->ownerUser)
            ->getJson('/api/v2/reports/dashboard')
            ->assertStatus(200);
    }

    // ─── Feature toggle disabled ───────────────────────────────────────────────

    /** @test */
    public function owner_with_active_subscription_but_reports_basic_disabled_gets_403(): void
    {
        PlanFeatureToggle::where('subscription_plan_id', $this->plan->id)
            ->where('feature_key', 'reports_basic')
            ->update(['is_enabled' => false]);

        $this->actingAs($this->ownerUser)
            ->getJson('/api/v2/reports/dashboard')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'feature_not_available');
    }

    // ─── Owner bypasses permission but still needs plan feature ───────────────

    /** @test */
    public function owner_with_valid_plan_can_access_dashboard(): void
    {
        $this->actingAs($this->ownerUser)
            ->getJson('/api/v2/reports/dashboard')
            ->assertStatus(200);
    }

    /** @test */
    public function owner_with_valid_plan_can_access_sales_summary(): void
    {
        $this->actingAs($this->ownerUser)
            ->getJson('/api/v2/reports/sales-summary')
            ->assertStatus(200);
    }

    // ─── Staff missing permission ──────────────────────────────────────────────

    /** @test */
    public function staff_without_reports_view_cannot_access_dashboard(): void
    {
        $this->actingAs($this->staffUser)
            ->getJson('/api/v2/reports/dashboard')
            ->assertStatus(403);
    }

    /** @test */
    public function staff_without_reports_sales_cannot_access_sales_summary(): void
    {
        $this->actingAs($this->staffUser)
            ->getJson('/api/v2/reports/sales-summary')
            ->assertStatus(403);
    }

    /** @test */
    public function staff_without_reports_inventory_cannot_access_inventory_endpoints(): void
    {
        $this->actingAs($this->staffUser)
            ->getJson('/api/v2/reports/inventory/valuation')
            ->assertStatus(403);

        $this->actingAs($this->staffUser)
            ->getJson('/api/v2/reports/inventory/low-stock')
            ->assertStatus(403);
    }

    /** @test */
    public function staff_without_reports_view_financial_cannot_access_financial_endpoints(): void
    {
        $this->actingAs($this->staffUser)
            ->getJson('/api/v2/reports/financial/expenses')
            ->assertStatus(403);
    }

    // ─── Staff with correct permission ────────────────────────────────────────

    /** @test */
    public function staff_with_reports_view_can_access_dashboard(): void
    {
        $this->grantPermission($this->staffUser, 'reports.view');

        $this->actingAs($this->staffUser)
            ->getJson('/api/v2/reports/dashboard')
            ->assertStatus(200);
    }

    /** @test */
    public function staff_with_reports_sales_can_access_sales_summary(): void
    {
        $this->grantPermission($this->staffUser, 'reports.sales');

        $this->actingAs($this->staffUser)
            ->getJson('/api/v2/reports/sales-summary')
            ->assertStatus(200);
    }

    /** @test */
    public function staff_with_reports_inventory_can_access_inventory_valuation(): void
    {
        $this->grantPermission($this->staffUser, 'reports.inventory');

        $this->actingAs($this->staffUser)
            ->getJson('/api/v2/reports/inventory/valuation')
            ->assertStatus(200);
    }

    /** @test */
    public function staff_with_reports_staff_can_access_staff_performance(): void
    {
        $this->grantPermission($this->staffUser, 'reports.staff');

        $this->actingAs($this->staffUser)
            ->getJson('/api/v2/reports/staff-performance')
            ->assertStatus(200);
    }

    /** @test */
    public function staff_with_reports_customers_can_access_customer_endpoints(): void
    {
        $this->grantPermission($this->staffUser, 'reports.customers');

        $this->actingAs($this->staffUser)
            ->getJson('/api/v2/reports/customers/top')
            ->assertStatus(200);

        $this->actingAs($this->staffUser)
            ->getJson('/api/v2/reports/customers/retention')
            ->assertStatus(200);
    }

    /** @test */
    public function staff_with_reports_view_financial_can_access_financial_endpoints(): void
    {
        $this->grantPermission($this->staffUser, 'reports.view_financial');

        $this->actingAs($this->staffUser)
            ->getJson('/api/v2/reports/financial/expenses')
            ->assertStatus(200);

        $this->actingAs($this->staffUser)
            ->getJson('/api/v2/reports/financial/cash-variance')
            ->assertStatus(200);

        $this->actingAs($this->staffUser)
            ->getJson('/api/v2/reports/financial/delivery-commission')
            ->assertStatus(200);
    }

    // ─── reports_advanced gate for daily P&L ─────────────────────────────────

    /** @test */
    public function owner_without_reports_advanced_cannot_access_daily_pl(): void
    {
        // reports_basic is enabled but reports_advanced is NOT
        $this->actingAs($this->ownerUser)
            ->getJson('/api/v2/reports/financial/daily-pl')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'feature_not_available');
    }

    /** @test */
    public function owner_with_reports_advanced_can_access_daily_pl(): void
    {
        PlanFeatureToggle::create([
            'subscription_plan_id' => $this->plan->id,
            'feature_key' => 'reports_advanced',
            'name' => 'Reports Advanced',
            'is_enabled' => true,
        ]);

        $this->actingAs($this->ownerUser)
            ->getJson('/api/v2/reports/financial/daily-pl')
            ->assertStatus(200);
    }

    // ─── reports.view_margin gates margin endpoint ────────────────────────────

    /** @test */
    public function staff_without_reports_view_margin_cannot_access_product_margin(): void
    {
        // Give reports.sales but not reports.view_margin
        $this->grantPermission($this->staffUser, 'reports.sales');

        $this->actingAs($this->staffUser)
            ->getJson('/api/v2/reports/products/margin')
            ->assertStatus(403);
    }

    /** @test */
    public function staff_with_reports_view_margin_can_access_product_margin(): void
    {
        $this->grantPermission($this->staffUser, 'reports.view_margin');

        $this->actingAs($this->staffUser)
            ->getJson('/api/v2/reports/products/margin')
            ->assertStatus(200);
    }

    // ─── reports.export gates export and schedules ────────────────────────────

    /** @test */
    public function staff_without_reports_export_cannot_post_export(): void
    {
        $this->grantPermission($this->staffUser, 'reports.view');

        $this->actingAs($this->staffUser)
            ->postJson('/api/v2/reports/export', [
                'report_type' => 'sales_summary',
                'format' => 'csv',
            ])
            ->assertStatus(403);
    }

    /** @test */
    public function staff_with_reports_export_can_post_export(): void
    {
        $this->grantPermission($this->staffUser, 'reports.export');

        $this->actingAs($this->staffUser)
            ->postJson('/api/v2/reports/export', [
                'report_type' => 'sales_summary',
                'format' => 'csv',
            ])
            ->assertStatus(200);
    }

    /** @test */
    public function staff_without_reports_export_cannot_list_schedules(): void
    {
        $this->grantPermission($this->staffUser, 'reports.view');

        $this->actingAs($this->staffUser)
            ->getJson('/api/v2/reports/schedules')
            ->assertStatus(403);
    }

    /** @test */
    public function staff_with_reports_export_can_manage_schedules(): void
    {
        $this->grantPermission($this->staffUser, 'reports.export');

        $this->actingAs($this->staffUser)
            ->getJson('/api/v2/reports/schedules')
            ->assertStatus(200);

        $this->actingAs($this->staffUser)
            ->postJson('/api/v2/reports/schedules', [
                'report_type' => 'sales_summary',
                'name' => 'Staff Schedule',
                'frequency' => 'weekly',
                'recipients' => ['staff@test.com'],
                'format' => 'csv',
            ])
            ->assertStatus(201);
    }

    // ─── Owner role bypasses permission checks ────────────────────────────────

    /** @test */
    public function owner_does_not_need_explicit_permission_role(): void
    {
        // Owner has NO roles attached — but owner role enum bypasses permission checks
        $this->actingAs($this->ownerUser)
            ->getJson('/api/v2/reports/dashboard')
            ->assertStatus(200);

        $this->actingAs($this->ownerUser)
            ->getJson('/api/v2/reports/staff-performance')
            ->assertStatus(200);

        $this->actingAs($this->ownerUser)
            ->getJson('/api/v2/reports/customers/top')
            ->assertStatus(200);
    }

    // ─── Cross-org isolation of plan enforcement ──────────────────────────────

    /** @test */
    public function user_from_org_without_plan_cannot_access_reports(): void
    {
        $otherOrg = Organization::create(['name' => 'No Plan Org', 'business_type' => 'grocery', 'country' => 'SA']);
        $otherStore = Store::create(['organization_id' => $otherOrg->id, 'name' => 'No Plan Store', 'business_type' => 'grocery', 'currency' => 'SAR', 'is_active' => true]);

        $otherUser = User::create([
            'name' => 'NoPlan',
            'email' => 'noplan@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $otherStore->id,
            'organization_id' => $otherOrg->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->actingAs($otherUser)
            ->getJson('/api/v2/reports/dashboard')
            ->assertStatus(403);
    }
}
