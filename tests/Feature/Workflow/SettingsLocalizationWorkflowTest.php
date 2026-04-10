<?php

namespace Tests\Feature\Workflow;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * SETTINGS & LOCALIZATION WORKFLOW TESTS
 *
 * Covers settings/locales, translations, overrides, versioning,
 * config endpoints (feature-flags, tax, payment-methods, locales).
 *
 * Cross-references: Workflows #701-730
 */
class SettingsLocalizationWorkflowTest extends WorkflowTestCase
{
    use RefreshDatabase;

    private User $owner;
    private Organization $org;
    private Store $store;
    private string $ownerToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();

        $this->org = Organization::create([
            'name' => 'Settings Org',
            'name_ar' => 'منظمة إعدادات',
            'business_type' => 'grocery',
            'country' => 'SA',
            'vat_number' => '300000000000003',
            'is_active' => true,
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Settings Store',
            'name_ar' => 'متجر إعدادات',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Settings Owner',
            'email' => 'settings-owner@workflow.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->ownerToken = $this->owner->createToken('test', ['*'])->plainTextToken;
        $this->assignOwnerRole($this->owner, $this->store->id);
    }

    // ══════════════════════════════════════════════
    //  LOCALIZATION SETTINGS — WF #701-711
    // ══════════════════════════════════════════════

    /** @test */
    public function wf701_list_locales(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/settings/locales');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf702_save_locale(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/settings/locales', [
                'code' => 'en',
                'name' => 'English',
                'direction' => 'ltr',
                'is_active' => true,
            ]);

        $this->assertContains($response->status(), [200, 201, 422]);
    }

    /** @test */
    public function wf703_get_translations(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/settings/translations?locale=ar');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf704_save_translation(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/settings/translations', [
                'locale' => 'ar',
                'group' => 'pos',
                'key' => 'checkout_button',
                'value' => 'الدفع',
            ]);

        $this->assertContains($response->status(), [200, 201, 422]);
    }

    /** @test */
    public function wf705_bulk_import_translations(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/settings/translations/bulk-import', [
                'locale' => 'ar',
                'translations' => [
                    ['group' => 'pos', 'key' => 'total', 'value' => 'المجموع'],
                    ['group' => 'pos', 'key' => 'subtotal', 'value' => 'المجموع الفرعي'],
                ],
            ]);

        $this->assertContains($response->status(), [200, 201, 422]);
    }

    /** @test */
    public function wf706_export_translations(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/settings/export-translations');

        $this->assertContains($response->status(), [200, 422]);
    }

    /** @test */
    public function wf707_get_overrides(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/settings/translation-overrides?store_id=' . $this->store->id);

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf708_save_override(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/settings/translation-overrides', [
                'locale' => 'ar',
                'group' => 'pos',
                'key' => 'receipt_header',
                'value' => 'رأس الإيصال المخصص',
            ]);

        $this->assertContains($response->status(), [200, 201, 422]);
    }

    /** @test */
    public function wf709_remove_override(): void
    {
        // Seed an override
        DB::table('translation_overrides')->insert([
            'id' => '11111111-1111-1111-1111-111111111111',
            'store_id' => $this->store->id,
            'string_key' => 'pos.temp_override',
            'locale' => 'ar',
            'custom_value' => 'temp',
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->deleteJson('/api/v2/settings/translation-overrides/11111111-1111-1111-1111-111111111111');

        $this->assertContains($response->status(), [200, 204, 404]);
    }

    /** @test */
    public function wf710_publish_translations(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/settings/publish-translations');

        $this->assertContains($response->status(), [200, 201, 422]);
    }

    /** @test */
    public function wf711_list_translation_versions(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/settings/translation-versions');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    // ══════════════════════════════════════════════
    //  CONFIG ENDPOINTS — WF #712-720
    // ══════════════════════════════════════════════

    /** @test */
    public function wf712_maintenance_mode_check(): void
    {
        // Public endpoint — no auth needed
        $response = $this->getJson('/api/v2/config/maintenance');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf713_feature_flags(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/config/feature-flags');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf714_tax_config(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/config/tax');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf715_age_restrictions_config(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/config/age-restrictions');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf716_payment_methods_config(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/config/payment-methods');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf717_hardware_catalog_config(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/config/hardware-catalog');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf718_translation_version_config(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/config/translations/version');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf719_translations_by_locale(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/config/translations/ar');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf720_config_locales(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/config/locales');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf721_security_policies_config(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/config/security-policies');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }
}
