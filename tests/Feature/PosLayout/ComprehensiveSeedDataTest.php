<?php

namespace Tests\Feature\PosLayout;

use App\Domain\Auth\Models\User;
use App\Domain\ContentOnboarding\Enums\MarketplaceListingStatus;
use App\Domain\ContentOnboarding\Enums\MarketplacePricingType;
use App\Domain\ContentOnboarding\Enums\PurchaseType;
use App\Domain\ContentOnboarding\Enums\SubscriptionInterval;
use App\Domain\ContentOnboarding\Enums\WidgetCategory;
use App\Domain\ContentOnboarding\Models\BusinessType;
use App\Domain\ContentOnboarding\Models\CfdTheme;
use App\Domain\ContentOnboarding\Models\LabelLayoutTemplate;
use App\Domain\ContentOnboarding\Models\LayoutWidget;
use App\Domain\ContentOnboarding\Models\LayoutWidgetPlacement;
use App\Domain\ContentOnboarding\Models\MarketplaceCategory;
use App\Domain\ContentOnboarding\Models\MarketplacePurchaseInvoice;
use App\Domain\ContentOnboarding\Models\PosLayoutTemplate;
use App\Domain\ContentOnboarding\Models\ReceiptLayoutTemplate;
use App\Domain\ContentOnboarding\Models\SignageTemplate;
use App\Domain\ContentOnboarding\Models\TemplateMarketplaceListing;
use App\Domain\ContentOnboarding\Models\TemplatePurchase;
use App\Domain\ContentOnboarding\Models\Theme;
use App\Domain\ContentOnboarding\Models\ThemeVariable;
use App\Domain\ContentOnboarding\Services\MarketplaceService;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComprehensiveSeedDataTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;
    private BusinessType $bt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Seed Test Org', 'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Seed Test Store', 'business_type' => 'grocery',
            'currency' => 'SAR', 'is_active' => true, 'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Seed Tester', 'email' => 'seed@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner', 'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;

        $this->bt = BusinessType::create([
            'name' => 'Grocery', 'name_ar' => 'بقالة', 'slug' => 'grocery',
            'icon' => '🛒', 'is_active' => true, 'sort_order' => 1,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  THEMES (10)
    // ═══════════════════════════════════════════════════════════

    public function test_can_create_10_themes_with_unique_slugs(): void
    {
        $slugs = ['light_classic', 'dark_mode', 'high_contrast', 'thawani_brand',
                   'ocean_breeze', 'sunset_warmth', 'forest_green', 'royal_purple',
                   'midnight_blue', 'desert_sand'];

        foreach ($slugs as $slug) {
            Theme::create([
                'name' => ucwords(str_replace('_', ' ', $slug)),
                'slug' => $slug,
                'primary_color' => '#' . substr(md5($slug), 0, 6),
                'secondary_color' => '#' . substr(md5($slug . 's'), 0, 6),
                'background_color' => '#FFFFFF',
                'text_color' => '#1F2937',
                'is_active' => true, 'is_system' => true,
            ]);
        }

        $this->assertCount(10, Theme::all());
        $this->assertEquals(10, Theme::where('is_active', true)->count());
    }

    public function test_themes_have_valid_hex_colours(): void
    {
        $theme = Theme::create([
            'name' => 'Test', 'slug' => 'test_hex',
            'primary_color' => '#1E40AF', 'secondary_color' => '#3B82F6',
            'background_color' => '#FFFFFF', 'text_color' => '#1F2937',
            'is_active' => true, 'is_system' => false,
        ]);

        $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $theme->primary_color);
        $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $theme->secondary_color);
    }

    public function test_theme_variables_belong_to_theme(): void
    {
        $theme = Theme::create([
            'name' => 'VarTheme', 'slug' => 'var_theme',
            'primary_color' => '#000', 'secondary_color' => '#111',
            'background_color' => '#FFF', 'text_color' => '#333',
            'is_active' => true, 'is_system' => false,
        ]);

        ThemeVariable::create([
            'theme_id' => $theme->id, 'variable_key' => '--primary',
            'variable_value' => '#000000', 'variable_type' => 'color',
            'category' => 'colors',
        ]);

        $this->assertCount(1, $theme->fresh()->variables);
    }

    public function test_themes_api_returns_all_themes(): void
    {
        Theme::create([
            'name' => 'API Theme', 'slug' => 'api_theme',
            'primary_color' => '#AAA', 'secondary_color' => '#BBB',
            'background_color' => '#FFF', 'text_color' => '#000',
            'is_active' => true, 'is_system' => false,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/themes');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'slug', 'primary_color']]]);
    }

    // ═══════════════════════════════════════════════════════════
    //  RECEIPT TEMPLATES (10)
    // ═══════════════════════════════════════════════════════════

    public function test_can_create_10_receipt_templates(): void
    {
        $slugs = ['standard_80mm', 'compact_58mm', 'premium_branded_80mm',
                   'bilingual_deluxe_80mm', 'thermal_express_58mm',
                   'luxury_gift_receipt_80mm', 'restaurant_dinein_80mm',
                   'pharmacy_prescription_80mm', 'e_receipt_digital_80mm',
                   'a4_full_page_invoice'];

        foreach ($slugs as $i => $slug) {
            ReceiptLayoutTemplate::create([
                'name' => ucwords(str_replace('_', ' ', $slug)),
                'name_ar' => 'قالب ' . ($i + 1),
                'slug' => $slug,
                'paper_width' => str_contains($slug, '58') ? 58 : 80,
                'header_config' => ['logo_max_height' => 60],
                'body_config' => ['item_font_size' => 12],
                'footer_config' => ['show_receipt_number' => true],
                'zatca_qr_position' => 'footer',
                'show_bilingual' => true,
                'is_active' => true,
                'sort_order' => $i,
            ]);
        }

        $this->assertCount(10, ReceiptLayoutTemplate::all());
    }

    public function test_receipt_templates_api_returns_list(): void
    {
        ReceiptLayoutTemplate::create([
            'name' => 'Receipt API', 'name_ar' => 'إيصال', 'slug' => 'receipt_api_test',
            'paper_width' => 80, 'header_config' => [], 'body_config' => [],
            'footer_config' => [], 'zatca_qr_position' => 'footer',
            'show_bilingual' => true, 'is_active' => true, 'sort_order' => 0,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/receipt-templates');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'slug', 'paper_width']]]);
    }

    public function test_receipt_template_show_returns_detail(): void
    {
        $tpl = ReceiptLayoutTemplate::create([
            'name' => 'Detail Test', 'name_ar' => 'تفصيل', 'slug' => 'detail_test',
            'paper_width' => 80, 'header_config' => ['logo_max_height' => 60],
            'body_config' => ['item_font_size' => 12],
            'footer_config' => ['show_receipt_number' => true],
            'zatca_qr_position' => 'footer', 'show_bilingual' => true,
            'is_active' => true, 'sort_order' => 0,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/ui/receipt-templates/{$tpl->slug}");

        $response->assertOk()
            ->assertJsonPath('data.slug', 'detail_test');
    }

    // ═══════════════════════════════════════════════════════════
    //  CFD THEMES (10)
    // ═══════════════════════════════════════════════════════════

    public function test_can_create_10_cfd_themes(): void
    {
        $slugs = ['cfd_clean_white', 'cfd_dark_elegant', 'cfd_neon_glow',
                   'cfd_corporate_blue', 'cfd_fresh_green', 'cfd_warm_sunset',
                   'cfd_royal_gold', 'cfd_ocean_wave', 'cfd_minimalist_gray',
                   'cfd_festival_red'];

        foreach ($slugs as $slug) {
            CfdTheme::create([
                'name' => ucwords(str_replace(['cfd_', '_'], ['', ' '], $slug)),
                'slug' => $slug,
                'background_color' => '#FFFFFF', 'text_color' => '#1F2937',
                'accent_color' => '#2563EB', 'font_family' => 'Inter',
                'cart_layout' => 'list', 'idle_layout' => 'slideshow',
                'animation_style' => 'fade', 'transition_seconds' => 5,
                'show_store_logo' => true, 'show_running_total' => true,
                'thank_you_animation' => 'confetti', 'is_active' => true,
            ]);
        }

        $this->assertCount(10, CfdTheme::all());
    }

    public function test_cfd_themes_api_returns_list(): void
    {
        CfdTheme::create([
            'name' => 'CFD API', 'slug' => 'cfd_api_test',
            'background_color' => '#FFF', 'text_color' => '#000',
            'accent_color' => '#00F', 'font_family' => 'Inter',
            'cart_layout' => 'list', 'idle_layout' => 'slideshow',
            'animation_style' => 'fade', 'transition_seconds' => 5,
            'show_store_logo' => true, 'show_running_total' => true,
            'thank_you_animation' => 'confetti', 'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/cfd-themes');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'slug', 'cart_layout']]]);
    }

    // ═══════════════════════════════════════════════════════════
    //  SIGNAGE TEMPLATES (10)
    // ═══════════════════════════════════════════════════════════

    public function test_can_create_10_signage_templates(): void
    {
        $types = ['menu_board', 'promo_slideshow', 'queue_display', 'welcome',
                  'menu_board', 'info_board', 'menu_board', 'promo_slideshow',
                  'menu_board', 'menu_board'];

        for ($i = 0; $i < 10; $i++) {
            SignageTemplate::create([
                'name' => "Signage #{$i}",
                'name_ar' => "لوحة #{$i}",
                'slug' => "signage_test_{$i}",
                'template_type' => $types[$i],
                'layout_config' => [['region_id' => 'main', 'type' => 'text', 'x' => 0, 'y' => 0, 'w' => 100, 'h' => 100]],
                'placeholder_content' => ['main' => 'test'],
                'background_color' => '#1E293B', 'text_color' => '#F8FAFC',
                'font_family' => 'Inter', 'transition_style' => 'fade',
                'is_active' => true,
            ]);
        }

        $this->assertCount(10, SignageTemplate::all());
    }

    public function test_signage_templates_api_returns_list(): void
    {
        $tpl = SignageTemplate::create([
            'name' => 'Sig API', 'name_ar' => 'لوحة', 'slug' => 'sig_api_test',
            'template_type' => 'menu_board',
            'layout_config' => [], 'placeholder_content' => [],
            'background_color' => '#000', 'text_color' => '#FFF',
            'font_family' => 'Inter', 'transition_style' => 'fade',
            'is_active' => true,
        ]);
        $tpl->businessTypes()->attach($this->bt->id);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/signage-templates?business_type=' . $this->bt->slug);

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'slug', 'template_type']]]);
    }

    // ═══════════════════════════════════════════════════════════
    //  LABEL TEMPLATES (10)
    // ═══════════════════════════════════════════════════════════

    public function test_can_create_10_label_templates(): void
    {
        $types = ['barcode', 'shelf', 'pharmacy', 'jewelry', 'barcode',
                  'barcode', 'price', 'barcode', 'barcode', 'price'];

        for ($i = 0; $i < 10; $i++) {
            LabelLayoutTemplate::create([
                'name' => "Label #{$i}", 'name_ar' => "ملصق #{$i}",
                'slug' => "label_test_{$i}", 'label_type' => $types[$i],
                'label_width_mm' => 50, 'label_height_mm' => 25,
                'barcode_type' => 'CODE128',
                'barcode_position' => ['x' => 10, 'y' => 30, 'w' => 80, 'h' => 35],
                'show_barcode_number' => true,
                'field_layout' => [['field_key' => 'product_name', 'label_en' => 'Product', 'label_ar' => 'المنتج', 'x' => 5, 'y' => 5, 'w' => 90, 'h' => 20, 'font_size' => 'medium', 'is_bold' => true, 'alignment' => 'center']],
                'font_family' => 'Inter', 'default_font_size' => 'medium',
                'show_border' => true, 'border_style' => 'solid',
                'background_color' => '#FFFFFF', 'is_active' => true,
            ]);
        }

        $this->assertCount(10, LabelLayoutTemplate::all());
    }

    public function test_label_templates_api_returns_list(): void
    {
        $tpl = LabelLayoutTemplate::create([
            'name' => 'Lbl API', 'name_ar' => 'ملصق', 'slug' => 'lbl_api_test',
            'label_type' => 'barcode', 'label_width_mm' => 50, 'label_height_mm' => 25,
            'barcode_type' => 'CODE128', 'barcode_position' => ['x' => 0, 'y' => 0, 'w' => 100, 'h' => 100],
            'show_barcode_number' => true, 'field_layout' => [],
            'font_family' => 'Inter', 'default_font_size' => 'medium',
            'show_border' => false, 'border_style' => 'solid',
            'background_color' => '#FFF', 'is_active' => true,
        ]);
        $tpl->businessTypes()->attach($this->bt->id);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/label-templates?business_type=' . $this->bt->slug);

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'slug', 'label_type']]]);
    }

    public function test_label_template_business_type_association(): void
    {
        $tpl = LabelLayoutTemplate::create([
            'name' => 'BT Label', 'name_ar' => 'ملصق', 'slug' => 'bt_label_test',
            'label_type' => 'barcode', 'label_width_mm' => 50, 'label_height_mm' => 25,
            'barcode_type' => 'CODE128', 'barcode_position' => [],
            'show_barcode_number' => true, 'field_layout' => [],
            'font_family' => 'Inter', 'default_font_size' => 'medium',
            'show_border' => false, 'border_style' => 'solid',
            'background_color' => '#FFF', 'is_active' => true,
        ]);

        $tpl->businessTypes()->attach($this->bt->id);
        $this->assertCount(1, $tpl->fresh()->businessTypes);
    }

    // ═══════════════════════════════════════════════════════════
    //  LAYOUT WIDGETS (20)
    // ═══════════════════════════════════════════════════════════

    public function test_can_create_20_widgets_with_categories(): void
    {
        $categories = [
            WidgetCategory::Core, WidgetCategory::Core, WidgetCategory::Core,
            WidgetCategory::Commerce, WidgetCategory::Commerce, WidgetCategory::Commerce,
            WidgetCategory::Commerce, WidgetCategory::Commerce,
            WidgetCategory::Display, WidgetCategory::Display, WidgetCategory::Display,
            WidgetCategory::Display, WidgetCategory::Display,
            WidgetCategory::Utility, WidgetCategory::Utility, WidgetCategory::Utility,
            WidgetCategory::Utility,
            WidgetCategory::Custom, WidgetCategory::Custom, WidgetCategory::Custom,
        ];

        for ($i = 0; $i < 20; $i++) {
            LayoutWidget::create([
                'slug' => "widget_{$i}", 'name' => "Widget #{$i}",
                'name_ar' => "أداة #{$i}", 'category' => $categories[$i],
                'icon' => 'heroicon-o-squares-2x2',
                'default_width' => 6, 'default_height' => 4,
                'min_width' => 2, 'min_height' => 2,
                'max_width' => 24, 'max_height' => 16,
                'is_resizable' => true, 'is_required' => $i < 3,
                'properties_schema' => [], 'default_properties' => [],
                'is_active' => true, 'sort_order' => $i,
            ]);
        }

        $this->assertCount(20, LayoutWidget::all());
        $this->assertEquals(3, LayoutWidget::where('is_required', true)->count());
        $this->assertEquals(3, LayoutWidget::where('category', WidgetCategory::Core)->count());
        $this->assertEquals(5, LayoutWidget::where('category', WidgetCategory::Commerce)->count());
        $this->assertEquals(5, LayoutWidget::where('category', WidgetCategory::Display)->count());
        $this->assertEquals(4, LayoutWidget::where('category', WidgetCategory::Utility)->count());
        $this->assertEquals(3, LayoutWidget::where('category', WidgetCategory::Custom)->count());
    }

    public function test_widgets_api_returns_catalog(): void
    {
        LayoutWidget::create([
            'slug' => 'wgt_api', 'name' => 'API Widget', 'name_ar' => 'أداة',
            'category' => WidgetCategory::Core, 'icon' => 'heroicon-o-bolt',
            'default_width' => 6, 'default_height' => 4,
            'min_width' => 2, 'min_height' => 2, 'max_width' => 24, 'max_height' => 16,
            'is_resizable' => true, 'is_required' => false,
            'properties_schema' => [], 'default_properties' => [],
            'is_active' => true, 'sort_order' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/layout-builder/widgets');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'slug', 'category']]]);
    }

    // ═══════════════════════════════════════════════════════════
    //  POS LAYOUTS (30) + WIDGET PLACEMENTS
    // ═══════════════════════════════════════════════════════════

    public function test_can_create_30_pos_layouts_for_10_business_types(): void
    {
        // Use the 8 valid enum values + 2 extras by reusing some with unique slugs
        $btSlugs = ['grocery', 'restaurant', 'pharmacy', 'bakery', 'electronics',
                     'florist', 'jewelry', 'fashion', 'grocery_express', 'restaurant_cafe'];

        foreach ($btSlugs as $i => $slug) {
            $bt = \App\Domain\ContentOnboarding\Models\BusinessType::updateOrCreate(['slug' => $slug], [
                'name' => ucfirst($slug), 'name_ar' => $slug,
                'icon' => '🏪', 'is_active' => true, 'sort_order' => $i,
            ]);

            foreach (['standard_grid', 'compact_list', 'specialized'] as $j => $suffix) {
                PosLayoutTemplate::create([
                    'business_type_id' => $bt->id,
                    'layout_key' => "{$slug}_{$suffix}",
                    'name' => ucfirst($suffix), 'name_ar' => $suffix,
                    'config' => ['layout_type' => 'grid'],
                    'is_default' => $j === 0,
                    'is_active' => true, 'sort_order' => $j,
                    'canvas_columns' => 24, 'canvas_rows' => 16,
                    'canvas_gap_px' => 4, 'canvas_padding_px' => 8,
                    'breakpoints' => [], 'version' => '1.0.0', 'is_locked' => false,
                ]);
            }
        }

        $this->assertCount(30, PosLayoutTemplate::all());
        $this->assertEquals(10, PosLayoutTemplate::where('is_default', true)->count());
    }

    public function test_widget_placement_on_layout(): void
    {
        $widget = LayoutWidget::create([
            'slug' => 'placement_test', 'name' => 'PT Widget', 'name_ar' => 'أداة',
            'category' => WidgetCategory::Core, 'icon' => 'heroicon-o-bolt',
            'default_width' => 6, 'default_height' => 4,
            'min_width' => 2, 'min_height' => 2, 'max_width' => 24, 'max_height' => 16,
            'is_resizable' => true, 'is_required' => false,
            'properties_schema' => [], 'default_properties' => [],
            'is_active' => true, 'sort_order' => 1,
        ]);

        $layout = PosLayoutTemplate::create([
            'business_type_id' => $this->bt->id,
            'layout_key' => 'placement_test_layout',
            'name' => 'Placement Test', 'name_ar' => 'اختبار',
            'config' => [], 'is_default' => false, 'is_active' => true, 'sort_order' => 0,
            'canvas_columns' => 24, 'canvas_rows' => 16,
            'canvas_gap_px' => 4, 'canvas_padding_px' => 8,
            'breakpoints' => [], 'version' => '1.0.0', 'is_locked' => false,
        ]);

        $placement = LayoutWidgetPlacement::create([
            'pos_layout_template_id' => $layout->id,
            'layout_widget_id' => $widget->id,
            'instance_key' => 'pt_widget_0',
            'grid_x' => 0, 'grid_y' => 0, 'grid_w' => 12, 'grid_h' => 8,
            'z_index' => 1, 'properties' => ['columns' => 4], 'is_visible' => true,
        ]);

        $this->assertCount(1, $layout->fresh()->widgetPlacements);
        $this->assertEquals($widget->id, $placement->widget->id);
    }

    public function test_layouts_api_returns_list_by_business_type(): void
    {
        PosLayoutTemplate::create([
            'business_type_id' => $this->bt->id,
            'layout_key' => 'api_layout_test',
            'name' => 'API Layout', 'name_ar' => 'تخطيط',
            'config' => ['layout_type' => 'grid'],
            'is_default' => true, 'is_active' => true, 'sort_order' => 0,
            'canvas_columns' => 24, 'canvas_rows' => 16,
            'canvas_gap_px' => 4, 'canvas_padding_px' => 8,
            'breakpoints' => [], 'version' => '1.0.0', 'is_locked' => false,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/layouts?business_type=' . $this->bt->slug);

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'layout_key', 'config']]]);
    }

    // ═══════════════════════════════════════════════════════════
    //  MARKETPLACE CATEGORIES (10)
    // ═══════════════════════════════════════════════════════════

    public function test_can_create_10_marketplace_categories(): void
    {
        $cats = ['retail', 'restaurant', 'grocery', 'pharmacy', 'electronics',
                 'fashion', 'services', 'minimal', 'premium', 'seasonal'];

        foreach ($cats as $i => $slug) {
            MarketplaceCategory::create([
                'name' => ucfirst($slug), 'name_ar' => $slug,
                'slug' => $slug, 'icon' => 'heroicon-o-tag',
                'sort_order' => $i + 1, 'is_active' => true,
            ]);
        }

        $this->assertCount(10, MarketplaceCategory::all());
    }

    public function test_marketplace_categories_api_returns_list(): void
    {
        MarketplaceCategory::create([
            'name' => 'Cat API', 'name_ar' => 'فئة', 'slug' => 'cat_api_test',
            'icon' => 'heroicon-o-tag', 'sort_order' => 1, 'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/marketplace/categories');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'slug', 'name']]]);
    }

    // ═══════════════════════════════════════════════════════════
    //  MARKETPLACE LISTINGS (10)
    // ═══════════════════════════════════════════════════════════

    public function test_can_create_10_marketplace_listings_with_mixed_pricing(): void
    {
        $category = MarketplaceCategory::create([
            'name' => 'Retail', 'name_ar' => 'التجزئة', 'slug' => 'retail_listings',
            'icon' => 'heroicon-o-tag', 'sort_order' => 1, 'is_active' => true,
        ]);

        $theme = Theme::create([
            'name' => 'Light', 'slug' => 'listing_theme',
            'primary_color' => '#AAA', 'secondary_color' => '#BBB',
            'background_color' => '#FFF', 'text_color' => '#000',
            'is_active' => true, 'is_system' => false,
        ]);

        $pricingTypes = [
            MarketplacePricingType::Free,
            MarketplacePricingType::OneTime,
            MarketplacePricingType::Subscription,
            MarketplacePricingType::OneTime,
            MarketplacePricingType::Free,
            MarketplacePricingType::Subscription,
            MarketplacePricingType::OneTime,
            MarketplacePricingType::OneTime,
            MarketplacePricingType::OneTime,
            MarketplacePricingType::Subscription,
        ];

        for ($i = 0; $i < 10; $i++) {
            $layout = PosLayoutTemplate::create([
                'business_type_id' => $this->bt->id,
                'layout_key' => "listing_layout_{$i}",
                'name' => "Layout #{$i}", 'name_ar' => "تخطيط #{$i}",
                'config' => [], 'is_default' => false, 'is_active' => true,
                'sort_order' => $i, 'canvas_columns' => 24, 'canvas_rows' => 16,
                'canvas_gap_px' => 4, 'canvas_padding_px' => 8,
                'breakpoints' => [], 'version' => '1.0.0', 'is_locked' => false,
            ]);

            TemplateMarketplaceListing::create([
                'pos_layout_template_id' => $layout->id,
                'theme_id' => $theme->id,
                'publisher_name' => 'Publisher ' . $i,
                'title' => "Listing #{$i}", 'title_ar' => "قائمة #{$i}",
                'description' => "Desc #{$i}", 'description_ar' => "وصف #{$i}",
                'short_description' => "Short #{$i}", 'short_description_ar' => "قصير #{$i}",
                'preview_images' => [],
                'pricing_type' => $pricingTypes[$i],
                'price_amount' => $pricingTypes[$i] === MarketplacePricingType::Free ? 0 : ($i + 1) * 10,
                'price_currency' => 'SAR',
                'subscription_interval' => $pricingTypes[$i] === MarketplacePricingType::Subscription
                    ? SubscriptionInterval::Monthly : null,
                'category_id' => $category->id,
                'tags' => ['test'],
                'version' => '1.0.0',
                'download_count' => 0,
                'average_rating' => 0,
                'review_count' => 0,
                'is_featured' => $i < 3,
                'is_verified' => true,
                'status' => MarketplaceListingStatus::Approved,
                'approved_at' => now(),
                'published_at' => now(),
            ]);
        }

        $this->assertCount(10, TemplateMarketplaceListing::all());
        $this->assertEquals(2, TemplateMarketplaceListing::where('pricing_type', MarketplacePricingType::Free)->count());
        $this->assertEquals(5, TemplateMarketplaceListing::where('pricing_type', MarketplacePricingType::OneTime)->count());
        $this->assertEquals(3, TemplateMarketplaceListing::where('pricing_type', MarketplacePricingType::Subscription)->count());
    }

    public function test_marketplace_listings_api_returns_approved(): void
    {
        $cat = MarketplaceCategory::create([
            'name' => 'API Cat', 'name_ar' => 'فئة', 'slug' => 'api_listing_cat',
            'icon' => 'heroicon-o-tag', 'sort_order' => 1, 'is_active' => true,
        ]);

        $layout = PosLayoutTemplate::create([
            'business_type_id' => $this->bt->id,
            'layout_key' => 'listing_api_layout',
            'name' => 'LAL', 'name_ar' => 'ل', 'config' => [],
            'is_default' => false, 'is_active' => true, 'sort_order' => 0,
            'canvas_columns' => 24, 'canvas_rows' => 16,
            'canvas_gap_px' => 4, 'canvas_padding_px' => 8,
            'breakpoints' => [], 'version' => '1.0.0', 'is_locked' => false,
        ]);

        TemplateMarketplaceListing::create([
            'pos_layout_template_id' => $layout->id,
            'publisher_name' => 'API Publisher',
            'title' => 'API Listing', 'title_ar' => 'قائمة',
            'description' => 'D', 'description_ar' => 'و',
            'short_description' => 'S', 'short_description_ar' => 'ق',
            'preview_images' => [], 'pricing_type' => MarketplacePricingType::Free,
            'price_amount' => 0, 'price_currency' => 'SAR',
            'category_id' => $cat->id, 'tags' => ['api'],
            'version' => '1.0.0', 'download_count' => 0,
            'average_rating' => 0, 'review_count' => 0,
            'is_featured' => false, 'is_verified' => true,
            'status' => MarketplaceListingStatus::Approved,
            'approved_at' => now(), 'published_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/marketplace/listings');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'title', 'pricing_type']]]);
    }

    // ═══════════════════════════════════════════════════════════
    //  PURCHASE + INVOICE GENERATION
    // ═══════════════════════════════════════════════════════════

    public function test_purchase_creates_invoice_automatically(): void
    {
        $cat = MarketplaceCategory::create([
            'name' => 'Pcat', 'name_ar' => 'ف', 'slug' => 'purchase_cat',
            'icon' => 'heroicon-o-tag', 'sort_order' => 1, 'is_active' => true,
        ]);

        $layout = PosLayoutTemplate::create([
            'business_type_id' => $this->bt->id,
            'layout_key' => 'purchase_layout',
            'name' => 'PL', 'name_ar' => 'ب', 'config' => [],
            'is_default' => false, 'is_active' => true, 'sort_order' => 0,
            'canvas_columns' => 24, 'canvas_rows' => 16,
            'canvas_gap_px' => 4, 'canvas_padding_px' => 8,
            'breakpoints' => [], 'version' => '1.0.0', 'is_locked' => false,
        ]);

        $listing = TemplateMarketplaceListing::create([
            'pos_layout_template_id' => $layout->id,
            'publisher_name' => 'Test Publisher',
            'title' => 'Purchasable', 'title_ar' => 'قابل للشراء',
            'description' => 'D', 'description_ar' => 'و',
            'short_description' => 'S', 'short_description_ar' => 'ق',
            'preview_images' => [],
            'pricing_type' => MarketplacePricingType::OneTime,
            'price_amount' => 49.99,
            'price_currency' => 'SAR',
            'category_id' => $cat->id, 'tags' => ['paid'],
            'version' => '1.0.0', 'download_count' => 0,
            'average_rating' => 0, 'review_count' => 0,
            'is_featured' => false, 'is_verified' => true,
            'status' => MarketplaceListingStatus::Approved,
            'approved_at' => now(), 'published_at' => now(),
        ]);

        $service = app(MarketplaceService::class);
        $purchase = $service->purchaseTemplate(
            storeId: $this->store->id,
            listingId: $listing->id,
            paymentData: [
                'payment_reference' => 'TEST-REF-001',
                'payment_gateway' => 'test_gateway',
            ],
        );

        $this->assertNotNull($purchase);
        $this->assertEquals(PurchaseType::OneTime, $purchase->purchase_type);
        $this->assertEquals('49.99', $purchase->amount_paid);
        $this->assertTrue($purchase->is_active);

        // Invoice must have been auto-generated
        $this->assertNotNull($purchase->invoice_id);
        $invoice = MarketplacePurchaseInvoice::find($purchase->invoice_id);
        $this->assertNotNull($invoice);
        $this->assertStringStartsWith('MKT-', $invoice->invoice_number);
        $this->assertEquals($this->store->id, $invoice->store_id);
        $this->assertEquals('49.99', $invoice->unit_price);
        $this->assertEquals('15.00', $invoice->tax_rate);
        $this->assertEquals('SAR', $invoice->currency);
        $this->assertEquals('paid', $invoice->status);

        // Tax calculation validation (15% SAT VAT)
        $expectedTax = round(49.99 * 0.15, 2);
        $expectedTotal = round(49.99 + $expectedTax, 2);
        $this->assertEquals($expectedTax, (float) $invoice->tax_amount);
        $this->assertEquals($expectedTotal, (float) $invoice->total_amount);

        // Download count incremented
        $this->assertEquals(1, $listing->fresh()->download_count);
    }

    public function test_purchase_subscription_creates_invoice_with_billing_period(): void
    {
        $cat = MarketplaceCategory::create([
            'name' => 'Scat', 'name_ar' => 'ف', 'slug' => 'sub_cat',
            'icon' => 'heroicon-o-tag', 'sort_order' => 1, 'is_active' => true,
        ]);

        $layout = PosLayoutTemplate::create([
            'business_type_id' => $this->bt->id,
            'layout_key' => 'sub_layout',
            'name' => 'SL', 'name_ar' => 'ا', 'config' => [],
            'is_default' => false, 'is_active' => true, 'sort_order' => 0,
            'canvas_columns' => 24, 'canvas_rows' => 16,
            'canvas_gap_px' => 4, 'canvas_padding_px' => 8,
            'breakpoints' => [], 'version' => '1.0.0', 'is_locked' => false,
        ]);

        $listing = TemplateMarketplaceListing::create([
            'pos_layout_template_id' => $layout->id,
            'publisher_name' => 'Sub Publisher',
            'title' => 'Sub Listing', 'title_ar' => 'اشتراك',
            'description' => 'D', 'description_ar' => 'و',
            'short_description' => 'S', 'short_description_ar' => 'ق',
            'preview_images' => [],
            'pricing_type' => MarketplacePricingType::Subscription,
            'price_amount' => 29.99,
            'price_currency' => 'SAR',
            'subscription_interval' => SubscriptionInterval::Monthly,
            'category_id' => $cat->id, 'tags' => ['sub'],
            'version' => '1.0.0', 'download_count' => 0,
            'average_rating' => 0, 'review_count' => 0,
            'is_featured' => false, 'is_verified' => true,
            'status' => MarketplaceListingStatus::Approved,
            'approved_at' => now(), 'published_at' => now(),
        ]);

        $service = app(MarketplaceService::class);
        $purchase = $service->purchaseTemplate(
            storeId: $this->store->id,
            listingId: $listing->id,
            paymentData: [
                'payment_reference' => 'SUB-REF-001',
                'payment_gateway' => 'test_gateway',
                'auto_renew' => true,
            ],
        );

        $this->assertNotNull($purchase);
        $this->assertEquals(PurchaseType::Subscription, $purchase->purchase_type);
        $this->assertNotNull($purchase->subscription_starts_at);
        $this->assertNotNull($purchase->subscription_expires_at);
        $this->assertTrue($purchase->auto_renew);

        $invoice = MarketplacePurchaseInvoice::find($purchase->invoice_id);
        $this->assertNotNull($invoice);
        $this->assertNotNull($invoice->billing_period);
        $this->assertTrue($invoice->is_recurring);
        $this->assertStringContainsString(' to ', $invoice->billing_period);
    }

    public function test_free_listing_purchase_creates_zero_amount_invoice(): void
    {
        $cat = MarketplaceCategory::create([
            'name' => 'Fcat', 'name_ar' => 'م', 'slug' => 'free_cat',
            'icon' => 'heroicon-o-tag', 'sort_order' => 1, 'is_active' => true,
        ]);

        $layout = PosLayoutTemplate::create([
            'business_type_id' => $this->bt->id,
            'layout_key' => 'free_layout',
            'name' => 'FL', 'name_ar' => 'م', 'config' => [],
            'is_default' => false, 'is_active' => true, 'sort_order' => 0,
            'canvas_columns' => 24, 'canvas_rows' => 16,
            'canvas_gap_px' => 4, 'canvas_padding_px' => 8,
            'breakpoints' => [], 'version' => '1.0.0', 'is_locked' => false,
        ]);

        $listing = TemplateMarketplaceListing::create([
            'pos_layout_template_id' => $layout->id,
            'publisher_name' => 'Free Pub',
            'title' => 'Free Listing', 'title_ar' => 'مجاني',
            'description' => 'D', 'description_ar' => 'و',
            'short_description' => 'S', 'short_description_ar' => 'ق',
            'preview_images' => [],
            'pricing_type' => MarketplacePricingType::Free,
            'price_amount' => 0,
            'price_currency' => 'SAR',
            'category_id' => $cat->id, 'tags' => ['free'],
            'version' => '1.0.0', 'download_count' => 0,
            'average_rating' => 0, 'review_count' => 0,
            'is_featured' => false, 'is_verified' => true,
            'status' => MarketplaceListingStatus::Approved,
            'approved_at' => now(), 'published_at' => now(),
        ]);

        $service = app(MarketplaceService::class);
        $purchase = $service->purchaseTemplate(
            storeId: $this->store->id,
            listingId: $listing->id,
            paymentData: [],
        );

        $this->assertNotNull($purchase);
        $this->assertEquals('0.00', $purchase->amount_paid);

        $invoice = MarketplacePurchaseInvoice::find($purchase->invoice_id);
        $this->assertNotNull($invoice);
        $this->assertEquals('0.00', $invoice->unit_price);
        $this->assertEquals('0.00', $invoice->tax_amount);
        $this->assertEquals('0.00', $invoice->total_amount);
    }

    public function test_purchase_api_endpoint(): void
    {
        $cat = MarketplaceCategory::create([
            'name' => 'Api Cat', 'name_ar' => 'ف', 'slug' => 'api_purchase_cat',
            'icon' => 'heroicon-o-tag', 'sort_order' => 1, 'is_active' => true,
        ]);

        $layout = PosLayoutTemplate::create([
            'business_type_id' => $this->bt->id,
            'layout_key' => 'api_purchase_layout',
            'name' => 'APL', 'name_ar' => 'ا', 'config' => [],
            'is_default' => false, 'is_active' => true, 'sort_order' => 0,
            'canvas_columns' => 24, 'canvas_rows' => 16,
            'canvas_gap_px' => 4, 'canvas_padding_px' => 8,
            'breakpoints' => [], 'version' => '1.0.0', 'is_locked' => false,
        ]);

        $listing = TemplateMarketplaceListing::create([
            'pos_layout_template_id' => $layout->id,
            'publisher_name' => 'API Publisher',
            'title' => 'API Purchase', 'title_ar' => 'شراء',
            'description' => 'D', 'description_ar' => 'و',
            'short_description' => 'S', 'short_description_ar' => 'ق',
            'preview_images' => [],
            'pricing_type' => MarketplacePricingType::OneTime,
            'price_amount' => 19.99,
            'price_currency' => 'SAR',
            'category_id' => $cat->id, 'tags' => ['api'],
            'version' => '1.0.0', 'download_count' => 0,
            'average_rating' => 0, 'review_count' => 0,
            'is_featured' => false, 'is_verified' => true,
            'status' => MarketplaceListingStatus::Approved,
            'approved_at' => now(), 'published_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/ui/marketplace/listings/{$listing->id}/purchase", [
                'payment_reference' => 'API-REF-001',
                'payment_gateway' => 'api_gateway',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.amount_paid', '19.99')
            ->assertJsonStructure(['data' => ['id', 'purchase_type', 'amount_paid', 'invoice']]);

        // Verify invoice was included in response
        $this->assertArrayHasKey('invoice', $response->json('data'));
    }

    public function test_duplicate_purchase_returns_failure(): void
    {
        $cat = MarketplaceCategory::create([
            'name' => 'Dup Cat', 'name_ar' => 'م', 'slug' => 'dup_cat',
            'icon' => 'heroicon-o-tag', 'sort_order' => 1, 'is_active' => true,
        ]);

        $layout = PosLayoutTemplate::create([
            'business_type_id' => $this->bt->id,
            'layout_key' => 'dup_layout',
            'name' => 'DL', 'name_ar' => 'م', 'config' => [],
            'is_default' => false, 'is_active' => true, 'sort_order' => 0,
            'canvas_columns' => 24, 'canvas_rows' => 16,
            'canvas_gap_px' => 4, 'canvas_padding_px' => 8,
            'breakpoints' => [], 'version' => '1.0.0', 'is_locked' => false,
        ]);

        $listing = TemplateMarketplaceListing::create([
            'pos_layout_template_id' => $layout->id,
            'publisher_name' => 'Dup Pub',
            'title' => 'Dup List', 'title_ar' => 'مكرر',
            'description' => 'D', 'description_ar' => 'و',
            'short_description' => 'S', 'short_description_ar' => 'ق',
            'preview_images' => [],
            'pricing_type' => MarketplacePricingType::OneTime,
            'price_amount' => 10,
            'price_currency' => 'SAR',
            'category_id' => $cat->id, 'tags' => [],
            'version' => '1.0.0', 'download_count' => 0,
            'average_rating' => 0, 'review_count' => 0,
            'is_featured' => false, 'is_verified' => true,
            'status' => MarketplaceListingStatus::Approved,
            'approved_at' => now(), 'published_at' => now(),
        ]);

        // First purchase
        $this->withToken($this->token)
            ->postJson("/api/v2/ui/marketplace/listings/{$listing->id}/purchase", [
                'payment_reference' => 'DUP-1',
                'payment_gateway' => 'test',
            ])->assertCreated();

        // Duplicate purchase should fail
        $this->withToken($this->token)
            ->postJson("/api/v2/ui/marketplace/listings/{$listing->id}/purchase", [
                'payment_reference' => 'DUP-2',
                'payment_gateway' => 'test',
            ])->assertStatus(422);
    }

    public function test_cannot_purchase_non_approved_listing(): void
    {
        $cat = MarketplaceCategory::create([
            'name' => 'NA Cat', 'name_ar' => 'ف', 'slug' => 'na_cat',
            'icon' => 'heroicon-o-tag', 'sort_order' => 1, 'is_active' => true,
        ]);

        $layout = PosLayoutTemplate::create([
            'business_type_id' => $this->bt->id,
            'layout_key' => 'na_layout',
            'name' => 'NA', 'name_ar' => 'غ', 'config' => [],
            'is_default' => false, 'is_active' => true, 'sort_order' => 0,
            'canvas_columns' => 24, 'canvas_rows' => 16,
            'canvas_gap_px' => 4, 'canvas_padding_px' => 8,
            'breakpoints' => [], 'version' => '1.0.0', 'is_locked' => false,
        ]);

        $draftListing = TemplateMarketplaceListing::create([
            'pos_layout_template_id' => $layout->id,
            'publisher_name' => 'Draft Pub',
            'title' => 'Draft', 'title_ar' => 'مسودة',
            'description' => 'D', 'description_ar' => 'و',
            'short_description' => 'S', 'short_description_ar' => 'ق',
            'preview_images' => [],
            'pricing_type' => MarketplacePricingType::OneTime,
            'price_amount' => 10,
            'price_currency' => 'SAR',
            'category_id' => $cat->id, 'tags' => [],
            'version' => '1.0.0', 'download_count' => 0,
            'average_rating' => 0, 'review_count' => 0,
            'is_featured' => false, 'is_verified' => false,
            'status' => MarketplaceListingStatus::Draft,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/ui/marketplace/listings/{$draftListing->id}/purchase", [
                'payment_reference' => 'DRAFT-REF',
                'payment_gateway' => 'test',
            ]);

        $response->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════
    //  INVOICE API
    // ═══════════════════════════════════════════════════════════

    public function test_store_invoices_api(): void
    {
        $cat = MarketplaceCategory::create([
            'name' => 'Inv Cat', 'name_ar' => 'ف', 'slug' => 'inv_cat',
            'icon' => 'heroicon-o-tag', 'sort_order' => 1, 'is_active' => true,
        ]);

        $layout = PosLayoutTemplate::create([
            'business_type_id' => $this->bt->id,
            'layout_key' => 'inv_layout',
            'name' => 'IL', 'name_ar' => 'ف', 'config' => [],
            'is_default' => false, 'is_active' => true, 'sort_order' => 0,
            'canvas_columns' => 24, 'canvas_rows' => 16,
            'canvas_gap_px' => 4, 'canvas_padding_px' => 8,
            'breakpoints' => [], 'version' => '1.0.0', 'is_locked' => false,
        ]);

        $listing = TemplateMarketplaceListing::create([
            'pos_layout_template_id' => $layout->id,
            'publisher_name' => 'Inv Pub',
            'title' => 'Inv List', 'title_ar' => 'فاتورة',
            'description' => 'D', 'description_ar' => 'و',
            'short_description' => 'S', 'short_description_ar' => 'ق',
            'preview_images' => [],
            'pricing_type' => MarketplacePricingType::OneTime,
            'price_amount' => 25.00,
            'price_currency' => 'SAR',
            'category_id' => $cat->id, 'tags' => [],
            'version' => '1.0.0', 'download_count' => 0,
            'average_rating' => 0, 'review_count' => 0,
            'is_featured' => false, 'is_verified' => true,
            'status' => MarketplaceListingStatus::Approved,
            'approved_at' => now(), 'published_at' => now(),
        ]);

        // Purchase to generate invoice
        $service = app(MarketplaceService::class);
        $purchase = $service->purchaseTemplate(
            storeId: $this->store->id,
            listingId: $listing->id,
            paymentData: ['payment_reference' => 'INV-REF', 'payment_gateway' => 'test'],
        );

        // List invoices for this store
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/ui/marketplace/my-invoices');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'invoice_number', 'total_amount']]]);

        // Get specific invoice
        $invoiceId = $purchase->invoice_id;
        $response2 = $this->withToken($this->token)
            ->getJson("/api/v2/ui/marketplace/invoices/{$invoiceId}");

        $response2->assertOk()
            ->assertJsonPath('data.invoice_number', fn ($v) => str_starts_with($v, 'MKT-'));
    }

    // ═══════════════════════════════════════════════════════════
    //  TRANSLATIONS
    // ═══════════════════════════════════════════════════════════

    public function test_english_translations_contain_new_keys(): void
    {
        $keys = [
            'layout_type_scan_focused', 'layout_type_table_management',
            'layout_type_prescription', 'widget_loyalty_points',
            'widget_table_map', 'marketplace_cat_premium',
            'marketplace_cat_seasonal', 'label_food_expiry',
            'invoice_number', 'invoice_details', 'tax_rate', 'total_amount',
        ];

        foreach ($keys as $key) {
            $this->assertNotNull(
                __("ui.{$key}"),
                "Missing EN translation for ui.{$key}"
            );
            $this->assertNotEquals(
                "ui.{$key}",
                __("ui.{$key}"),
                "Untranslated key: ui.{$key}"
            );
        }
    }

    public function test_arabic_translations_contain_new_keys(): void
    {
        $keys = [
            'layout_type_scan_focused', 'widget_loyalty_points',
            'marketplace_cat_premium', 'invoice_number',
        ];

        foreach ($keys as $key) {
            $value = trans("ui.{$key}", [], 'ar');
            $this->assertNotEquals("ui.{$key}", $value, "Missing AR translation for ui.{$key}");
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  AUTHENTICATION GUARD
    // ═══════════════════════════════════════════════════════════

    public function test_unauthenticated_access_denied(): void
    {
        $endpoints = [
            '/api/v2/ui/themes',
            '/api/v2/ui/layouts',
            '/api/v2/ui/receipt-templates',
            '/api/v2/ui/cfd-themes',
            '/api/v2/ui/signage-templates',
            '/api/v2/ui/label-templates',
            '/api/v2/ui/marketplace/categories',
            '/api/v2/ui/marketplace/listings',
        ];

        foreach ($endpoints as $endpoint) {
            $this->getJson($endpoint)->assertUnauthorized();
        }
    }
}
