<?php

namespace Tests\Feature\Admin;

use App\Domain\AdminPanel\Models\AdminPermission;
use App\Domain\AdminPanel\Models\AdminRole;
use App\Domain\AdminPanel\Models\AdminRolePermission;
use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\AdminPanel\Models\AdminUserRole;
use App\Domain\Core\Models\Organization;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceResourceTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $adminWithPermission;
    private AdminUser $adminWithoutPermission;

    protected function setUp(): void
    {
        parent::setUp();

        $perm = AdminPermission::forceCreate([
            'name' => 'billing.invoices',
            'group' => 'billing',
            'description' => 'Manage billing invoices',
        ]);

        $allowedRole = AdminRole::forceCreate([
            'name' => 'Billing Manager',
            'slug' => 'billing_manager',
            'is_system' => false,
        ]);

        AdminRolePermission::create([
            'admin_role_id' => $allowedRole->id,
            'admin_permission_id' => $perm->id,
        ]);

        $deniedRole = AdminRole::forceCreate([
            'name' => 'No Billing Access',
            'slug' => 'no_billing_access',
            'is_system' => false,
        ]);

        $this->adminWithPermission = AdminUser::forceCreate([
            'name' => 'Billing Admin',
            'email' => 'billing-admin@test.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        AdminUserRole::create([
            'admin_user_id' => $this->adminWithPermission->id,
            'admin_role_id' => $allowedRole->id,
            'assigned_at' => now(),
        ]);

        $this->adminWithoutPermission = AdminUser::forceCreate([
            'name' => 'No Billing Admin',
            'email' => 'no-billing-admin@test.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        AdminUserRole::create([
            'admin_user_id' => $this->adminWithoutPermission->id,
            'admin_role_id' => $deniedRole->id,
            'assigned_at' => now(),
        ]);

        // Seed one subscription to force option label rendering on create form.
        $org = Organization::create([
            'name' => 'Invoice Test Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $plan = SubscriptionPlan::create([
            'name' => 'Starter',
            'name_ar' => 'الأساسية',
            'slug' => 'starter',
            'monthly_price' => 10,
            'annual_price' => 100,
            'is_active' => true,
        ]);

        StoreSubscription::create([
            'organization_id' => $org->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);
    }

    public function test_invoice_create_page_renders_for_authorized_admin(): void
    {
        $this->actingAs($this->adminWithPermission, 'admin')
            ->get('/admin/invoices/create')
            ->assertOk();
    }

    public function test_invoice_create_page_forbidden_without_permission(): void
    {
        $this->actingAs($this->adminWithoutPermission, 'admin')
            ->get('/admin/invoices/create')
            ->assertForbidden();
    }

    public function test_invoice_create_page_redirects_when_unauthenticated(): void
    {
        $this->get('/admin/invoices/create')
            ->assertRedirect('/admin/login');
    }
}
