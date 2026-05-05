<?php

namespace Tests\Unit\Domain\ContentOnboarding;

use App\Domain\ContentOnboarding\Models\BusinessType;
use App\Domain\ContentOnboarding\Models\BusinessTypeCategoryTemplate;
use App\Domain\ContentOnboarding\Models\BusinessTypeCommissionTemplate;
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
use App\Domain\ContentOnboarding\Services\BusinessTypeSeederService;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit / integration tests for BusinessTypeSeederService.
 *
 * Verifies that calling seed() correctly copies templates into the store's
 * related tables, and that the operation is idempotent.
 *
 * Test DB: PostgreSQL (thawani_pos_test). FK enforcement disabled via
 * session_replication_role = replica (set in TestCase::setUp).
 */
class BusinessTypeSeederServiceTest extends TestCase
{
    use RefreshDatabase;

    private BusinessTypeSeederService $service;
    private BusinessType $businessType;
    private Organization $org;
    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(BusinessTypeSeederService::class);

        // ── Create an active business type with all template types ──────────
        $this->businessType = BusinessType::create([
            'name'       => 'Retail',
            'name_ar'    => 'تجزئة',
            'slug'       => 'retail-seeder-test',
            'is_active'  => true,
            'sort_order' => 1,
        ]);

        // ── Add category templates ─────────────────────────────────────────
        BusinessTypeCategoryTemplate::create([
            'business_type_id' => $this->businessType->id,
            'category_name'    => 'Electronics',
            'category_name_ar' => 'إلكترونيات',
            'sort_order'       => 0,
        ]);
        BusinessTypeCategoryTemplate::create([
            'business_type_id' => $this->businessType->id,
            'category_name'    => 'Accessories',
            'category_name_ar' => 'إكسسوارات',
            'sort_order'       => 1,
        ]);

        // ── Add shift templates ────────────────────────────────────────────
        BusinessTypeShiftTemplate::create([
            'business_type_id'       => $this->businessType->id,
            'name'                   => 'Morning Shift',
            'name_ar'                => 'الوردية الصباحية',
            'start_time'             => '08:00:00',
            'end_time'               => '16:00:00',
            'break_duration_minutes' => 30,
            'is_default'             => true,
            'sort_order'             => 0,
        ]);

        // ── Add customer group templates ───────────────────────────────────
        BusinessTypeCustomerGroupTemplate::create([
            'business_type_id'    => $this->businessType->id,
            'name'                => 'Walk-in',
            'name_ar'             => 'عميل عادي',
            'discount_percentage' => 0,
            'is_default_group'    => true,
            'sort_order'          => 0,
        ]);
        BusinessTypeCustomerGroupTemplate::create([
            'business_type_id'    => $this->businessType->id,
            'name'                => 'VIP',
            'name_ar'             => 'مميز',
            'discount_percentage' => 10,
            'is_default_group'    => false,
            'sort_order'          => 1,
        ]);

        // ── Add loyalty config ─────────────────────────────────────────────
        BusinessTypeLoyaltyConfig::create([
            'business_type_id'      => $this->businessType->id,
            'program_type'          => 'points',
            'earning_rate'          => 1.0,
            'redemption_value'      => 0.01,
            'min_redemption_points' => 100,
            'is_active'             => false,
        ]);

        // ── Add promotion template ─────────────────────────────────────────
        BusinessTypePromotionTemplate::create([
            'business_type_id' => $this->businessType->id,
            'name'             => 'Weekend Sale',
            'name_ar'          => 'تخفيضات نهاية الأسبوع',
            'promotion_type'   => 'percentage',
            'discount_value'   => 10.00,
            'minimum_order'    => 50.00,
            'sort_order'       => 0,
        ]);

        // ── Org + Store ────────────────────────────────────────────────────
        $this->org = Organization::create([
            'name'    => 'Test Org',
            'name_ar' => 'مؤسسة اختبار',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id'  => $this->org->id,
            'name'             => 'Test Store',
            'name_ar'          => 'متجر اختبار',
            'business_type_id' => $this->businessType->id,
        ]);

