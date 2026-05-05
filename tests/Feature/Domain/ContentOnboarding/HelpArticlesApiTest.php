<?php

namespace Tests\Feature\Domain\ContentOnboarding;

use App\Domain\ContentOnboarding\Models\KnowledgeBaseArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Comprehensive API tests for help-articles endpoints.
 *
 * Endpoints (all public, no auth required):
 *   GET /api/v2/help-articles              — paginated list, ?category, ?delivery_platform_id, ?per_page
 *   GET /api/v2/help-articles/{slug}       — single article with body
 *
 * Valid categories (KnowledgeBaseCategory enum):
 *   general, getting_started, pos_usage, inventory, delivery, billing, troubleshooting
 */
class HelpArticlesApiTest extends TestCase
{
    use RefreshDatabase;

    // ═══════════════════════════════════════════════════════════════════════
    //  GET /api/v2/help-articles — List
    // ═══════════════════════════════════════════════════════════════════════

    public function test_list_does_not_require_authentication(): void
    {
        $this->getJson('/api/v2/help-articles')->assertOk();
    }

    public function test_list_returns_only_published_articles(): void
    {
        $this->makeArticle(['slug' => 'pub-1', 'is_published' => true]);
        $this->makeArticle(['slug' => 'draft-1', 'is_published' => false]);

        $response = $this->getJson('/api/v2/help-articles');
        $response->assertOk();

        $slugs = collect($response->json('data'))->pluck('slug')->toArray();
        $this->assertContains('pub-1', $slugs);
        $this->assertNotContains('draft-1', $slugs);
    }

    public function test_list_returns_empty_when_no_published_articles(): void
    {
        $this->makeArticle(['slug' => 'draft', 'is_published' => false]);

        $response = $this->getJson('/api/v2/help-articles');
        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_list_returns_articles_ordered_by_sort_order_then_created_at(): void
    {
        $this->makeArticle(['slug' => 'z-article', 'sort_order' => 2, 'is_published' => true]);
        $this->makeArticle(['slug' => 'a-article', 'sort_order' => 1, 'is_published' => true]);
        $this->makeArticle(['slug' => 'b-article', 'sort_order' => 1, 'is_published' => true]);

        $response = $this->getJson('/api/v2/help-articles');
        $slugs = collect($response->json('data'))->pluck('slug')->toArray();

        // a-article and b-article (sort_order=1) must come before z-article (sort_order=2)
        $this->assertLessThan(
            array_search('z-article', $slugs),
            array_search('a-article', $slugs),
            'lower sort_order articles must appear first'
        );
    }

    public function test_list_body_not_included_in_list_response(): void
    {
        $this->makeArticle([
            'slug'       => 'no-body-in-list',
            'body'       => 'Full article body content here',
            'is_published' => true,
        ]);

        $response = $this->getJson('/api/v2/help-articles');
        $article = collect($response->json('data'))->firstWhere('slug', 'no-body-in-list');

        // body should not appear in list endpoint (only in show)
        $this->assertArrayNotHasKey('body', $article);
        $this->assertArrayNotHasKey('body_ar', $article);
    }

    public function test_list_response_shape(): void
    {
        $this->makeArticle(['slug' => 'shape-test', 'is_published' => true]);

        $response = $this->getJson('/api/v2/help-articles');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'slug', 'title', 'title_ar',
                        'category', 'delivery_platform_id',
                        'sort_order', 'created_at', 'updated_at',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_list_includes_pagination_metadata(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->makeArticle(['slug' => "page-art-$i", 'is_published' => true]);
        }

        $response = $this->getJson('/api/v2/help-articles?per_page=2');
        $response->assertOk();

        $meta = $response->json('meta');
        $this->assertNotNull($meta);
        $this->assertEquals(3, $meta['total']);
        $this->assertEquals(2, $meta['per_page']);
        $this->assertEquals(2, $meta['last_page']);
    }

    public function test_list_per_page_parameter_works(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->makeArticle(['slug' => "per-page-art-$i", 'is_published' => true]);
        }

        $response = $this->getJson('/api/v2/help-articles?per_page=3');
        $response->assertOk();

        $this->assertCount(3, $response->json('data'));
    }

    public function test_list_per_page_max_is_100(): void
    {
        $this->getJson('/api/v2/help-articles?per_page=101')
            ->assertUnprocessable();
    }

