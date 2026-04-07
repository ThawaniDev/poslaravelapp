<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Legacy seeder — delegates to ComprehensivePermissionSeeder.
 *
 * Run: php artisan db:seed --class=RolePermissionSeeder
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ComprehensivePermissionSeeder::class);
    }
}
