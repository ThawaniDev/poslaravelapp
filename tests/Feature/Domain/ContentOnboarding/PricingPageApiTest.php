<?php

namespace Tests\Feature\Domain\ContentOnboarding;

use App\Domain\ContentOnboarding\Models\PricingPageContent;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Comprehensive API tests for pricing endpoints.
 *
 * Endpoints (all public, no auth required):
 *   GET /api/v2/pricing                    — list published, ordered by sort_order
 *   GET /api/v2/pricing/{planSlug}         — by plan slug
 *   GET /api/v2/pricing/plan/{planId}      — by plan UUID
 */
class PricingPageApiTest extends TestCase
{
    use RefreshDatabase;

    // ═══════════════════════════════════════════════════════════════════════
    //  GET /api/v2/pricing — List
    // ═══════════════════════════════════════════════════════════════════════

    public function test_list_does_not_require_authentication(): void
    {
        $this->getJson('/api/v2/pricing')->assertOk();
    }

    public function test_list_returns_only_published_content(): void
    {
        $starter = $this->makePlan('starter');
        $pro      = $this->makePlan('pro');

        $this->makeContent($starter, ['is_published' => true, 'sort_order' => 1]);
        $this->makeContent($pro, ['is_published' => false, 'sort_order' => 2]);

        $response = $this->getJson('/api/v2/pricing');
        $response->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('starter', $response->json('data.0.plan.slug'));
    }

    public function test_list_ordered_by_sort_order_ascending(): void
    {
        $planA = $this->makePlan('plan-a');
        $planB = $this->makePlan('plan-b');
        $planC = $this->makePlan('plan-c');

        $this->makeContent($planC, ['is_published' => true, 'sort_order' => 3]);
        $this->makeContent($planA, ['is_published' => true, 'sort_order' => 1]);
        $this->makeContent($planB, ['is_published' => true, 'sort_order' => 2]);

        $response = $this->getJson('/api/v2/pricing');
        $slugs = collect($response->json('data'))->map(fn ($d) => $d['plan']['slug'])->toArray();

        $this->assertEquals(['plan-a', 'plan-b', 'plan-c'], $slugs);
    }

    public function test_list_returns_empty_data_when_none_published(): void
    {
        $plan = $this->makePlan('unpublished-plan');
        $this->makeContent($plan, ['is_published' => false]);

        $response = $this->getJson('/api/v2/pricing');
        $response->assertOk();
        $this->assertEquals([], $response->json('data'));
    }

