<?php

namespace Database\Seeders;

use App\Domain\WameedAI\Seeders\AIFeatureDefinitionSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Order matters — each seeder depends on the ones above it.
     */
    public function run(): void
    {
        // 1. Subscription plans (needed by TestDataSeeder)
        $this->call(SubscriptionPlanSeeder::class);

        // 2. Admin permissions (needed by TestDataSeeder super-admin setup)
        $this->call(ComprehensivePermissionSeeder::class);
        $this->call(AllPermissionsSyncSeeder::class);

        // 3. Core org / store / users (depends on plans + permissions)
        $this->call(TestDataSeeder::class);

        // 4. Roles for all stores + predefined permission assignments
        $this->call(AllRolesSeeder::class);
        $this->call(PredefinedRolePermissionsSeeder::class);

        // 5. System-wide reference data
        $this->call(SystemSettingSeeder::class);
        $this->call(NotificationTemplateSeeder::class);
        $this->call(SystemLabelTemplateSeeder::class);

        // 6. AI feature definitions & LLM models
        $this->call(AIFeatureDefinitionSeeder::class);
        $this->call(AILlmModelSeeder::class);

        // 7. Comprehensive test data for all remaining tables
        $this->call(ComprehensiveTestDataSeeder::class);

        // 8. Provider default role templates (syncs canonical 16 roles → default_role_templates)
        $this->call(ProviderDefaultRoleTemplatesSeeder::class);
    }
}
