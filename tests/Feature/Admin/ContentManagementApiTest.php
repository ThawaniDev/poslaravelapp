<?php

namespace Tests\Feature\Admin;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Announcement\Models\PlatformAnnouncement;
use App\Domain\ContentOnboarding\Models\CmsPage;
use App\Domain\ContentOnboarding\Models\KnowledgeBaseArticle;
use App\Domain\Notification\Models\NotificationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContentManagementApiTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = AdminUser::forceCreate([
            'name' => 'P8 Test Admin',
            'email' => 'p8admin@test.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);
        Sanctum::actingAs($this->admin, ['*'], 'admin-api');
    }

    // ═══════════════════════════════════════════════════════════
    //  Authentication
    // ═══════════════════════════════════════════════════════════

    public function test_unauthenticated_access_to_content_is_rejected(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v2/admin/content/pages')->assertUnauthorized();
        $this->getJson('/api/v2/admin/content/articles')->assertUnauthorized();
        $this->getJson('/api/v2/admin/content/announcements')->assertUnauthorized();
        $this->getJson('/api/v2/admin/content/templates')->assertUnauthorized();
    }

    // ═══════════════════════════════════════════════════════════
    //  CMS Pages - CRUD
    // ═══════════════════════════════════════════════════════════

    public function test_list_pages_empty(): void
    {
        $this->getJson('/api/v2/admin/content/pages')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 0);
    }

    public function test_create_cms_page(): void
    {
        $this->postJson('/api/v2/admin/content/pages', [
            'title' => 'Terms of Service',
            'title_ar' => 'شروط الخدمة',
            'body' => '<h1>Terms</h1>',
            'body_ar' => '<h1>الشروط</h1>',
            'page_type' => 'legal',
            'is_published' => true,
        ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Terms of Service')
            ->assertJsonPath('data.page_type', 'legal')
            ->assertJsonPath('data.is_published', true);

        $this->assertDatabaseHas('cms_pages', ['title' => 'Terms of Service']);
    }

    public function test_create_page_auto_generates_slug(): void
    {
        $this->postJson('/api/v2/admin/content/pages', [
            'title' => 'About Us Page',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'about-us-page');
    }

    public function test_create_page_with_custom_slug(): void
    {
        $this->postJson('/api/v2/admin/content/pages', [
            'title' => 'About Us',
            'slug' => 'custom-about',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'custom-about');
    }

    public function test_create_page_duplicate_slug_fails(): void
    {
        CmsPage::forceCreate([
            'title' => 'First',
            'slug' => 'unique-slug',
        ]);

        $this->postJson('/api/v2/admin/content/pages', [
            'title' => 'Second',
            'slug' => 'unique-slug',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_show_cms_page(): void
    {
        $page = CmsPage::forceCreate([
            'title' => 'Privacy Policy',
            'title_ar' => 'سياسة الخصوصية',
            'slug' => 'privacy',
            'body' => 'Privacy body',
            'page_type' => 'legal',
        ]);

        $this->getJson("/api/v2/admin/content/pages/{$page->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Privacy Policy')
            ->assertJsonPath('data.page_type', 'legal');
    }

    public function test_show_page_not_found(): void
    {
        $this->getJson('/api/v2/admin/content/pages/nonexistent-id')
            ->assertNotFound();
    }

    public function test_update_cms_page(): void
    {
        $page = CmsPage::forceCreate([
            'title' => 'Old Title',
            'slug' => 'old-title',
        ]);

        $this->putJson("/api/v2/admin/content/pages/{$page->id}", [
            'title' => 'New Title',
            'meta_title' => 'SEO Title',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'New Title')
            ->assertJsonPath('data.meta_title', 'SEO Title');
    }

    public function test_delete_cms_page(): void
    {
        $page = CmsPage::forceCreate([
            'title' => 'To Delete',
            'slug' => 'to-delete',
        ]);

        $this->deleteJson("/api/v2/admin/content/pages/{$page->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('cms_pages', ['id' => $page->id]);
    }

    public function test_publish_toggle_cms_page(): void
    {
        $page = CmsPage::forceCreate([
            'title' => 'Draft Page',
            'slug' => 'draft',
            'is_published' => false,
        ]);

        // Publish
        $this->postJson("/api/v2/admin/content/pages/{$page->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.is_published', true);

        // Unpublish
        $this->postJson("/api/v2/admin/content/pages/{$page->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.is_published', false);
    }

    public function test_list_pages_with_search_filter(): void
    {
        CmsPage::forceCreate(['title' => 'Terms Page', 'slug' => 'terms']);
        CmsPage::forceCreate(['title' => 'About Page', 'slug' => 'about']);

        $this->getJson('/api/v2/admin/content/pages?search=Terms')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_list_pages_with_type_filter(): void
    {
        CmsPage::forceCreate(['title' => 'Legal', 'slug' => 'legal', 'page_type' => 'legal']);
        CmsPage::forceCreate(['title' => 'Marketing', 'slug' => 'marketing', 'page_type' => 'marketing']);

        $this->getJson('/api/v2/admin/content/pages?page_type=legal')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_list_pages_with_published_filter(): void
    {
        CmsPage::forceCreate(['title' => 'Published', 'slug' => 'pub', 'is_published' => true]);
        CmsPage::forceCreate(['title' => 'Draft', 'slug' => 'draft', 'is_published' => false]);

        $this->getJson('/api/v2/admin/content/pages?is_published=true')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    // ═══════════════════════════════════════════════════════════
    //  Knowledge Base Articles - CRUD
    // ═══════════════════════════════════════════════════════════

    public function test_list_articles_empty(): void
    {
        $this->getJson('/api/v2/admin/content/articles')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 0);
    }

    public function test_create_article(): void
    {
        $this->postJson('/api/v2/admin/content/articles', [
            'title' => 'Getting Started Guide',
            'title_ar' => 'دليل البداية',
            'body' => 'Step 1: ...',
            'body_ar' => 'الخطوة 1: ...',
            'category' => 'getting_started',
            'is_published' => true,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.title', 'Getting Started Guide')
            ->assertJsonPath('data.category', 'getting_started');

        $this->assertDatabaseHas('knowledge_base_articles', ['title' => 'Getting Started Guide']);
    }

    public function test_create_article_auto_generates_slug(): void
    {
        $this->postJson('/api/v2/admin/content/articles', [
            'title' => 'How To Use POS',
            'title_ar' => 'كيف تستخدم النقاط',
            'body' => 'Content',
            'body_ar' => 'محتوى',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'how-to-use-pos');
    }

    public function test_show_article(): void
    {
        $article = KnowledgeBaseArticle::forceCreate([
            'title' => 'Test Article',
            'title_ar' => 'مقال اختبار',
            'slug' => 'test-article',
            'body' => 'Body',
            'body_ar' => 'محتوى',
            'category' => 'pos_usage',
        ]);

        $this->getJson("/api/v2/admin/content/articles/{$article->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Test Article');
    }

    public function test_update_article(): void
    {
        $article = KnowledgeBaseArticle::forceCreate([
            'title' => 'Old',
            'title_ar' => 'قديم',
            'slug' => 'old',
            'body' => 'Old body',
            'body_ar' => 'محتوى قديم',
        ]);

        $this->putJson("/api/v2/admin/content/articles/{$article->id}", [
            'title' => 'Updated',
            'category' => 'billing',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated')
            ->assertJsonPath('data.category', 'billing');
    }

    public function test_delete_article(): void
    {
        $article = KnowledgeBaseArticle::forceCreate([
            'title' => 'Delete Me',
            'title_ar' => 'احذفني',
            'slug' => 'delete-me',
            'body' => 'Body',
            'body_ar' => 'محتوى',
        ]);

        $this->deleteJson("/api/v2/admin/content/articles/{$article->id}")
            ->assertOk();

        $this->assertDatabaseMissing('knowledge_base_articles', ['id' => $article->id]);
    }

    public function test_publish_toggle_article(): void
    {
        $article = KnowledgeBaseArticle::forceCreate([
            'title' => 'Draft',
            'title_ar' => 'مسودة',
            'slug' => 'draft-article',
            'body' => 'Body',
            'body_ar' => 'محتوى',
            'is_published' => false,
        ]);

        $this->postJson("/api/v2/admin/content/articles/{$article->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.is_published', true);
    }

    public function test_list_articles_with_category_filter(): void
    {
        KnowledgeBaseArticle::forceCreate([
            'title' => 'Billing FAQ', 'title_ar' => 'أ', 'slug' => 'billing-faq',
            'body' => 'b', 'body_ar' => 'ب', 'category' => 'billing',
        ]);
        KnowledgeBaseArticle::forceCreate([
            'title' => 'POS Guide', 'title_ar' => 'أ', 'slug' => 'pos-guide',
            'body' => 'b', 'body_ar' => 'ب', 'category' => 'pos_usage',
        ]);

        $this->getJson('/api/v2/admin/content/articles?category=billing')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_list_articles_pagination(): void
    {
        for ($i = 0; $i < 20; $i++) {
            KnowledgeBaseArticle::forceCreate([
                'title' => "Article {$i}", 'title_ar' => 'أ', 'slug' => "article-{$i}",
                'body' => 'b', 'body_ar' => 'ب',
            ]);
        }

        $this->getJson('/api/v2/admin/content/articles?per_page=5')
            ->assertOk()
            ->assertJsonPath('data.total', 20)
            ->assertJsonPath('data.last_page', 4)
            ->assertJsonCount(5, 'data.articles');
    }

    // ═══════════════════════════════════════════════════════════
    //  Platform Announcements - CRUD
    // ═══════════════════════════════════════════════════════════

    public function test_list_announcements_empty(): void
    {
        $this->getJson('/api/v2/admin/content/announcements')
            ->assertOk()
            ->assertJsonPath('data.total', 0);
    }

    public function test_create_announcement(): void
    {
        $this->postJson('/api/v2/admin/content/announcements', [
            'title' => 'Scheduled Maintenance',
            'title_ar' => 'صيانة مجدولة',
            'body' => 'We will be down for maintenance.',
            'body_ar' => 'سيكون هناك صيانة',
            'type' => 'maintenance',
            'is_banner' => true,
            'send_push' => true,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.title', 'Scheduled Maintenance')
            ->assertJsonPath('data.type', 'maintenance')
            ->assertJsonPath('data.is_banner', true)
            ->assertJsonPath('data.send_push', true)
            ->assertJsonPath('data.created_by', $this->admin->id);

        $this->assertDatabaseHas('platform_announcements', ['title' => 'Scheduled Maintenance']);
    }

    public function test_create_announcement_with_schedule(): void
    {
        $this->postJson('/api/v2/admin/content/announcements', [
            'title' => 'Upcoming Feature',
            'body' => 'New feature next week.',
            'type' => 'update',
            'display_start_at' => '2025-01-01 00:00:00',
            'display_end_at' => '2025-01-31 23:59:59',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.title', 'Upcoming Feature');
    }

    public function test_create_announcement_invalid_type(): void
    {
        $this->postJson('/api/v2/admin/content/announcements', [
            'title' => 'Bad Type',
            'body' => 'x',
            'type' => 'invalid_type',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_show_announcement(): void
    {
        $ann = PlatformAnnouncement::forceCreate([
            'title' => 'Test',
            'body' => 'Test body',
            'type' => 'info',
        ]);

        $this->getJson("/api/v2/admin/content/announcements/{$ann->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Test');
    }

    public function test_update_announcement(): void
    {
        $ann = PlatformAnnouncement::forceCreate([
            'title' => 'Old',
            'body' => 'Old body',
            'type' => 'info',
        ]);

        $this->putJson("/api/v2/admin/content/announcements/{$ann->id}", [
            'title' => 'Updated',
            'type' => 'warning',
            'send_email' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated')
            ->assertJsonPath('data.type', 'warning')
            ->assertJsonPath('data.send_email', true);
    }

    public function test_delete_announcement(): void
    {
        $ann = PlatformAnnouncement::forceCreate([
            'title' => 'Delete',
            'body' => 'x',
            'type' => 'info',
        ]);

        $this->deleteJson("/api/v2/admin/content/announcements/{$ann->id}")
            ->assertOk();

        $this->assertDatabaseMissing('platform_announcements', ['id' => $ann->id]);
    }

    public function test_list_announcements_with_type_filter(): void
    {
        PlatformAnnouncement::forceCreate(['title' => 'Info', 'body' => 'x', 'type' => 'info']);
        PlatformAnnouncement::forceCreate(['title' => 'Warning', 'body' => 'x', 'type' => 'warning']);

        $this->getJson('/api/v2/admin/content/announcements?type=warning')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_list_announcements_pagination(): void
    {
        for ($i = 0; $i < 20; $i++) {
            PlatformAnnouncement::forceCreate([
                'title' => "Ann {$i}",
                'body' => 'body',
                'type' => 'info',
            ]);
        }

        $this->getJson('/api/v2/admin/content/announcements?per_page=5')
            ->assertOk()
            ->assertJsonPath('data.total', 20)
            ->assertJsonPath('data.last_page', 4)
            ->assertJsonCount(5, 'data.announcements');
    }

    // ═══════════════════════════════════════════════════════════
    //  Notification Templates - CRUD
    // ═══════════════════════════════════════════════════════════

    public function test_list_templates_empty(): void
    {
        $this->getJson('/api/v2/admin/content/templates')
            ->assertOk()
            ->assertJsonPath('data.total', 0);
    }

    public function test_create_template(): void
    {
        $this->postJson('/api/v2/admin/content/templates', [
            'event_key' => 'order_placed',
            'channel' => 'push',
            'title' => 'New Order #{order_id}',
            'title_ar' => 'طلب جديد #{order_id}',
            'body' => 'You have a new order from {customer_name}.',
            'body_ar' => 'لديك طلب جديد من {customer_name}.',
            'available_variables' => ['order_id', 'customer_name'],
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.event_key', 'order_placed')
            ->assertJsonPath('data.channel', 'push')
            ->assertJsonPath('data.title', 'New Order #{order_id}');

        $this->assertDatabaseHas('notification_templates', ['event_key' => 'order_placed']);
    }

    public function test_create_duplicate_event_channel_fails(): void
    {
        NotificationTemplate::forceCreate([
            'event_key' => 'order_placed',
            'channel' => 'push',
            'title' => 'Existing',
        ]);

        $this->postJson('/api/v2/admin/content/templates', [
            'event_key' => 'order_placed',
            'channel' => 'push',
            'title' => 'Duplicate',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_same_event_different_channel_allowed(): void
    {
        NotificationTemplate::forceCreate([
            'event_key' => 'order_placed',
            'channel' => 'push',
        ]);

        $this->postJson('/api/v2/admin/content/templates', [
            'event_key' => 'order_placed',
            'channel' => 'email',
            'title' => 'Email Template',
        ])
            ->assertStatus(201);
    }

    public function test_create_template_invalid_channel(): void
    {
        $this->postJson('/api/v2/admin/content/templates', [
            'event_key' => 'test',
            'channel' => 'carrier_pigeon',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['channel']);
    }

    public function test_show_template(): void
    {
        $template = NotificationTemplate::forceCreate([
            'event_key' => 'payment_received',
            'channel' => 'sms',
            'title' => 'Payment Received',
        ]);

        $this->getJson("/api/v2/admin/content/templates/{$template->id}")
            ->assertOk()
            ->assertJsonPath('data.event_key', 'payment_received');
    }

    public function test_update_template(): void
    {
        $template = NotificationTemplate::forceCreate([
            'event_key' => 'payment_received',
            'channel' => 'sms',
            'title' => 'Old Title',
        ]);

        $this->putJson("/api/v2/admin/content/templates/{$template->id}", [
            'title' => 'Updated Title',
            'body' => 'New body',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated Title')
            ->assertJsonPath('data.body', 'New body');
    }

    public function test_update_template_duplicate_event_channel_fails(): void
    {
        NotificationTemplate::forceCreate([
            'event_key' => 'order_placed',
            'channel' => 'email',
        ]);

        $template = NotificationTemplate::forceCreate([
            'event_key' => 'order_placed',
            'channel' => 'sms',
        ]);

        // Try to change channel to email (conflict)
        $this->putJson("/api/v2/admin/content/templates/{$template->id}", [
            'channel' => 'email',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_delete_template(): void
    {
        $template = NotificationTemplate::forceCreate([
            'event_key' => 'test_event',
            'channel' => 'push',
        ]);

        $this->deleteJson("/api/v2/admin/content/templates/{$template->id}")
            ->assertOk();

        $this->assertDatabaseMissing('notification_templates', ['id' => $template->id]);
    }

    public function test_toggle_template_active(): void
    {
        $template = NotificationTemplate::forceCreate([
            'event_key' => 'test_event',
            'channel' => 'push',
            'is_active' => true,
        ]);

        // Deactivate
        $this->postJson("/api/v2/admin/content/templates/{$template->id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        // Reactivate
        $this->postJson("/api/v2/admin/content/templates/{$template->id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);
    }

    public function test_list_templates_with_channel_filter(): void
    {
        NotificationTemplate::forceCreate(['event_key' => 'e1', 'channel' => 'push']);
        NotificationTemplate::forceCreate(['event_key' => 'e2', 'channel' => 'email']);
        NotificationTemplate::forceCreate(['event_key' => 'e3', 'channel' => 'push']);

        $this->getJson('/api/v2/admin/content/templates?channel=push')
            ->assertOk()
            ->assertJsonPath('data.total', 2);
    }

    public function test_list_templates_with_search_filter(): void
    {
        NotificationTemplate::forceCreate([
            'event_key' => 'order_placed',
            'channel' => 'push',
            'title' => 'New Order',
        ]);
        NotificationTemplate::forceCreate([
            'event_key' => 'payment_received',
            'channel' => 'push',
            'title' => 'Payment OK',
        ]);

        $this->getJson('/api/v2/admin/content/templates?search=order')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_list_templates_with_active_filter(): void
    {
        NotificationTemplate::forceCreate(['event_key' => 'active', 'channel' => 'push', 'is_active' => true]);
        NotificationTemplate::forceCreate(['event_key' => 'inactive', 'channel' => 'push', 'is_active' => false]);

        $this->getJson('/api/v2/admin/content/templates?is_active=true')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    // ═══════════════════════════════════════════════════════════
    //  Edge Cases & Cross-Cutting
    // ═══════════════════════════════════════════════════════════

    public function test_page_not_found_returns_404(): void
    {
        $this->getJson('/api/v2/admin/content/pages/fake-id')->assertNotFound();
        $this->putJson('/api/v2/admin/content/pages/fake-id', ['title' => 'x'])->assertNotFound();
        $this->deleteJson('/api/v2/admin/content/pages/fake-id')->assertNotFound();
    }

    public function test_article_not_found_returns_404(): void
    {
        $this->getJson('/api/v2/admin/content/articles/fake-id')->assertNotFound();
        $this->putJson('/api/v2/admin/content/articles/fake-id', ['title' => 'x', 'title_ar' => 'x', 'body' => 'x', 'body_ar' => 'x'])->assertNotFound();
        $this->deleteJson('/api/v2/admin/content/articles/fake-id')->assertNotFound();
    }

    public function test_template_not_found_returns_404(): void
    {
        $this->getJson('/api/v2/admin/content/templates/fake-id')->assertNotFound();
        $this->putJson('/api/v2/admin/content/templates/fake-id', ['title' => 'x'])->assertNotFound();
        $this->deleteJson('/api/v2/admin/content/templates/fake-id')->assertNotFound();
    }

    public function test_announcement_not_found_returns_404(): void
    {
        $this->getJson('/api/v2/admin/content/announcements/fake-id')->assertNotFound();
        $this->putJson('/api/v2/admin/content/announcements/fake-id', ['title' => 'x'])->assertNotFound();
        $this->deleteJson('/api/v2/admin/content/announcements/fake-id')->assertNotFound();
    }

    public function test_create_page_validation_requires_title(): void
    {
        $this->postJson('/api/v2/admin/content/pages', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_create_article_validation_requires_bilingual(): void
    {
        $this->postJson('/api/v2/admin/content/articles', [
            'title' => 'English Only',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title_ar', 'body', 'body_ar']);
    }

    public function test_create_announcement_validation_requires_body(): void
    {
        $this->postJson('/api/v2/admin/content/announcements', [
            'title' => 'No Body',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    }

    public function test_create_template_validation_requires_event_and_channel(): void
    {
        $this->postJson('/api/v2/admin/content/templates', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['event_key', 'channel']);
    }

    public function test_announcement_end_before_start_fails(): void
    {
        $this->postJson('/api/v2/admin/content/announcements', [
            'title' => 'Bad Schedule',
            'body' => 'x',
            'display_start_at' => '2025-12-31',
            'display_end_at' => '2025-01-01',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['display_end_at']);
    }

    public function test_page_meta_fields_stored(): void
    {
        $this->postJson('/api/v2/admin/content/pages', [
            'title' => 'SEO Page',
            'meta_title' => 'Best Page',
            'meta_title_ar' => 'أفضل صفحة',
            'meta_description' => 'Description here',
            'meta_description_ar' => 'الوصف هنا',
            'sort_order' => 5,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.meta_title', 'Best Page')
            ->assertJsonPath('data.meta_description', 'Description here')
            ->assertJsonPath('data.sort_order', 5);
    }

    public function test_template_available_variables_stored_as_array(): void
    {
        $this->postJson('/api/v2/admin/content/templates', [
            'event_key' => 'welcome',
            'channel' => 'email',
            'available_variables' => ['user_name', 'store_name', 'plan_name'],
        ])
            ->assertStatus(201);

        $template = NotificationTemplate::where('event_key', 'welcome')->first();
        $this->assertIsArray($template->available_variables);
        $this->assertCount(3, $template->available_variables);
    }

    public function test_announcement_target_filter_stored_as_json(): void
    {
        $this->postJson('/api/v2/admin/content/announcements', [
            'title' => 'Targeted',
            'body' => 'Only for some',
            'target_filter' => ['plan_ids' => ['plan-1', 'plan-2'], 'region' => 'Muscat'],
        ])
            ->assertStatus(201);

        $ann = PlatformAnnouncement::where('title', 'Targeted')->first();
        $this->assertIsArray($ann->target_filter);
        $this->assertEquals('Muscat', $ann->target_filter['region']);
    }
}
