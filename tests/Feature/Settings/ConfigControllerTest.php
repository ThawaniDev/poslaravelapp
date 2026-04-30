<?php

namespace Tests\Feature\Settings;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\SystemConfig\Models\FeatureFlag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * ConfigController Feature Tests
 *
 * Covers all 10 /config/* provider-facing endpoints:
 *   - GET /config/feature-flags
 *   - GET /config/maintenance         (public)
 *   - GET /config/tax
 *   - GET /config/age-restrictions
 *   - GET /config/payment-methods
 *   - GET /config/hardware-catalog
 *   - GET /config/translations/{locale}
 *   - GET /config/translations/version
 *   - GET /config/locales
 *   - GET /config/security-policies
 */
class ConfigControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Config Test Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Config Test Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Config User',
            'email' => 'config@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('config-test', ['*'])->plainTextToken;

        // Clear all relevant caches between tests
        Cache::flush();
    }

    // ─── Feature Flags ──────────────────────────────────────────────────────

    public function test_get_feature_flags_requires_auth(): void
    {
        $this->getJson('/api/v2/config/feature-flags')
            ->assertUnauthorized();
    }

    public function test_get_feature_flags_returns_enabled_flags(): void
    {
        FeatureFlag::forceCreate([
            'id' => Str::uuid(),
            'flag_key' => 'loyalty_program',
            'is_enabled' => true,
            'rollout_percentage' => 100,
            'target_plan_ids' => null,
            'target_store_ids' => null,
        ]);

        FeatureFlag::forceCreate([
            'id' => Str::uuid(),
            'flag_key' => 'advanced_reports',
            'is_enabled' => false,
            'rollout_percentage' => 100,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v2/config/feature-flags')
            ->assertOk()
            ->assertJsonPath('data.loyalty_program', true)
            ->assertJsonMissingPath('data.advanced_reports');
    }

    public function test_feature_flags_respects_store_targeting(): void
    {
        $otherStoreId = Str::uuid()->toString();

        FeatureFlag::forceCreate([
            'id' => Str::uuid(),
            'flag_key' => 'exclusive_feature',
            'is_enabled' => true,
            'rollout_percentage' => 100,
            'target_store_ids' => [$otherStoreId], // Not our store
        ]);

        $this->withToken($this->token)
            ->withHeaders(['X-Store-Id' => $this->store->id])
            ->getJson('/api/v2/config/feature-flags')
            ->assertOk()
            ->assertJsonMissingPath('data.exclusive_feature');
    }

    public function test_feature_flags_are_cached(): void
    {
        // Seed a flag
        FeatureFlag::forceCreate([
            'id' => Str::uuid(),
            'flag_key' => 'cached_flag',
            'is_enabled' => true,
            'rollout_percentage' => 100,
        ]);

        // First call — populates cache
        $this->withToken($this->token)->getJson('/api/v2/config/feature-flags')->assertOk();

        // Delete from DB — cached version should still return it
        FeatureFlag::where('flag_key', 'cached_flag')->delete();

        $this->withToken($this->token)
            ->getJson('/api/v2/config/feature-flags')
            ->assertOk()
            ->assertJsonPath('data.cached_flag', true);
    }

    // ─── Maintenance ────────────────────────────────────────────────────────

    public function test_maintenance_is_publicly_accessible_without_auth(): void
    {
        $this->getJson('/api/v2/config/maintenance')
            ->assertOk()
            ->assertJsonStructure(['data' => ['is_enabled']]);
    }

    public function test_maintenance_returns_false_when_no_settings(): void
    {
        $this->getJson('/api/v2/config/maintenance')
            ->assertOk()
            ->assertJsonPath('data.is_enabled', false);
    }

    // ─── Tax ────────────────────────────────────────────────────────────────

    public function test_get_tax_requires_auth(): void
    {
        $this->getJson('/api/v2/config/tax')->assertUnauthorized();
    }

    public function test_get_tax_returns_vat_rate(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v2/config/tax')
            ->assertOk()
            ->assertJsonStructure(['data' => ['vat_rate', 'exemption_types']]);
    }

    public function test_tax_vat_rate_is_numeric(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/config/tax')
            ->assertOk();

        $vatRate = $response->json('data.vat_rate');
        $this->assertIsNumeric($vatRate);
        $this->assertGreaterThanOrEqual(0, $vatRate);
    }

    // ─── Age Restrictions ───────────────────────────────────────────────────

    public function test_get_age_restrictions_requires_auth(): void
    {
        $this->getJson('/api/v2/config/age-restrictions')->assertUnauthorized();
    }

    public function test_get_age_restrictions_returns_array(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v2/config/age-restrictions')
            ->assertOk()
            ->assertJsonIsArray('data');
    }

    // ─── Payment Methods ────────────────────────────────────────────────────

    public function test_get_payment_methods_requires_auth(): void
    {
        $this->getJson('/api/v2/config/payment-methods')->assertUnauthorized();
    }

    public function test_get_payment_methods_returns_array(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v2/config/payment-methods')
            ->assertOk()
            ->assertJsonIsArray('data');
    }

    // ─── Hardware Catalog ───────────────────────────────────────────────────

    public function test_get_hardware_catalog_requires_auth(): void
    {
        $this->getJson('/api/v2/config/hardware-catalog')->assertUnauthorized();
    }

    public function test_get_hardware_catalog_returns_array(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v2/config/hardware-catalog')
            ->assertOk()
            ->assertJsonIsArray('data');
    }

    // ─── Translations ───────────────────────────────────────────────────────

    public function test_get_translations_requires_auth(): void
    {
        $this->getJson('/api/v2/config/translations/ar')->assertUnauthorized();
    }

    public function test_get_translations_returns_key_value_map(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/config/translations/ar')
            ->assertOk();

        // data is a key-value map (object) or empty array when no strings seeded — both are valid
        $data = $response->json('data');
        $this->assertTrue(is_array($data) || is_object($data));
    }

    public function test_get_translations_different_locales(): void
    {
        foreach (['ar', 'en', 'ur', 'bn'] as $locale) {
            $this->withToken($this->token)
                ->getJson("/api/v2/config/translations/{$locale}")
                ->assertOk();
        }
    }

    // ─── Translation Version ────────────────────────────────────────────────

    public function test_get_translation_version_requires_auth(): void
    {
        $this->getJson('/api/v2/config/translations/version')->assertUnauthorized();
    }

    public function test_get_translation_version_returns_hash_field(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v2/config/translations/version')
            ->assertOk()
            ->assertJsonStructure(['data' => ['version_hash', 'published_at']]);
    }

    // ─── Locales ────────────────────────────────────────────────────────────

    public function test_get_locales_requires_auth(): void
    {
        $this->getJson('/api/v2/config/locales')->assertUnauthorized();
    }

    public function test_get_locales_returns_array(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v2/config/locales')
            ->assertOk()
            ->assertJsonIsArray('data');
    }

    // ─── Security Policies ──────────────────────────────────────────────────

    public function test_get_security_policies_requires_auth(): void
    {
        $this->getJson('/api/v2/config/security-policies')->assertUnauthorized();
    }

    public function test_get_security_policies_returns_expected_fields(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v2/config/security-policies')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
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
                ],
            ]);
    }

    public function test_security_policies_defaults_are_reasonable(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/config/security-policies')
            ->assertOk();

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(5, $data['session_timeout_minutes']);
        $this->assertLessThanOrEqual(480, $data['session_timeout_minutes']);
        $this->assertGreaterThanOrEqual(4, $data['pin_min_length']);
        $this->assertGreaterThanOrEqual(1, $data['max_failed_login_attempts']);
    }

    // ─── Cache Busting ──────────────────────────────────────────────────────

    public function test_all_config_endpoints_accept_cache_bust_param(): void
    {
        // Endpoints should work regardless of cache state
        $endpoints = [
            '/api/v2/config/feature-flags',
            '/api/v2/config/tax',
            '/api/v2/config/age-restrictions',
            '/api/v2/config/payment-methods',
            '/api/v2/config/hardware-catalog',
            '/api/v2/config/translations/ar',
            '/api/v2/config/translations/version',
            '/api/v2/config/locales',
            '/api/v2/config/security-policies',
        ];

        foreach ($endpoints as $endpoint) {
            Cache::flush();
            $this->withToken($this->token)
                ->getJson($endpoint)
                ->assertOk();
        }
    }
}
