<?php

namespace App\Domain\WameedAI\Policies;

use App\Models\User;
use App\Domain\WameedAI\Models\AIFeatureDefinition;
use App\Domain\WameedAI\Models\AIStoreConfig;

class WameedAIPolicy
{
    private const PREMIUM_FEATURES = [
        'menu_engineering',
        'recipe_optimizer',
        'flower_lifecycle',
        'device_valuation',
        'prescription_validator',
        'custom_cake_estimator',
        'invoice_ocr',
        'smart_scheduling',
        'commission_optimizer',
        'revenue_anomaly',
        'efficiency_score',
        'competitor_pricing',
        'weather_demand',
        'seasonal_planner',
        'arabic_seo',
        'social_content',
        'customer_response',
    ];

    public function accessFeature(User $user, string $featureSlug): bool
    {
        if (!$user->store_id) {
            return false;
        }

        $storeConfig = AIStoreConfig::where('store_id', $user->store_id)
            ->where('is_enabled', true)
            ->first();

        if (!$storeConfig) {
            return false;
        }

        $feature = AIFeatureDefinition::where('slug', $featureSlug)
            ->where('is_enabled', true)
            ->first();

        if (!$feature) {
            return false;
        }

        if (in_array($featureSlug, self::PREMIUM_FEATURES)) {
            $plan = $storeConfig->subscription_plan ?? 'basic';
            if (!in_array($plan, ['premium', 'enterprise'])) {
                return false;
            }
        }

        return $user->can('wameed_ai.use') || $user->can('wameed_ai.manage');
    }

    public function manageFeatures(User $user): bool
    {
        return $user->can('wameed_ai.manage');
    }

    public function viewUsage(User $user): bool
    {
        return $user->can('wameed_ai.use') || $user->can('wameed_ai.manage');
    }

    public function manageConfig(User $user): bool
    {
        return $user->can('wameed_ai.manage');
    }
}
