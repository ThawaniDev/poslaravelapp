<?php

namespace Tests\Feature\Settings;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
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
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Extended ConfigController Feature Tests — real DB data, edge cases, API contract.
 *
 * Complements ConfigControllerTest by seeding actual model data for every
 * endpoint and asserting the precise response contract that the Flutter
 * MaintenanceStatus / TaxConfig / SecurityPolicy / etc. models expect.
 */
class ConfigControllerExtendedTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Extended Config Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Extended Config Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Extended Config User',
            'email' => 'ext-config@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('ext-config-test', ['*'])->plainTextToken;
        Cache::flush();
    }

    // ─── Maintenance — active mode ──────────────────────────────────────────

    /** Maintenance fields must match Flutter MaintenanceStatus.fromJson keys exactly. */
    public function test_maintenance_response_matches_flutter_contract(): void
    {
        $this->getJson('/api/v2/config/maintenance')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'is_active',
                'message',
                'message_ar',
                'ends_at',
                'allowed_ips',
            ]]);
    }

    public function test_maintenance_active_when_setting_enabled(): void
    {
        \DB::table('system_settings')->insert([
            'id'    => Str::uuid(),
            'key'   => 'maintenance_enabled',
            'value' => json_encode(true),
            'group' => 'maintenance',
        ]);
        \DB::table('system_settings')->insert([
            'id'    => Str::uuid(),
            'key'   => 'maintenance_banner_en',
            'value' => json_encode('We are upgrading the platform.'),
            'group' => 'maintenance',
        ]);
        \DB::table('system_settings')->insert([
            'id'    => Str::uuid(),
            'key'   => 'maintenance_banner_ar',
            'value' => json_encode('نحن نقوم بترقية المنصة.'),
            'group' => 'maintenance',
        ]);
        \DB::table('system_settings')->insert([
            'id'    => Str::uuid(),
            'key'   => 'maintenance_expected_end',
            'value' => json_encode('2030-01-01T06:00:00Z'),
            'group' => 'maintenance',
        ]);

        Cache::flush();

        $this->getJson('/api/v2/config/maintenance')
            ->assertOk()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.message', 'We are upgrading the platform.')
            ->assertJsonPath('data.message_ar', 'نحن نقوم بترقية المنصة.')
            ->assertJsonPath('data.ends_at', '2030-01-01T06:00:00Z');
    }

    public function test_maintenance_inactive_when_setting_is_false(): void
    {
        \DB::table('system_settings')->insert([
            'id'    => Str::uuid(),
            'key'   => 'maintenance_enabled',
            'value' => json_encode(false),
            'group' => 'maintenance',
        ]);

        Cache::flush();

        $this->getJson('/api/v2/config/maintenance')
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_maintenance_allowed_ips_field_is_present(): void
    {
        $response = $this->getJson('/api/v2/config/maintenance')->assertOk();
        $this->assertArrayHasKey('allowed_ips', $response->json('data'));
    }

    // ─── Feature Flags — edge cases ─────────────────────────────────────────

    public function test_feature_flags_resolves_only_enabled(): void
    {
        FeatureFlag::forceCreate([
            'id' => Str::uuid(), 'flag_key' => 'flag_on',
            'is_enabled' => true, 'rollout_percentage' => 100,
        ]);
        FeatureFlag::forceCreate([
            'id' => Str::uuid(), 'flag_key' => 'flag_off',
            'is_enabled' => false, 'rollout_percentage' => 100,
        ]);

        $this->withToken($this->token)->getJson('/api/v2/config/feature-flags')
            ->assertOk()
            ->assertJsonPath('data.flag_on', true)
            ->assertJsonMissingPath('data.flag_off');
    }

    public function test_feature_flags_plan_targeting_includes_correct_plan(): void
    {
        $planId = Str::uuid()->toString();

        FeatureFlag::forceCreate([
            'id' => Str::uuid(), 'flag_key' => 'plan_feature',
            'is_enabled' => true, 'rollout_percentage' => 100,
            'target_plan_ids' => [$planId],
        ]);

        // With matching plan → flag resolved
        $this->withToken($this->token)
            ->withHeaders(['X-Plan-Id' => $planId])
            ->getJson('/api/v2/config/feature-flags')
            ->assertOk()
            ->assertJsonPath('data.plan_feature', true);
    }

    public function test_feature_flags_plan_targeting_excludes_wrong_plan(): void
    {
        $planId = Str::uuid()->toString();

        FeatureFlag::forceCreate([
            'id' => Str::uuid(), 'flag_key' => 'plan_exclusive',
            'is_enabled' => true, 'rollout_percentage' => 100,
            'target_plan_ids' => [$planId],
        ]);

        // Different plan → excluded
        $this->withToken($this->token)
            ->withHeaders(['X-Plan-Id' => Str::uuid()->toString()])
            ->getJson('/api/v2/config/feature-flags')
            ->assertOk()
            ->assertJsonMissingPath('data.plan_exclusive');
    }

    public function test_feature_flags_store_targeting_includes_correct_store(): void
    {
        FeatureFlag::forceCreate([
            'id' => Str::uuid(), 'flag_key' => 'store_feature',
            'is_enabled' => true, 'rollout_percentage' => 100,
            'target_store_ids' => [$this->store->id],
        ]);

        $this->withToken($this->token)
            ->withHeaders(['X-Store-Id' => $this->store->id])
            ->getJson('/api/v2/config/feature-flags')
            ->assertOk()
            ->assertJsonPath('data.store_feature', true);
    }

    public function test_feature_flags_rollout_0_percent_excluded(): void
    {
        FeatureFlag::forceCreate([
            'id' => Str::uuid(), 'flag_key' => 'zero_rollout',
            'is_enabled' => true, 'rollout_percentage' => 0,
        ]);

        // CRC32 of any store+key with 0% rollout: 0 % 100 = 0 >= 0, always excluded
        $this->withToken($this->token)
            ->withHeaders(['X-Store-Id' => $this->store->id])
            ->getJson('/api/v2/config/feature-flags')
            ->assertOk()
            ->assertJsonMissingPath('data.zero_rollout');
    }

    public function test_feature_flags_rollout_100_percent_always_included(): void
    {
        FeatureFlag::forceCreate([
            'id' => Str::uuid(), 'flag_key' => 'full_rollout',
            'is_enabled' => true, 'rollout_percentage' => 100,
        ]);

        $this->withToken($this->token)
            ->withHeaders(['X-Store-Id' => $this->store->id])
            ->getJson('/api/v2/config/feature-flags')
            ->assertOk()
            ->assertJsonPath('data.full_rollout', true);
    }

    /** CRC32 bucketing must be deterministic for the same store+flag pair. */
    public function test_feature_flags_rollout_bucketing_is_deterministic(): void
    {
        FeatureFlag::forceCreate([
            'id' => Str::uuid(), 'flag_key' => 'deterministic_flag',
            'is_enabled' => true, 'rollout_percentage' => 50,
        ]);

        $storeId = $this->store->id;
        $headers = ['X-Store-Id' => $storeId];

        $r1 = $this->withToken($this->token)->withHeaders($headers)
            ->getJson('/api/v2/config/feature-flags')->json('data');
        Cache::flush();
        $r2 = $this->withToken($this->token)->withHeaders($headers)
            ->getJson('/api/v2/config/feature-flags')->json('data');

        $this->assertSame(
            isset($r1['deterministic_flag']),
            isset($r2['deterministic_flag']),
            'CRC32 bucketing must produce identical results for the same store+flag.',
        );
    }

    /** Flag with no store header + rollout < 100% should still be included (no storeId = skip rollout check). */
    public function test_feature_flags_no_store_id_skips_rollout_check(): void
    {
        FeatureFlag::forceCreate([
            'id' => Str::uuid(), 'flag_key' => 'partial_rollout',
            'is_enabled' => true, 'rollout_percentage' => 50,
        ]);

        // No X-Store-Id header → rollout check skipped → flag included
        $this->withToken($this->token)
            ->getJson('/api/v2/config/feature-flags')
            ->assertOk()
            ->assertJsonPath('data.partial_rollout', true);
    }

    // ─── Tax — with real data ────────────────────────────────────────────────

    public function test_tax_response_contract(): void
    {
        $this->withToken($this->token)->getJson('/api/v2/config/tax')
            ->assertOk()
            ->assertJsonStructure(['data' => ['vat_rate', 'vat_enabled', 'vat_number', 'exemption_types']]);
    }

    public function test_tax_returns_seeded_exemption_types(): void
    {
        TaxExemptionType::forceCreate([
            'id' => Str::uuid(), 'code' => 'GOV', 'name' => 'Government',
            'name_ar' => 'حكومة', 'required_documents' => json_encode(['official_letter']),
            'is_active' => true,
        ]);

        Cache::flush();

        $response = $this->withToken($this->token)->getJson('/api/v2/config/tax')->assertOk();

        $exemptions = $response->json('data.exemption_types');
        $this->assertNotEmpty($exemptions);
        $codes = array_column($exemptions, 'code');
        $this->assertContains('GOV', $codes);
    }

    public function test_tax_vat_rate_from_system_settings(): void
    {
        \DB::table('system_settings')->updateOrInsert(
            ['key' => 'vat_rate'],
            ['id' => Str::uuid(), 'value' => json_encode(5), 'group' => 'vat']
        );

        Cache::flush();

        $this->withToken($this->token)->getJson('/api/v2/config/tax')
            ->assertOk()
            ->assertJsonPath('data.vat_rate', 5);
    }

    public function test_tax_inactive_exemption_types_excluded(): void
    {
        TaxExemptionType::forceCreate([
            'id' => Str::uuid(), 'code' => 'INACTIVE', 'name' => 'Inactive Type',
            'name_ar' => 'نوع غير نشط', 'required_documents' => json_encode([]),
            'is_active' => false,
        ]);

        Cache::flush();

        $response = $this->withToken($this->token)->getJson('/api/v2/config/tax')->assertOk();
        $codes = array_column($response->json('data.exemption_types'), 'code');
        $this->assertNotContains('INACTIVE', $codes);
    }

    // ─── Age Restrictions — with real data ──────────────────────────────────

    public function test_age_restrictions_returns_only_active(): void
    {
        AgeRestrictedCategory::forceCreate([
            'id' => Str::uuid(), 'category_slug' => 'alcohol', 'min_age' => 21, 'is_active' => true,
        ]);
        AgeRestrictedCategory::forceCreate([
            'id' => Str::uuid(), 'category_slug' => 'deprecated', 'min_age' => 18, 'is_active' => false,
        ]);

        Cache::flush();

        $response = $this->withToken($this->token)->getJson('/api/v2/config/age-restrictions')->assertOk();

        $slugs = array_column($response->json('data'), 'category_slug');
        $this->assertContains('alcohol', $slugs);
        $this->assertNotContains('deprecated', $slugs);
    }

    public function test_age_restrictions_each_item_has_required_fields(): void
    {
        AgeRestrictedCategory::forceCreate([
            'id' => Str::uuid(), 'category_slug' => 'tobacco', 'min_age' => 18, 'is_active' => true,
        ]);

        Cache::flush();

        $response = $this->withToken($this->token)->getJson('/api/v2/config/age-restrictions')->assertOk();

        $item = collect($response->json('data'))->firstWhere('category_slug', 'tobacco');
        $this->assertNotNull($item);
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('category_slug', $item);
        $this->assertArrayHasKey('min_age', $item);
        $this->assertEquals(18, $item['min_age']);
    }

    // ─── Payment Methods — with real data ───────────────────────────────────

    public function test_payment_methods_returns_only_active(): void
    {
        PaymentMethod::forceCreate([
            'id' => Str::uuid(), 'method_key' => 'tabby', 'name' => 'Tabby BNPL',
            'name_ar' => 'تابي', 'category' => 'installment', 'is_active' => true,
            'requires_terminal' => false, 'sort_order' => 20,
        ]);
        PaymentMethod::forceCreate([
            'id' => Str::uuid(), 'method_key' => 'tamara', 'name' => 'Tamara Disabled',
            'name_ar' => 'تمارا', 'category' => 'installment', 'is_active' => false,
            'requires_terminal' => false, 'sort_order' => 21,
        ]);

        Cache::flush();

        $response = $this->withToken($this->token)->getJson('/api/v2/config/payment-methods')->assertOk();

        $keys = array_column($response->json('data'), 'method_key');
        $this->assertContains('tabby', $keys);
        $this->assertNotContains('tamara', $keys);
    }

    public function test_payment_methods_sorted_by_sort_order(): void
    {
        PaymentMethod::forceCreate([
            'id' => Str::uuid(), 'method_key' => 'mispay', 'name' => 'MisPay', 'name_ar' => 'ميس بي',
            'category' => 'credit', 'is_active' => true, 'requires_terminal' => false, 'sort_order' => 50,
        ]);
        PaymentMethod::forceCreate([
            'id' => Str::uuid(), 'method_key' => 'madfu', 'name' => 'Madfu', 'name_ar' => 'مدفو',
            'category' => 'credit', 'is_active' => true, 'requires_terminal' => false, 'sort_order' => 49,
        ]);

        Cache::flush();

        $response = $this->withToken($this->token)->getJson('/api/v2/config/payment-methods')->assertOk();

        $methods = $response->json('data');
        $keys = array_column($methods, 'method_key');
        $madFuPos = array_search('madfu', $keys);
        $misPayPos = array_search('mispay', $keys);
        $this->assertNotFalse($madFuPos);
        $this->assertNotFalse($misPayPos);
        $this->assertLessThan($misPayPos, $madFuPos, 'madfu (sort_order=49) should come before mispay (sort_order=50)');
    }

    // ─── Hardware Catalog — with real data ──────────────────────────────────

    public function test_hardware_catalog_returns_only_active(): void
    {
        CertifiedHardware::forceCreate([
            'id' => Str::uuid(), 'device_type' => 'receipt_printer', 'brand' => 'Epson', 'model' => 'TM-T82III',
            'driver_protocol' => 'esc_pos', 'connection_types' => ['usb', 'ethernet'],
            'is_certified' => true, 'is_active' => true,
        ]);
        CertifiedHardware::forceCreate([
            'id' => Str::uuid(), 'device_type' => 'barcode_scanner', 'brand' => 'OldBrand', 'model' => 'Legacy-100',
            'driver_protocol' => 'hid', 'connection_types' => ['usb'],
            'is_certified' => false, 'is_active' => false,
        ]);

        Cache::flush();

        $response = $this->withToken($this->token)->getJson('/api/v2/config/hardware-catalog')->assertOk();

        $models = array_column($response->json('data'), 'model');
        $this->assertContains('TM-T82III', $models);
        $this->assertNotContains('Legacy-100', $models);
    }

    public function test_hardware_catalog_item_has_connection_types(): void
    {
        CertifiedHardware::forceCreate([
            'id' => Str::uuid(), 'device_type' => 'cash_drawer', 'brand' => 'TestBrand', 'model' => 'CD-100',
            'driver_protocol' => 'generic', 'connection_types' => ['usb', 'bluetooth'],
            'is_certified' => true, 'is_active' => true,
        ]);

        Cache::flush();

        $response = $this->withToken($this->token)->getJson('/api/v2/config/hardware-catalog')->assertOk();

        $item = collect($response->json('data'))->firstWhere('model', 'CD-100');
        $this->assertIsArray($item['connection_types']);
        $this->assertContains('usb', $item['connection_types']);
        $this->assertContains('bluetooth', $item['connection_types']);
    }

    // ─── Translations — with real data ──────────────────────────────────────

    public function test_translations_returns_key_value_map_for_en(): void
    {
        MasterTranslationString::forceCreate([
            'id' => Str::uuid(), 'string_key' => 'common.save',
            'category' => 'ui', 'value_en' => 'Save', 'value_ar' => 'حفظ',
        ]);

        Cache::flush();

        $response = $this->withToken($this->token)->getJson('/api/v2/config/translations/en')->assertOk();

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertArrayHasKey('common.save', $data);
        $this->assertEquals('Save', $data['common.save']);
    }

    public function test_translations_returns_ar_values_for_ar_locale(): void
    {
        MasterTranslationString::forceCreate([
            'id' => Str::uuid(), 'string_key' => 'common.cancel',
            'category' => 'ui', 'value_en' => 'Cancel', 'value_ar' => 'إلغاء',
        ]);

        Cache::flush();

        $response = $this->withToken($this->token)->getJson('/api/v2/config/translations/ar')->assertOk();
        $data = $response->json('data');
        $this->assertEquals('إلغاء', $data['common.cancel']);
    }

    public function test_translations_different_locales_return_same_keys(): void
    {
        MasterTranslationString::forceCreate([
            'id' => Str::uuid(), 'string_key' => 'pos.checkout',
            'category' => 'pos', 'value_en' => 'Checkout', 'value_ar' => 'الدفع',
        ]);

        Cache::flush();

        $en = $this->withToken($this->token)->getJson('/api/v2/config/translations/en')->assertOk()->json('data');
        $ar = $this->withToken($this->token)->getJson('/api/v2/config/translations/ar')->assertOk()->json('data');

        $this->assertArrayHasKey('pos.checkout', $en);
        $this->assertArrayHasKey('pos.checkout', $ar);
        $this->assertNotEquals($en['pos.checkout'], $ar['pos.checkout']);
    }

    // ─── Translation Version — with real data ───────────────────────────────

    public function test_translation_version_returns_latest_hash(): void
    {
        $hash1 = 'abc123';
        $hash2 = 'def456';

        TranslationVersion::forceCreate([
            'id' => Str::uuid(), 'version_hash' => $hash1,
            'published_at' => now()->subHour(), 'notes' => null,
        ]);
        TranslationVersion::forceCreate([
            'id' => Str::uuid(), 'version_hash' => $hash2,
            'published_at' => now(), 'notes' => null,
        ]);

        Cache::flush();

        $this->withToken($this->token)->getJson('/api/v2/config/translations/version')
            ->assertOk()
            ->assertJsonPath('data.version_hash', $hash2);
    }

    public function test_translation_version_null_when_none_exists(): void
    {
        $this->withToken($this->token)->getJson('/api/v2/config/translations/version')
            ->assertOk()
            ->assertJsonPath('data.version_hash', null);
    }

    // ─── Locales — with real data ────────────────────────────────────────────

    public function test_locales_returns_active_only(): void
    {
        SupportedLocale::forceCreate([
            'id' => Str::uuid(), 'locale_code' => 'xx-active', 'language_name' => 'Xtestlang Active',
            'language_name_native' => 'Xtestlang Active', 'direction' => 'rtl',
            'date_format' => 'dd/MM/yyyy', 'number_format' => 'arabic_indic',
            'calendar_system' => 'gregorian', 'is_active' => true, 'is_default' => false,
        ]);
        SupportedLocale::forceCreate([
            'id' => Str::uuid(), 'locale_code' => 'zz', 'language_name' => 'Zap',
            'language_name_native' => 'Zap', 'direction' => 'ltr',
            'date_format' => 'MM/dd/yyyy', 'number_format' => 'latin',
            'calendar_system' => 'gregorian', 'is_active' => false, 'is_default' => false,
        ]);

        Cache::flush();

        $response = $this->withToken($this->token)->getJson('/api/v2/config/locales')->assertOk();
        $codes = array_column($response->json('data'), 'locale_code');
        $this->assertContains('xx-active', $codes);
        $this->assertNotContains('zz', $codes);
    }

    public function test_locales_each_item_has_direction_field(): void
    {
        SupportedLocale::forceCreate([
            'id' => Str::uuid(), 'locale_code' => 'xx-dir', 'language_name' => 'Xtestlang',
            'language_name_native' => 'Xtestlang', 'direction' => 'ltr',
            'date_format' => 'MM/dd/yyyy', 'number_format' => 'latin',
            'calendar_system' => 'gregorian', 'is_active' => true, 'is_default' => false,
        ]);

        Cache::flush();

        $response = $this->withToken($this->token)->getJson('/api/v2/config/locales')->assertOk();
        $item = collect($response->json('data'))->firstWhere('locale_code', 'xx-dir');
        $this->assertNotNull($item);
        $this->assertArrayHasKey('direction', $item);
        $this->assertEquals('ltr', $item['direction']);
    }

    // ─── Security Policies — with DB record ─────────────────────────────────

    public function test_security_policies_reads_from_db_when_record_exists(): void
    {
        SecurityPolicyDefault::query()->delete();
        SecurityPolicyDefault::forceCreate([
            'id' => Str::uuid(),
            'session_timeout_minutes' => 60,
            'require_reauth_on_wake' => false,
            'pin_min_length' => 6,
            'pin_complexity' => 'alphanumeric',
            'require_unique_pins' => true,
            'pin_expiry_days' => 90,
            'biometric_enabled_default' => false,
            'biometric_can_replace_pin' => false,
            'max_failed_login_attempts' => 3,
            'lockout_duration_minutes' => 30,
            'failed_attempt_alert_to_owner' => true,
            'device_registration_policy' => 'approval_required',
            'max_devices_per_store' => 5,
        ]);

        Cache::flush();

        $this->withToken($this->token)->getJson('/api/v2/config/security-policies')
            ->assertOk()
            ->assertJsonPath('data.session_timeout_minutes', 60)
            ->assertJsonPath('data.pin_min_length', 6)
            ->assertJsonPath('data.pin_complexity', 'alphanumeric')
            ->assertJsonPath('data.max_failed_login_attempts', 3)
            ->assertJsonPath('data.device_registration_policy', 'approval_required')
            ->assertJsonPath('data.max_devices_per_store', 5);
    }

    public function test_security_policies_uses_hardcoded_defaults_when_no_record(): void
    {
        $this->withToken($this->token)->getJson('/api/v2/config/security-policies')
            ->assertOk()
            ->assertJsonPath('data.session_timeout_minutes', 30)
            ->assertJsonPath('data.pin_min_length', 4)
            ->assertJsonPath('data.max_failed_login_attempts', 5)
            ->assertJsonPath('data.device_registration_policy', 'open')
            ->assertJsonPath('data.max_devices_per_store', 10);
    }

    public function test_security_policies_all_13_fields_present(): void
    {
        $this->withToken($this->token)->getJson('/api/v2/config/security-policies')
            ->assertOk()
            ->assertJsonStructure(['data' => [
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
            ]]);
    }

    // ─── Auth checks ─────────────────────────────────────────────────────────

    /** All authenticated endpoints must reject unauthenticated requests. */
    public function test_all_authenticated_endpoints_reject_no_token(): void
    {
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
            $this->getJson($endpoint)->assertUnauthorized();
        }
    }

    /** maintenance is public — must NOT require auth. */
    public function test_maintenance_accessible_without_token(): void
    {
        $this->getJson('/api/v2/config/maintenance')->assertOk();
    }

    // ─── Caching ─────────────────────────────────────────────────────────────

    public function test_maintenance_data_cached_and_stale_after_flush(): void
    {
        \DB::table('system_settings')->insert([
            'id'    => Str::uuid(),
            'key'   => 'maintenance_enabled',
            'value' => json_encode(true),
            'group' => 'maintenance',
        ]);

        // Warm cache
        $this->getJson('/api/v2/config/maintenance')->assertJsonPath('data.is_active', true);

        // Delete from DB — cache still returns old value
        \DB::table('system_settings')->where('key', 'maintenance_enabled')->delete();
        $this->getJson('/api/v2/config/maintenance')->assertJsonPath('data.is_active', true);

        // After flush — fresh read shows false
        Cache::flush();
        $this->getJson('/api/v2/config/maintenance')->assertJsonPath('data.is_active', false);
    }
}
