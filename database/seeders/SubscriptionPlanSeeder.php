<?php

namespace Database\Seeders;

use App\Domain\Subscription\Models\PlanAddOn;
use App\Domain\Subscription\Models\PlanFeatureToggle;
use App\Domain\Subscription\Models\PlanLimit;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Seed 3 subscription plans: Starter, Growth, Enterprise.
     */
    public function run(): void
    {
        $plans = $this->createPlans();

        foreach ($plans as $planData) {
            $features = $planData['features'];
            $limits = $planData['limits'];
            unset($planData['features'], $planData['limits']);

            $plan = SubscriptionPlan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData,
            );

            // Sync feature toggles
            PlanFeatureToggle::where('subscription_plan_id', $plan->id)->delete();
            foreach ($features as $feature) {
                PlanFeatureToggle::create([
                    'subscription_plan_id' => $plan->id,
                    ...$feature,
                ]);
            }

            // Sync limits
            PlanLimit::where('subscription_plan_id', $plan->id)->delete();
            foreach ($limits as $limit) {
                PlanLimit::create([
                    'subscription_plan_id' => $plan->id,
                    ...$limit,
                ]);
            }
        }

        $this->createAddOns();

        $this->command?->info('Subscription plans seeded: Starter, Growth, Enterprise + 3 add-ons.');
    }

    private function createPlans(): array
    {
        return [
            // ─── Starter ── Free tier
            [
                'name' => 'Starter',
                'name_ar' => 'المبتدئ',
                'slug' => 'starter',
                'monthly_price' => 0,
                'annual_price' => 0,
                'trial_days' => 14,
                'grace_period_days' => 3,
                'is_active' => true,
                'is_highlighted' => false,
                'sort_order' => 1,
                'features' => [
                    ['feature_key' => 'pos', 'is_enabled' => true],
                    ['feature_key' => 'inventory', 'is_enabled' => true],
                    ['feature_key' => 'reports_basic', 'is_enabled' => true],
                    ['feature_key' => 'reports_advanced', 'is_enabled' => false],
                    ['feature_key' => 'multi_branch', 'is_enabled' => false],
                    ['feature_key' => 'customer_loyalty', 'is_enabled' => false],
                    ['feature_key' => 'api_access', 'is_enabled' => false],
                    ['feature_key' => 'white_label', 'is_enabled' => false],
                    ['feature_key' => 'priority_support', 'is_enabled' => false],
                ],
                'limits' => [
                    ['limit_key' => 'products', 'limit_value' => 50, 'price_per_extra_unit' => null],
                    ['limit_key' => 'staff_members', 'limit_value' => 2, 'price_per_extra_unit' => null],
                    ['limit_key' => 'stores', 'limit_value' => 1, 'price_per_extra_unit' => null],
                    ['limit_key' => 'transactions_per_month', 'limit_value' => 500, 'price_per_extra_unit' => null],
                    ['limit_key' => 'storage_mb', 'limit_value' => 100, 'price_per_extra_unit' => null],
                ],
            ],

            // ─── Growth ── Mid tier (highlighted)
            [
                'name' => 'Growth',
                'name_ar' => 'النمو',
                'slug' => 'growth',
                'monthly_price' => 29.99,
                'annual_price' => 299.99,
                'trial_days' => 14,
                'grace_period_days' => 7,
                'is_active' => true,
                'is_highlighted' => true,
                'sort_order' => 2,
                'features' => [
                    ['feature_key' => 'pos', 'is_enabled' => true],
                    ['feature_key' => 'inventory', 'is_enabled' => true],
                    ['feature_key' => 'reports_basic', 'is_enabled' => true],
                    ['feature_key' => 'reports_advanced', 'is_enabled' => true],
                    ['feature_key' => 'multi_branch', 'is_enabled' => true],
                    ['feature_key' => 'customer_loyalty', 'is_enabled' => true],
                    ['feature_key' => 'api_access', 'is_enabled' => false],
                    ['feature_key' => 'white_label', 'is_enabled' => false],
                    ['feature_key' => 'priority_support', 'is_enabled' => false],
                ],
                'limits' => [
                    ['limit_key' => 'products', 'limit_value' => 1000, 'price_per_extra_unit' => 0.05],
                    ['limit_key' => 'staff_members', 'limit_value' => 10, 'price_per_extra_unit' => 5.00],
                    ['limit_key' => 'stores', 'limit_value' => 3, 'price_per_extra_unit' => 15.00],
                    ['limit_key' => 'transactions_per_month', 'limit_value' => 10000, 'price_per_extra_unit' => 0.01],
                    ['limit_key' => 'storage_mb', 'limit_value' => 5000, 'price_per_extra_unit' => 0.10],
                ],
            ],

            // ─── Enterprise ── Top tier
            [
                'name' => 'Enterprise',
                'name_ar' => 'المؤسسات',
                'slug' => 'enterprise',
                'monthly_price' => 99.99,
                'annual_price' => 999.99,
                'trial_days' => 30,
                'grace_period_days' => 14,
                'is_active' => true,
                'is_highlighted' => false,
                'sort_order' => 3,
                'features' => [
                    ['feature_key' => 'pos', 'is_enabled' => true],
                    ['feature_key' => 'inventory', 'is_enabled' => true],
                    ['feature_key' => 'reports_basic', 'is_enabled' => true],
                    ['feature_key' => 'reports_advanced', 'is_enabled' => true],
                    ['feature_key' => 'multi_branch', 'is_enabled' => true],
                    ['feature_key' => 'customer_loyalty', 'is_enabled' => true],
                    ['feature_key' => 'api_access', 'is_enabled' => true],
                    ['feature_key' => 'white_label', 'is_enabled' => true],
                    ['feature_key' => 'priority_support', 'is_enabled' => true],
                ],
                'limits' => [
                    ['limit_key' => 'products', 'limit_value' => 100000, 'price_per_extra_unit' => 0.01],
                    ['limit_key' => 'staff_members', 'limit_value' => 100, 'price_per_extra_unit' => 3.00],
                    ['limit_key' => 'stores', 'limit_value' => 50, 'price_per_extra_unit' => 10.00],
                    ['limit_key' => 'transactions_per_month', 'limit_value' => 500000, 'price_per_extra_unit' => 0.005],
                    ['limit_key' => 'storage_mb', 'limit_value' => 50000, 'price_per_extra_unit' => 0.05],
                ],
            ],
        ];
    }

    private function createAddOns(): void
    {
        $addOns = [
            [
                'name' => 'Extra Storage (10 GB)',
                'name_ar' => 'مساحة تخزين إضافية (10 جيجابايت)',
                'slug' => 'extra-storage-10gb',
                'monthly_price' => 4.99,
                'description' => 'Add 10 GB of additional cloud storage.',
                'is_active' => true,
            ],
            [
                'name' => 'Priority Support',
                'name_ar' => 'دعم أولوي',
                'slug' => 'priority-support',
                'monthly_price' => 19.99,
                'description' => '24/7 priority support with dedicated account manager.',
                'is_active' => true,
            ],
            [
                'name' => 'Advanced Analytics',
                'name_ar' => 'تحليلات متقدمة',
                'slug' => 'advanced-analytics',
                'monthly_price' => 9.99,
                'description' => 'Unlock advanced reporting dashboards and export options.',
                'is_active' => true,
            ],
        ];

        foreach ($addOns as $addOn) {
            PlanAddOn::updateOrCreate(
                ['slug' => $addOn['slug']],
                $addOn,
            );
        }
    }
}
