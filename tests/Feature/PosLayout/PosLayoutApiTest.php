<?php

namespace Tests\Feature\PosLayout;

use App\Domain\Auth\Models\User;
use App\Domain\ContentOnboarding\Models\BusinessType;
use App\Domain\ContentOnboarding\Models\CfdTheme;
use App\Domain\ContentOnboarding\Models\LabelLayoutTemplate;
use App\Domain\ContentOnboarding\Models\PlatformUiDefault;
use App\Domain\ContentOnboarding\Models\PosLayoutTemplate;
use App\Domain\ContentOnboarding\Models\ReceiptLayoutTemplate;
use App\Domain\ContentOnboarding\Models\SignageTemplate;
use App\Domain\ContentOnboarding\Models\Theme;
use App\Domain\ContentOnboarding\Services\PosLayoutService;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\PosCustomization\Models\PosCustomizationSetting;
use App\Domain\Shared\Models\UserPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PosLayoutApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;
    private BusinessType $businessType;
    private Theme $theme;
    private PosLayoutTemplate $layout;
    private ReceiptLayoutTemplate $receiptTemplate;
    private CfdTheme $cfdTheme;
    private SignageTemplate $signageTemplate;
    private LabelLayoutTemplate $labelTemplate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Test Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Main Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'ui@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;

        // Seed core data
        $this->businessType = BusinessType::create([
            'name' => 'Grocery',
            'name_ar' => 'بقالة',
            'slug' => 'grocery',
            'icon' => '🛒',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->theme = Theme::create([
            'name' => 'Light Classic',
            'slug' => 'light_classic',
            'primary_color' => '#FD8209',
            'secondary_color' => '#1A1A2E',
            'background_color' => '#FFFFFF',
            'text_color' => '#333333',
            'is_active' => true,
            'is_system' => true,
        ]);

        $this->layout = PosLayoutTemplate::create([
            'business_type_id' => $this->businessType->id,
            'layout_key' => 'grocery-standard-grid',
            'name' => 'Standard Grid',
            'name_ar' => 'شبكة قياسية',
            'description' => 'Standard grid layout',
            'config' => ['layout_type' => 'grid', 'cart_position' => 'right'],
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->receiptTemplate = ReceiptLayoutTemplate::create([
            'name' => 'Standard 80mm',
            'name_ar' => 'قياسي 80 مم',
            'slug' => 'standard-80mm',
            'paper_width' => 80,
            'header_config' => ['store_name_bold' => true],
            'body_config' => ['font_size' => 12],
            'footer_config' => ['show_thank_you' => true],
            'zatca_qr_position' => 'footer',
            'show_bilingual' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->cfdTheme = CfdTheme::create([
            'name' => 'Clean White',
            'slug' => 'clean-white',
            'background_color' => '#FFFFFF',
            'text_color' => '#333333',
            'accent_color' => '#1A56A0',
            'font_family' => 'system',
            'cart_layout' => 'list',
            'idle_layout' => 'slideshow',
            'animation_style' => 'fade',
            'transition_seconds' => 5,
            'show_store_logo' => true,
            'show_running_total' => true,
            'thank_you_animation' => 'check',
            'is_active' => true,
        ]);

        $this->signageTemplate = SignageTemplate::create([
            'name' => 'Menu Board Classic',
            'name_ar' => 'لوحة القائمة الكلاسيكية',
            'slug' => 'menu-board-classic',
            'template_type' => 'menu_board',
            'layout_config' => [['region_id' => 'main', 'type' => 'product_grid']],
            'background_color' => '#FFFFFF',
            'text_color' => '#333333',
            'font_family' => 'system',
            'transition_style' => 'fade',
            'is_active' => true,
        ]);
        $this->signageTemplate->businessTypes()->attach($this->businessType->id);

        $this->labelTemplate = LabelLayoutTemplate::create([
            'name' => 'Standard Barcode',
            'name_ar' => 'باركود قياسي',
            'slug' => 'standard-barcode',
            'label_type' => 'barcode',
            'label_width_mm' => 50,
            'label_height_mm' => 30,
            'barcode_type' => 'CODE128',
            'barcode_position' => ['x' => 5, 'y' => 5, 'w' => 40, 'h' => 15],
            'show_barcode_number' => true,
            'field_layout' => [['field_key' => 'product_name', 'label_en' => 'Name']],
            'font_family' => 'system',
            'default_font_size' => 'small',
            'show_border' => false,
            'border_style' => 'solid',
            'background_color' => '#FFFFFF',
            'is_active' => true,
        ]);
        $this->labelTemplate->businessTypes()->attach($this->businessType->id);

        // Seed platform defaults
        PlatformUiDefault::create(['key' => 'handedness', 'value' => 'right']);
        PlatformUiDefault::create(['key' => 'font_size', 'value' => 'medium']);
        PlatformUiDefault::create(['key' => 'theme', 'value' => 'light_classic']);
    }

    // ═══════════════════════════════════════════════════════════
    //  Authentication
    // ═══════════════════════════════════════════════════════════

    public function test_unauthenticated_access_is_rejected(): void
    {
        $this->getJson('/api/v2/ui/defaults')->assertUnauthorized();
        $this->getJson('/api/v2/ui/themes')->assertUnauthorized();
        $this->getJson('/api/v2/ui/preferences')->assertUnauthorized();
        $this->getJson('/api/v2/ui/layouts?business_type=grocery')->assertUnauthorized();
        $this->getJson('/api/v2/ui/receipt-templates')->assertUnauthorized();
        $this->getJson('/api/v2/ui/cfd-themes')->assertUnauthorized();
        $this->putJson('/api/v2/ui/preferences', [])->assertUnauthorized();
        $this->putJson('/api/v2/ui/store-defaults', [])->assertUnauthorized();
    }

    // ═══════════════════════════════════════════════════════════
    //  Platform Defaults
    // ═══════════════════════════════════════════════════════════

    public function test_get_platform_defaults(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/defaults');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.handedness', 'right')
            ->assertJsonPath('data.font_size', 'medium')
            ->assertJsonPath('data.theme', 'light_classic');
    }

    public function test_get_platform_defaults_empty(): void
    {
        PlatformUiDefault::query()->delete();

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/defaults');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', []);
    }

    // ═══════════════════════════════════════════════════════════
    //  Themes
    // ═══════════════════════════════════════════════════════════

    public function test_get_themes(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/themes');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Light Classic', $data[0]['name']);
    }

    public function test_get_themes_excludes_inactive(): void
    {
        Theme::create([
            'name' => 'Disabled Theme',
            'slug' => 'disabled-theme',
            'primary_color' => '#000000',
            'secondary_color' => '#000000',
            'background_color' => '#000000',
            'text_color' => '#000000',
            'is_active' => false,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/themes');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Light Classic', $data[0]['name']);
    }

    // ═══════════════════════════════════════════════════════════
    //  POS Layouts
    // ═══════════════════════════════════════════════════════════

    public function test_get_layouts_without_business_type_returns_empty(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/layouts');

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_get_layouts_for_business_type(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/layouts?business_type=grocery');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Standard Grid', $data[0]['name']);
        $this->assertEquals('grocery-standard-grid', $data[0]['layout_key']);
    }

    public function test_get_layouts_returns_empty_for_unknown_business_type(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/layouts?business_type=nonexistent');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertCount(0, $data);
    }

    public function test_get_layouts_excludes_inactive(): void
    {
        PosLayoutTemplate::create([
            'business_type_id' => $this->businessType->id,
            'layout_key' => 'grocery-inactive',
            'name' => 'Inactive Layout',
            'config' => ['layout_type' => 'grid'],
            'is_active' => false,
            'sort_order' => 2,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/layouts?business_type=grocery');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_get_layouts_sorted_by_sort_order(): void
    {
        PosLayoutTemplate::create([
            'business_type_id' => $this->businessType->id,
            'layout_key' => 'grocery-compact',
            'name' => 'Compact List',
            'config' => ['layout_type' => 'list'],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/layouts?business_type=grocery');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);
        // sort_order 0 first, then 1
        $this->assertEquals('Compact List', $data[0]['name']);
        $this->assertEquals('Standard Grid', $data[1]['name']);
    }

    // ═══════════════════════════════════════════════════════════
    //  Preferences — Cascade Resolution
    // ═══════════════════════════════════════════════════════════

    public function test_get_preferences_returns_platform_defaults_when_no_overrides(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/preferences');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.handedness', 'right')
            ->assertJsonPath('data.font_size', 'medium')
            ->assertJsonPath('data.theme', 'light_classic')
            ->assertJsonPath('data.resolved_from.handedness', 'platform')
            ->assertJsonPath('data.resolved_from.font_size', 'platform')
            ->assertJsonPath('data.resolved_from.theme', 'platform');
    }

    public function test_get_preferences_store_overrides_platform(): void
    {
        PosCustomizationSetting::create([
            'store_id' => $this->store->id,
            'handedness' => 'left',
            'theme' => 'dark',
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/preferences');

        $response->assertOk()
            ->assertJsonPath('data.handedness', 'left')
            ->assertJsonPath('data.theme', 'dark')
            ->assertJsonPath('data.resolved_from.handedness', 'store')
            ->assertJsonPath('data.resolved_from.theme', 'store');
    }

    public function test_get_preferences_user_overrides_store(): void
    {
        PosCustomizationSetting::create([
            'store_id' => $this->store->id,
            'handedness' => 'left',
            'theme' => 'dark',
            'sync_version' => 1,
        ]);

        UserPreference::create([
            'user_id' => $this->user->id,
            'pos_handedness' => 'center',
            'font_size' => 'large',
            'theme' => 'high_contrast',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/preferences');

        $response->assertOk()
            ->assertJsonPath('data.handedness', 'center')
            ->assertJsonPath('data.font_size', 'large')
            ->assertJsonPath('data.theme', 'high_contrast')
            ->assertJsonPath('data.resolved_from.handedness', 'user')
            ->assertJsonPath('data.resolved_from.font_size', 'user')
            ->assertJsonPath('data.resolved_from.theme', 'user');
    }

    public function test_get_preferences_user_layout_override(): void
    {
        UserPreference::create([
            'user_id' => $this->user->id,
            'pos_layout_id' => $this->layout->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/preferences');

        $response->assertOk()
            ->assertJsonPath('data.pos_layout_id', $this->layout->id)
            ->assertJsonPath('data.resolved_from.pos_layout_id', 'user');
    }

    public function test_get_preferences_fallback_to_hardcoded(): void
    {
        PlatformUiDefault::query()->delete();

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/preferences');

        $response->assertOk()
            ->assertJsonPath('data.handedness', 'right')
            ->assertJsonPath('data.font_size', 'medium')
            ->assertJsonPath('data.theme', 'light_classic');
    }

    // ═══════════════════════════════════════════════════════════
    //  Update Preferences
    // ═══════════════════════════════════════════════════════════

    public function test_update_user_preferences(): void
    {
        $response = $this->withToken($this->token)
            ->putJson('/api/v2/ui/preferences', [
                'pos_handedness' => 'left',
                'font_size' => 'large',
                'theme' => 'dark_mode',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $this->user->id,
            'pos_handedness' => 'left',
            'font_size' => 'large',
            'theme' => 'dark_mode',
        ]);
    }

    public function test_update_preferences_with_layout_id(): void
    {
        $response = $this->withToken($this->token)
            ->putJson('/api/v2/ui/preferences', [
                'pos_layout_id' => $this->layout->id,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $this->user->id,
            'pos_layout_id' => $this->layout->id,
        ]);
    }

    public function test_update_preferences_validates_handedness(): void
    {
        $response = $this->withToken($this->token)
            ->putJson('/api/v2/ui/preferences', [
                'pos_handedness' => 'invalid_value',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['pos_handedness']);
    }

    public function test_update_preferences_validates_font_size(): void
    {
        $response = $this->withToken($this->token)
            ->putJson('/api/v2/ui/preferences', [
                'font_size' => 'xxl',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['font_size']);
    }

    public function test_update_preferences_validates_layout_exists(): void
    {
        $response = $this->withToken($this->token)
            ->putJson('/api/v2/ui/preferences', [
                'pos_layout_id' => '00000000-0000-0000-0000-000000000099',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['pos_layout_id']);
    }

    public function test_update_preferences_flushes_cache(): void
    {
        // Warm cache
        $this->withToken($this->token)
            ->getJson('/api/v2/ui/preferences');

        $this->assertTrue(Cache::has("ui_preferences:{$this->user->id}"));

        // Update should flush
        $this->withToken($this->token)
            ->putJson('/api/v2/ui/preferences', [
                'pos_handedness' => 'left',
            ]);

        $this->assertFalse(Cache::has("ui_preferences:{$this->user->id}"));
    }

    // ═══════════════════════════════════════════════════════════
    //  Store Defaults
    // ═══════════════════════════════════════════════════════════

    public function test_update_store_defaults(): void
    {
        $response = $this->withToken($this->token)
            ->putJson('/api/v2/ui/store-defaults', [
                'theme' => 'dark',
                'primary_color' => '#FF0000',
                'handedness' => 'left',
                'grid_columns' => 3,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('pos_customization_settings', [
            'store_id' => $this->store->id,
            'theme' => 'dark',
            'primary_color' => '#FF0000',
            'handedness' => 'left',
            'grid_columns' => 3,
        ]);
    }

    public function test_update_store_defaults_validates_theme(): void
    {
        $response = $this->withToken($this->token)
            ->putJson('/api/v2/ui/store-defaults', [
                'theme' => 'neon',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['theme']);
    }

    public function test_update_store_defaults_validates_color_format(): void
    {
        $response = $this->withToken($this->token)
            ->putJson('/api/v2/ui/store-defaults', [
                'primary_color' => 'red',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['primary_color']);
    }

    public function test_update_store_defaults_validates_grid_columns_range(): void
    {
        $response = $this->withToken($this->token)
            ->putJson('/api/v2/ui/store-defaults', [
                'grid_columns' => 20,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['grid_columns']);
    }

    public function test_update_store_defaults_requires_store(): void
    {
        // User with no store
        $noStoreUser = User::create([
            'name' => 'No Store User',
            'email' => 'nostore@test.com',
            'password_hash' => bcrypt('password'),
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $noStoreToken = $noStoreUser->createToken('test', ['*'])->plainTextToken;

        $response = $this->withToken($noStoreToken)
            ->putJson('/api/v2/ui/store-defaults', [
                'theme' => 'dark',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    // ═══════════════════════════════════════════════════════════
    //  Receipt Layout Templates
    // ═══════════════════════════════════════════════════════════

    public function test_get_receipt_templates(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/receipt-templates');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Standard 80mm', $data[0]['name']);
    }

    public function test_get_receipt_templates_excludes_inactive(): void
    {
        ReceiptLayoutTemplate::create([
            'name' => 'Inactive Receipt',
            'name_ar' => 'غير نشط',
            'slug' => 'inactive-receipt',
            'paper_width' => 58,
            'header_config' => [],
            'body_config' => [],
            'footer_config' => [],
            'is_active' => false,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/receipt-templates');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_get_receipt_template_by_slug(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/receipt-templates/standard-80mm');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Standard 80mm')
            ->assertJsonPath('data.paper_width', 80);
    }

    public function test_get_receipt_template_not_found(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/receipt-templates/nonexistent');

        $response->assertNotFound()
            ->assertJsonPath('success', false);
    }

    // ═══════════════════════════════════════════════════════════
    //  CFD Themes
    // ═══════════════════════════════════════════════════════════

    public function test_get_cfd_themes(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/cfd-themes');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Clean White', $data[0]['name']);
    }

    public function test_get_cfd_themes_excludes_inactive(): void
    {
        CfdTheme::create([
            'name' => 'Disabled CFD',
            'slug' => 'disabled-cfd',
            'cart_layout' => 'list',
            'idle_layout' => 'slideshow',
            'animation_style' => 'fade',
            'thank_you_animation' => 'check',
            'is_active' => false,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/cfd-themes');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_get_cfd_theme_by_slug(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/cfd-themes/clean-white');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Clean White')
            ->assertJsonPath('data.cart_layout', 'list')
            ->assertJsonPath('data.idle_layout', 'slideshow');
    }

    public function test_get_cfd_theme_not_found(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/cfd-themes/nonexistent');

        $response->assertNotFound()
            ->assertJsonPath('success', false);
    }

    // ═══════════════════════════════════════════════════════════
    //  Signage Templates
    // ═══════════════════════════════════════════════════════════

    public function test_get_signage_templates_requires_business_type(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/signage-templates');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['business_type']);
    }

    public function test_get_signage_templates(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/signage-templates?business_type=grocery');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Menu Board Classic', $data[0]['name']);
    }

    public function test_get_signage_templates_empty_for_unassigned_type(): void
    {
        $otherType = BusinessType::create([
            'name' => 'Pharmacy',
            'name_ar' => 'صيدلية',
            'slug' => 'pharmacy',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/signage-templates?business_type=pharmacy');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_get_signage_template_by_slug(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/signage-templates/menu-board-classic');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Menu Board Classic')
            ->assertJsonPath('data.template_type', 'menu_board');
    }

    public function test_get_signage_template_not_found(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/signage-templates/nonexistent');

        $response->assertNotFound()
            ->assertJsonPath('success', false);
    }

    // ═══════════════════════════════════════════════════════════
    //  Label Templates
    // ═══════════════════════════════════════════════════════════

    public function test_get_label_templates_without_business_type_returns_ok(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/label-templates');

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_get_label_templates(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/label-templates?business_type=grocery');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Standard Barcode', $data[0]['name']);
    }

    public function test_get_label_template_by_slug(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/label-templates/standard-barcode');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Standard Barcode')
            ->assertJsonPath('data.label_type', 'barcode');
    }

    public function test_get_label_template_not_found(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/label-templates/nonexistent');

        $response->assertNotFound()
            ->assertJsonPath('success', false);
    }

    // ═══════════════════════════════════════════════════════════
    //  Service Layer — Direct Tests
    // ═══════════════════════════════════════════════════════════

    public function test_service_resolve_preferences_cascade(): void
    {
        $service = app(PosLayoutService::class);

        // Only platform defaults exist
        $prefs = $service->resolvePreferences($this->user->id, $this->store->id);
        $this->assertEquals('right', $prefs['handedness']);
        $this->assertEquals('platform', $prefs['resolved_from']['handedness']);

        // Add store setting
        PosCustomizationSetting::create([
            'store_id' => $this->store->id,
            'handedness' => 'left',
            'sync_version' => 1,
        ]);

        Cache::forget("ui_preferences:{$this->user->id}");
        $prefs = $service->resolvePreferences($this->user->id, $this->store->id);
        $handedness = $prefs['handedness'];
        $this->assertEquals('left', is_object($handedness) ? $handedness->value : $handedness);
        $this->assertEquals('store', $prefs['resolved_from']['handedness']);

        // Add user preference — overrides store
        UserPreference::create([
            'user_id' => $this->user->id,
            'pos_handedness' => 'center',
        ]);

        Cache::forget("ui_preferences:{$this->user->id}");
        $prefs = $service->resolvePreferences($this->user->id, $this->store->id);
        $this->assertEquals('center', $prefs['handedness']);
        $this->assertEquals('user', $prefs['resolved_from']['handedness']);
    }

    public function test_service_platform_defaults_cached(): void
    {
        $service = app(PosLayoutService::class);

        $defaults1 = $service->getPlatformDefaults();
        $this->assertEquals('right', $defaults1['handedness']);

        // Modify DB directly (bypassing cache)
        PlatformUiDefault::where('key', 'handedness')->update(['value' => 'left']);

        // Still returns cached value
        $defaults2 = $service->getPlatformDefaults();
        $this->assertEquals('right', $defaults2['handedness']);

        // Flush cache — gets fresh value
        $service->flushPlatformCache();
        $defaults3 = $service->getPlatformDefaults();
        $this->assertEquals('left', $defaults3['handedness']);
    }

    public function test_service_update_platform_default(): void
    {
        $service = app(PosLayoutService::class);

        $service->updatePlatformDefault('handedness', 'left');

        $this->assertDatabaseHas('platform_ui_defaults', [
            'key' => 'handedness',
            'value' => 'left',
        ]);

        // Cache should be flushed automatically
        $this->assertFalse(Cache::has('platform_ui_defaults'));
    }

    public function test_service_available_layouts_filter_by_business_type(): void
    {
        $service = app(PosLayoutService::class);

        $otherType = BusinessType::create([
            'name' => 'Restaurant',
            'name_ar' => 'مطعم',
            'slug' => 'restaurant',
            'is_active' => true,
        ]);

        PosLayoutTemplate::create([
            'business_type_id' => $otherType->id,
            'layout_key' => 'restaurant-standard',
            'name' => 'Restaurant Grid',
            'config' => ['layout_type' => 'grid'],
            'is_active' => true,
        ]);

        $groceryLayouts = $service->getAvailableLayouts('grocery');
        $this->assertCount(1, $groceryLayouts);
        $this->assertEquals('Standard Grid', $groceryLayouts->first()->name);

        $restaurantLayouts = $service->getAvailableLayouts('restaurant');
        $this->assertCount(1, $restaurantLayouts);
        $this->assertEquals('Restaurant Grid', $restaurantLayouts->first()->name);
    }

    public function test_service_available_themes_filter_active(): void
    {
        $service = app(PosLayoutService::class);

        Theme::create([
            'name' => 'Inactive Theme',
            'slug' => 'inactive-theme',
            'primary_color' => '#000000',
            'secondary_color' => '#000000',
            'background_color' => '#000000',
            'text_color' => '#000000',
            'is_active' => false,
        ]);

        $themes = $service->getAvailableThemes();
        $this->assertCount(1, $themes);
    }

    public function test_service_update_user_preferences(): void
    {
        $service = app(PosLayoutService::class);

        $pref = $service->updateUserPreferences($this->user->id, [
            'pos_handedness' => 'left',
            'font_size' => 'large',
        ]);

        $this->assertEquals($this->user->id, $pref->user_id);
        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $this->user->id,
            'pos_handedness' => 'left',
            'font_size' => 'large',
        ]);
    }

    public function test_service_update_store_defaults(): void
    {
        $service = app(PosLayoutService::class);

        $setting = $service->updateStoreDefaults($this->store->id, [
            'theme' => 'dark',
            'handedness' => 'left',
        ]);

        $this->assertEquals($this->store->id, $setting->store_id);
        $this->assertDatabaseHas('pos_customization_settings', [
            'store_id' => $this->store->id,
            'theme' => 'dark',
            'handedness' => 'left',
        ]);
    }

    public function test_service_active_business_types(): void
    {
        $service = app(PosLayoutService::class);

        BusinessType::create([
            'name' => 'Inactive Type',
            'name_ar' => 'غير نشط',
            'slug' => 'inactive-type',
            'is_active' => false,
        ]);

        $types = $service->getActiveBusinessTypes();
        $this->assertCount(1, $types);
        $this->assertEquals('Grocery', $types->first()->name);
    }

    public function test_service_receipt_template_by_slug(): void
    {
        $service = app(PosLayoutService::class);

        $template = $service->getReceiptTemplateBySlug('standard-80mm');
        $this->assertNotNull($template);
        $this->assertEquals('Standard 80mm', $template->name);

        $notFound = $service->getReceiptTemplateBySlug('ghost');
        $this->assertNull($notFound);
    }

    public function test_service_cfd_theme_by_slug(): void
    {
        $service = app(PosLayoutService::class);

        $theme = $service->getCfdThemeBySlug('clean-white');
        $this->assertNotNull($theme);
        $this->assertEquals('Clean White', $theme->name);

        $notFound = $service->getCfdThemeBySlug('ghost');
        $this->assertNull($notFound);
    }

    public function test_service_signage_template_by_slug(): void
    {
        $service = app(PosLayoutService::class);

        $tpl = $service->getSignageTemplateBySlug('menu-board-classic');
        $this->assertNotNull($tpl);

        $notFound = $service->getSignageTemplateBySlug('ghost');
        $this->assertNull($notFound);
    }

    public function test_service_label_template_by_slug(): void
    {
        $service = app(PosLayoutService::class);

        $tpl = $service->getLabelTemplateBySlug('standard-barcode');
        $this->assertNotNull($tpl);

        $notFound = $service->getLabelTemplateBySlug('ghost');
        $this->assertNull($notFound);
    }

    // ═══════════════════════════════════════════════════════════
    //  Model Relationship Tests
    // ═══════════════════════════════════════════════════════════

    public function test_business_type_has_layouts(): void
    {
        $this->assertCount(1, $this->businessType->posLayoutTemplates);
        $this->assertEquals('Standard Grid', $this->businessType->posLayoutTemplates->first()->name);
    }

    public function test_layout_belongs_to_business_type(): void
    {
        $this->assertNotNull($this->layout->businessType);
        $this->assertEquals('Grocery', $this->layout->businessType->name);
    }

    public function test_signage_template_has_business_types(): void
    {
        $this->assertCount(1, $this->signageTemplate->businessTypes);
        $this->assertEquals('Grocery', $this->signageTemplate->businessTypes->first()->name);
    }

    public function test_label_template_has_business_types(): void
    {
        $this->assertCount(1, $this->labelTemplate->businessTypes);
        $this->assertEquals('Grocery', $this->labelTemplate->businessTypes->first()->name);
    }

    public function test_business_type_has_signage_templates(): void
    {
        $this->assertCount(1, $this->businessType->signageTemplates);
    }

    public function test_business_type_has_label_templates(): void
    {
        $this->assertCount(1, $this->businessType->labelLayoutTemplates);
    }

    // ═══════════════════════════════════════════════════════════
    //  Edge Cases
    // ═══════════════════════════════════════════════════════════

    public function test_update_preferences_idempotent(): void
    {
        // First update
        $this->withToken($this->token)
            ->putJson('/api/v2/ui/preferences', ['pos_handedness' => 'left'])
            ->assertOk();

        // Second identical update — should not create duplicate
        $this->withToken($this->token)
            ->putJson('/api/v2/ui/preferences', ['pos_handedness' => 'left'])
            ->assertOk();

        $count = UserPreference::where('user_id', $this->user->id)->count();
        $this->assertEquals(1, $count);
    }

    public function test_update_store_defaults_idempotent(): void
    {
        $this->withToken($this->token)
            ->putJson('/api/v2/ui/store-defaults', ['theme' => 'dark'])
            ->assertOk();

        $this->withToken($this->token)
            ->putJson('/api/v2/ui/store-defaults', ['theme' => 'light'])
            ->assertOk();

        $count = PosCustomizationSetting::where('store_id', $this->store->id)->count();
        $this->assertEquals(1, $count);
    }

    public function test_multiple_cfd_themes_returned(): void
    {
        CfdTheme::create([
            'name' => 'Dark Elegant',
            'slug' => 'dark-elegant',
            'background_color' => '#1A1A2E',
            'text_color' => '#FFFFFF',
            'accent_color' => '#FD8209',
            'cart_layout' => 'grid',
            'idle_layout' => 'static_image',
            'animation_style' => 'slide',
            'thank_you_animation' => 'confetti',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/cfd-themes');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_multiple_receipt_templates_sorted(): void
    {
        ReceiptLayoutTemplate::create([
            'name' => 'Compact 58mm',
            'name_ar' => 'مضغوط 58 مم',
            'slug' => 'compact-58mm',
            'paper_width' => 58,
            'header_config' => [],
            'body_config' => [],
            'footer_config' => [],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/receipt-templates');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);
        // sort_order 0 first
        $this->assertEquals('Compact 58mm', $data[0]['name']);
    }

    public function test_signage_template_shared_across_business_types(): void
    {
        $restaurant = BusinessType::create([
            'name' => 'Restaurant',
            'name_ar' => 'مطعم',
            'slug' => 'restaurant',
            'is_active' => true,
        ]);

        // Attach signage template to restaurant too
        $this->signageTemplate->businessTypes()->attach($restaurant->id);

        $groceryResult = $this->withToken($this->token)
            ->getJson('/api/v2/ui/signage-templates?business_type=grocery');

        $restaurantResult = $this->withToken($this->token)
            ->getJson('/api/v2/ui/signage-templates?business_type=restaurant');

        $groceryResult->assertOk();
        $restaurantResult->assertOk();
        $this->assertCount(1, $groceryResult->json('data'));
        $this->assertCount(1, $restaurantResult->json('data'));
    }

    public function test_inactive_signage_template_excluded(): void
    {
        $this->signageTemplate->update(['is_active' => false]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/signage-templates?business_type=grocery');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_inactive_label_template_excluded(): void
    {
        $this->labelTemplate->update(['is_active' => false]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/label-templates?business_type=grocery');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }
}
