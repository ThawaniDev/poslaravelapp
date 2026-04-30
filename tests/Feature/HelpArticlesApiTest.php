<?php

namespace Tests\Feature;

use App\Domain\ContentOnboarding\Models\KnowledgeBaseArticle;
use App\Domain\SystemConfig\Enums\KnowledgeBaseCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for GET /api/v2/help-articles and GET /api/v2/help-articles/{slug}.
 * Public endpoints — no auth required.
 */
class HelpArticlesApiTest extends TestCase
{
    use RefreshDatabase;

    private KnowledgeBaseArticle $published;
    private KnowledgeBaseArticle $draft;

    protected function setUp(): void
    {
        parent::setUp();

        $this->published = KnowledgeBaseArticle::create([
            'title'        => 'Getting Started with POS',
            'title_ar'     => 'البدء مع نقطة البيع',
            'slug'         => 'getting-started',
            'body'         => '<p>Welcome to Wameed POS.</p>',
            'body_ar'      => '<p>مرحباً بك في وميض POS.</p>',
            'category'     => KnowledgeBaseCategory::GettingStarted->value,
            'is_published' => true,
            'sort_order'   => 1,
        ]);

        $this->draft = KnowledgeBaseArticle::create([
            'title'        => 'Advanced Reports',
            'title_ar'     => 'تقارير متقدمة',
            'slug'         => 'advanced-reports',
            'body'         => '<p>Coming soon.</p>',
            'body_ar'      => '<p>قريباً.</p>',
            'category'     => KnowledgeBaseCategory::General->value,
            'is_published' => false,
            'sort_order'   => 2,
        ]);
    }

    // ─── Index ─────────────────────────────────────────────────────

    public function test_can_list_help_articles_without_auth(): void
    {
        $response = $this->getJson('/api/v2/help-articles');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'slug', 'title', 'title_ar', 'category', 'sort_order'],
                ],
                'meta' => ['current_page', 'total', 'per_page'],
                'links',
            ]);

        $slugs = collect($response->json('data'))->pluck('slug')->toArray();
        $this->assertContains('getting-started', $slugs);
        $this->assertNotContains('advanced-reports', $slugs); // draft hidden
    }

    public function test_index_does_not_include_body_in_list(): void
    {
        $response = $this->getJson('/api/v2/help-articles');
        $response->assertOk();

        // body and body_ar should be absent from list endpoint
        $article = collect($response->json('data'))->first();
        $this->assertArrayNotHasKey('body', $article);
        $this->assertArrayNotHasKey('body_ar', $article);
    }

    public function test_can_filter_by_category(): void
    {
        KnowledgeBaseArticle::create([
            'title'        => 'Hardware Setup',
            'title_ar'     => 'إعداد الأجهزة',
            'slug'         => 'hardware-setup',
            'body'         => '<p>Hardware guide.</p>',
            'body_ar'      => '<p>دليل الأجهزة.</p>',
            'category'     => KnowledgeBaseCategory::Troubleshooting->value,
            'is_published' => true,
            'sort_order'   => 10,
        ]);

        $response = $this->getJson('/api/v2/help-articles?category=' . KnowledgeBaseCategory::Troubleshooting->value);
        $response->assertOk();

        $slugs = collect($response->json('data'))->pluck('slug')->toArray();
        $this->assertContains('hardware-setup', $slugs);
        $this->assertNotContains('getting-started', $slugs);
    }

    public function test_category_filter_rejects_invalid_values(): void
    {
        // category is a free string filter — very long values are rejected
        $longCategory = str_repeat('x', 51);
        $this->getJson('/api/v2/help-articles?category=' . $longCategory)
            ->assertUnprocessable();
    }

    public function test_pagination_works(): void
    {
        // Create 5 more published articles
        for ($i = 2; $i <= 6; $i++) {
            KnowledgeBaseArticle::create([
                'title'        => "Article $i",
                'title_ar'     => "مقالة $i",
                'slug'         => "article-$i",
                'body'         => '<p>Body.</p>',
                'body_ar'      => '<p>المحتوى.</p>',
                'category'     => KnowledgeBaseCategory::GettingStarted->value,
                'is_published' => true,
                'sort_order'   => $i,
            ]);
        }

        $response = $this->getJson('/api/v2/help-articles?per_page=3&page=1');
        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
        $this->assertEquals(6, $response->json('meta.total')); // 1 original + 5 new
    }

    // ─── Show ──────────────────────────────────────────────────────

    public function test_can_get_article_by_slug(): void
    {
        $response = $this->getJson('/api/v2/help-articles/getting-started');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'slug', 'title', 'title_ar',
                    'body', 'body_ar',
                    'category', 'sort_order',
                ],
            ]);

        $this->assertEquals('Getting Started with POS', $response->json('data.title'));
        $this->assertEquals('<p>Welcome to Wameed POS.</p>', $response->json('data.body'));
    }

    public function test_show_returns_404_for_draft_article(): void
    {
        $this->getJson('/api/v2/help-articles/advanced-reports')
            ->assertNotFound();
    }

    public function test_show_returns_404_for_unknown_slug(): void
    {
        $this->getJson('/api/v2/help-articles/does-not-exist')
            ->assertNotFound();
    }
}