    public function test_list_response_shape(): void
    {
        $plan = $this->makePlan('shape-plan');
        $this->makeContent($plan, [
            'is_published'       => true,
            'hero_title'         => 'Start Your Journey',
            'hero_title_ar'      => 'ابدأ رحلتك',
            'cta_label'          => 'Get Started',
            'cta_label_ar'       => 'ابدأ الآن',
            'feature_bullet_list' => ['Feature A', 'Feature B'],
            'faq'                => [['q' => 'What?', 'a' => 'This.']],
        ]);

        $response = $this->getJson('/api/v2/pricing');
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'plan', 'hero_title', 'hero_title_ar',
                        'hero_subtitle', 'hero_subtitle_ar',
                        'highlight_badge', 'highlight_badge_ar',
                        'highlight_color', 'is_highlighted',
                        'cta_label', 'cta_label_ar',
                        'cta_secondary_label', 'cta_secondary_label_ar', 'cta_url',
                        'price_prefix', 'price_prefix_ar',
                        'price_suffix', 'price_suffix_ar',
                        'annual_discount_label', 'annual_discount_label_ar',
                        'trial_label', 'trial_label_ar',
                        'money_back_days',
                        'feature_bullet_list', 'feature_categories', 'faq',
                        'testimonials', 'comparison_highlights',
                        'meta_title', 'meta_title_ar',
                        'meta_description', 'meta_description_ar',
                        'color_theme', 'card_icon', 'card_image_url',
                        'is_published', 'sort_order', 'updated_at',
                    ],
                ],
            ]);
    }

    public function test_list_plan_sub_object_shape(): void
    {
        $plan = $this->makePlan('plan-sub', [
            'monthly_price' => 99.0,
            'annual_price'  => 990.0,
            'trial_days'    => 14,
            'is_highlighted' => true,
        ]);
        $this->makeContent($plan, ['is_published' => true]);

        $response = $this->getJson('/api/v2/pricing');
        $planData = $response->json('data.0.plan');

        $this->assertArrayHasKey('id', $planData);
        $this->assertArrayHasKey('name', $planData);
        $this->assertArrayHasKey('name_ar', $planData);
        $this->assertArrayHasKey('slug', $planData);
        $this->assertArrayHasKey('monthly_price', $planData);
        $this->assertArrayHasKey('annual_price', $planData);
        $this->assertArrayHasKey('trial_days', $planData);
        $this->assertArrayHasKey('is_highlighted', $planData);

        $this->assertEquals(99.0, $planData['monthly_price']);
        $this->assertEquals(990.0, $planData['annual_price']);
        $this->assertEquals(14, $planData['trial_days']);
        $this->assertTrue($planData['is_highlighted']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  Feature arrays
    // ═══════════════════════════════════════════════════════════════════════

    public function test_feature_bullet_list_returns_empty_array_when_null(): void
    {
        $plan = $this->makePlan('null-bullets');
        $this->makeContent($plan, ['is_published' => true, 'feature_bullet_list' => null]);

        $response = $this->getJson('/api/v2/pricing');
        $this->assertEquals([], $response->json('data.0.feature_bullet_list'));
    }

    public function test_faq_returns_empty_array_when_null(): void
    {
        $plan = $this->makePlan('null-faq');
        $this->makeContent($plan, ['is_published' => true, 'faq' => null]);

        $response = $this->getJson('/api/v2/pricing');
        $this->assertEquals([], $response->json('data.0.faq'));
    }

    public function test_feature_categories_structure_is_preserved(): void
    {
        $plan = $this->makePlan('cat-features');
        $this->makeContent($plan, [
            'is_published'      => true,
            'feature_categories' => [
                [
                    'title'    => 'Core',
                    'features' => ['Inventory', 'Reporting'],
                ],
            ],
        ]);

        $response = $this->getJson('/api/v2/pricing');
        $cats = $response->json('data.0.feature_categories');

        $this->assertCount(1, $cats);
        $this->assertEquals('Core', $cats[0]['title']);
        $this->assertEquals(['Inventory', 'Reporting'], $cats[0]['features']);
    }

    public function test_faq_items_structure_is_preserved(): void
    {
        $plan = $this->makePlan('faq-plan');
        $this->makeContent($plan, [
            'is_published' => true,
            'faq'          => [
                ['q' => 'Can I cancel?', 'a' => 'Yes, anytime.'],
                ['q' => 'Free trial?', 'a' => '14 days.'],
            ],
        ]);

        $response = $this->getJson('/api/v2/pricing');
        $faq = $response->json('data.0.faq');

        $this->assertCount(2, $faq);
        $this->assertEquals('Can I cancel?', $faq[0]['q']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  Numeric type correctness
    // ═══════════════════════════════════════════════════════════════════════

    public function test_sort_order_is_integer(): void
    {
        $plan = $this->makePlan('sort-int');
        $this->makeContent($plan, ['is_published' => true, 'sort_order' => 5]);

        $response = $this->getJson('/api/v2/pricing');
        $this->assertIsInt($response->json('data.0.sort_order'));
        $this->assertEquals(5, $response->json('data.0.sort_order'));
    }

    public function test_monthly_price_is_float(): void
    {
        $plan = $this->makePlan('price-float', ['monthly_price' => 149.50]);
        $this->makeContent($plan, ['is_published' => true]);

        $response = $this->getJson('/api/v2/pricing');
        $this->assertIsFloat($response->json('data.0.plan.monthly_price'));
    }

    public function test_is_highlighted_is_boolean(): void
    {
        $plan = $this->makePlan('bool-highlight', ['is_highlighted' => true]);
        $this->makeContent($plan, ['is_published' => true, 'is_highlighted' => true]);

        $response = $this->getJson('/api/v2/pricing');
        $this->assertIsBool($response->json('data.0.is_highlighted'));
        $this->assertTrue($response->json('data.0.is_highlighted'));
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  GET /api/v2/pricing/{planSlug}
    // ═══════════════════════════════════════════════════════════════════════

    public function test_show_by_slug_does_not_require_authentication(): void
    {
        $plan = $this->makePlan('open-plan');
        $this->makeContent($plan, ['is_published' => true]);

        $this->getJson('/api/v2/pricing/open-plan')->assertOk();
    }

    public function test_show_by_slug_returns_correct_plan(): void
    {
        $plan = $this->makePlan('starter-plan', ['monthly_price' => 99.0]);
        $this->makeContent($plan, [
            'is_published'  => true,
            'hero_title'    => 'Starter Plan',
            'hero_title_ar' => 'باقة المبتدئين',
        ]);

        $response = $this->getJson('/api/v2/pricing/starter-plan');
        $response->assertOk();

        $this->assertEquals('starter-plan', $response->json('data.plan.slug'));
        $this->assertEquals('Starter Plan', $response->json('data.hero_title'));
        $this->assertEquals('باقة المبتدئين', $response->json('data.hero_title_ar'));
    }

    public function test_show_by_slug_returns_404_for_unknown_slug(): void
    {
        $this->getJson('/api/v2/pricing/this-plan-does-not-exist')
            ->assertNotFound();
    }

    public function test_show_by_slug_returns_404_for_unpublished_content(): void
    {
        $plan = $this->makePlan('draft-plan');
        $this->makeContent($plan, ['is_published' => false]);

        $this->getJson('/api/v2/pricing/draft-plan')->assertNotFound();
    }

    public function test_show_by_slug_response_includes_plan_object(): void
    {
        $plan = $this->makePlan('pro-plan', ['monthly_price' => 249.0]);
        $this->makeContent($plan, ['is_published' => true]);

        $response = $this->getJson('/api/v2/pricing/pro-plan');
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'plan' => ['id', 'name', 'name_ar', 'slug', 'monthly_price', 'is_highlighted'],
                    'feature_bullet_list', 'faq', 'is_published', 'sort_order', 'updated_at',
                ],
            ]);
    }

    public function test_show_by_slug_bilingual_hero(): void
    {
        $plan = $this->makePlan('bi-plan');
        $this->makeContent($plan, [
            'is_published'     => true,
            'hero_title'       => 'Grow Your Business',
            'hero_title_ar'    => 'نمّ أعمالك',
            'hero_subtitle'    => 'Everything you need',
            'hero_subtitle_ar' => 'كل ما تحتاجه',
        ]);

        $response = $this->getJson('/api/v2/pricing/bi-plan');
        $this->assertEquals('Grow Your Business', $response->json('data.hero_title'));
        $this->assertEquals('نمّ أعمالك', $response->json('data.hero_title_ar'));
        $this->assertEquals('Everything you need', $response->json('data.hero_subtitle'));
        $this->assertEquals('كل ما تحتاجه', $response->json('data.hero_subtitle_ar'));
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  GET /api/v2/pricing/plan/{planId}
    // ═══════════════════════════════════════════════════════════════════════

    public function test_show_by_plan_id_does_not_require_authentication(): void
    {
        $plan = $this->makePlan('uuid-plan');
        $this->makeContent($plan, ['is_published' => true]);

        $this->getJson("/api/v2/pricing/plan/{$plan->id}")->assertOk();
    }

    public function test_show_by_plan_id_returns_correct_content(): void
    {
        $plan = $this->makePlan('uuid-plan-2', ['monthly_price' => 399.0]);
        $this->makeContent($plan, [
            'is_published'  => true,
            'hero_title'    => 'Enterprise Plan',
            'hero_title_ar' => 'باقة المؤسسات',
        ]);

        $response = $this->getJson("/api/v2/pricing/plan/{$plan->id}");
        $response->assertOk();

        $this->assertEquals('Enterprise Plan', $response->json('data.hero_title'));
        $this->assertEquals($plan->id, $response->json('data.plan.id'));
    }

    public function test_show_by_plan_id_returns_404_for_unknown_uuid(): void
    {
        $unknownId = Str::uuid()->toString();

        $this->getJson("/api/v2/pricing/plan/{$unknownId}")
            ->assertNotFound();
    }

    public function test_show_by_plan_id_returns_404_for_unpublished_content(): void
    {
        $plan = $this->makePlan('unpub-uuid-plan');
        $this->makeContent($plan, ['is_published' => false]);

        $this->getJson("/api/v2/pricing/plan/{$plan->id}")
            ->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  Display defaults / fallbacks
    // ═══════════════════════════════════════════════════════════════════════

    public function test_highlight_color_defaults_to_primary_when_null(): void
    {
        $plan = $this->makePlan('color-default');
        $this->makeContent($plan, ['is_published' => true, 'highlight_color' => null]);

        $response = $this->getJson('/api/v2/pricing');
        $this->assertEquals('primary', $response->json('data.0.highlight_color'));
    }

    public function test_color_theme_defaults_to_primary_when_null(): void
    {
        $plan = $this->makePlan('theme-default');
        $this->makeContent($plan, ['is_published' => true, 'color_theme' => null]);

        $response = $this->getJson('/api/v2/pricing');
        $this->assertEquals('primary', $response->json('data.0.color_theme'));
    }

    public function test_annual_price_is_null_when_not_set(): void
    {
        $plan = $this->makePlan('no-annual', ['annual_price' => null]);
        $this->makeContent($plan, ['is_published' => true]);

        $response = $this->getJson('/api/v2/pricing');
        $this->assertNull($response->json('data.0.plan.annual_price'));
    }

    public function test_money_back_days_can_be_null(): void
    {
        $plan = $this->makePlan('no-money-back');
        $this->makeContent($plan, ['is_published' => true, 'money_back_days' => null]);

        $response = $this->getJson('/api/v2/pricing');
        $this->assertNull($response->json('data.0.money_back_days'));
    }

    public function test_money_back_days_value_preserved(): void
    {
        $plan = $this->makePlan('with-money-back');
        $this->makeContent($plan, ['is_published' => true, 'money_back_days' => 30]);

        $response = $this->getJson('/api/v2/pricing');
        $this->assertEquals(30, $response->json('data.0.money_back_days'));
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  updated_at ISO 8601
    // ═══════════════════════════════════════════════════════════════════════

    public function test_updated_at_is_iso8601(): void
    {
        $plan = $this->makePlan('ts-plan');
        $this->makeContent($plan, ['is_published' => true]);

        $response = $this->getJson('/api/v2/pricing');
        $updatedAt = $response->json('data.0.updated_at');

        $this->assertNotNull($updatedAt);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $updatedAt,
            'updated_at must be ISO 8601'
        );
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private function makePlan(string $slug, array $overrides = []): SubscriptionPlan
    {
        $defaults = [
            'name'           => ucfirst(str_replace('-', ' ', $slug)),
            'name_ar'        => 'باقة ' . $slug,
            'slug'           => $slug,
            'monthly_price'  => 99.0,
            'annual_price'   => 990.0,
            'trial_days'     => 0,
            'is_active'      => true,
            'is_highlighted' => false,
            'sort_order'     => 0,
        ];

        return SubscriptionPlan::create(array_merge($defaults, $overrides));
    }

    private function makeContent(SubscriptionPlan $plan, array $overrides = []): PricingPageContent
    {
        $defaults = [
            'subscription_plan_id' => $plan->id,
            'hero_title'           => 'Hero Title',
            'hero_title_ar'        => 'عنوان رئيسي',
            'cta_label'            => 'Start Free Trial',
            'cta_label_ar'         => 'ابدأ التجربة المجانية',
            'feature_bullet_list'  => ['Feature 1', 'Feature 2'],
            'faq'                  => [],
            'is_published'         => true,
            'sort_order'           => 0,
        ];

        return PricingPageContent::create(array_merge($defaults, $overrides));
    }
}
