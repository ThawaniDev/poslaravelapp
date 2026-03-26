<?php

namespace App\Domain\ContentOnboarding\Services;

use App\Domain\ContentOnboarding\Models\BusinessType;
use App\Domain\ContentOnboarding\Models\CfdTheme;
use App\Domain\ContentOnboarding\Models\LabelLayoutTemplate;
use App\Domain\ContentOnboarding\Models\PlatformUiDefault;
use App\Domain\ContentOnboarding\Models\PosLayoutTemplate;
use App\Domain\ContentOnboarding\Models\ReceiptLayoutTemplate;
use App\Domain\ContentOnboarding\Models\SignageTemplate;
use App\Domain\ContentOnboarding\Models\Theme;
use App\Domain\PosCustomization\Models\PosCustomizationSetting;
use App\Domain\Shared\Models\UserPreference;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PosLayoutService
{
    private const CACHE_TTL = 300; // 5 minutes

    // ─── Cascade Resolution ──────────────────────────────────

    /**
     * Resolve full UI preferences for a user using cascade:
     * user → store → platform → hardcoded defaults.
     */
    public function resolvePreferences(string $userId, ?string $storeId = null): array
    {
        $cacheKey = "ui_preferences:{$userId}";

        return Cache::remember($cacheKey, 60, function () use ($userId, $storeId) {
            $userPref = UserPreference::where('user_id', $userId)->first();
            $storeSetting = $storeId ? PosCustomizationSetting::where('store_id', $storeId)->first() : null;
            $platformDefaults = $this->getPlatformDefaults();

            $hardcoded = [
                'handedness' => 'right',
                'font_size' => 'medium',
                'theme' => 'light_classic',
                'pos_layout_id' => null,
            ];

            return [
                'handedness' => $userPref?->pos_handedness?->value
                    ?? $storeSetting?->handedness
                    ?? $platformDefaults['handedness']
                    ?? $hardcoded['handedness'],

                'font_size' => $userPref?->font_size?->value
                    ?? $platformDefaults['font_size']
                    ?? $hardcoded['font_size'],

                'theme' => $userPref?->theme?->value
                    ?? $storeSetting?->theme?->value
                    ?? $platformDefaults['theme']
                    ?? $hardcoded['theme'],

                'pos_layout_id' => $userPref?->pos_layout_id
                    ?? $hardcoded['pos_layout_id'],

                'resolved_from' => [
                    'handedness' => $userPref?->pos_handedness ? 'user' : ($storeSetting?->handedness ? 'store' : 'platform'),
                    'font_size' => $userPref?->font_size ? 'user' : 'platform',
                    'theme' => $userPref?->theme ? 'user' : ($storeSetting?->theme ? 'store' : 'platform'),
                    'pos_layout_id' => $userPref?->pos_layout_id ? 'user' : 'default',
                ],
            ];
        });
    }

    /**
     * Get platform-wide UI defaults as key-value array.
     */
    public function getPlatformDefaults(): array
    {
        return Cache::remember('platform_ui_defaults', self::CACHE_TTL, function () {
            return PlatformUiDefault::pluck('value', 'key')->toArray();
        });
    }

    /**
     * Update a single platform default.
     */
    public function updatePlatformDefault(string $key, string $value): void
    {
        PlatformUiDefault::updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
        Cache::forget('platform_ui_defaults');
    }

    /**
     * Flush all preference caches for a user.
     */
    public function flushUserCache(string $userId): void
    {
        Cache::forget("ui_preferences:{$userId}");
    }

    /**
     * Flush platform defaults cache.
     */
    public function flushPlatformCache(): void
    {
        Cache::forget('platform_ui_defaults');
    }

    // ─── Plan-Filtered Queries ───────────────────────────────

    /**
     * Get layouts available for a business type + subscription plan.
     */
    public function getAvailableLayouts(string $businessTypeSlug, ?string $planId = null): Collection
    {
        $query = PosLayoutTemplate::where('is_active', true)
            ->whereHas('businessType', fn ($q) => $q->where('slug', $businessTypeSlug));

        if ($planId) {
            $query->where(function ($q) use ($planId) {
                $q->whereHas('subscriptionPlans', fn ($sq) => $sq->where('subscription_plans.id', $planId))
                    ->orWhereDoesntHave('subscriptionPlans');
            });
        }

        return $query->orderBy('sort_order')->get();
    }

    /**
     * Get themes available for a subscription plan.
     */
    public function getAvailableThemes(?string $planId = null): Collection
    {
        $query = Theme::where('is_active', true);

        if ($planId) {
            $query->where(function ($q) use ($planId) {
                $q->whereHas('subscriptionPlans', fn ($sq) => $sq->where('subscription_plans.id', $planId))
                    ->orWhereDoesntHave('subscriptionPlans');
            });
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Get receipt layout templates available for a plan.
     */
    public function getAvailableReceiptTemplates(?string $planId = null): Collection
    {
        $query = ReceiptLayoutTemplate::where('is_active', true);

        if ($planId) {
            $query->where(function ($q) use ($planId) {
                $q->whereHas('subscriptionPlans', fn ($sq) => $sq->where('subscription_plans.id', $planId))
                    ->orWhereDoesntHave('subscriptionPlans');
            });
        }

        return $query->orderBy('sort_order')->get();
    }

    /**
     * Get a single receipt template by slug.
     */
    public function getReceiptTemplateBySlug(string $slug): ?ReceiptLayoutTemplate
    {
        return ReceiptLayoutTemplate::where('slug', $slug)->where('is_active', true)->first();
    }

    /**
     * Get CFD themes available for a plan.
     */
    public function getAvailableCfdThemes(?string $planId = null): Collection
    {
        $query = CfdTheme::where('is_active', true);

        if ($planId) {
            $query->where(function ($q) use ($planId) {
                $q->whereHas('subscriptionPlans', fn ($sq) => $sq->where('subscription_plans.id', $planId))
                    ->orWhereDoesntHave('subscriptionPlans');
            });
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Get a single CFD theme by slug.
     */
    public function getCfdThemeBySlug(string $slug): ?CfdTheme
    {
        return CfdTheme::where('slug', $slug)->where('is_active', true)->first();
    }

    /**
     * Get signage templates for a business type slug + plan.
     */
    public function getAvailableSignageTemplates(string $businessTypeSlug, ?string $planId = null): Collection
    {
        $query = SignageTemplate::where('is_active', true)
            ->whereHas('businessTypes', fn ($q) => $q->where('business_types.slug', $businessTypeSlug));

        if ($planId) {
            $query->where(function ($q) use ($planId) {
                $q->whereHas('subscriptionPlans', fn ($sq) => $sq->where('subscription_plans.id', $planId))
                    ->orWhereDoesntHave('subscriptionPlans');
            });
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Get a single signage template by slug.
     */
    public function getSignageTemplateBySlug(string $slug): ?SignageTemplate
    {
        return SignageTemplate::where('slug', $slug)->where('is_active', true)->first();
    }

    /**
     * Get label layout templates for a business type slug + plan.
     */
    public function getAvailableLabelTemplates(string $businessTypeSlug, ?string $planId = null): Collection
    {
        $query = LabelLayoutTemplate::where('is_active', true)
            ->whereHas('businessTypes', fn ($q) => $q->where('business_types.slug', $businessTypeSlug));

        if ($planId) {
            $query->where(function ($q) use ($planId) {
                $q->whereHas('subscriptionPlans', fn ($sq) => $sq->where('subscription_plans.id', $planId))
                    ->orWhereDoesntHave('subscriptionPlans');
            });
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Get a single label template by slug.
     */
    public function getLabelTemplateBySlug(string $slug): ?LabelLayoutTemplate
    {
        return LabelLayoutTemplate::where('slug', $slug)->where('is_active', true)->first();
    }

    // ─── User Preference Management ─────────────────────────

    /**
     * Update user-level UI preferences.
     */
    public function updateUserPreferences(string $userId, array $data): UserPreference
    {
        $pref = UserPreference::updateOrCreate(
            ['user_id' => $userId],
            array_filter($data, fn ($v) => $v !== null),
        );

        $this->flushUserCache($userId);

        return $pref;
    }

    /**
     * Update store-level default preferences.
     */
    public function updateStoreDefaults(string $storeId, array $data): PosCustomizationSetting
    {
        $setting = PosCustomizationSetting::updateOrCreate(
            ['store_id' => $storeId],
            array_merge(
                array_filter($data, fn ($v) => $v !== null),
                ['sync_version' => now()->timestamp],
            ),
        );

        return $setting;
    }

    // ─── Business Type Helpers ───────────────────────────────

    /**
     * Get active business types with layout counts.
     */
    public function getActiveBusinessTypes(): Collection
    {
        return BusinessType::where('is_active', true)
            ->withCount('posLayoutTemplates')
            ->orderBy('sort_order')
            ->get();
    }
}