    public function test_list_per_page_min_is_1(): void
    {
        $this->getJson('/api/v2/help-articles?per_page=0')
            ->assertUnprocessable();
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  Category filter
    // ═══════════════════════════════════════════════════════════════════════

    public function test_list_filters_by_category_getting_started(): void
    {
        $this->makeArticle(['slug' => 'gs-1', 'category' => 'getting_started', 'is_published' => true]);
        $this->makeArticle(['slug' => 'gs-2', 'category' => 'getting_started', 'is_published' => true]);
        $this->makeArticle(['slug' => 'billing-1', 'category' => 'billing', 'is_published' => true]);

        $response = $this->getJson('/api/v2/help-articles?category=getting_started');
        $response->assertOk();

        $slugs = collect($response->json('data'))->pluck('slug')->toArray();
        $this->assertContains('gs-1', $slugs);
        $this->assertContains('gs-2', $slugs);
        $this->assertNotContains('billing-1', $slugs);
    }

    public function test_list_filters_by_all_seven_categories(): void
    {
        $categories = ['general', 'getting_started', 'pos_usage', 'inventory', 'delivery', 'billing', 'troubleshooting'];

        foreach ($categories as $cat) {
            $this->makeArticle(['slug' => "cat-$cat", 'category' => $cat, 'is_published' => true]);
        }

        foreach ($categories as $cat) {
            $response = $this->getJson("/api/v2/help-articles?category=$cat");
            $response->assertOk();
            $slugs = collect($response->json('data'))->pluck('slug')->toArray();
            $this->assertContains("cat-$cat", $slugs, "Category filter '$cat' should return its article");
        }
    }

    public function test_list_category_filter_returns_empty_when_no_match(): void
    {
        $this->makeArticle(['slug' => 'billing-only', 'category' => 'billing', 'is_published' => true]);

        $response = $this->getJson('/api/v2/help-articles?category=inventory');
        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_list_category_filter_max_50_chars(): void
    {
        $long = str_repeat('a', 51);
        $this->getJson("/api/v2/help-articles?category=$long")
            ->assertUnprocessable();
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  delivery_platform_id filter
    // ═══════════════════════════════════════════════════════════════════════

    public function test_list_filters_by_delivery_platform_id(): void
    {
        $platformId = Str::uuid()->toString();

        $this->makeArticle(['slug' => 'platform-specific', 'delivery_platform_id' => $platformId, 'is_published' => true]);
        $this->makeArticle(['slug' => 'generic', 'delivery_platform_id' => null, 'is_published' => true]);

        $response = $this->getJson("/api/v2/help-articles?delivery_platform_id=$platformId");
        $response->assertOk();

        $slugs = collect($response->json('data'))->pluck('slug')->toArray();
        $this->assertContains('platform-specific', $slugs);
        $this->assertNotContains('generic', $slugs);
    }

    public function test_list_delivery_platform_id_must_be_valid_uuid(): void
    {
        $this->getJson('/api/v2/help-articles?delivery_platform_id=not-a-uuid')
            ->assertUnprocessable();
    }

    public function test_list_delivery_platform_id_in_response_is_uuid_or_null(): void
    {
        $platformId = Str::uuid()->toString();
        $this->makeArticle(['slug' => 'with-platform', 'delivery_platform_id' => $platformId, 'is_published' => true]);
        $this->makeArticle(['slug' => 'without-platform', 'delivery_platform_id' => null, 'is_published' => true]);

        $response = $this->getJson('/api/v2/help-articles');
        $articles = collect($response->json('data'));

        $withPlatform = $articles->firstWhere('slug', 'with-platform');
        $withoutPlatform = $articles->firstWhere('slug', 'without-platform');

        $this->assertEquals($platformId, $withPlatform['delivery_platform_id']);
        $this->assertNull($withoutPlatform['delivery_platform_id']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  GET /api/v2/help-articles/{slug} — Show
    // ═══════════════════════════════════════════════════════════════════════

    public function test_show_does_not_require_authentication(): void
    {
        $this->makeArticle(['slug' => 'public-article', 'is_published' => true]);

        $this->getJson('/api/v2/help-articles/public-article')->assertOk();
    }

    public function test_show_returns_full_shape_including_body(): void
    {
        $this->makeArticle([
            'slug'       => 'full-article',
            'title'      => 'Getting Started Guide',
            'title_ar'   => 'دليل البداية',
            'body'       => '<h1>Welcome</h1><p>Start here.</p>',
            'body_ar'    => '<h1>أهلاً</h1><p>ابدأ هنا.</p>',
            'category'   => 'getting_started',
            'is_published' => true,
        ]);

        $response = $this->getJson('/api/v2/help-articles/full-article');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'slug', 'title', 'title_ar',
                    'body', 'body_ar',
                    'category', 'delivery_platform_id',
                    'sort_order', 'created_at', 'updated_at',
                ],
            ]);

        $this->assertEquals('Getting Started Guide', $response->json('data.title'));
        $this->assertEquals('دليل البداية', $response->json('data.title_ar'));
        $this->assertStringContainsString('Welcome', $response->json('data.body'));
        $this->assertStringContainsString('أهلاً', $response->json('data.body_ar'));
    }

    public function test_show_returns_404_for_draft_article(): void
    {
        $this->makeArticle(['slug' => 'draft-article', 'is_published' => false]);

        $this->getJson('/api/v2/help-articles/draft-article')
            ->assertNotFound();
    }

    public function test_show_returns_404_for_unknown_slug(): void
    {
        $this->getJson('/api/v2/help-articles/this-slug-does-not-exist')
            ->assertNotFound();
    }

    public function test_show_category_returned_as_string_value(): void
    {
        $this->makeArticle([
            'slug'       => 'cat-string-check',
            'category'   => 'billing',
            'is_published' => true,
        ]);

        $response = $this->getJson('/api/v2/help-articles/cat-string-check');
        $this->assertEquals('billing', $response->json('data.category'));
    }

    public function test_show_sort_order_returned_as_integer(): void
    {
        $this->makeArticle([
            'slug'        => 'sortorder-int-check',
            'sort_order'  => 5,
            'is_published' => true,
        ]);

        $response = $this->getJson('/api/v2/help-articles/sortorder-int-check');
        $this->assertIsInt($response->json('data.sort_order'));
        $this->assertEquals(5, $response->json('data.sort_order'));
    }

    public function test_show_created_at_is_iso8601(): void
    {
        $this->makeArticle(['slug' => 'timestamp-test', 'is_published' => true]);

        $response = $this->getJson('/api/v2/help-articles/timestamp-test');
        $createdAt = $response->json('data.created_at');

        $this->assertNotNull($createdAt);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $createdAt,
            'created_at must be ISO 8601'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  Arabic content
    // ═══════════════════════════════════════════════════════════════════════

    public function test_arabic_content_preserved_correctly(): void
    {
        $this->makeArticle([
            'slug'       => 'arabic-content',
            'title'      => 'POS Usage Guide',
            'title_ar'   => 'دليل استخدام نقطة البيع',
            'body'       => 'English body',
            'body_ar'    => 'محتوى عربي كامل لاختبار الترميز',
            'category'   => 'pos_usage',
            'is_published' => true,
        ]);

        $response = $this->getJson('/api/v2/help-articles/arabic-content');

        $this->assertEquals('دليل استخدام نقطة البيع', $response->json('data.title_ar'));
        $this->assertEquals('محتوى عربي كامل لاختبار الترميز', $response->json('data.body_ar'));
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  Cross-filter
    // ═══════════════════════════════════════════════════════════════════════

    public function test_category_and_delivery_platform_combined_filter(): void
    {
        $platformId = Str::uuid()->toString();

        // Matches both
        $this->makeArticle([
            'slug'                 => 'both-match',
            'category'             => 'delivery',
            'delivery_platform_id' => $platformId,
            'is_published'         => true,
        ]);
        // Only category match
        $this->makeArticle([
            'slug'                 => 'cat-match',
            'category'             => 'delivery',
            'delivery_platform_id' => null,
            'is_published'         => true,
        ]);
        // Only platform match
        $this->makeArticle([
            'slug'                 => 'platform-match',
            'category'             => 'billing',
            'delivery_platform_id' => $platformId,
            'is_published'         => true,
        ]);

        $response = $this->getJson(
            "/api/v2/help-articles?category=delivery&delivery_platform_id=$platformId"
        );

        $response->assertOk();
        $slugs = collect($response->json('data'))->pluck('slug')->toArray();

        $this->assertContains('both-match', $slugs);
        $this->assertNotContains('cat-match', $slugs);
        $this->assertNotContains('platform-match', $slugs);
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  Pagination edge cases
    // ═══════════════════════════════════════════════════════════════════════

    public function test_list_page_2_returns_correct_items(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            $this->makeArticle([
                'slug'        => "paginate-art-$i",
                'sort_order'  => $i,
                'is_published' => true,
            ]);
        }

        $response = $this->getJson('/api/v2/help-articles?per_page=2&page=2');
        $response->assertOk();

        $this->assertCount(2, $response->json('data'));
        $this->assertEquals(2, $response->json('meta.current_page'));
    }

    public function test_list_beyond_last_page_returns_empty_data(): void
    {
        $this->makeArticle(['slug' => 'only-one', 'is_published' => true]);

        $response = $this->getJson('/api/v2/help-articles?per_page=10&page=999');
        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    // ─── Helper ───────────────────────────────────────────────────────────

    private function makeArticle(array $overrides = []): KnowledgeBaseArticle
    {
        $defaults = [
            'slug'                 => 'article-' . Str::random(6),
            'title'                => 'Sample Article',
            'title_ar'             => 'مقالة تجريبية',
            'body'                 => '<p>Article body.</p>',
            'body_ar'              => '<p>نص المقالة.</p>',
            'category'             => 'general',
            'delivery_platform_id' => null,
            'sort_order'           => 0,
            'is_published'         => true,
        ];

        return KnowledgeBaseArticle::create(array_merge($defaults, $overrides));
    }
}
