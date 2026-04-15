<?php

namespace Database\Seeders;

use App\Domain\AdminPanel\Enums\AdminRoleSlug;
use App\Domain\AdminPanel\Models\AdminPermission;
use App\Domain\AdminPanel\Models\AdminRole;
use App\Domain\AdminPanel\Models\AdminRolePermission;
use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\AdminPanel\Models\AdminUserRole;
use App\Domain\Auth\Enums\UserRole;
use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Core\Models\StoreSettings;
use App\Domain\Core\Models\StoreWorkingHour;
use App\Domain\ProviderRegistration\Models\OnboardingProgress;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds:
 *  1. Platform super-admin user + role + all permissions
 *  2. "Ostora" supermarket organization, store, owner user
 *  3. Store settings, working hours, onboarding, subscription
 *
 * Run:  php artisan db:seed --class=TestDataSeeder
 */
class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPlatformSuperAdmin();
        $this->seedOstoraProvider();
    }

    // ─────────────────────────────────────────────────────────────
    // 1. PLATFORM SUPER ADMIN
    // ─────────────────────────────────────────────────────────────

    private function seedPlatformSuperAdmin(): void
    {
        $this->command->info('Creating platform super admin...');

        // ── Admin Role (already exists from schema seeder) ─────
        $superRole = AdminRole::where('slug', AdminRoleSlug::SuperAdmin->value)->first();
        if (!$superRole) {
            $superRole = AdminRole::create([
                'name' => 'Super Admin',
                'slug' => AdminRoleSlug::SuperAdmin->value,
                'description' => 'Full platform access — all permissions.',
                'is_system' => true,
            ]);
        }

        // ── Attach ALL existing permissions to super-admin role ─
        $allPermissionIds = AdminPermission::pluck('id')->toArray();
        foreach ($allPermissionIds as $permId) {
            AdminRolePermission::firstOrCreate([
                'admin_role_id' => $superRole->id,
                'admin_permission_id' => $permId,
            ]);
        }

        // ── Admin User ─────────────────────────────────────────
        $admin = AdminUser::firstOrCreate(
            ['email' => 'dev@wameedpos.com'],
            [
                'name' => 'Thawani Super Admin',
                'email' => 'dev@wameedpos.com',
                'password_hash' => Hash::make('Admin@2026'),
                'phone' => '+96890000001',
                'is_active' => true,
                'last_login_at' => now(),
            ]
        );

        // Assign role
        AdminUserRole::firstOrCreate([
            'admin_user_id' => $admin->id,
            'admin_role_id' => $superRole->id,
        ], [
            'assigned_at' => now(),
        ]);

        $this->command->info("  ✓ Super Admin: dev@wameedpos.com / Admin@2026");
        $this->command->info("  ✓ Role: super_admin with {$this->countNewPerms($superRole)} permissions");
    }

    private function countNewPerms(AdminRole $role): int
    {
        return AdminRolePermission::where('admin_role_id', $role->id)->count();
    }

    // ─────────────────────────────────────────────────────────────
    // 2. OSTORA SUPERMARKET (Provider)
    // ─────────────────────────────────────────────────────────────

    private function seedOstoraProvider(): void
    {
        $this->command->info('Creating Ostora supermarket provider...');

        // ── Organization ───────────────────────────────────────
        $org = Organization::firstOrCreate(
            ['slug' => 'ostora-supermarket'],
            [
                'name' => 'Ostora Supermarket',
                'name_ar' => 'سوبرماركت أستورا',
                'slug' => 'ostora-supermarket',
                'cr_number' => '1234567890',
                'vat_number' => '300012345600003',
                'business_type' => 'grocery',
                'logo_url' => null,
                'country' => 'SA',
                'city' => 'Riyadh',
                'address' => 'King Fahd Road, Al Olaya District, Riyadh 12211',
                'phone' => '+966501234567',
                'email' => 'info@ostora.sa',
                'is_active' => true,
            ]
        );

        // ── Store (main branch) ────────────────────────────────
        $store = Store::firstOrCreate(
            ['slug' => 'ostora-main'],
            [
                'organization_id' => $org->id,
                'name' => 'Ostora Main Branch',
                'name_ar' => 'أستورا - الفرع الرئيسي',
                'slug' => 'ostora-main',
                'branch_code' => 'OST-001',
                'address' => 'King Fahd Road, Al Olaya District, Riyadh 12211',
                'city' => 'Riyadh',
                'latitude' => 24.7136,
                'longitude' => 46.6753,
                'phone' => '+966501234567',
                'email' => 'main@ostora.sa',
                'timezone' => 'Asia/Riyadh',
                'currency' => 'SAR',
                'locale' => 'ar',
                'business_type' => 'grocery',
                'is_active' => true,
                'is_main_branch' => true,
            ]
        );

        // ── Owner User ─────────────────────────────────────────
        $owner = User::firstOrCreate(
            ['email' => 'owner@ostora.sa'],
            [
                'organization_id' => $org->id,
                'store_id' => $store->id,
                'name' => 'Mohammed Al-Ostora',
                'email' => 'owner@ostora.sa',
                'phone' => '+966501234567',
                'password_hash' => Hash::make('Owner@2026'),
                'pin_hash' => Hash::make('1234'),
                'role' => UserRole::Owner,
                'locale' => 'ar',
                'is_active' => true,
                'email_verified_at' => now(),
                'last_login_at' => now(),
            ]
        );

        // ── Store Settings ─────────────────────────────────────
        StoreSettings::firstOrCreate(
            ['store_id' => $store->id],
            [
                'store_id' => $store->id,
                // Tax — Saudi 15% VAT
                'tax_label' => 'VAT',
                'tax_rate' => 15.00,
                'prices_include_tax' => true,
                'tax_number' => '300012345600003',
                // Receipt
                'receipt_header' => 'Ostora Supermarket — أستورا سوبرماركت',
                'receipt_footer' => 'شكراً لتسوقكم — Thank you for shopping!',
                'receipt_show_logo' => true,
                'receipt_show_tax_breakdown' => true,
                // Currency
                'currency_code' => 'SAR',
                'currency_symbol' => 'ر.س',
                'decimal_places' => 2,
                'thousand_separator' => ',',
                'decimal_separator' => '.',
                // POS behaviour
                'allow_negative_stock' => false,
                'require_customer_for_sale' => false,
                'auto_print_receipt' => true,
                'session_timeout_minutes' => 30,
                'max_discount_percent' => 20,
                'enable_tips' => false,
                'enable_kitchen_display' => false,
                // Alerts
                'low_stock_alert' => true,
                'low_stock_threshold' => 10,
                // Extra
                'extra' => [
                    'track_expiry_dates' => true,
                    'suggested_categories' => [
                        'Fruits & Vegetables',
                        'Dairy & Eggs',
                        'Meat & Poultry',
                        'Bakery',
                        'Beverages',
                        'Snacks & Sweets',
                        'Canned & Dry Goods',
                        'Cleaning Supplies',
                        'Personal Care',
                        'Frozen Food',
                    ],
                ],
            ]
        );

        // ── Working Hours ──────────────────────────────────────
        $schedule = [
            // day_of_week => [is_open, open_time, close_time]
            0 => [true,  '08:00', '23:00'],  // Sunday
            1 => [true,  '08:00', '23:00'],  // Monday
            2 => [true,  '08:00', '23:00'],  // Tuesday
            3 => [true,  '08:00', '23:00'],  // Wednesday
            4 => [true,  '08:00', '23:00'],  // Thursday
            5 => [true,  '13:00', '23:00'],  // Friday (after Jummah)
            6 => [true,  '08:00', '23:00'],  // Saturday
        ];

        foreach ($schedule as $day => [$isOpen, $open, $close]) {
            StoreWorkingHour::firstOrCreate(
                ['store_id' => $store->id, 'day_of_week' => $day],
                [
                    'store_id' => $store->id,
                    'day_of_week' => $day,
                    'is_open' => $isOpen,
                    'open_time' => $open,
                    'close_time' => $close,
                    'break_start' => null,
                    'break_end' => null,
                ]
            );
        }

        // ── Subscription Plan (create starter plan if none) ────
        $starterPlan = SubscriptionPlan::firstOrCreate(
            ['slug' => 'starter'],
            [
                'name' => 'Starter',
                'name_ar' => 'المبتدئ',
                'slug' => 'starter',
                'monthly_price' => 0.00,
                'annual_price' => 0.00,
                'trial_days' => 14,
                'grace_period_days' => 7,
                'is_active' => true,
                'is_highlighted' => false,
                'sort_order' => 1,
            ]
        );

        $proPlan = SubscriptionPlan::firstOrCreate(
            ['slug' => 'professional'],
            [
                'name' => 'Professional',
                'name_ar' => 'الاحترافي',
                'slug' => 'professional',
                'monthly_price' => 199.00,
                'annual_price' => 1990.00,
                'trial_days' => 14,
                'grace_period_days' => 7,
                'is_active' => true,
                'is_highlighted' => true,
                'sort_order' => 2,
            ]
        );

        $enterprisePlan = SubscriptionPlan::firstOrCreate(
            ['slug' => 'enterprise'],
            [
                'name' => 'Enterprise',
                'name_ar' => 'المؤسسي',
                'slug' => 'enterprise',
                'monthly_price' => 499.00,
                'annual_price' => 4990.00,
                'trial_days' => 14,
                'grace_period_days' => 7,
                'is_active' => true,
                'is_highlighted' => false,
                'sort_order' => 3,
            ]
        );

        // ── Store Subscription (trial on Pro plan) ─────────────
        StoreSubscription::firstOrCreate(
            ['organization_id' => $org->id],
            [
                'organization_id' => $org->id,
                'subscription_plan_id' => $proPlan->id,
                'status' => 'trial',
                'billing_cycle' => 'monthly',
                'current_period_start' => now(),
                'current_period_end' => now()->addDays(14),
                'trial_ends_at' => now()->addDays(14),
            ]
        );

        // ── Onboarding Progress ────────────────────────────────
        OnboardingProgress::firstOrCreate(
            ['store_id' => $store->id],
            [
                'store_id' => $store->id,
                'current_step' => 'welcome',
                'completed_steps' => [],
                'checklist_items' => [
                    'add_first_product' => false,
                    'configure_receipt' => false,
                    'invite_staff' => false,
                    'make_first_sale' => false,
                    'set_working_hours' => true, // already set above
                ],
                'is_wizard_completed' => false,
                'is_checklist_dismissed' => false,
                'started_at' => now(),
            ]
        );

        $this->command->info("  ✓ Organization: Ostora Supermarket (slug: ostora-supermarket)");
        $this->command->info("  ✓ Store:        Ostora Main Branch (slug: ostora-main)");
        $this->command->info("  ✓ Owner:        owner@ostora.sa / Owner@2026 (PIN: 1234)");
        $this->command->info("  ✓ Settings:     SAR, 15% VAT, receipt configured");
        $this->command->info("  ✓ Hours:        08:00–23:00 (Fri 13:00–23:00)");
        $this->command->info("  ✓ Subscription: Professional plan (14-day trial)");
        $this->command->info("  ✓ Onboarding:   Initialized at welcome step");
    }
}
