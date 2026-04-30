<?php

namespace Tests\Feature;

use App\Domain\ContentOnboarding\Models\BusinessType;
use App\Domain\ContentOnboarding\Models\BusinessTypeCategoryTemplate;
use App\Domain\ContentOnboarding\Models\BusinessTypeShiftTemplate;
use App\Domain\ContentOnboarding\Models\BusinessTypeLoyaltyConfig;
use App\Domain\ContentOnboarding\Models\BusinessTypeReturnPolicy;
use App\Domain\ContentOnboarding\Models\BusinessTypeGamificationBadge;
use App\Domain\ContentOnboarding\Models\BusinessTypeGamificationChallenge;
use App\Domain\ContentOnboarding\Models\BusinessTypeGamificationMilestone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for GET /api/v2/onboarding/business-types and sub-endpoints.
 * These are public (no auth required).
 */
class BusinessTypeDefaultsApiTest extends TestCase
{
    use RefreshDatabase;

    private BusinessType $retail;
    private BusinessType $inactive;

    protected function setUp(): void
    {
        parent::setUp();

        $this->retail = BusinessType::create([
            'name'      => 'Retail',
            'name_ar'   => 'البيع بالتجزئة',
            'slug'      => 'retail',
            'icon'      => 'storefront',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->inactive = BusinessType::create([
            'name'      => 'Legacy',
            'name_ar'   => 'قديم',
            'slug'      => 'legacy',
            'is_active' => false,
            'sort_order' => 99,
        ]);
    }

    // ─── Index ────────────────────────────────────────────────────

    public function test_can_list_active_business_types_without_auth(): void
    {
        $response = $this->getJson('/api/v2/onboarding/business-types');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'name_ar', 'slug', 'sort_order'],
                ],
            ]);

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains((string) $this->retail->id, $ids);
        $this->assertNotContains((string) $this->inactive->id, $ids);
    }

    public function test_business_types_list_ordered_by_sort_order(): void
    {
        BusinessType::create([
            'name'       => 'Restaurant',
            'name_ar'    => 'مطعم',
            'slug'       => 'restaurant',
            'is_active'  => true,
            'sort_order' => 0,
        ]);

        $response = $this->getJson('/api/v2/onboarding/business-types');
        $response->assertOk();

        $slugs = collect($response->json('data'))->pluck('slug')->toArray();
        $this->assertEquals('restaurant', $slugs[0]);
        $this->assertEquals('retail', $slugs[1]);
    }

    // ─── Defaults ────────────────────────────────────────────────

    public function test_can_get_full_defaults_bundle_for_business_type(): void
    {
        // Seed some templates
        BusinessTypeCategoryTemplate::create([
            'business_type_id'  => $this->retail->id,
            'category_name'     => 'Electronics',
            'category_name_ar'  => 'إلكترونيات',
            'sort_order'        => 1,
        ]);

        BusinessTypeShiftTemplate::create([
            'business_type_id'        => $this->retail->id,
            'name'                    => 'Morning',
            'name_ar'                 => 'صباحي',
            'start_time'              => '08:00',
            'end_time'                => '16:00',
            'days_of_week'            => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'break_duration_minutes'  => 30,
            'is_default'              => true,
            'sort_order'              => 1,
        ]);

        $response = $this->getJson('/api/v2/onboarding/business-types/retail/defaults');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'name', 'name_ar', 'slug',
                    'category_templates',
                    'shift_templates',
                    'receipt_template',
                    'industry_config',
                    'promotion_templates',
                    'commission_templates',
                    'loyalty_config',
                    'customer_group_templates',
                    'return_policy',
                    'waste_reason_templates',
                    'appointment_config',
                    'gift_registry_types',
                    'gamification_templates' => ['badges', 'challenges', 'milestones'],
                ],
            ]);

        $this->assertCount(1, $response->json('data.category_templates'));
        $this->assertEquals('Electronics', $response->json('data.category_templates.0.category_name'));
        $this->assertCount(1, $response->json('data.shift_templates'));
        $this->assertEquals('Morning', $response->json('data.shift_templates.0.name'));
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

    // ─── Category Templates sub-endpoint ─────────────────────────

    public function test_can_get_category_templates(): void
    {
        BusinessTypeCategoryTemplate::create([
            'business_type_id' => $this->retail->id,
            'category_name'    => 'Clothing',
            'category_name_ar' => 'ملابس',
            'sort_order'       => 1,
        ]);

        $response = $this->getJson('/api/v2/onboarding/business-types/retail/category-templates');
        $response->assertOk()->assertJsonStructure(['data' => [['id', 'category_name', 'category_name_ar', 'sort_order']]]);
    }

    // ─── Shift Templates sub-endpoint ────────────────────────────

    public function test_can_get_shift_templates(): void
    {
        BusinessTypeShiftTemplate::create([
            'business_type_id'        => $this->retail->id,
            'name'                    => 'Evening',
            'name_ar'                 => 'مسائي',
            'start_time'              => '16:00',
            'end_time'                => '00:00',
            'days_of_week'            => ['mon', 'tue'],
            'break_duration_minutes'  => 15,
            'is_default'              => false,
            'sort_order'              => 2,
        ]);

        $response = $this->getJson('/api/v2/onboarding/business-types/retail/shift-templates');
        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'name_ar', 'start_time', 'end_time', 'days_of_week']]]);
    }

    // ─── Loyalty Config sub-endpoint ─────────────────────────────

    public function test_loyalty_config_returns_null_when_not_configured(): void
    {
        $response = $this->getJson('/api/v2/onboarding/business-types/retail/loyalty-config');
        $response->assertOk()->assertJson(['data' => null]);
    }

    public function test_can_get_loyalty_config(): void
    {
        BusinessTypeLoyaltyConfig::create([
            'business_type_id'      => $this->retail->id,
            'program_type'          => 'points',
            'earning_rate'          => 1.0,
            'redemption_value'      => 0.01,
            'min_redemption_points' => 100,
            'points_expiry_days'    => 365,
            'enable_tiers'          => false,
            'is_active'             => true,
        ]);

        $response = $this->getJson('/api/v2/onboarding/business-types/retail/loyalty-config');
        $response->assertOk()
            ->assertJsonStructure(['data' => ['program_type', 'earning_rate', 'redemption_value', 'is_active']]);
        $this->assertEquals('points', $response->json('data.program_type'));
    }

    // ─── Return Policy sub-endpoint ──────────────────────────────

    public function test_return_policy_returns_null_when_not_configured(): void
    {
        $this->getJson('/api/v2/onboarding/business-types/retail/return-policy')
            ->assertOk()
            ->assertJson(['data' => null]);
    }

    public function test_can_get_return_policy(): void
    {
        BusinessTypeReturnPolicy::create([
            'business_type_id'            => $this->retail->id,
            'return_window_days'          => 14,
            'refund_methods'              => ['cash', 'card'],
            'require_receipt'             => true,
            'restocking_fee_percentage'   => 0.0,
            'void_grace_period_minutes'   => 30,
            'require_manager_approval'    => false,
            'max_return_without_approval' => 500.0,
            'return_reason_required'      => true,
            'partial_return_allowed'      => true,
        ]);

        $response = $this->getJson('/api/v2/onboarding/business-types/retail/return-policy');
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'return_window_days', 'refund_methods', 'require_receipt',
                    'void_grace_period_minutes', 'require_manager_approval',
                ],
            ]);
        $this->assertEquals(14, $response->json('data.return_window_days'));
    }

    // ─── Gamification Templates sub-endpoint ─────────────────────

    public function test_can_get_gamification_templates(): void
    {
        BusinessTypeGamificationBadge::create([
            'business_type_id'  => $this->retail->id,
            'name'              => 'First Purchase',
            'name_ar'           => 'أول عملية شراء',
            'trigger_type'      => 'purchase_count',
            'trigger_threshold' => 1,
            'points_reward'     => 100,
        ]);
        BusinessTypeGamificationChallenge::create([
            'business_type_id' => $this->retail->id,
            'name'             => 'Weekend Warrior',
            'name_ar'          => 'محارب نهاية الأسبوع',
            'challenge_type'   => 'visit_streak',
            'target_value'     => 4,
            'reward_type'      => 'points',
            'reward_value'     => '200',
            'duration_days'    => 30,
            'is_recurring'     => true,
        ]);
        BusinessTypeGamificationMilestone::create([
            'business_type_id' => $this->retail->id,
            'name'             => '1K Club',
            'name_ar'          => 'نادي الألف',
            'milestone_type'   => 'total_spend',
            'threshold_value'  => 1000,
            'reward_type'      => 'points',
            'reward_value'     => '500',
        ]);

        $response = $this->getJson('/api/v2/onboarding/business-types/retail/gamification-templates');
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'badges'     => [['id', 'name', 'trigger_type', 'points_reward']],
                    'challenges' => [['id', 'name', 'challenge_type', 'reward_type']],
                    'milestones' => [['id', 'name', 'milestone_type', 'threshold_value']],
                ],
            ]);
        $this->assertCount(1, $response->json('data.badges'));
        $this->assertCount(1, $response->json('data.challenges'));
        $this->assertCount(1, $response->json('data.milestones'));
    }
}
