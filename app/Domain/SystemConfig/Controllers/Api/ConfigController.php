<?php

namespace App\Domain\SystemConfig\Controllers\Api;

use App\Domain\SystemConfig\Models\AgeRestrictedCategory;
use App\Domain\SystemConfig\Models\CertifiedHardware;
use App\Domain\SystemConfig\Models\FeatureFlag;
use App\Domain\SystemConfig\Models\MasterTranslationString;
use App\Domain\SystemConfig\Models\PaymentMethod;
use App\Domain\SystemConfig\Models\SecurityPolicyDefault;
use App\Domain\SystemConfig\Models\SupportedLocale;
use App\Domain\SystemConfig\Models\SystemSetting;
use App\Domain\SystemConfig\Models\TaxExemptionType;
use App\Domain\SystemConfig\Models\TranslationVersion;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ConfigController extends BaseApiController
{
    /**
     * GET /config/feature-flags
     *
     * Returns resolved feature flags for the calling store,
     * considering plan, rollout %, and store targeting.
     */
    public function featureFlags(Request $request): JsonResponse
    {
        $storeId = $request->header('X-Store-Id');
        $planId = $request->header('X-Plan-Id');

        $flags = Cache::remember('config:feature_flags', 60, function () {
            return FeatureFlag::where('is_enabled', true)->get();
        });

        $resolved = $flags->filter(function ($flag) use ($storeId, $planId) {
            // Check store targeting
            $targetStores = $flag->target_store_ids ?? [];
            if (!empty($targetStores) && $storeId && !in_array($storeId, $targetStores)) {
                return false;
            }

            // Check plan targeting
            $targetPlans = $flag->target_plan_ids ?? [];
            if (!empty($targetPlans) && $planId && !in_array($planId, $targetPlans)) {
                return false;
            }

            // Check rollout percentage
            if ($flag->rollout_percentage < 100 && $storeId) {
                $hash = crc32($flag->flag_key . $storeId);
                if (($hash % 100) >= $flag->rollout_percentage) {
                    return false;
                }
            }

            return true;
        })->mapWithKeys(fn ($flag) => [$flag->flag_key => true]);

        return $this->success($resolved);
    }

    /**
     * GET /config/maintenance
     *
     * Returns maintenance mode status + banner message.
     */
    public function maintenance(): JsonResponse
    {
        $settings = Cache::remember('config:maintenance', 300, function () {
            return SystemSetting::where('group', 'maintenance')
                ->get()
                ->pluck('value', 'key')
                ->toArray();
        });

        return $this->success([
            'is_enabled' => filter_var($settings['maintenance_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'banner_message_en' => $settings['maintenance_banner_en'] ?? null,
            'banner_message_ar' => $settings['maintenance_banner_ar'] ?? null,
            'expected_end_time' => $settings['maintenance_expected_end'] ?? null,
        ]);
    }

    /**
     * GET /config/tax
     *
     * Returns global VAT rate and active tax exemption types.
     */
    public function tax(): JsonResponse
    {
        $vatSettings = Cache::remember('config:vat', 300, function () {
            return SystemSetting::where('group', 'vat')
                ->get()
                ->pluck('value', 'key')
                ->toArray();
        });

        $exemptions = Cache::remember('config:tax_exemptions', 300, function () {
            return TaxExemptionType::where('is_active', true)
                ->select('id', 'code', 'name', 'name_ar', 'required_documents')
                ->get();
        });

        return $this->success([
            'vat_rate' => (float) ($vatSettings['vat_rate'] ?? 15),
            'vat_registration_number' => $vatSettings['vat_registration_number'] ?? null,
            'exemption_types' => $exemptions,
        ]);
    }

    /**
     * GET /config/age-restrictions
     *
     * Returns active age-restricted categories.
     */
    public function ageRestrictions(): JsonResponse
    {
        $categories = Cache::remember('config:age_restrictions', 300, function () {
            return AgeRestrictedCategory::where('is_active', true)
                ->select('id', 'category_slug', 'min_age')
                ->get();
        });

        return $this->success($categories);
    }

    /**
     * GET /config/payment-methods
     *
     * Returns active payment methods with display info and config schema.
     */
    public function paymentMethods(): JsonResponse
    {
        $methods = Cache::remember('config:payment_methods', 300, function () {
            return PaymentMethod::where('is_active', true)
                ->orderBy('sort_order')
                ->select('id', 'method_key', 'name', 'name_ar', 'icon', 'category', 'requires_terminal', 'requires_customer_profile', 'provider_config_schema', 'sort_order')
                ->get();
        });

        return $this->success($methods);
    }

    /**
     * GET /config/hardware-catalog
     *
     * Returns certified hardware list for POS auto-detection and setup.
     */
    public function hardwareCatalog(): JsonResponse
    {
        $hardware = Cache::remember('config:hardware_catalog', 300, function () {
            return CertifiedHardware::where('is_active', true)
                ->select('id', 'device_type', 'brand', 'model', 'driver_protocol', 'connection_types', 'firmware_version_min', 'paper_widths', 'setup_instructions', 'setup_instructions_ar', 'is_certified')
                ->get();
        });

        return $this->success($hardware);
    }

    /**
     * GET /config/translations/{locale}
     *
     * Returns full master translation strings for the given locale.
     */
    public function translations(string $locale): JsonResponse
    {
        $column = $locale === 'ar' ? 'value_ar' : 'value_en';

        $strings = Cache::remember("config:translations:{$locale}", 300, function () use ($column) {
            return MasterTranslationString::pluck($column, 'string_key');
        });

        return $this->success($strings);
    }

    /**
     * GET /config/translations/version
     *
     * Returns current translation version hash.
     */
    public function translationVersion(): JsonResponse
    {
        $version = Cache::remember('config:translation_version', 300, function () {
            return TranslationVersion::orderByDesc('published_at')->first();
        });

        return $this->success([
            'version_hash' => $version?->version_hash,
            'published_at' => $version?->published_at,
        ]);
    }

    /**
     * GET /config/locales
     *
     * Returns list of active supported locales with formatting info.
     */
    public function locales(): JsonResponse
    {
        $locales = Cache::remember('config:locales', 300, function () {
            return SupportedLocale::where('is_active', true)
                ->select('locale_code', 'language_name', 'language_name_native', 'direction', 'date_format', 'number_format', 'calendar_system', 'is_default')
                ->get();
        });

        return $this->success($locales);
    }

    /**
     * GET /config/security-policies
     *
     * Returns platform security policy defaults.
     */
    public function securityPolicies(): JsonResponse
    {
        $policy = Cache::remember('config:security_policies', 300, function () {
            return SecurityPolicyDefault::first();
        });

        if (!$policy) {
            return $this->success([
                'session_timeout_minutes' => 30,
                'require_reauth_on_wake' => true,
                'pin_min_length' => 4,
                'pin_complexity' => 'numeric_only',
                'require_unique_pins' => true,
                'pin_expiry_days' => 0,
                'biometric_enabled_default' => true,
                'biometric_can_replace_pin' => false,
                'max_failed_login_attempts' => 5,
                'lockout_duration_minutes' => 15,
                'failed_attempt_alert_to_owner' => true,
                'device_registration_policy' => 'open',
                'max_devices_per_store' => 10,
            ]);
        }

        return $this->success($policy->only([
            'session_timeout_minutes',
            'require_reauth_on_wake',
            'pin_min_length',
            'pin_complexity',
            'require_unique_pins',
            'pin_expiry_days',
            'biometric_enabled_default',
            'biometric_can_replace_pin',
            'max_failed_login_attempts',
            'lockout_duration_minutes',
            'failed_attempt_alert_to_owner',
            'device_registration_policy',
            'max_devices_per_store',
        ]));
    }
}
