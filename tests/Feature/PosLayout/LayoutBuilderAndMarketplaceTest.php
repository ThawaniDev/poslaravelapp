<?php

namespace Tests\Feature\PosLayout;

use App\Domain\Auth\Models\User;
use App\Domain\ContentOnboarding\Enums\MarketplaceListingStatus;
use App\Domain\ContentOnboarding\Enums\MarketplacePricingType;
use App\Domain\ContentOnboarding\Enums\PurchaseType;
use App\Domain\ContentOnboarding\Enums\SubscriptionInterval;
use App\Domain\ContentOnboarding\Enums\WidgetCategory;
use App\Domain\ContentOnboarding\Models\BusinessType;
use App\Domain\ContentOnboarding\Models\LayoutWidget;
use App\Domain\ContentOnboarding\Models\LayoutWidgetPlacement;
use App\Domain\ContentOnboarding\Models\MarketplaceCategory;
use App\Domain\ContentOnboarding\Models\PosLayoutTemplate;
use App\Domain\ContentOnboarding\Models\TemplateMarketplaceListing;
use App\Domain\ContentOnboarding\Models\TemplatePurchase;
use App\Domain\ContentOnboarding\Models\TemplateReview;
use App\Domain\ContentOnboarding\Models\TemplateVersion;
use App\Domain\ContentOnboarding\Models\Theme;
use App\Domain\ContentOnboarding\Models\ThemeVariable;
use App\Domain\ContentOnboarding\Models\WidgetThemeOverride;
use App\Domain\ContentOnboarding\Services\LayoutBuilderService;
use App\Domain\ContentOnboarding\Services\MarketplaceService;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutBuilderAndMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;
    private BusinessType $businessType;
    private Theme $theme;
    private PosLayoutTemplate $layout;
    private LayoutWidget $coreWidget;
    private LayoutWidget $commerceWidget;
    private MarketplaceCategory $category;

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
            'email' => 'builder@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;

        $this->businessType = BusinessType::create([
            'name' => 'Grocery', 'name_ar' => 'بقالة', 'slug' => 'grocery',
            'icon' => '🛒', 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->theme = Theme::create([
            'name' => 'Light', 'slug' => 'light',
            'primary_color' => '#FD8209', 'secondary_color' => '#1A1A2E',
            'background_color' => '#FFFFFF', 'text_color' => '#333333',
            'is_active' => true, 'is_system' => true,
            'typography_config' => ['font_family' => 'Inter'],
            'spacing_config' => ['base' => 4],
            'border_config' => [], 'shadow_config' => [],
            'animation_config' => [], 'css_variables' => [],
        ]);

        $this->layout = PosLayoutTemplate::create([
            'business_type_id' => $this->businessType->id,
            'layout_key' => 'grocery-standard',
            'name' => 'Standard Grid', 'name_ar' => 'شبكة قياسية',
            'config' => ['layout_type' => 'grid'],
            'is_default' => true, 'is_active' => true, 'sort_order' => 1,
            'canvas_columns' => 24, 'canvas_rows' => 16,
            'canvas_gap_px' => 4, 'canvas_padding_px' => 8,
            'breakpoints' => [], 'version' => '1.0.0', 'is_locked' => false,
        ]);

        $this->coreWidget = LayoutWidget::create([
            'slug' => 'product_grid', 'name' => 'Product Grid', 'name_ar' => 'شبكة المنتجات',
            'category' => WidgetCategory::Core, 'icon' => 'heroicon-o-squares-2x2',
            'default_width' => 12, 'default_height' => 10,
            'min_width' => 6, 'min_height' => 4, 'max_width' => 24, 'max_height' => 16,
            'is_resizable' => true, 'is_required' => true,
            'properties_schema' => ['columns' => ['type' => 'integer', 'default' => 4]],
            'default_properties' => ['columns' => 4],
            'is_active' => true, 'sort_order' => 1,
        ]);

        $this->commerceWidget = LayoutWidget::create([
            'slug' => 'cart_panel', 'name' => 'Cart Panel', 'name_ar' => 'لوحة السلة',
            'category' => WidgetCategory::Commerce, 'icon' => 'heroicon-o-shopping-cart',
            'default_width' => 8, 'default_height' => 14,
            'min_width' => 6, 'min_height' => 6, 'max_width' => 12, 'max_height' => 16,
            'is_resizable' => true, 'is_required' => true,
            'properties_schema' => ['display_mode' => ['type' => 'select', 'default' => 'detailed']],
            'default_properties' => ['display_mode' => 'detailed'],
            'is_active' => true, 'sort_order' => 2,
        ]);

        $this->category = MarketplaceCategory::create([
            'name' => 'Retail', 'name_ar' => 'التجزئة', 'slug' => 'retail',
            'icon' => 'heroicon-o-shopping-bag', 'sort_order' => 1, 'is_active' => true,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  Layout Builder — Widget Catalog API
    // ═══════════════════════════════════════════════════════════

    public function test_unauthenticated_builder_access_rejected(): void
    {
        $this->getJson('/api/v2/ui/layout-builder/widgets')->assertUnauthorized();
    }

    public function test_get_widget_catalog(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v2/ui/layout-builder/widgets')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_get_widget_catalog_filtered_by_category(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v2/ui/layout-builder/widgets?category=core')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'product_grid');
    }

    public function test_get_single_widget(): void
    {
        $this->withToken($this->token)
            ->getJson("/api/v2/ui/layout-builder/widgets/{$this->coreWidget->id}")
            ->assertOk()
            ->assertJsonPath('data.slug', 'product_grid')
            ->assertJsonPath('data.name', 'Product Grid');
    }

    public function test_get_nonexistent_widget_returns_404(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v2/ui/layout-builder/widgets/00000000-0000-0000-0000-000000000000')
            ->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════
    //  Layout Builder — Canvas Config API
    // ═══════════════════════════════════════════════════════════

    public function test_get_canvas_config(): void
    {
        $this->withToken($this->token)
            ->getJson("/api/v2/ui/layout-builder/templates/{$this->layout->id}/canvas")
            ->assertOk()
            ->assertJsonPath('data.canvas_columns', 24)
            ->assertJsonPath('data.canvas_rows', 16)
            ->assertJsonPath('data.canvas_gap_px', 4)
            ->assertJsonPath('data.is_locked', false);
    }

    public function test_update_canvas_config(): void
    {
        $this->withToken($this->token)
            ->putJson("/api/v2/ui/layout-builder/templates/{$this->layout->id}/canvas", [
                'canvas_columns' => 32,
                'canvas_gap_px' => 8,
            ])
            ->assertOk()
            ->assertJsonPath('data.canvas_columns', 32)
            ->assertJsonPath('data.canvas_gap_px', 8);
    }

    public function test_update_canvas_config_validates_bounds(): void
    {
        $this->withToken($this->token)
            ->putJson("/api/v2/ui/layout-builder/templates/{$this->layout->id}/canvas", [
                'canvas_columns' => 100,
            ])
            ->assertUnprocessable();
    }

    public function test_update_canvas_fails_when_locked(): void
    {
        $this->layout->update(['is_locked' => true]);

        $this->withToken($this->token)
            ->putJson("/api/v2/ui/layout-builder/templates/{$this->layout->id}/canvas", [
                'canvas_columns' => 32,
            ])
            ->assertJsonPath('success', false);
    }

    // ═══════════════════════════════════════════════════════════
    //  Layout Builder — Widget Placements API
    // ═══════════════════════════════════════════════════════════

    public function test_get_empty_placements(): void
    {
        $this->withToken($this->token)
            ->getJson("/api/v2/ui/layout-builder/templates/{$this->layout->id}/placements")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_add_widget_to_template(): void
    {
        $this->withToken($this->token)
            ->postJson("/api/v2/ui/layout-builder/templates/{$this->layout->id}/placements", [
                'widget_id' => $this->coreWidget->id,
                'grid_x' => 0,
                'grid_y' => 0,
                'grid_w' => 12,
                'grid_h' => 10,
            ])
            ->assertCreated()
            ->assertJsonPath('data.grid_x', 0)
            ->assertJsonPath('data.grid_w', 12);

        $this->assertDatabaseCount('layout_widget_placements', 1);
    }

    public function test_add_widget_uses_defaults_when_size_omitted(): void
    {
        $this->withToken($this->token)
            ->postJson("/api/v2/ui/layout-builder/templates/{$this->layout->id}/placements", [
                'widget_id' => $this->coreWidget->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.grid_w', 12)
            ->assertJsonPath('data.grid_h', 10);
    }

    public function test_add_widget_fails_for_locked_template(): void
    {
        $this->layout->update(['is_locked' => true]);

        $this->withToken($this->token)
            ->postJson("/api/v2/ui/layout-builder/templates/{$this->layout->id}/placements", [
                'widget_id' => $this->coreWidget->id,
            ])
            ->assertJsonPath('success', false);
    }

    public function test_add_widget_fails_for_inactive_widget(): void
    {
        $this->coreWidget->update(['is_active' => false]);

        $this->withToken($this->token)
            ->postJson("/api/v2/ui/layout-builder/templates/{$this->layout->id}/placements", [
                'widget_id' => $this->coreWidget->id,
            ])
            ->assertJsonPath('success', false);
    }

    public function test_update_placement_position(): void
    {
        $placement = LayoutWidgetPlacement::create([
            'pos_layout_template_id' => $this->layout->id,
            'layout_widget_id' => $this->coreWidget->id,
            'instance_key' => 'product_grid_1',
            'grid_x' => 0, 'grid_y' => 0, 'grid_w' => 12, 'grid_h' => 10,
            'z_index' => 0, 'properties' => [],
        ]);

        $this->withToken($this->token)
            ->putJson("/api/v2/ui/layout-builder/placements/{$placement->id}", [
                'grid_x' => 4,
                'grid_y' => 2,
                'z_index' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('data.grid_x', 4)
            ->assertJsonPath('data.grid_y', 2)
            ->assertJsonPath('data.z_index', 5);
    }

    public function test_update_placement_enforces_min_max_size(): void
    {
        $placement = LayoutWidgetPlacement::create([
            'pos_layout_template_id' => $this->layout->id,
            'layout_widget_id' => $this->coreWidget->id,
            'instance_key' => 'product_grid_1',
            'grid_x' => 0, 'grid_y' => 0, 'grid_w' => 12, 'grid_h' => 10,
            'z_index' => 0, 'properties' => [],
        ]);

        $this->withToken($this->token)
            ->putJson("/api/v2/ui/layout-builder/placements/{$placement->id}", [
                'grid_w' => 2,  // Below min_width of 6
            ])
            ->assertOk()
            ->assertJsonPath('data.grid_w', 6);  // Clamped to min
    }

    public function test_remove_placement(): void
    {
        $placement = LayoutWidgetPlacement::create([
            'pos_layout_template_id' => $this->layout->id,
            'layout_widget_id' => $this->coreWidget->id,
            'instance_key' => 'product_grid_1',
            'grid_x' => 0, 'grid_y' => 0, 'grid_w' => 12, 'grid_h' => 10,
            'z_index' => 0, 'properties' => [],
        ]);

        $this->withToken($this->token)
            ->deleteJson("/api/v2/ui/layout-builder/placements/{$placement->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('layout_widget_placements', 0);
    }

    public function test_batch_update_placements(): void
    {
        $p1 = LayoutWidgetPlacement::create([
            'pos_layout_template_id' => $this->layout->id,
            'layout_widget_id' => $this->coreWidget->id,
            'instance_key' => 'pg_1',
            'grid_x' => 0, 'grid_y' => 0, 'grid_w' => 12, 'grid_h' => 10,
            'z_index' => 0, 'properties' => [],
        ]);
        $p2 = LayoutWidgetPlacement::create([
            'pos_layout_template_id' => $this->layout->id,
            'layout_widget_id' => $this->commerceWidget->id,
            'instance_key' => 'cp_1',
            'grid_x' => 12, 'grid_y' => 0, 'grid_w' => 8, 'grid_h' => 14,
            'z_index' => 0, 'properties' => [],
        ]);

        $this->withToken($this->token)
            ->putJson("/api/v2/ui/layout-builder/templates/{$this->layout->id}/placements/batch", [
                'placements' => [
                    ['id' => $p1->id, 'grid_x' => 2, 'z_index' => 1],
                    ['id' => $p2->id, 'grid_x' => 14, 'z_index' => 2],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('layout_widget_placements', ['id' => $p1->id, 'grid_x' => 2]);
        $this->assertDatabaseHas('layout_widget_placements', ['id' => $p2->id, 'grid_x' => 14]);
    }

    // ═══════════════════════════════════════════════════════════
    //  Layout Builder — Theme Overrides API
    // ═══════════════════════════════════════════════════════════

    public function test_set_widget_theme_overrides(): void
    {
        $placement = LayoutWidgetPlacement::create([
            'pos_layout_template_id' => $this->layout->id,
            'layout_widget_id' => $this->coreWidget->id,
            'instance_key' => 'pg_1',
            'grid_x' => 0, 'grid_y' => 0, 'grid_w' => 12, 'grid_h' => 10,
            'z_index' => 0, 'properties' => [],
        ]);

        $this->withToken($this->token)
            ->putJson("/api/v2/ui/layout-builder/placements/{$placement->id}/theme-overrides", [
                'overrides' => [
                    '--bg-color' => '#FF0000',
                    '--font-size' => '14px',
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseCount('widget_theme_overrides', 2);
    }

    public function test_remove_widget_theme_override(): void
    {
        $placement = LayoutWidgetPlacement::create([
            'pos_layout_template_id' => $this->layout->id,
            'layout_widget_id' => $this->coreWidget->id,
            'instance_key' => 'pg_1',
            'grid_x' => 0, 'grid_y' => 0, 'grid_w' => 12, 'grid_h' => 10,
            'z_index' => 0, 'properties' => [],
        ]);

        WidgetThemeOverride::create([
            'layout_widget_placement_id' => $placement->id,
            'variable_key' => '--bg-color',
            'value' => '#FF0000',
        ]);

        $this->withToken($this->token)
            ->deleteJson("/api/v2/ui/layout-builder/placements/{$placement->id}/theme-overrides/--bg-color")
            ->assertOk();

        $this->assertDatabaseCount('widget_theme_overrides', 0);
    }

    // ═══════════════════════════════════════════════════════════
    //  Layout Builder — Template Cloning API
    // ═══════════════════════════════════════════════════════════

    public function test_clone_template(): void
    {
        // Add widgets to source
        LayoutWidgetPlacement::create([
            'pos_layout_template_id' => $this->layout->id,
            'layout_widget_id' => $this->coreWidget->id,
            'instance_key' => 'pg_1',
            'grid_x' => 0, 'grid_y' => 0, 'grid_w' => 12, 'grid_h' => 10,
            'z_index' => 0, 'properties' => ['columns' => 4],
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v2/ui/layout-builder/templates/{$this->layout->id}/clone", [
                'name' => 'Cloned Layout',
                'name_ar' => 'تخطيط مستنسخ',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Cloned Layout')
            ->assertJsonPath('data.is_locked', false);

        $this->assertDatabaseCount('pos_layout_templates', 2);
        $this->assertDatabaseCount('layout_widget_placements', 2);
    }

    public function test_cloned_template_has_correct_source_id(): void
    {
        $this->withToken($this->token)
            ->postJson("/api/v2/ui/layout-builder/templates/{$this->layout->id}/clone")
            ->assertCreated();

        $clone = PosLayoutTemplate::where('clone_source_id', $this->layout->id)->first();
        $this->assertNotNull($clone);
        $this->assertEquals($this->layout->id, $clone->clone_source_id);
    }

    // ═══════════════════════════════════════════════════════════
    //  Layout Builder — Versioning API
    // ═══════════════════════════════════════════════════════════

    public function test_create_template_version(): void
    {
        LayoutWidgetPlacement::create([
            'pos_layout_template_id' => $this->layout->id,
            'layout_widget_id' => $this->coreWidget->id,
            'instance_key' => 'pg_1',
            'grid_x' => 0, 'grid_y' => 0, 'grid_w' => 12, 'grid_h' => 10,
            'z_index' => 0, 'properties' => ['columns' => 4],
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v2/ui/layout-builder/templates/{$this->layout->id}/versions", [
                'version_number' => '1.1.0',
                'changelog' => 'Added product grid widget',
            ])
            ->assertCreated()
            ->assertJsonPath('data.version_number', '1.1.0');

        $this->assertDatabaseCount('template_versions', 1);
        $this->layout->refresh();
        $this->assertEquals('1.1.0', $this->layout->version);
    }

    public function test_get_template_versions(): void
    {
        TemplateVersion::create([
            'pos_layout_template_id' => $this->layout->id,
            'version_number' => '1.0.0',
            'canvas_snapshot' => ['canvas_columns' => 24],
            'widget_placements_snapshot' => [],
            'published_at' => now(),
        ]);

        $this->withToken($this->token)
            ->getJson("/api/v2/ui/layout-builder/templates/{$this->layout->id}/versions")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // ═══════════════════════════════════════════════════════════
    //  Layout Builder — Full Layout API
    // ═══════════════════════════════════════════════════════════

    public function test_get_full_layout(): void
    {
        LayoutWidgetPlacement::create([
            'pos_layout_template_id' => $this->layout->id,
            'layout_widget_id' => $this->coreWidget->id,
            'instance_key' => 'pg_1',
            'grid_x' => 0, 'grid_y' => 0, 'grid_w' => 12, 'grid_h' => 10,
            'z_index' => 0, 'properties' => [],
        ]);

        $this->withToken($this->token)
            ->getJson("/api/v2/ui/layout-builder/templates/{$this->layout->id}/full")
            ->assertOk()
            ->assertJsonStructure(['data' => ['template', 'canvas', 'placements']]);
    }

    public function test_full_layout_returns_404_for_nonexistent_template(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v2/ui/layout-builder/templates/00000000-0000-0000-0000-000000000000/full')
            ->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════
    //  Marketplace — Browse API
    // ═══════════════════════════════════════════════════════════

    public function test_unauthenticated_marketplace_access_rejected(): void
    {
        $this->getJson('/api/v2/ui/marketplace/listings')->assertUnauthorized();
    }

    public function test_browse_marketplace_listings(): void
    {
        $this->createApprovedListing();

        $this->withToken($this->token)
            ->getJson('/api/v2/ui/marketplace/listings')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_browse_listings_filters_by_pricing_type(): void
    {
        $this->createApprovedListing(['pricing_type' => MarketplacePricingType::Free]);

        $layout2 = PosLayoutTemplate::create([
            'business_type_id' => $this->businessType->id,
            'layout_key' => 'paid-layout',
            'name' => 'Paid Layout', 'name_ar' => 'تخطيط مدفوع',
            'config' => [], 'is_active' => true, 'sort_order' => 2,
        ]);

        TemplateMarketplaceListing::create([
            'pos_layout_template_id' => $layout2->id,
            'publisher_name' => 'Publisher',
            'title' => 'Paid', 'title_ar' => 'مدفوع',
            'description' => 'Paid template', 'description_ar' => 'قالب مدفوع',
            'pricing_type' => MarketplacePricingType::OneTime,
            'price_amount' => 29.99,
            'status' => MarketplaceListingStatus::Approved,
            'published_at' => now(),
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v2/ui/marketplace/listings?pricing_type=free')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_browse_excludes_non_approved_listings(): void
    {
        TemplateMarketplaceListing::create([
            'pos_layout_template_id' => $this->layout->id,
            'publisher_name' => 'Publisher',
            'title' => 'Draft', 'title_ar' => 'مسودة',
            'description' => 'Draft', 'description_ar' => 'مسودة',
            'pricing_type' => MarketplacePricingType::Free,
            'status' => MarketplaceListingStatus::Draft,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v2/ui/marketplace/listings')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_get_single_listing(): void
    {
        $listing = $this->createApprovedListing();

        $this->withToken($this->token)
            ->getJson("/api/v2/ui/marketplace/listings/{$listing->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Test Listing');
    }

    public function test_get_marketplace_categories(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v2/ui/marketplace/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Retail');
    }

    public function test_get_single_category(): void
    {
        $this->withToken($this->token)
            ->getJson("/api/v2/ui/marketplace/categories/{$this->category->id}")
            ->assertOk()
            ->assertJsonPath('data.slug', 'retail');
    }

    // ═══════════════════════════════════════════════════════════
    //  Marketplace — Purchases API
    // ═══════════════════════════════════════════════════════════

    public function test_purchase_free_template(): void
    {
        $listing = $this->createApprovedListing(['pricing_type' => MarketplacePricingType::Free]);

        $this->withToken($this->token)
            ->postJson("/api/v2/ui/marketplace/listings/{$listing->id}/purchase")
            ->assertCreated()
            ->assertJsonPath('data.amount_paid', '0.00');

        $this->assertDatabaseCount('template_purchases', 1);
        $listing->refresh();
        $this->assertEquals(1, $listing->download_count);
    }

    public function test_purchase_one_time_template(): void
    {
        $listing = $this->createApprovedListing([
            'pricing_type' => MarketplacePricingType::OneTime,
            'price_amount' => 49.99,
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v2/ui/marketplace/listings/{$listing->id}/purchase", [
                'payment_reference' => 'PAY-123',
                'payment_gateway' => 'stripe',
            ])
            ->assertCreated()
            ->assertJsonPath('data.amount_paid', '49.99')
            ->assertJsonPath('data.payment_reference', 'PAY-123');
    }

    public function test_purchase_subscription_template(): void
    {
        $listing = $this->createApprovedListing([
            'pricing_type' => MarketplacePricingType::Subscription,
            'price_amount' => 9.99,
            'subscription_interval' => SubscriptionInterval::Monthly,
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v2/ui/marketplace/listings/{$listing->id}/purchase", [
                'payment_reference' => 'SUB-123',
            ])
            ->assertCreated()
            ->assertJsonPath('data.purchase_type', 'subscription')
            ->assertJsonPath('data.auto_renew', true);

        $purchase = TemplatePurchase::first();
        $this->assertNotNull($purchase->subscription_starts_at);
        $this->assertNotNull($purchase->subscription_expires_at);
    }

    public function test_cannot_purchase_twice(): void
    {
        $listing = $this->createApprovedListing(['pricing_type' => MarketplacePricingType::Free]);

        $this->withToken($this->token)
            ->postJson("/api/v2/ui/marketplace/listings/{$listing->id}/purchase");

        $this->withToken($this->token)
            ->postJson("/api/v2/ui/marketplace/listings/{$listing->id}/purchase")
            ->assertJsonPath('success', false);
    }

    public function test_check_access(): void
    {
        $listing = $this->createApprovedListing(['pricing_type' => MarketplacePricingType::Free]);

        // Before purchase
        $this->withToken($this->token)
            ->getJson("/api/v2/ui/marketplace/listings/{$listing->id}/check-access")
            ->assertOk()
            ->assertJsonPath('data.has_access', false);

        // After purchase
        $this->withToken($this->token)
            ->postJson("/api/v2/ui/marketplace/listings/{$listing->id}/purchase");

        $this->withToken($this->token)
            ->getJson("/api/v2/ui/marketplace/listings/{$listing->id}/check-access")
            ->assertOk()
            ->assertJsonPath('data.has_access', true);
    }

    public function test_get_my_purchases(): void
    {
        $listing = $this->createApprovedListing(['pricing_type' => MarketplacePricingType::Free]);

        $this->withToken($this->token)
            ->postJson("/api/v2/ui/marketplace/listings/{$listing->id}/purchase");

        $this->withToken($this->token)
            ->getJson('/api/v2/ui/marketplace/my-purchases')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_cancel_subscription(): void
    {
        $listing = $this->createApprovedListing([
            'pricing_type' => MarketplacePricingType::Subscription,
            'price_amount' => 9.99,
            'subscription_interval' => SubscriptionInterval::Monthly,
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v2/ui/marketplace/listings/{$listing->id}/purchase", [
                'payment_reference' => 'SUB-1',
            ]);

        $purchase = TemplatePurchase::first();

        $this->withToken($this->token)
            ->postJson("/api/v2/ui/marketplace/purchases/{$purchase->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.auto_renew', false);

        $purchase->refresh();
        $this->assertNotNull($purchase->cancelled_at);
    }

    // ═══════════════════════════════════════════════════════════
    //  Marketplace — Reviews API
    // ═══════════════════════════════════════════════════════════

    public function test_get_listing_reviews(): void
    {
        $listing = $this->createApprovedListing();

        TemplateReview::create([
            'marketplace_listing_id' => $listing->id,
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'rating' => 5,
            'title' => 'Great',
            'body' => 'Excellent template!',
            'is_published' => true,
        ]);

        $this->withToken($this->token)
            ->getJson("/api/v2/ui/marketplace/listings/{$listing->id}/reviews")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_create_review(): void
    {
        $listing = $this->createApprovedListing();

        // Purchase first to mark as verified
        $this->withToken($this->token)
            ->postJson("/api/v2/ui/marketplace/listings/{$listing->id}/purchase");

        $this->withToken($this->token)
            ->postJson("/api/v2/ui/marketplace/listings/{$listing->id}/reviews", [
                'rating' => 4,
                'title' => 'Good layout',
                'body' => 'Works well for our store.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.rating', 4)
            ->assertJsonPath('data.is_verified_purchase', true);

        $listing->refresh();
        $this->assertEquals(4.0, (float) $listing->average_rating);
        $this->assertEquals(1, $listing->review_count);
    }

    public function test_cannot_review_twice(): void
    {
        $listing = $this->createApprovedListing();

        $this->withToken($this->token)
            ->postJson("/api/v2/ui/marketplace/listings/{$listing->id}/reviews", ['rating' => 5]);

        $this->withToken($this->token)
            ->postJson("/api/v2/ui/marketplace/listings/{$listing->id}/reviews", ['rating' => 3])
            ->assertJsonPath('success', false);
    }

    public function test_update_review(): void
    {
        $listing = $this->createApprovedListing();

        $review = TemplateReview::create([
            'marketplace_listing_id' => $listing->id,
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'rating' => 3,
            'is_published' => true,
        ]);

        $this->withToken($this->token)
            ->putJson("/api/v2/ui/marketplace/reviews/{$review->id}", [
                'rating' => 5,
                'title' => 'Updated',
            ])
            ->assertOk()
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.title', 'Updated');
    }

    public function test_delete_review(): void
    {
        $listing = $this->createApprovedListing();

        $review = TemplateReview::create([
            'marketplace_listing_id' => $listing->id,
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'rating' => 5,
            'is_published' => true,
        ]);

        $this->withToken($this->token)
            ->deleteJson("/api/v2/ui/marketplace/reviews/{$review->id}")
            ->assertOk();

        $this->assertDatabaseCount('template_reviews', 0);
    }

    // ═══════════════════════════════════════════════════════════
    //  Service — LayoutBuilderService
    // ═══════════════════════════════════════════════════════════

    public function test_service_get_widget_catalog_all(): void
    {
        $service = app(LayoutBuilderService::class);
        $result = $service->getWidgetCatalog();
        $this->assertCount(2, $result);
    }

    public function test_service_get_widget_catalog_by_category(): void
    {
        $service = app(LayoutBuilderService::class);
        $result = $service->getWidgetCatalog(WidgetCategory::Commerce);
        $this->assertCount(1, $result);
        $this->assertEquals('cart_panel', $result->first()->slug);
    }

    public function test_service_get_widget_by_slug(): void
    {
        $service = app(LayoutBuilderService::class);
        $widget = $service->getWidgetBySlug('product_grid');
        $this->assertNotNull($widget);
        $this->assertEquals('Product Grid', $widget->name);
    }

    public function test_service_canvas_config(): void
    {
        $service = app(LayoutBuilderService::class);
        $config = $service->getCanvasConfig($this->layout->id);
        $this->assertEquals(24, $config['canvas_columns']);
        $this->assertEquals(16, $config['canvas_rows']);
    }

    public function test_service_clone_with_overrides_and_placements(): void
    {
        $service = app(LayoutBuilderService::class);

        // Add placements with theme overrides
        $placement = LayoutWidgetPlacement::create([
            'pos_layout_template_id' => $this->layout->id,
            'layout_widget_id' => $this->coreWidget->id,
            'instance_key' => 'pg_1',
            'grid_x' => 0, 'grid_y' => 0, 'grid_w' => 12, 'grid_h' => 10,
            'z_index' => 0, 'properties' => [],
        ]);

        WidgetThemeOverride::create([
            'layout_widget_placement_id' => $placement->id,
            'variable_key' => '--bg', 'value' => '#FFF',
        ]);

        $clone = $service->cloneTemplate($this->layout->id, ['name' => 'Clone']);
        $this->assertNotNull($clone);
        $this->assertEquals('Clone', $clone->name);
        $this->assertEquals($this->layout->id, $clone->clone_source_id);

        $clonePlacements = LayoutWidgetPlacement::where('pos_layout_template_id', $clone->id)->get();
        $this->assertCount(1, $clonePlacements);

        $cloneOverrides = WidgetThemeOverride::where('layout_widget_placement_id', $clonePlacements->first()->id)->get();
        $this->assertCount(1, $cloneOverrides);
    }

    public function test_service_create_version_snapshot(): void
    {
        $service = app(LayoutBuilderService::class);

        LayoutWidgetPlacement::create([
            'pos_layout_template_id' => $this->layout->id,
            'layout_widget_id' => $this->coreWidget->id,
            'instance_key' => 'pg_1',
            'grid_x' => 0, 'grid_y' => 0, 'grid_w' => 12, 'grid_h' => 10,
            'z_index' => 0, 'properties' => ['columns' => 4],
        ]);

        $version = $service->createVersion($this->layout->id, '2.0.0', 'Major update', $this->user->id);
        $this->assertNotNull($version);
        $this->assertEquals('2.0.0', $version->version_number);
        $this->assertIsArray($version->canvas_snapshot);
        $this->assertCount(1, $version->widget_placements_snapshot);
        $this->assertEquals(24, $version->canvas_snapshot['canvas_columns']);
    }

    // ═══════════════════════════════════════════════════════════
    //  Service — MarketplaceService
    // ═══════════════════════════════════════════════════════════

    public function test_service_approval_workflow(): void
    {
        $service = app(MarketplaceService::class);

        $listing = $service->createListing([
            'pos_layout_template_id' => $this->layout->id,
            'publisher_name' => 'Publisher',
            'title' => 'My Template', 'title_ar' => 'قالبي',
            'description' => 'A great template', 'description_ar' => 'قالب رائع',
            'pricing_type' => MarketplacePricingType::Free,
        ]);

        $this->assertEquals(MarketplaceListingStatus::Draft, $listing->status);

        // Submit for review
        $submitted = $service->submitForReview($listing->id);
        $this->assertEquals(MarketplaceListingStatus::PendingReview, $submitted->status);

        // Cannot submit again
        $resubmit = $service->submitForReview($listing->id);
        $this->assertNull($resubmit);

        // Approve
        $approved = $service->approveListing($listing->id, $this->user->id);
        $this->assertEquals(MarketplaceListingStatus::Approved, $approved->status);
        $this->assertNotNull($approved->approved_at);
        $this->assertNotNull($approved->published_at);

        // Suspend
        $suspended = $service->suspendListing($listing->id);
        $this->assertEquals(MarketplaceListingStatus::Suspended, $suspended->status);
    }

    public function test_service_rejection_workflow(): void
    {
        $service = app(MarketplaceService::class);

        $listing = $service->createListing([
            'pos_layout_template_id' => $this->layout->id,
            'publisher_name' => 'Publisher',
            'title' => 'Bad', 'title_ar' => 'سيء',
            'description' => 'Bad template', 'description_ar' => 'قالب سيء',
            'pricing_type' => MarketplacePricingType::Free,
        ]);

        $service->submitForReview($listing->id);
        $rejected = $service->rejectListing($listing->id, 'Low quality');
        $this->assertEquals(MarketplaceListingStatus::Rejected, $rejected->status);
        $this->assertEquals('Low quality', $rejected->rejection_reason);
    }

    public function test_service_rating_recalculation(): void
    {
        $service = app(MarketplaceService::class);
        $listing = $this->createApprovedListing();

        // Create a second user to review
        $user2 = User::create([
            'name' => 'User 2', 'email' => 'user2@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'cashier', 'is_active' => true,
        ]);

        $service->createReview($listing->id, $this->store->id, $this->user->id, ['rating' => 4]);
        $service->createReview($listing->id, $this->store->id, $user2->id, ['rating' => 2]);

        $listing->refresh();
        $this->assertEquals(3.0, (float) $listing->average_rating);
        $this->assertEquals(2, $listing->review_count);
    }

    public function test_service_respond_to_review(): void
    {
        $service = app(MarketplaceService::class);
        $listing = $this->createApprovedListing();

        $review = $service->createReview($listing->id, $this->store->id, $this->user->id, [
            'rating' => 5, 'title' => 'Great',
        ]);

        $responded = $service->respondToReview($review->id, 'Thank you!');
        $this->assertEquals('Thank you!', $responded->admin_response);
        $this->assertNotNull($responded->admin_responded_at);
    }

    public function test_service_has_active_purchase_respects_expiry(): void
    {
        $service = app(MarketplaceService::class);
        $listing = $this->createApprovedListing([
            'pricing_type' => MarketplacePricingType::Subscription,
            'price_amount' => 9.99,
            'subscription_interval' => SubscriptionInterval::Monthly,
        ]);

        $service->purchaseTemplate($this->store->id, $listing->id, ['payment_reference' => 'SUB-1']);

        $this->assertTrue($service->hasActivePurchase($this->store->id, $listing->id));

        // Expire the subscription
        TemplatePurchase::first()->update(['subscription_expires_at' => now()->subDay()]);
        $this->assertFalse($service->hasActivePurchase($this->store->id, $listing->id));
    }

    // ═══════════════════════════════════════════════════════════
    //  Model Relationships
    // ═══════════════════════════════════════════════════════════

    public function test_pos_layout_template_widget_placements_relation(): void
    {
        LayoutWidgetPlacement::create([
            'pos_layout_template_id' => $this->layout->id,
            'layout_widget_id' => $this->coreWidget->id,
            'instance_key' => 'pg_1',
            'grid_x' => 0, 'grid_y' => 0, 'grid_w' => 12, 'grid_h' => 10,
            'z_index' => 0, 'properties' => [],
        ]);

        $this->assertCount(1, $this->layout->widgetPlacements);
    }

    public function test_pos_layout_template_versions_relation(): void
    {
        TemplateVersion::create([
            'pos_layout_template_id' => $this->layout->id,
            'version_number' => '1.0.0',
            'canvas_snapshot' => [],
            'widget_placements_snapshot' => [],
            'published_at' => now(),
        ]);

        $this->assertCount(1, $this->layout->versions);
    }

    public function test_pos_layout_template_marketplace_listing_relation(): void
    {
        TemplateMarketplaceListing::create([
            'pos_layout_template_id' => $this->layout->id,
            'publisher_name' => 'Test',
            'title' => 'Test', 'title_ar' => 'اختبار',
            'description' => 'Test', 'description_ar' => 'اختبار',
            'pricing_type' => MarketplacePricingType::Free,
            'status' => MarketplaceListingStatus::Draft,
        ]);

        $this->assertNotNull($this->layout->marketplaceListing);
    }

    public function test_theme_variables_relation(): void
    {
        ThemeVariable::create([
            'theme_id' => $this->theme->id,
            'variable_key' => '--primary-color',
            'variable_value' => '#FD8209',
            'variable_type' => 'color',
            'category' => 'colors',
        ]);

        $this->assertCount(1, $this->theme->variables);
    }

    public function test_marketplace_category_children_relation(): void
    {
        $child = MarketplaceCategory::create([
            'name' => 'Sub-Retail', 'name_ar' => 'فرعي',
            'slug' => 'sub-retail', 'parent_id' => $this->category->id,
            'is_active' => true, 'sort_order' => 1,
        ]);

        $this->assertCount(1, $this->category->children);
        $this->assertEquals($this->category->id, $child->parent->id);
    }

    public function test_widget_placement_theme_overrides_relation(): void
    {
        $placement = LayoutWidgetPlacement::create([
            'pos_layout_template_id' => $this->layout->id,
            'layout_widget_id' => $this->coreWidget->id,
            'instance_key' => 'pg_1',
            'grid_x' => 0, 'grid_y' => 0, 'grid_w' => 12, 'grid_h' => 10,
            'z_index' => 0, 'properties' => [],
        ]);

        WidgetThemeOverride::create([
            'layout_widget_placement_id' => $placement->id,
            'variable_key' => '--bg', 'value' => '#000',
        ]);

        $this->assertCount(1, $placement->themeOverrides);
    }

    // ═══════════════════════════════════════════════════════════
    //  Enum Consistency
    // ═══════════════════════════════════════════════════════════

    public function test_widget_category_enum_values(): void
    {
        $values = array_column(WidgetCategory::cases(), 'value');
        $this->assertEquals(['core', 'commerce', 'display', 'utility', 'custom'], $values);
    }

    public function test_marketplace_pricing_type_enum_values(): void
    {
        $values = array_column(MarketplacePricingType::cases(), 'value');
        $this->assertEquals(['free', 'one_time', 'subscription'], $values);
    }

    public function test_marketplace_listing_status_enum_values(): void
    {
        $values = array_column(MarketplaceListingStatus::cases(), 'value');
        $this->assertEquals(['draft', 'pending_review', 'approved', 'rejected', 'suspended'], $values);
    }

    public function test_purchase_type_enum_values(): void
    {
        $values = array_column(PurchaseType::cases(), 'value');
        $this->assertEquals(['one_time', 'subscription'], $values);
    }

    public function test_subscription_interval_enum_values(): void
    {
        $values = array_column(SubscriptionInterval::cases(), 'value');
        $this->assertEquals(['monthly', 'yearly'], $values);
    }

    // ═══════════════════════════════════════════════════════════
    //  Helpers
    // ═══════════════════════════════════════════════════════════

    private function createApprovedListing(array $overrides = []): TemplateMarketplaceListing
    {
        return TemplateMarketplaceListing::create(array_merge([
            'pos_layout_template_id' => $this->layout->id,
            'publisher_name' => 'Test Publisher',
            'title' => 'Test Listing',
            'title_ar' => 'قائمة اختبار',
            'description' => 'A test marketplace listing',
            'description_ar' => 'قائمة متجر اختبار',
            'pricing_type' => MarketplacePricingType::Free,
            'status' => MarketplaceListingStatus::Approved,
            'published_at' => now(),
            'category_id' => $this->category->id,
        ], $overrides));
    }
}
