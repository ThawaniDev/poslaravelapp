<?php

namespace Tests\Feature\Domain\ContentOnboarding;

use App\Domain\ContentOnboarding\Models\BusinessType;
use App\Domain\ContentOnboarding\Models\BusinessTypeAppointmentConfig;
use App\Domain\ContentOnboarding\Models\BusinessTypeCategoryTemplate;
use App\Domain\ContentOnboarding\Models\BusinessTypeCustomerGroupTemplate;
use App\Domain\ContentOnboarding\Models\BusinessTypeGamificationBadge;
use App\Domain\ContentOnboarding\Models\BusinessTypeGamificationChallenge;
use App\Domain\ContentOnboarding\Models\BusinessTypeGamificationMilestone;
use App\Domain\ContentOnboarding\Models\BusinessTypeGiftRegistryType;
use App\Domain\ContentOnboarding\Models\BusinessTypeIndustryConfig;
use App\Domain\ContentOnboarding\Models\BusinessTypeLoyaltyConfig;
use App\Domain\ContentOnboarding\Models\BusinessTypePromotionTemplate;
use App\Domain\ContentOnboarding\Models\BusinessTypeReceiptTemplate;
use App\Domain\ContentOnboarding\Models\BusinessTypeReturnPolicy;
use App\Domain\ContentOnboarding\Models\BusinessTypeServiceCategoryTemplate;
use App\Domain\ContentOnboarding\Models\BusinessTypeShiftTemplate;
use App\Domain\ContentOnboarding\Models\BusinessTypeWasteReasonTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Comprehensive API tests for GET /api/v2/onboarding/business-types/* endpoints.
 *
 * All endpoints are public (no auth required).
 * Covers: listing, ordering, filtering, all sub-endpoints, error cases,
 * payload shape, bilingual fields, edge cases.
 */
class BusinessTypeDefaultsApiTest extends TestCase
{
    use RefreshDatabase;

    private BusinessType $retail;
    private BusinessType $restaurant;
    private BusinessType $inactive;