        // Manually create store_settings (usually created by initDefaultSettings)
        DB::table('store_settings')->insert([
            'id'         => \Illuminate\Support\Str::uuid()->toString(),
            'store_id'   => $this->store->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  Basic Seeding
    // ═══════════════════════════════════════════════════════════════════════

    public function test_seed_returns_structured_result(): void
    {
        $result = $this->service->seed($this->store, $this->businessType);

        $this->assertArrayHasKey('seeded', $result);
        $this->assertArrayHasKey('skipped', $result);
        $this->assertIsArray($result['seeded']);
        $this->assertIsArray($result['skipped']);
    }

    public function test_seed_copies_category_templates_to_org_categories(): void
    {
        $this->service->seed($this->store, $this->businessType);

        $categories = DB::table('categories')
            ->where('organization_id', $this->org->id)
            ->get();

        $this->assertCount(2, $categories);

        $names = $categories->pluck('name')->sort()->values()->toArray();
        $this->assertContains('Electronics', $names);
        $this->assertContains('Accessories', $names);
    }

    public function test_seeded_categories_have_bilingual_names(): void
    {
        $this->service->seed($this->store, $this->businessType);

        $electronics = DB::table('categories')
            ->where('organization_id', $this->org->id)
            ->where('name', 'Electronics')
            ->first();

        $this->assertNotNull($electronics);
        $this->assertEquals('إلكترونيات', $electronics->name_ar);
    }

    public function test_seed_copies_shift_templates_to_store(): void
    {
        $this->service->seed($this->store, $this->businessType);

        $shifts = DB::table('shift_templates')
            ->where('store_id', $this->store->id)
            ->get();

        $this->assertCount(1, $shifts);
        $this->assertEquals('Morning Shift', $shifts->first()->name);
        $this->assertEquals('08:00:00', $shifts->first()->start_time);
    }

    public function test_seed_copies_customer_groups_to_org(): void
    {
        $this->service->seed($this->store, $this->businessType);

        $groups = DB::table('customer_groups')
            ->where('organization_id', $this->org->id)
            ->get();

        $this->assertCount(2, $groups);
        $names = $groups->pluck('name')->toArray();
        $this->assertContains('Walk-in', $names);
        $this->assertContains('VIP', $names);
    }

    public function test_seed_copies_loyalty_config_as_inactive(): void
    {
        $this->service->seed($this->store, $this->businessType);

        $loyalty = DB::table('loyalty_config')
            ->where('organization_id', $this->org->id)
            ->first();

        $this->assertNotNull($loyalty);
        $this->assertFalse((bool) $loyalty->is_active, 'Loyalty config must be seeded as inactive (Business Rule #9)');
    }

    public function test_seed_copies_promotion_templates_as_inactive(): void
    {
        $this->service->seed($this->store, $this->businessType);

        $promos = DB::table('promotions')
            ->where('organization_id', $this->org->id)
            ->get();

        $this->assertCount(1, $promos);
        $this->assertEquals('Weekend Sale', $promos->first()->name);
        $this->assertFalse((bool) $promos->first()->is_active, 'Promotions must be seeded as inactive (Business Rule #6)');
    }

    public function test_seed_result_reports_seeded_counts(): void
    {
        $result = $this->service->seed($this->store, $this->businessType);

        $this->assertArrayHasKey('categories', $result['seeded']);
        $this->assertEquals(2, $result['seeded']['categories']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  Idempotency
    // ═══════════════════════════════════════════════════════════════════════

    public function test_seed_is_idempotent_does_not_duplicate_categories(): void
    {
        // Seed twice
        $this->service->seed($this->store, $this->businessType);
        $this->service->seed($this->store, $this->businessType);

        $count = DB::table('categories')
            ->where('organization_id', $this->org->id)
            ->count();

        $this->assertEquals(2, $count, 'Categories must not be duplicated on second seed call');
    }

    public function test_seed_is_idempotent_does_not_duplicate_shift_templates(): void
    {
        $this->service->seed($this->store, $this->businessType);
        $this->service->seed($this->store, $this->businessType);

        $count = DB::table('shift_templates')
            ->where('store_id', $this->store->id)
            ->count();

        $this->assertEquals(1, $count, 'Shift templates must not be duplicated on second seed call');
    }

    public function test_seed_is_idempotent_does_not_duplicate_loyalty_config(): void
    {
        $this->service->seed($this->store, $this->businessType);
        $this->service->seed($this->store, $this->businessType);

        $count = DB::table('loyalty_config')
            ->where('organization_id', $this->org->id)
            ->count();

        $this->assertEquals(1, $count, 'Loyalty config must not be duplicated on second seed call');
    }

    public function test_seed_is_idempotent_does_not_duplicate_customer_groups(): void
    {
        $this->service->seed($this->store, $this->businessType);
        $this->service->seed($this->store, $this->businessType);

        $count = DB::table('customer_groups')
            ->where('organization_id', $this->org->id)
            ->count();

        $this->assertEquals(2, $count, 'Customer groups must not be duplicated on second seed call');
    }

    public function test_seed_skips_categories_when_org_already_has_some(): void
    {
        // Pre-create a category for the org
        DB::table('categories')->insert([
            'id'              => \Illuminate\Support\Str::uuid()->toString(),
            'organization_id' => $this->org->id,
            'name'            => 'Pre-existing Category',
            'name_ar'         => 'فئة موجودة مسبقاً',
            'is_active'       => true,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $result = $this->service->seed($this->store, $this->businessType);

        // Should skip categories since org already has them
        $this->assertContains('categories', $result['skipped']);

        // Count should remain 1 (only the pre-existing one)
        $count = DB::table('categories')->where('organization_id', $this->org->id)->count();
        $this->assertEquals(1, $count);
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  Edge Cases
    // ═══════════════════════════════════════════════════════════════════════

    public function test_seed_with_no_category_templates_skips_categories(): void
    {
        $emptyType = BusinessType::create([
            'name'      => 'Empty Type',
            'name_ar'   => 'نوع فارغ',
            'slug'      => 'empty-type',
            'is_active' => true,
        ]);

        $result = $this->service->seed($this->store, $emptyType);

        $this->assertContains('categories', $result['skipped']);
    }

    public function test_seed_with_loyalty_program_type_none_skips_loyalty_config(): void
    {
        // Create business type with loyalty.program_type = 'none'
        $noloyaltyType = BusinessType::create([
            'name'      => 'No Loyalty Type',
            'name_ar'   => 'بدون ولاء',
            'slug'      => 'no-loyalty',
            'is_active' => true,
        ]);

        BusinessTypeLoyaltyConfig::create([
            'business_type_id' => $noloyaltyType->id,
            'program_type'     => 'none',
            'earning_rate'     => 0,
            'redemption_value' => 0,
            'is_active'        => false,
        ]);

        $result = $this->service->seed($this->store, $noloyaltyType);

        // Should skip loyalty since program_type = 'none' (Business Rule #9)
        $this->assertContains('loyalty_config', $result['skipped']);

        $count = DB::table('loyalty_config')->where('organization_id', $this->org->id)->count();
        $this->assertEquals(0, $count);
    }

    public function test_seed_unknown_promotion_type_skips_that_row(): void
    {
        // Add promotion with an unknown type that can't be mapped.
        // Use DB::table to bypass Eloquent's enum cast (testing invalid data handling).
        DB::table('business_type_promotion_templates')->insert([
            'id'               => \Illuminate\Support\Str::uuid()->toString(),
            'business_type_id' => $this->businessType->id,
            'name'             => 'Unknown Promo',
            'name_ar'          => 'عرض غير معروف',
            'promotion_type'   => 'mystery_discount_xyz',
            'discount_value'   => 5.00,
            'sort_order'       => 99,
        ]);

        // Should not throw; unknown row silently skipped
        $this->service->seed($this->store, $this->businessType);

        $promos = DB::table('promotions')
            ->where('organization_id', $this->org->id)
            ->pluck('name')
            ->toArray();

        $this->assertNotContains('Unknown Promo', $promos);
    }

    public function test_seed_all_category_templates_preserve_sort_order(): void
    {
        $this->service->seed($this->store, $this->businessType);

        $categories = DB::table('categories')
            ->where('organization_id', $this->org->id)
            ->orderBy('sort_order')
            ->get();

        $this->assertEquals(0, $categories->first()->sort_order);
        $this->assertEquals(1, $categories->last()->sort_order);
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  Gamification template validation (no seeding yet but no crash)
    // ═══════════════════════════════════════════════════════════════════════

    public function test_seed_with_gamification_templates_does_not_throw(): void
    {
        BusinessTypeGamificationBadge::create([
            'business_type_id'  => $this->businessType->id,
            'name'              => 'First Purchase',
            'name_ar'           => 'أول شراء',
            'trigger_type'      => 'purchase_count',
            'trigger_threshold' => 1,
            'points_reward'     => 50,
            'sort_order'        => 0,
        ]);

        BusinessTypeGamificationChallenge::create([
            'business_type_id' => $this->businessType->id,
            'name'             => 'Spend Challenge',
            'name_ar'          => 'تحدي الإنفاق',
            'challenge_type'   => 'spend_target',
            'target_value'     => 500,
            'reward_type'      => 'points',
            'reward_value'     => '200',
            'sort_order'       => 0,
        ]);

        // Should complete without exception even though gamification seeding is not yet wired
        $result = $this->service->seed($this->store, $this->businessType);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('seeded', $result);
    }
}
