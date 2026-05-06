<?php

namespace Database\Seeders;

use App\Domain\Auth\Enums\UserRole;
use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\StaffManagement\Services\RoleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Dedicated seeder for TestSprite / API integration tests.
 * Idempotent. Creates a known org + store + users with documented credentials.
 *
 * Credentials:
 *   owner@testsprite.local      / Password123!  (PIN 1111) — Owner
 *   manager@testsprite.local    / Password123!  (PIN 2222) — BranchManager
 *   cashier@testsprite.local    / Password123!  (PIN 3333) — Cashier
 *   inventory@testsprite.local  / Password123!  (PIN 4444) — InventoryClerk
 *   accountant@testsprite.local / Password123!  (PIN 5555) — Accountant
 */
class TestSpriteSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::firstOrCreate(
            ['slug' => 'testsprite-org'],
            [
                'name' => 'TestSprite Org',
                'name_ar' => 'منظمة الاختبار',
                'slug' => 'testsprite-org',
                'country' => 'OM',
                'business_type' => 'grocery',
                'email' => 'owner@testsprite.local',
                'is_active' => true,
            ]
        );

        $store = Store::firstOrCreate(
            ['slug' => 'testsprite-main'],
            [
                'organization_id' => $org->id,
                'name' => 'TestSprite Main Branch',
                'name_ar' => 'الفرع الرئيسي للاختبار',
                'slug' => 'testsprite-main',
                'timezone' => 'Asia/Muscat',
                'currency' => 'SAR',
                'locale' => 'en',
                'business_type' => 'grocery',
                'is_active' => true,
                'is_main_branch' => true,
            ]
        );

        // Materialize the canonical DefaultRoleTemplates (owner, manager, cashier,
        // accountant, inventory clerk, …) as predefined Spatie roles scoped to this
        // store. The CheckPermission middleware resolves the user's role enum →
        // store role of the same name → permissions, so without this step every
        // non-Owner user gets a 403 on permission-gated routes.
        app(RoleService::class)->syncDefaultTemplates($store->id);

        $users = [
            ['name' => 'TS Owner',      'email' => 'owner@testsprite.local',      'phone' => '+96890000001', 'role' => UserRole::Owner,          'pin' => '1111'],
            ['name' => 'TS Manager',    'email' => 'manager@testsprite.local',    'phone' => '+96890000002', 'role' => UserRole::BranchManager,  'pin' => '2222'],
            ['name' => 'TS Cashier',    'email' => 'cashier@testsprite.local',    'phone' => '+96890000003', 'role' => UserRole::Cashier,        'pin' => '3333'],
            ['name' => 'TS Inventory',  'email' => 'inventory@testsprite.local',  'phone' => '+96890000004', 'role' => UserRole::InventoryClerk, 'pin' => '4444'],
            ['name' => 'TS Accountant', 'email' => 'accountant@testsprite.local', 'phone' => '+96890000005', 'role' => UserRole::Accountant,     'pin' => '5555'],
        ];

        foreach ($users as $u) {
            User::firstOrCreate(
                ['email' => $u['email']],
                [
                    'organization_id'   => $org->id,
                    'store_id'          => $store->id,
                    'name'              => $u['name'],
                    'email'             => $u['email'],
                    'phone'             => $u['phone'],
                    'password_hash'     => Hash::make('Password123!'),
                    'pin_hash'          => Hash::make($u['pin']),
                    'role'              => $u['role'],
                    'locale'            => 'en',
                    'is_active'         => true,
                    'email_verified_at' => now(),
                    'last_login_at'     => now(),
                ]
            );
        }

        $this->command->info('TestSprite users seeded: owner@testsprite.local / Password123! (and 4 others).');
    }
}
