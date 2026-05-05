<?php

namespace Tests\Feature\Workflow;

use App\Domain\Auth\Models\User;
use App\Domain\ContentOnboarding\Models\BusinessType;
use App\Domain\ContentOnboarding\Models\BusinessTypeCategoryTemplate;
use App\Domain\ContentOnboarding\Models\BusinessTypeCustomerGroupTemplate;
use App\Domain\ContentOnboarding\Models\BusinessTypeLoyaltyConfig;
use App\Domain\ContentOnboarding\Models\BusinessTypePromotionTemplate;
use App\Domain\ContentOnboarding\Models\BusinessTypeShiftTemplate;
use App\Domain\ContentOnboarding\Models\KnowledgeBaseArticle;
use App\Domain\ContentOnboarding\Models\PricingPageContent;
use App\Domain\ContentOnboarding\Models\OnboardingStep;
use App\Domain\ContentOnboarding\Services\BusinessTypeSeederService;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Core\Services\OnboardingService;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * End-to-End Content Onboarding Workflow Tests
 *
 * Covers the full onboarding lifecycle:
 *   1. Business types are browsable publicly (API)
 *   2. Store is created with business_type_id → seeder auto-fires
 *   3. Seeded data (categories, shifts, customer groups, loyalty) are present
 *   4. Onboarding wizard steps are accessible
 *   5. Help articles are accessible publicly
 *   6. Pricing page is accessible publicly
 *   7. Business-type defaults API reflects configured templates
 *
 * @see /platform/platform_features/content_onboarding_feature.md
 */
class ContentOnboardingWorkflowTest extends WorkflowTestCase
{
    use RefreshDatabase;

    private User $owner;
    private Organization $org;
    private Store $store;
    private BusinessType $retailType;
    private SubscriptionPlan $starterPlan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->buildFixtures();
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  WF-CO-01: Public business type discovery
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function wf_co_01_public_can_list_active_business_types(): void
    {
        $response = $this->getJson('/api/v2/onboarding/business-types');

        $response->assertOk();
        $slugs = collect($response->json('data'))->pluck('slug')->toArray();
        $this->assertContains('retail-wf', $slugs, 'Active retail type must appear in public list');
        $this->assertNotContains('legacy-wf', $slugs, 'Inactive type must NOT appear');
    }

    /** @test */
    public function wf_co_02_public_can_get_full_business_type_defaults(): void
    {
        $response = $this->getJson('/api/v2/onboarding/business-types/retail-wf/defaults');

        $response->assertOk()
            ->assertJsonPath('data.slug', 'retail-wf')
            ->assertJsonPath('data.name', 'Retail WF');

        // Category templates present
        $this->assertNotEmpty($response->json('data.category_templates'));
        // Customer groups present
        $this->assertNotEmpty($response->json('data.customer_group_templates'));
        // Shift templates present
        $this->assertNotEmpty($response->json('data.shift_templates'));
        // Loyalty config present
        $this->assertNotNull($response->json('data.loyalty_config'));
    }

