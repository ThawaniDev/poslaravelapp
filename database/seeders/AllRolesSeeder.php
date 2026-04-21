<?php

namespace Database\Seeders;

use App\Domain\AdminPanel\Enums\AdminRoleSlug;
use App\Domain\AdminPanel\Models\AdminRole;
use App\Domain\Auth\Enums\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds all platform admin roles + all predefined store-level roles
 * for every existing store.
 *
 * Run: php artisan db:seed --class=AllRolesSeeder
 */
class AllRolesSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAdminRoles();
        $this->seedStoreRoles();
    }

    private function seedAdminRoles(): void
    {
        $this->command->info('Seeding platform admin roles...');

        $defs = [
            AdminRoleSlug::SuperAdmin->value         => ['Super Admin',         'Full platform access — all permissions.'],
            AdminRoleSlug::PlatformManager->value    => ['Platform Manager',    'Manages tenants, plans, and platform-wide settings.'],
            AdminRoleSlug::SupportAgent->value       => ['Support Agent',       'Handles tickets and reads tenant data for assistance.'],
            AdminRoleSlug::FinanceAdmin->value       => ['Finance Admin',       'Manages billing, invoices, and revenue reports.'],
            AdminRoleSlug::IntegrationManager->value => ['Integration Manager', 'Manages 3rd-party integrations and API configs.'],
            AdminRoleSlug::Sales->value              => ['Sales',               'Manages leads, demos, and provider onboarding.'],
            AdminRoleSlug::Viewer->value             => ['Viewer',              'Read-only access to platform metrics.'],
        ];

        foreach ($defs as $slug => [$name, $desc]) {
            AdminRole::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => $desc, 'is_system' => true]
            );
        }

        $this->command->info('  ✓ ' . count($defs) . ' admin roles ensured.');
    }

    private function seedStoreRoles(): void
    {
        $this->command->info('Seeding store-level predefined roles...');

        $defs = [
            UserRole::Owner->value          => ['Owner',           'مالك',          'Full store access.',                            'صلاحية كاملة على المتجر.'],
            UserRole::ChainManager->value   => ['Chain Manager',   'مدير سلسلة',    'Manages all branches in the chain.',           'يدير جميع فروع السلسلة.'],
            UserRole::BranchManager->value  => ['Branch Manager',  'مدير فرع',     'Manages a single branch.',                      'يدير فرعاً واحداً.'],
            UserRole::Cashier->value        => ['Cashier',         'كاشير',         'POS sell, basic customer ops.',                'البيع وإدارة العملاء الأساسية.'],
            UserRole::InventoryClerk->value => ['Inventory Clerk', 'موظف مخزون',    'Receives stock, adjusts inventory.',           'استلام البضائع وتعديل المخزون.'],
            UserRole::Accountant->value     => ['Accountant',      'محاسب',         'Reports, financial reconciliation.',           'التقارير والمطابقات المالية.'],
            UserRole::KitchenStaff->value   => ['Kitchen Staff',   'طاقم المطبخ',  'Kitchen tickets and order prep.',              'تذاكر المطبخ وتحضير الطلبات.'],
        ];

        $stores = DB::table('stores')->pluck('id');
        if ($stores->isEmpty()) {
            $this->command->warn('  No stores found — skipping store-level role seeding.');
            return;
        }

        $count = 0;
        foreach ($stores as $storeId) {
            foreach ($defs as $slug => [$name, $nameAr, $desc, $descAr]) {
                $exists = DB::table('roles')
                    ->where('store_id', $storeId)
                    ->where('name', $slug)
                    ->where('guard_name', 'web')
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('roles')->insert([
                    'store_id'        => $storeId,
                    'name'            => $slug,
                    'display_name'    => $name,
                    'display_name_ar' => $nameAr,
                    'description'     => $desc,
                    'description_ar'  => $descAr,
                    'guard_name'      => 'web',
                    'is_predefined'   => true,
                    'scope'           => $slug === UserRole::ChainManager->value ? 'chain' : 'branch',
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
                $count++;
            }
        }

        $this->command->info("  ✓ {$count} store-level roles created across {$stores->count()} store(s).");
    }
}