    protected function setUp(): void
    {
        parent::setUp();

        $this->retail = BusinessType::create([
            'name'       => 'Retail',
            'name_ar'    => 'البيع بالتجزئة',
            'slug'       => 'retail',
            'icon'       => '🛍️',
            'is_active'  => true,
            'sort_order' => 2,
        ]);

        $this->restaurant = BusinessType::create([
            'name'       => 'Restaurant',
            'name_ar'    => 'مطعم',
            'slug'       => 'restaurant',
            'icon'       => '🍔',
            'is_active'  => true,
            'sort_order' => 1,
        ]);

        $this->inactive = BusinessType::create([
            'name'       => 'Legacy',
            'name_ar'    => 'قديم',
            'slug'       => 'legacy',
            'icon'       => null,
            'is_active'  => false,
            'sort_order' => 99,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  GET /api/v2/onboarding/business-types — List
    // ═══════════════════════════════════════════════════════════════════════

    public function test_list_does_not_require_authentication(): void
    {
        $this->getJson('/api/v2/onboarding/business-types')
            ->assertOk();
    }

    public function test_list_returns_only_active_types(): void
    {
        $response = $this->getJson('/api/v2/onboarding/business-types');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->toArray();

        $this->assertContains((string) $this->retail->id, $ids);
        $this->assertContains((string) $this->restaurant->id, $ids);
        $this->assertNotContains((string) $this->inactive->id, $ids);
    }

    public function test_list_ordered_by_sort_order_ascending(): void
    {
        $response = $this->getJson('/api/v2/onboarding/business-types');
        $response->assertOk();

        $slugs = collect($response->json('data'))->pluck('slug')->toArray();
        $restaurantPos = array_search('restaurant', $slugs);
        $retailPos     = array_search('retail', $slugs);

        $this->assertLessThan($retailPos, $restaurantPos, 'Restaurant (sort_order=1) must appear before Retail (sort_order=2)');
    }

    public function test_list_response_shape(): void
    {
        $response = $this->getJson('/api/v2/onboarding/business-types');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'name_ar', 'slug', 'sort_order'],
                ],
            ]);
    }

    public function test_list_includes_bilingual_names(): void
    {
        $response = $this->getJson('/api/v2/onboarding/business-types');

        $retail = collect($response->json('data'))
            ->firstWhere('slug', 'retail');

        $this->assertEquals('Retail', $retail['name']);
        $this->assertEquals('البيع بالتجزئة', $retail['name_ar']);
    }

    public function test_list_empty_when_no_active_types(): void
    {
        BusinessType::query()->update(['is_active' => false]);

        $response = $this->getJson('/api/v2/onboarding/business-types');
        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  GET /api/v2/onboarding/business-types/{slug}/defaults
    // ═══════════════════════════════════════════════════════════════════════

    public function test_defaults_returns_full_bundle_without_auth(): void
    {
        $this->addAllTemplates($this->retail);

        $response = $this->getJson('/api/v2/onboarding/business-types/retail/defaults');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'name', 'name_ar', 'slug',
                    'category_templates',
                    'shift_templates',
                    'receipt_template',
                    'industry_config',
                    'loyalty_config',
                    'customer_group_templates',
                    'return_policy',
                    'waste_reason_templates',
                    'appointment_config',
                    'gift_registry_types',
                    'gamification_templates',
                    'promotion_templates',
                    'commission_templates',
                ],
            ]);
    }

    public function test_defaults_returns_404_for_inactive_type(): void
    {
        $this->getJson('/api/v2/onboarding/business-types/legacy/defaults')
            ->assertNotFound();
    }

    public function test_defaults_returns_404_for_unknown_slug(): void
    {
        $this->getJson('/api/v2/onboarding/business-types/does-not-exist/defaults')
            ->assertNotFound();
    }

    public function test_defaults_null_sub_resources_when_not_configured(): void
    {
        // No templates added for restaurant
        $response = $this->getJson('/api/v2/onboarding/business-types/restaurant/defaults');

        $response->assertOk();
        $this->assertNull($response->json('data.receipt_template'));
        $this->assertNull($response->json('data.industry_config'));
        $this->assertNull($response->json('data.loyalty_config'));
        $this->assertNull($response->json('data.return_policy'));
        $this->assertNull($response->json('data.appointment_config'));
    }

    public function test_defaults_empty_array_sub_resources_when_not_configured(): void
    {
        $response = $this->getJson('/api/v2/onboarding/business-types/restaurant/defaults');

        $response->assertOk();
        $this->assertEquals([], $response->json('data.category_templates'));
        $this->assertEquals([], $response->json('data.shift_templates'));
        $this->assertEquals([], $response->json('data.customer_group_templates'));
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  GET /{slug}/category-templates
    // ═══════════════════════════════════════════════════════════════════════

    public function test_category_templates_returns_ordered_list(): void
    {
        BusinessTypeCategoryTemplate::create([
            'business_type_id' => $this->retail->id,
            'category_name'    => 'B Category',
            'category_name_ar' => 'ب',
            'sort_order'       => 1,
        ]);
        BusinessTypeCategoryTemplate::create([
            'business_type_id' => $this->retail->id,
            'category_name'    => 'A Category',
            'category_name_ar' => 'أ',
            'sort_order'       => 0,
        ]);

        $response = $this->getJson('/api/v2/onboarding/business-types/retail/category-templates');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['*' => ['id', 'category_name', 'category_name_ar', 'sort_order']],
            ]);

        $names = collect($response->json('data'))->pluck('category_name')->toArray();
        $this->assertEquals(['A Category', 'B Category'], $names, 'Must be ordered by sort_order ASC');
    }

    public function test_category_templates_returns_empty_array_when_none(): void
    {
        $this->getJson('/api/v2/onboarding/business-types/retail/category-templates')
            ->assertOk()
            ->assertJson(['data' => []]);
    }

    public function test_category_templates_404_for_inactive_type(): void
    {
        $this->getJson('/api/v2/onboarding/business-types/legacy/category-templates')
            ->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  GET /{slug}/shift-templates
    // ═══════════════════════════════════════════════════════════════════════

    public function test_shift_templates_returns_correct_shape(): void
    {
        BusinessTypeShiftTemplate::create([
            'business_type_id'       => $this->retail->id,
            'name'                   => 'Morning',
            'name_ar'                => 'صباحية',
            'start_time'             => '08:00:00',
            'end_time'               => '16:00:00',
            'break_duration_minutes' => 30,
            'is_default'             => true,
            'sort_order'             => 0,
        ]);

        $response = $this->getJson('/api/v2/onboarding/business-types/retail/shift-templates');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'name', 'name_ar',
                        'start_time', 'end_time',
                        'days_of_week', 'break_duration_minutes',
                        'is_default', 'sort_order',
                    ],
                ],
            ]);

        $shift = $response->json('data.0');
        $this->assertEquals('08:00', $shift['start_time']);  // HH:MM format
        $this->assertEquals('16:00', $shift['end_time']);
        $this->assertTrue($shift['is_default']);
        $this->assertIsInt($shift['break_duration_minutes']);
    }

    public function test_shift_time_format_is_hhmm(): void
    {
        BusinessTypeShiftTemplate::create([
            'business_type_id' => $this->retail->id,
            'name'             => 'Night',
            'name_ar'          => 'ليلية',
            'start_time'       => '22:00:00',
            'end_time'         => '06:00:00',
            'sort_order'       => 0,
        ]);

        $response = $this->getJson('/api/v2/onboarding/business-types/retail/shift-templates');
        $shift = $response->json('data.0');

        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $shift['start_time'], 'Time must be HH:MM (5 chars)');
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $shift['end_time'], 'Time must be HH:MM (5 chars)');
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  GET /{slug}/receipt-template
    // ═══════════════════════════════════════════════════════════════════════

    public function test_receipt_template_returns_null_when_not_configured(): void
    {
        $this->getJson('/api/v2/onboarding/business-types/retail/receipt-template')
            ->assertOk()
            ->assertJson(['data' => null]);
    }

    public function test_receipt_template_returns_correct_shape(): void
    {
        BusinessTypeReceiptTemplate::create([
            'business_type_id'  => $this->retail->id,
            'paper_width'       => 80,
            'header_sections'   => ['store_logo', 'store_name'],
            'body_sections'     => ['items_table', 'total'],
            'footer_sections'   => ['zatca_qr'],
            'zatca_qr_position' => 'footer',
            'show_bilingual'    => true,
            'font_size'         => 'medium',
        ]);

        $response = $this->getJson('/api/v2/onboarding/business-types/retail/receipt-template');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'paper_width', 'header_sections', 'body_sections',
                    'footer_sections', 'zatca_qr_position', 'show_bilingual',
                    'font_size', 'custom_footer_text', 'custom_footer_text_ar',
                ],
            ]);

        $this->assertEquals(80, $response->json('data.paper_width'));
        $this->assertTrue($response->json('data.show_bilingual'));
        $this->assertIsArray($response->json('data.header_sections'));
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  GET /{slug}/industry-config
    // ═══════════════════════════════════════════════════════════════════════

    public function test_industry_config_returns_null_when_not_configured(): void
    {
        $this->getJson('/api/v2/onboarding/business-types/retail/industry-config')
            ->assertOk()
            ->assertJson(['data' => null]);
    }

    public function test_industry_config_returns_correct_shape(): void
    {
        BusinessTypeIndustryConfig::create([
            'business_type_id'        => $this->retail->id,
            'active_modules'          => ['loyalty', 'gift_cards', 'inventory'],
            'default_settings'        => ['track_inventory' => true],
            'required_product_fields' => ['barcode'],
        ]);

        $response = $this->getJson('/api/v2/onboarding/business-types/retail/industry-config');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['active_modules', 'default_settings', 'required_product_fields'],
            ]);

        $this->assertIsArray($response->json('data.active_modules'));
        $this->assertContains('loyalty', $response->json('data.active_modules'));
        $this->assertContains('inventory', $response->json('data.active_modules'));
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  GET /{slug}/loyalty-config
    // ═══════════════════════════════════════════════════════════════════════

    public function test_loyalty_config_returns_null_when_not_configured(): void
    {
        $this->getJson('/api/v2/onboarding/business-types/retail/loyalty-config')
            ->assertOk()
            ->assertJson(['data' => null]);
    }

    public function test_loyalty_config_returns_full_shape_when_configured(): void
    {
        BusinessTypeLoyaltyConfig::create([
            'business_type_id'      => $this->retail->id,
            'program_type'          => 'points',
            'earning_rate'          => 1.5,
            'redemption_value'      => 0.01,
            'min_redemption_points' => 100,
            'points_expiry_days'    => 365,
            'enable_tiers'          => true,
            'tier_definitions'      => [['name' => 'Silver', 'min_points' => 1000]],
            'is_active'             => false,
        ]);

        $response = $this->getJson('/api/v2/onboarding/business-types/retail/loyalty-config');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'program_type', 'earning_rate', 'redemption_value',
                    'min_redemption_points', 'stamps_card_size', 'cashback_percentage',
                    'points_expiry_days', 'enable_tiers', 'tier_definitions', 'is_active',
                ],
            ]);

        $this->assertEquals('points', $response->json('data.program_type'));
        $this->assertEquals(1.5, $response->json('data.earning_rate'));
        $this->assertTrue($response->json('data.enable_tiers'));
        $this->assertFalse($response->json('data.is_active'));
        $this->assertIsArray($response->json('data.tier_definitions'));
    }

    public function test_loyalty_config_numeric_types_are_correct(): void
    {
        BusinessTypeLoyaltyConfig::create([
            'business_type_id'      => $this->retail->id,
            'program_type'          => 'stamps',
            'earning_rate'          => 1.0,
            'redemption_value'      => 0.0,
            'min_redemption_points' => 0,
            'stamps_card_size'      => 10,
            'is_active'             => false,
        ]);

        $response = $this->getJson('/api/v2/onboarding/business-types/retail/loyalty-config');

        $this->assertEquals(1.0, $response->json('data.earning_rate'));
        $this->assertIsInt($response->json('data.min_redemption_points'));
        $this->assertIsInt($response->json('data.stamps_card_size'));
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  GET /{slug}/customer-groups
    // ═══════════════════════════════════════════════════════════════════════

    public function test_customer_groups_returns_ordered_list(): void
    {
        BusinessTypeCustomerGroupTemplate::create([
            'business_type_id'    => $this->retail->id,
            'name'                => 'Walk-in',
            'name_ar'             => 'عميل عادي',
            'discount_percentage' => 0,
            'credit_limit'        => 0,
            'is_default_group'    => true,
            'sort_order'          => 0,
        ]);
        BusinessTypeCustomerGroupTemplate::create([
            'business_type_id'    => $this->retail->id,
            'name'                => 'VIP',
            'name_ar'             => 'مميز',
            'discount_percentage' => 15,
            'credit_limit'        => 5000,
            'is_default_group'    => false,
            'sort_order'          => 1,
        ]);

        $response = $this->getJson('/api/v2/onboarding/business-types/retail/customer-groups');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'name', 'name_ar', 'description',
                        'discount_percentage', 'credit_limit',
                        'payment_terms_days', 'is_default_group', 'sort_order',
                    ],
                ],
            ]);

        $this->assertCount(2, $response->json('data'));
        $first = $response->json('data.0');
        $this->assertTrue($first['is_default_group'], 'Default group should appear first (sort_order=0)');
        $this->assertEquals(0.0, $first['discount_percentage']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  GET /{slug}/return-policy
    // ═══════════════════════════════════════════════════════════════════════

    public function test_return_policy_returns_null_when_not_configured(): void
    {
        $this->getJson('/api/v2/onboarding/business-types/retail/return-policy')
            ->assertOk()
            ->assertJson(['data' => null]);
    }

    public function test_return_policy_returns_full_shape(): void
    {
        BusinessTypeReturnPolicy::create([
            'business_type_id'            => $this->retail->id,
            'return_window_days'          => 14,
            'refund_methods'              => ['original_payment', 'store_credit'],
            'require_receipt'             => true,
            'restocking_fee_percentage'   => 0,
            'void_grace_period_minutes'   => 5,
            'require_manager_approval'    => false,
            'max_return_without_approval' => 500.00,
            'return_reason_required'      => true,
            'partial_return_allowed'      => true,
        ]);

        $response = $this->getJson('/api/v2/onboarding/business-types/retail/return-policy');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'return_window_days', 'refund_methods', 'require_receipt',
                    'restocking_fee_percentage', 'void_grace_period_minutes',
                    'require_manager_approval', 'max_return_without_approval',
                    'return_reason_required', 'partial_return_allowed',
                ],
            ]);

        $this->assertEquals(14, $response->json('data.return_window_days'));
        $this->assertIsArray($response->json('data.refund_methods'));
        $this->assertContains('store_credit', $response->json('data.refund_methods'));
    }

    public function test_return_policy_zero_window_days_means_no_returns(): void
    {
        BusinessTypeReturnPolicy::create([
            'business_type_id'         => $this->restaurant->id,
            'return_window_days'       => 0,
            'refund_methods'           => [],
            'require_receipt'          => false,
            'void_grace_period_minutes' => 3,
        ]);

        $response = $this->getJson('/api/v2/onboarding/business-types/restaurant/return-policy');

        $this->assertEquals(0, $response->json('data.return_window_days'));
        $this->assertEquals([], $response->json('data.refund_methods'));
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  GET /{slug}/waste-reasons
    // ═══════════════════════════════════════════════════════════════════════

    public function test_waste_reasons_returns_ordered_list(): void
    {
        BusinessTypeWasteReasonTemplate::create([
            'business_type_id'       => $this->retail->id,
            'reason_code'            => 'EXPIRED',
            'name'                   => 'Expired Product',
            'name_ar'                => 'منتج منتهي الصلاحية',
            'category'               => 'spoilage',
            'requires_approval'      => false,
            'affects_cost_reporting' => true,
            'sort_order'             => 0,
        ]);

        $response = $this->getJson('/api/v2/onboarding/business-types/retail/waste-reasons');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'reason_code', 'name', 'name_ar',
                        'category', 'description', 'requires_approval',
                        'affects_cost_reporting', 'sort_order',
                    ],
                ],
            ]);

        $this->assertEquals('EXPIRED', $response->json('data.0.reason_code'));
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  GET /{slug}/appointment-config
    // ═══════════════════════════════════════════════════════════════════════

    public function test_appointment_config_returns_null_when_not_configured(): void
    {
        $this->getJson('/api/v2/onboarding/business-types/retail/appointment-config')
            ->assertOk()
            ->assertJson(['data' => null]);
    }

    public function test_appointment_config_includes_service_categories(): void
    {
        BusinessTypeAppointmentConfig::create([
            'business_type_id'              => $this->retail->id,
            'default_slot_duration_minutes' => 30,
            'min_advance_booking_hours'     => 2,
            'max_advance_booking_days'      => 30,
            'cancellation_window_hours'     => 24,
            'cancellation_fee_type'         => 'none',
            'cancellation_fee_value'        => 0,
            'allow_walkins'                 => true,
        ]);
        BusinessTypeServiceCategoryTemplate::create([
            'business_type_id'         => $this->retail->id,
            'name'                     => 'Tailoring',
            'name_ar'                  => 'خياطة',
            'default_duration_minutes' => 45,
            'default_price'            => 50.000,
            'sort_order'               => 0,
        ]);

        $response = $this->getJson('/api/v2/onboarding/business-types/retail/appointment-config');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'default_slot_duration_minutes',
                    'min_advance_booking_hours',
                    'max_advance_booking_days',
                    'cancellation_window_hours',
                    'cancellation_fee_type',
                    'cancellation_fee_value',
                    'allow_walkins',
                    'service_category_templates' => [
                        '*' => ['id', 'name', 'name_ar', 'default_duration_minutes', 'default_price', 'sort_order'],
                    ],
                ],
            ]);

        $this->assertCount(1, $response->json('data.service_category_templates'));
        $this->assertEquals('Tailoring', $response->json('data.service_category_templates.0.name'));
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  GET /{slug}/gift-registry-types
    // ═══════════════════════════════════════════════════════════════════════

    public function test_gift_registry_types_returns_correct_shape(): void
    {
        BusinessTypeGiftRegistryType::create([
            'business_type_id'         => $this->retail->id,
            'name'                     => 'Wedding',
            'name_ar'                  => 'زفاف',
            'description'              => 'Wedding gift list',
            'icon'                     => '💍',
            'default_expiry_days'      => 90,
            'allow_public_sharing'     => true,
            'allow_partial_fulfilment' => true,
            'sort_order'               => 0,
        ]);

        $response = $this->getJson('/api/v2/onboarding/business-types/retail/gift-registry-types');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'name', 'name_ar', 'description', 'icon',
                        'default_expiry_days', 'allow_public_sharing',
                        'allow_partial_fulfilment', 'require_minimum_items',
                        'minimum_items_count', 'sort_order',
                    ],
                ],
            ]);

        $registry = $response->json('data.0');
        $this->assertEquals(90, $registry['default_expiry_days']);
        $this->assertTrue($registry['allow_public_sharing']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  GET /{slug}/gamification-templates
    // ═══════════════════════════════════════════════════════════════════════

    public function test_gamification_templates_returns_badges_challenges_milestones(): void
    {
        BusinessTypeGamificationBadge::create([
            'business_type_id'  => $this->retail->id,
            'name'              => 'First Purchase',
            'name_ar'           => 'أول شراء',
            'trigger_type'      => 'purchase_count',
            'trigger_threshold' => 1,
            'points_reward'     => 50,
            'sort_order'        => 0,
        ]);
        BusinessTypeGamificationChallenge::create([
            'business_type_id' => $this->retail->id,
            'name'             => 'Spend 500 SAR',
            'name_ar'          => 'أنفق 500 ريال',
            'challenge_type'   => 'spend_target',
            'target_value'     => 500,
            'reward_type'      => 'points',
            'reward_value'     => '200',
            'duration_days'    => 30,
            'is_recurring'     => false,
            'sort_order'       => 0,
        ]);
        BusinessTypeGamificationMilestone::create([
            'business_type_id' => $this->retail->id,
            'name'             => 'Gold Member',
            'name_ar'          => 'عضو ذهبي',
            'milestone_type'   => 'total_spend',
            'threshold_value'  => 1000.00,
            'reward_type'      => 'tier_upgrade',
            'reward_value'     => 'gold',
            'sort_order'       => 0,
        ]);

        $response = $this->getJson('/api/v2/onboarding/business-types/retail/gamification-templates');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'badges' => [
                        '*' => [
                            'id', 'name', 'name_ar', 'icon_url',
                            'trigger_type', 'trigger_threshold', 'points_reward',
                            'description', 'description_ar',
                        ],
                    ],
                    'challenges' => [
                        '*' => [
                            'id', 'name', 'name_ar', 'challenge_type',
                            'target_value', 'reward_type', 'reward_value',
                            'duration_days', 'is_recurring',
                            'description', 'description_ar',
                        ],
                    ],
                    'milestones' => [
                        '*' => [
                            'id', 'name', 'name_ar', 'milestone_type',
                            'threshold_value', 'reward_type', 'reward_value',
                        ],
                    ],
                ],
            ]);

        $this->assertCount(1, $response->json('data.badges'));
        $this->assertCount(1, $response->json('data.challenges'));
        $this->assertCount(1, $response->json('data.milestones'));
    }

    public function test_gamification_templates_empty_arrays_when_no_templates(): void
    {
        $response = $this->getJson('/api/v2/onboarding/business-types/retail/gamification-templates');

        $response->assertOk();
        $this->assertEquals([], $response->json('data.badges'));
        $this->assertEquals([], $response->json('data.challenges'));
        $this->assertEquals([], $response->json('data.milestones'));
    }

    public function test_gamification_templates_badge_integer_types(): void
    {
        BusinessTypeGamificationBadge::create([
            'business_type_id'  => $this->retail->id,
            'name'              => 'Loyal',
            'name_ar'           => 'مخلص',
            'trigger_type'      => 'spend_total',
            'trigger_threshold' => 5,
            'points_reward'     => 100,
        ]);

        $response = $this->getJson('/api/v2/onboarding/business-types/retail/gamification-templates');

        $badge = $response->json('data.badges.0');
        $this->assertIsInt($badge['trigger_threshold']);
        $this->assertIsInt($badge['points_reward']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  Slug routing constraints
    // ═══════════════════════════════════════════════════════════════════════

    public function test_slug_with_uppercase_not_matched_by_route(): void
    {
        // Route constraint is [a-z0-9_-]+ so uppercase is not matched
        $this->getJson('/api/v2/onboarding/business-types/RETAIL/defaults')
            ->assertNotFound();
    }

    public function test_slug_with_special_chars_not_matched_by_route(): void
    {
        $this->getJson('/api/v2/onboarding/business-types/retail%20store/defaults')
            ->assertNotFound();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    /**
     * Add all template types to a business type for full-bundle tests.
     */
    private function addAllTemplates(BusinessType $bt): void
    {
        BusinessTypeCategoryTemplate::create([
            'business_type_id' => $bt->id,
            'category_name'    => 'Electronics',
            'category_name_ar' => 'إلكترونيات',
        ]);

        BusinessTypeShiftTemplate::create([
            'business_type_id' => $bt->id,
            'name'             => 'Morning',
            'name_ar'          => 'صباحية',
            'start_time'       => '08:00:00',
            'end_time'         => '16:00:00',
        ]);

        BusinessTypeReceiptTemplate::create([
            'business_type_id' => $bt->id,
            'paper_width'      => 80,
            'header_sections'  => ['store_name'],
            'body_sections'    => ['items_table'],
            'footer_sections'  => ['zatca_qr'],
            'show_bilingual'   => true,
            'font_size'        => 'medium',
        ]);

        BusinessTypeIndustryConfig::create([
            'business_type_id' => $bt->id,
            'active_modules'   => ['loyalty'],
        ]);

        BusinessTypeLoyaltyConfig::create([
            'business_type_id'      => $bt->id,
            'program_type'          => 'points',
            'earning_rate'          => 1.0,
            'redemption_value'      => 0.01,
            'min_redemption_points' => 100,
        ]);

        BusinessTypeCustomerGroupTemplate::create([
            'business_type_id' => $bt->id,
            'name'             => 'Walk-in',
            'name_ar'          => 'عادي',
            'is_default_group' => true,
        ]);

        BusinessTypeReturnPolicy::create([
            'business_type_id'  => $bt->id,
            'return_window_days' => 14,
            'require_receipt'   => true,
        ]);
    }
}