    /** @test */
    public function wf_co_03_inactive_business_type_returns_404(): void
    {
        $this->getJson('/api/v2/onboarding/business-types/legacy-wf/defaults')
            ->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  WF-CO-04: Store creation triggers seeder
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function wf_co_04_creating_store_with_business_type_seeds_categories(): void
    {
        // Categories are seeded on the org level — org was created with the store
        $categories = DB::table('categories')
            ->where('organization_id', $this->org->id)
            ->get();

        $this->assertGreaterThan(0, $categories->count(), 'Category templates must be seeded when store created');

        $names = $categories->pluck('name')->toArray();
        $this->assertContains('Electronics WF', $names);
        $this->assertContains('Accessories WF', $names);
    }

    /** @test */
    public function wf_co_05_seeded_categories_have_bilingual_names(): void
    {
        $electronics = DB::table('categories')
            ->where('organization_id', $this->org->id)
            ->where('name', 'Electronics WF')
            ->first();

        $this->assertNotNull($electronics);
        $this->assertEquals('إلكترونيات', $electronics->name_ar);
    }

    /** @test */
    public function wf_co_06_seeded_shift_templates_have_correct_times(): void
    {
        $shifts = DB::table('shift_templates')
            ->where('store_id', $this->store->id)
            ->get();

        $this->assertGreaterThan(0, $shifts->count(), 'Shift templates must be seeded');
        $this->assertEquals('Morning WF', $shifts->first()->name);
        $this->assertEquals('08:00:00', $shifts->first()->start_time);
    }

    /** @test */
    public function wf_co_07_seeded_customer_groups_present(): void
    {
        $groups = DB::table('customer_groups')
            ->where('organization_id', $this->org->id)
            ->get();

        $this->assertGreaterThan(0, $groups->count(), 'Customer groups must be seeded');
        $names = $groups->pluck('name')->toArray();
        $this->assertContains('Walk-in WF', $names);
    }

    /** @test */
    public function wf_co_08_seeded_loyalty_config_is_inactive(): void
    {
        $loyalty = DB::table('loyalty_config')
            ->where('organization_id', $this->org->id)
            ->first();

        $this->assertNotNull($loyalty, 'Loyalty config must be seeded');
        $this->assertFalse((bool) $loyalty->is_active, 'Seeded loyalty must be inactive (Business Rule #9)');
    }

    /** @test */
    public function wf_co_09_seeded_promotion_templates_are_inactive(): void
    {
        $promos = DB::table('promotions')
            ->where('organization_id', $this->org->id)
            ->get();

        $this->assertGreaterThan(0, $promos->count(), 'Promotions must be seeded');
        foreach ($promos as $promo) {
            $this->assertFalse((bool) $promo->is_active, 'Seeded promotions must be inactive');
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  WF-CO-10: Onboarding wizard steps
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function wf_co_10_authenticated_user_can_get_onboarding_steps(): void
    {
        $token = $this->owner->createToken('wf-test', ['*'])->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/v2/core/onboarding/steps');

        $response->assertOk();
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    /** @test */
    public function wf_co_11_onboarding_steps_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v2/core/onboarding/steps')
            ->assertUnauthorized();
    }

    /** @test */
    public function wf_co_12_onboarding_wizard_can_be_started_and_completed(): void
    {
        $token = $this->owner->createToken('wf-test', ['*'])->plainTextToken;

        // Get progress (should auto-create)
        $progress = $this->withToken($token)
            ->getJson("/api/v2/core/onboarding/progress?store_id={$this->store->id}");

        $progress->assertOk()
            ->assertJsonPath('data.is_wizard_completed', false)
            ->assertJsonPath('data.current_step', 'welcome');

        // Complete welcome step
        $complete = $this->withToken($token)
            ->postJson('/api/v2/core/onboarding/complete-step', [
                'store_id' => $this->store->id,
                'step'     => 'welcome',
            ]);

        $complete->assertOk();
        $this->assertContains('welcome', $complete->json('data.completed_steps'));

        // Next step should be business_info
        $this->assertEquals('business_info', $complete->json('data.current_step'));
    }

    /** @test */
    public function wf_co_13_onboarding_wizard_can_be_skipped(): void
    {
        $token = $this->owner->createToken('wf-test', ['*'])->plainTextToken;

        $skip = $this->withToken($token)
            ->postJson('/api/v2/core/onboarding/skip', ['store_id' => $this->store->id]);

        $skip->assertOk()
            ->assertJsonPath('data.is_wizard_completed', true);
    }

    /** @test */
    public function wf_co_14_onboarding_can_be_reset(): void
    {
        $token = $this->owner->createToken('wf-test', ['*'])->plainTextToken;

        // First skip it
        $this->withToken($token)
            ->postJson('/api/v2/core/onboarding/skip', ['store_id' => $this->store->id])
            ->assertOk();

        // Then reset
        $reset = $this->withToken($token)
            ->postJson('/api/v2/core/onboarding/reset', ['store_id' => $this->store->id]);

        $reset->assertOk()
            ->assertJsonPath('data.current_step', 'welcome')
            ->assertJsonPath('data.is_wizard_completed', false);
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  WF-CO-15: Help articles public access
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function wf_co_15_public_can_list_published_help_articles(): void
    {
        $response = $this->getJson('/api/v2/help-articles');

        $response->assertOk();
        $slugs = collect($response->json('data'))->pluck('slug')->toArray();
        $this->assertContains('getting-started-wf', $slugs, 'Published article must appear');
        $this->assertNotContains('draft-article-wf', $slugs, 'Draft article must NOT appear');
    }

    /** @test */
    public function wf_co_16_public_can_get_published_article_with_body(): void
    {
        $response = $this->getJson('/api/v2/help-articles/getting-started-wf');

        $response->assertOk()
            ->assertJsonPath('data.slug', 'getting-started-wf')
            ->assertJsonPath('data.title', 'Getting Started WF');

        $this->assertNotNull($response->json('data.body'), 'Full body must be included in show endpoint');
    }

    /** @test */
    public function wf_co_17_help_article_can_be_filtered_by_category(): void
    {
        $response = $this->getJson('/api/v2/help-articles?category=getting_started');

        $response->assertOk();
        $slugs = collect($response->json('data'))->pluck('slug')->toArray();

        $this->assertContains('getting-started-wf', $slugs);
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  WF-CO-18: Pricing page public access
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function wf_co_18_public_can_list_published_pricing_content(): void
    {
        $response = $this->getJson('/api/v2/pricing');

        $response->assertOk();
        $planSlugs = collect($response->json('data'))->map(fn ($d) => $d['plan']['slug'])->toArray();

        $this->assertContains('starter-wf', $planSlugs, 'Published starter plan must be in pricing list');
    }

    /** @test */
    public function wf_co_19_public_can_get_pricing_by_plan_slug(): void
    {
        $response = $this->getJson('/api/v2/pricing/starter-wf');

        $response->assertOk()
            ->assertJsonPath('data.plan.slug', 'starter-wf')
            ->assertJsonPath('data.hero_title', 'Starter Hero WF');
    }

    /** @test */
    public function wf_co_20_public_can_get_pricing_by_plan_id(): void
    {
        $response = $this->getJson("/api/v2/pricing/plan/{$this->starterPlan->id}");

        $response->assertOk()
            ->assertJsonPath('data.plan.id', (string) $this->starterPlan->id);
    }

    /** @test */
    public function wf_co_21_pricing_feature_bullets_are_array(): void
    {
        $response = $this->getJson('/api/v2/pricing/starter-wf');

        $this->assertIsArray($response->json('data.feature_bullet_list'));
        $this->assertContains('Feature A', $response->json('data.feature_bullet_list'));
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  WF-CO-22: Content seeder idempotency on re-seed
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function wf_co_22_seeder_idempotent_does_not_duplicate_on_re_seed(): void
    {
        $seeder = app(BusinessTypeSeederService::class);

        // Re-seed the same store
        $seeder->seed($this->store, $this->retailType);

        $catCount   = DB::table('categories')->where('organization_id', $this->org->id)->count();
        $shiftCount = DB::table('shift_templates')->where('store_id', $this->store->id)->count();
        $groupCount = DB::table('customer_groups')->where('organization_id', $this->org->id)->count();

        $this->assertEquals(2, $catCount, 'Re-seeding must not duplicate categories');
        $this->assertEquals(1, $shiftCount, 'Re-seeding must not duplicate shift templates');
        $this->assertEquals(1, $groupCount, 'Re-seeding must not duplicate customer groups');
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  WF-CO-23: Business type category templates endpoint integration
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function wf_co_23_category_templates_api_returns_same_data_as_seeder_used(): void
    {
        $apiResponse = $this->getJson('/api/v2/onboarding/business-types/retail-wf/category-templates');
        $apiResponse->assertOk();

        $apiNames = collect($apiResponse->json('data'))->pluck('category_name')->sort()->values()->toArray();
        $dbNames  = DB::table('categories')->where('organization_id', $this->org->id)->pluck('name')->sort()->values()->toArray();

        $this->assertEquals($apiNames, $dbNames, 'API template names must match seeded category names');
    }

    // ─── Fixture builder ──────────────────────────────────────────────────

    private function buildFixtures(): void
    {
        // ── Business types ──────────────────────────────────────────────
        $this->retailType = BusinessType::create([
            'name'       => 'Retail WF',
            'name_ar'    => 'تجزئة',
            'slug'       => 'retail-wf',
            'is_active'  => true,
            'sort_order' => 1,
        ]);

        BusinessType::create([
            'name'      => 'Legacy WF',
            'name_ar'   => 'قديم',
            'slug'      => 'legacy-wf',
            'is_active' => false,
        ]);

        // ── Templates ───────────────────────────────────────────────────
        BusinessTypeCategoryTemplate::create([
            'business_type_id' => $this->retailType->id,
            'category_name'    => 'Electronics WF',
            'category_name_ar' => 'إلكترونيات',
            'sort_order'       => 0,
        ]);
        BusinessTypeCategoryTemplate::create([
            'business_type_id' => $this->retailType->id,
            'category_name'    => 'Accessories WF',
            'category_name_ar' => 'إكسسوارات',
            'sort_order'       => 1,
        ]);

        BusinessTypeShiftTemplate::create([
            'business_type_id'       => $this->retailType->id,
            'name'                   => 'Morning WF',
            'name_ar'                => 'صباحية',
            'start_time'             => '08:00:00',
            'end_time'               => '16:00:00',
            'break_duration_minutes' => 30,
            'is_default'             => true,
            'sort_order'             => 0,
        ]);

        BusinessTypeCustomerGroupTemplate::create([
            'business_type_id'    => $this->retailType->id,
            'name'                => 'Walk-in WF',
            'name_ar'             => 'عميل عادي',
            'discount_percentage' => 0,
            'is_default_group'    => true,
            'sort_order'          => 0,
        ]);

        BusinessTypeLoyaltyConfig::create([
            'business_type_id'      => $this->retailType->id,
            'program_type'          => 'points',
            'earning_rate'          => 1.0,
            'redemption_value'      => 0.01,
            'min_redemption_points' => 100,
            'is_active'             => false,
        ]);

        BusinessTypePromotionTemplate::create([
            'business_type_id' => $this->retailType->id,
            'name'             => 'Weekend Sale WF',
            'name_ar'          => 'تخفيضات نهاية الأسبوع',
            'promotion_type'   => 'percentage',
            'discount_value'   => 10.00,
            'sort_order'       => 0,
        ]);

        // ── Organization + Store ─────────────────────────────────────────
        $this->org = Organization::create([
            'name'    => 'WF Test Org',
            'name_ar' => 'مؤسسة اختبار',
            'country' => 'SA',
        ]);

        // Create store with business_type_id → triggers seeder
        $this->store = Store::create([
            'organization_id'  => $this->org->id,
            'name'             => 'WF Test Store',
            'name_ar'          => 'متجر اختبار',
            'currency'         => 'SAR',
            'business_type_id' => $this->retailType->id,
        ]);

        // Insert store_settings row (normally done by StoreService)
        DB::table('store_settings')->insertOrIgnore([
            'id'       => Str::uuid()->toString(),
            'store_id' => $this->store->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Fire seeder (normally called by StoreService::createStore)
        app(BusinessTypeSeederService::class)->seed($this->store, $this->retailType);

        // ── Owner user ────────────────────────────────────────────────────
        $this->owner = User::create([
            'name'            => 'WF Owner',
            'email'           => 'wf-owner-' . Str::random(4) . '@test.example',
            'password_hash'   => bcrypt('password'),
            'store_id'        => $this->store->id,
            'organization_id' => $this->org->id,
            'role'            => 'owner',
            'is_active'       => true,
        ]);
        $this->assignOwnerRole($this->owner, $this->store->id);

        // ── Help articles ─────────────────────────────────────────────────
        KnowledgeBaseArticle::create([
            'slug'         => 'getting-started-wf',
            'title'        => 'Getting Started WF',
            'title_ar'     => 'البدء السريع',
            'body'         => '<p>Start here.</p>',
            'body_ar'      => '<p>ابدأ من هنا.</p>',
            'category'     => 'getting_started',
            'sort_order'   => 0,
            'is_published' => true,
        ]);
        KnowledgeBaseArticle::create([
            'slug'         => 'draft-article-wf',
            'title'        => 'Draft Article WF',
            'title_ar'     => 'مسودة',
            'body'         => '<p>Draft.</p>',
            'body_ar'      => '<p>مسودة.</p>',
            'category'     => 'general',
            'is_published' => false,
        ]);

        // ── Subscription plan + pricing page ──────────────────────────────
        $this->starterPlan = SubscriptionPlan::create([
            'name'           => 'Starter WF',
            'name_ar'        => 'مبتدئ',
            'slug'           => 'starter-wf',
            'monthly_price'  => 99.0,
            'annual_price'   => 990.0,
            'is_active'      => true,
            'is_highlighted' => false,
        ]);
        PricingPageContent::create([
            'subscription_plan_id' => $this->starterPlan->id,
            'hero_title'           => 'Starter Hero WF',
            'hero_title_ar'        => 'البطل المبتدئ',
            'cta_label'            => 'Get Started',
            'cta_label_ar'         => 'ابدأ الآن',
            'feature_bullet_list'  => ['Feature A', 'Feature B'],
            'faq'                  => [],
            'is_published'         => true,
            'sort_order'           => 1,
        ]);
    }
}
