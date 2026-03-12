<?php

namespace Database\Seeders;

use App\Domain\Auth\Enums\UserRole;
use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create demo organization
        $org = Organization::firstOrCreate(
            ['slug' => 'thawani-demo'],
            [
                'name' => 'Thawani Demo Store',
                'name_ar' => 'متجر ثواني التجريبي',
                'slug' => 'thawani-demo',
                'country' => 'OM',
                'business_type' => 'retail',
                'email' => 'demo@thawani.om',
                'is_active' => true,
            ]
        );

        // Create demo store
        $store = Store::firstOrCreate(
            ['slug' => 'thawani-demo-main'],
            [
                'organization_id' => $org->id,
                'name' => 'Thawani Demo Main Branch',
                'name_ar' => 'الفرع الرئيسي - ثواني التجريبي',
                'slug' => 'thawani-demo-main',
                'timezone' => 'Asia/Muscat',
                'currency' => 'OMR',
                'locale' => 'ar',
                'business_type' => 'retail',
                'is_active' => true,
                'is_main_branch' => true,
            ]
        );

        // Create demo users
        $users = [
            [
                'name' => 'Demo Owner',
                'email' => 'owner@thawani.om',
                'phone' => '+96891234567',
                'role' => UserRole::Owner,
                'pin' => '1111',
            ],
            [
                'name' => 'Demo Manager',
                'email' => 'manager@thawani.om',
                'phone' => '+96891234568',
                'role' => UserRole::BranchManager,
                'pin' => '2222',
            ],
            [
                'name' => 'Demo Cashier',
                'email' => 'cashier@thawani.om',
                'phone' => '+96891234569',
                'role' => UserRole::Cashier,
                'pin' => '3333',
            ],
            [
                'name' => 'Demo Inventory',
                'email' => 'inventory@thawani.om',
                'phone' => '+96891234570',
                'role' => UserRole::InventoryClerk,
                'pin' => '4444',
            ],
            [
                'name' => 'Demo Accountant',
                'email' => 'accountant@thawani.om',
                'phone' => '+96891234571',
                'role' => UserRole::Accountant,
                'pin' => '5555',
            ],
        ];

        foreach ($users as $userData) {
            User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'organization_id' => $org->id,
                    'store_id' => $store->id,
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'phone' => $userData['phone'],
                    'password_hash' => Hash::make('password'),
                    'pin_hash' => Hash::make($userData['pin']),
                    'role' => $userData['role'],
                    'locale' => 'ar',
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'last_login_at' => now(),
                ]
            );
        }

        $this->command->info('Demo users created successfully.');
    }
}
