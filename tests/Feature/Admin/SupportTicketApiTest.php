<?php

namespace Tests\Feature\Admin;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\ContentOnboarding\Models\KnowledgeBaseArticle;
use App\Domain\Support\Models\CannedResponse;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Models\SupportTicketMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportTicketApiTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;
    private string $orgId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = AdminUser::forceCreate([
            'name'          => 'Support Admin',
            'email'         => 'support@test.com',
            'password_hash' => bcrypt('password'),
            'is_active'     => true,
        ]);

        Sanctum::actingAs($this->admin, ['*'], 'admin-api');

        $this->orgId = Str::uuid()->toString();
        \Illuminate\Support\Facades\DB::table('organizations')->insert([
            'id'   => $this->orgId,
            'name' => 'Test Org',
        ]);
    }

    // ─── Auth ────────────────────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v2/admin/support/tickets')->assertStatus(401);
    }

    // ═══════════════════════════════════════════════════════════
    //  TICKETS CRUD
    // ═══════════════════════════════════════════════════════════

    public function test_list_tickets_empty(): void
    {
        $this->getJson('/api/v2/admin/support/tickets')
            ->assertOk()
            ->assertJsonPath('message', 'Support tickets retrieved.')
            ->assertJsonPath('data.total', 0);
    }

    public function test_create_ticket(): void
    {
        $this->postJson('/api/v2/admin/support/tickets', [
            'organization_id' => $this->orgId,
            'subject'         => 'ZATCA integration issue',
            'description'     => 'E-invoice fails with timeout error',
            'category'        => 'zatca',
            'priority'        => 'high',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.subject', 'ZATCA integration issue')
            ->assertJsonPath('data.category', 'zatca')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.status', 'open');

        $this->assertDatabaseHas('support_tickets', [
            'subject'  => 'ZATCA integration issue',
            'category' => 'zatca',
        ]);
    }

    public function test_create_ticket_auto_generates_ticket_number(): void
    {
        $response = $this->postJson('/api/v2/admin/support/tickets', [
            'organization_id' => $this->orgId,
            'subject'         => 'Test',
            'description'     => 'Test desc',
            'category'        => 'general',
        ])->assertStatus(201);

        $this->assertStringStartsWith('TKT-', $response->json('data.ticket_number'));
    }

    public function test_create_ticket_validation(): void
    {
        $this->postJson('/api/v2/admin/support/tickets', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['organization_id', 'subject', 'description', 'category']);
    }

    public function test_create_ticket_validates_category(): void
    {
        $this->postJson('/api/v2/admin/support/tickets', [
            'organization_id' => $this->orgId,
            'subject'         => 'Test',
            'description'     => 'Test',
            'category'        => 'invalid_cat',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['category']);
    }

    public function test_show_ticket_with_messages(): void
    {
        $ticket = $this->createTicket();

        SupportTicketMessage::forceCreate([
            'support_ticket_id' => $ticket->id,
            'sender_type'       => 'admin',
            'sender_id'         => $this->admin->id,
            'message_text'      => 'Looking into this',
            'sent_at'           => now(),
        ]);

        $this->getJson("/api/v2/admin/support/tickets/{$ticket->id}")
            ->assertOk()
            ->assertJsonPath('data.subject', $ticket->subject)
            ->assertJsonPath('data.support_ticket_messages.0.message_text', 'Looking into this');
    }

    public function test_show_ticket_not_found(): void
    {
        $this->getJson('/api/v2/admin/support/tickets/nonexistent')
            ->assertNotFound();
    }

    public function test_update_ticket(): void
    {
        $ticket = $this->createTicket();

        $this->putJson("/api/v2/admin/support/tickets/{$ticket->id}", [
            'priority' => 'critical',
            'subject'  => 'Updated subject',
        ])
            ->assertOk()
            ->assertJsonPath('data.priority', 'critical')
            ->assertJsonPath('data.subject', 'Updated subject');
    }

    public function test_update_ticket_not_found(): void
    {
        $this->putJson('/api/v2/admin/support/tickets/nonexistent', ['subject' => 'X'])
            ->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════
    //  TICKET ASSIGNMENT & STATUS
    // ═══════════════════════════════════════════════════════════

    public function test_assign_ticket(): void
    {
        $ticket = $this->createTicket();
        $other = AdminUser::forceCreate([
            'name'          => 'Agent',
            'email'         => 'agent@test.com',
            'password_hash' => bcrypt('pass'),
            'is_active'     => true,
        ]);

        $this->postJson("/api/v2/admin/support/tickets/{$ticket->id}/assign", [
            'assigned_to' => $other->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.assigned_to', $other->id);
    }

    public function test_assign_ticket_requires_uuid(): void
    {
        $ticket = $this->createTicket();

        $this->postJson("/api/v2/admin/support/tickets/{$ticket->id}/assign", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['assigned_to']);
    }

    public function test_change_status_to_resolved(): void
    {
        $ticket = $this->createTicket();

        $this->postJson("/api/v2/admin/support/tickets/{$ticket->id}/status", [
            'status' => 'resolved',
        ])
            ->assertOk();

        $ticket->refresh();
        $this->assertEquals('resolved', $ticket->status->value);
        $this->assertNotNull($ticket->resolved_at);
    }

    public function test_change_status_to_closed(): void
    {
        $ticket = $this->createTicket();

        $this->postJson("/api/v2/admin/support/tickets/{$ticket->id}/status", [
            'status' => 'closed',
        ])
            ->assertOk();

        $ticket->refresh();
        $this->assertEquals('closed', $ticket->status->value);
        $this->assertNotNull($ticket->closed_at);
    }

    public function test_change_status_validates(): void
    {
        $ticket = $this->createTicket();

        $this->postJson("/api/v2/admin/support/tickets/{$ticket->id}/status", [
            'status' => 'invalid_status',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_change_status_not_found(): void
    {
        $this->postJson('/api/v2/admin/support/tickets/nonexistent/status', ['status' => 'closed'])
            ->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════
    //  TICKET FILTERS
    // ═══════════════════════════════════════════════════════════

    public function test_filter_tickets_by_status(): void
    {
        $this->createTicket(['status' => 'open']);
        $this->createTicket(['status' => 'closed', 'closed_at' => now()]);

        $this->getJson('/api/v2/admin/support/tickets?status=open')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_filter_tickets_by_priority(): void
    {
        $this->createTicket(['priority' => 'low']);
        $this->createTicket(['priority' => 'critical']);

        $this->getJson('/api/v2/admin/support/tickets?priority=critical')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_filter_tickets_by_category(): void
    {
        $this->createTicket(['category' => 'billing']);
        $this->createTicket(['category' => 'technical']);

        $this->getJson('/api/v2/admin/support/tickets?category=billing')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_search_tickets(): void
    {
        $this->createTicket(['subject' => 'Payment gateway error']);
        $this->createTicket(['subject' => 'Menu display issue']);

        $this->getJson('/api/v2/admin/support/tickets?search=gateway')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_filter_tickets_by_assigned_to(): void
    {
        $this->createTicket(['assigned_to' => $this->admin->id]);
        $other = AdminUser::forceCreate([
            'name' => 'Other', 'email' => 'other2@test.com',
            'password_hash' => bcrypt('p'), 'is_active' => true,
        ]);
        $this->createTicket(['assigned_to' => $other->id]);

        $this->getJson("/api/v2/admin/support/tickets?assigned_to={$this->admin->id}")
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    // ═══════════════════════════════════════════════════════════
    //  MESSAGES
    // ═══════════════════════════════════════════════════════════

    public function test_list_messages(): void
    {
        $ticket = $this->createTicket();

        SupportTicketMessage::forceCreate([
            'support_ticket_id' => $ticket->id,
            'sender_type'       => 'admin',
            'sender_id'         => $this->admin->id,
            'message_text'      => 'First reply',
            'sent_at'           => now(),
        ]);

        $this->getJson("/api/v2/admin/support/tickets/{$ticket->id}/messages")
            ->assertOk()
            ->assertJsonPath('message', 'Messages retrieved.')
            ->assertJsonCount(1, 'data');
    }

    public function test_add_message(): void
    {
        $ticket = $this->createTicket();

        $this->postJson("/api/v2/admin/support/tickets/{$ticket->id}/messages", [
            'message_text' => 'We are investigating',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.message_text', 'We are investigating')
            ->assertJsonPath('data.sender_type', 'admin');
    }

    public function test_add_message_sets_first_response(): void
    {
        $ticket = $this->createTicket();
        $this->assertNull($ticket->first_response_at);

        $this->postJson("/api/v2/admin/support/tickets/{$ticket->id}/messages", [
            'message_text' => 'First response!',
        ])->assertStatus(201);

        $ticket->refresh();
        $this->assertNotNull($ticket->first_response_at);
    }

    public function test_add_internal_note(): void
    {
        $ticket = $this->createTicket();

        $this->postJson("/api/v2/admin/support/tickets/{$ticket->id}/messages", [
            'message_text'     => 'Internal: escalate to engineering',
            'is_internal_note' => true,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.is_internal_note', true);
    }

    public function test_add_message_validation(): void
    {
        $ticket = $this->createTicket();

        $this->postJson("/api/v2/admin/support/tickets/{$ticket->id}/messages", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['message_text']);
    }

    public function test_add_message_ticket_not_found(): void
    {
        $this->postJson('/api/v2/admin/support/tickets/nonexistent/messages', [
            'message_text' => 'test',
        ])->assertNotFound();
    }

    public function test_list_messages_ticket_not_found(): void
    {
        $this->getJson('/api/v2/admin/support/tickets/nonexistent/messages')
            ->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════
    //  CANNED RESPONSES
    // ═══════════════════════════════════════════════════════════

    public function test_list_canned_responses(): void
    {
        CannedResponse::forceCreate([
            'title'      => 'Greeting',
            'body'       => 'Hello, how can I help?',
            'body_ar'    => 'مرحبا، كيف يمكنني مساعدتك؟',
            'is_active'  => true,
            'created_at' => now(),
        ]);

        $this->getJson('/api/v2/admin/support/canned-responses')
            ->assertOk()
            ->assertJsonPath('message', 'Canned responses retrieved.')
            ->assertJsonCount(1, 'data');
    }

    public function test_create_canned_response(): void
    {
        $this->postJson('/api/v2/admin/support/canned-responses', [
            'title'    => 'Billing help',
            'body'     => 'For billing inquiries, please check...',
            'body_ar'  => 'لاستفسارات الفواتير...',
            'shortcut' => '/billing',
            'category' => 'billing',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.title', 'Billing help')
            ->assertJsonPath('data.created_by', $this->admin->id);
    }

    public function test_create_canned_response_requires_bilingual(): void
    {
        $this->postJson('/api/v2/admin/support/canned-responses', [
            'title' => 'Test',
            'body'  => 'English only',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['body_ar']);
    }

    public function test_show_canned_response(): void
    {
        $cr = CannedResponse::forceCreate([
            'title'      => 'Thanks',
            'body'       => 'Thank you for contacting us',
            'body_ar'    => 'شكرا لتواصلك معنا',
            'is_active'  => true,
            'created_at' => now(),
        ]);

        $this->getJson("/api/v2/admin/support/canned-responses/{$cr->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Thanks');
    }

    public function test_show_canned_response_not_found(): void
    {
        $this->getJson('/api/v2/admin/support/canned-responses/nonexistent')
            ->assertNotFound();
    }

    public function test_update_canned_response(): void
    {
        $cr = CannedResponse::forceCreate([
            'title'      => 'Old',
            'body'       => 'Old body',
            'body_ar'    => 'قديم',
            'is_active'  => true,
            'created_at' => now(),
        ]);

        $this->putJson("/api/v2/admin/support/canned-responses/{$cr->id}", [
            'title' => 'Updated',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated');
    }

    public function test_delete_canned_response(): void
    {
        $cr = CannedResponse::forceCreate([
            'title'      => 'Delete me',
            'body'       => 'Will be deleted',
            'body_ar'    => 'سيتم حذفه',
            'is_active'  => true,
            'created_at' => now(),
        ]);

        $this->deleteJson("/api/v2/admin/support/canned-responses/{$cr->id}")
            ->assertOk();

        $this->assertDatabaseMissing('canned_responses', ['id' => $cr->id]);
    }

    public function test_toggle_canned_response(): void
    {
        $cr = CannedResponse::forceCreate([
            'title'      => 'Toggle me',
            'body'       => 'Body',
            'body_ar'    => 'عربي',
            'is_active'  => true,
            'created_at' => now(),
        ]);

        $this->postJson("/api/v2/admin/support/canned-responses/{$cr->id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->postJson("/api/v2/admin/support/canned-responses/{$cr->id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);
    }

    public function test_filter_canned_responses_by_category(): void
    {
        CannedResponse::forceCreate([
            'title' => 'A', 'body' => 'B', 'body_ar' => 'ب',
            'category' => 'billing', 'is_active' => true, 'created_at' => now(),
        ]);
        CannedResponse::forceCreate([
            'title' => 'C', 'body' => 'D', 'body_ar' => 'د',
            'category' => 'technical', 'is_active' => true, 'created_at' => now(),
        ]);

        $this->getJson('/api/v2/admin/support/canned-responses?category=billing')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_search_canned_responses(): void
    {
        CannedResponse::forceCreate([
            'title' => 'Greeting', 'body' => 'Hello there', 'body_ar' => 'مرحبا',
            'is_active' => true, 'created_at' => now(),
        ]);

        $this->getJson('/api/v2/admin/support/canned-responses?search=greeting')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // ═══════════════════════════════════════════════════════════
    //  PAGINATION
    // ═══════════════════════════════════════════════════════════

    public function test_tickets_pagination(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createTicket();
        }

        $this->getJson('/api/v2/admin/support/tickets?per_page=2')
            ->assertOk()
            ->assertJsonPath('data.per_page', 2)
            ->assertJsonPath('data.total', 5);
    }

    // ─── Helpers ─────────────────────────────────────────────

    private function createTicket(array $overrides = []): SupportTicket
    {
        return SupportTicket::forceCreate(array_merge([
            'ticket_number'   => 'TKT-' . strtoupper(Str::random(8)),
            'organization_id' => $this->orgId,
            'category'        => 'general',
            'priority'        => 'medium',
            'status'          => 'open',
            'subject'         => 'Test ticket ' . Str::random(4),
            'description'     => 'Test description',
        ], $overrides));
    }

    // ═══════════════════════════════════════════════════════════
    //  ADMIN STATS
    // ═══════════════════════════════════════════════════════════

    public function test_admin_stats_endpoint(): void
    {
        $this->createTicket(['status' => 'open']);
        $this->createTicket(['status' => 'in_progress']);
        $this->createTicket(['status' => 'resolved', 'resolved_at' => now()]);

        $this->getJson('/api/v2/admin/support/stats')
            ->assertOk()
            ->assertJsonStructure(['data' => ['total', 'open', 'in_progress', 'unresolved', 'sla_breached', 'resolved_today']]);
    }

    // ═══════════════════════════════════════════════════════════
    //  HARDWARE CATEGORY
    // ═══════════════════════════════════════════════════════════

    public function test_create_ticket_with_hardware_category(): void
    {
        $this->postJson('/api/v2/admin/support/tickets', [
            'organization_id' => $this->orgId,
            'subject'         => 'POS terminal broken',
            'description'     => 'Hardware malfunction',
            'category'        => 'hardware',
            'priority'        => 'high',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.category', 'hardware');
    }

    // ═══════════════════════════════════════════════════════════
    //  SLA BREACH FILTER
    // ═══════════════════════════════════════════════════════════

    public function test_filter_tickets_by_sla_breached(): void
    {
        $this->createTicket([
            'status'          => 'open',
            'sla_deadline_at' => now()->subHour(),
        ]);
        $this->createTicket([
            'status'          => 'open',
            'sla_deadline_at' => now()->addDay(),
        ]);

        $this->getJson('/api/v2/admin/support/tickets?sla_breached=1')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    // ═══════════════════════════════════════════════════════════
    //  KNOWLEDGE BASE ARTICLES
    // ═══════════════════════════════════════════════════════════

    public function test_list_kb_articles(): void
    {
        KnowledgeBaseArticle::forceCreate([
            'title'        => 'Getting Started Guide',
            'title_ar'     => 'دليل البدء',
            'slug'         => 'getting-started',
            'body'         => 'Welcome to the POS system',
            'body_ar'      => 'مرحبا بكم في نظام نقطة البيع',
            'category'     => 'getting_started',
            'is_published' => true,
            'sort_order'   => 1,
        ]);

        $this->getJson('/api/v2/admin/support/kb')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_create_kb_article(): void
    {
        $this->postJson('/api/v2/admin/support/kb', [
            'title'        => 'How to manage inventory',
            'title_ar'     => 'كيفية إدارة المخزون',
            'slug'         => 'manage-inventory',
            'body'         => 'Step 1: Go to inventory...',
            'body_ar'      => 'الخطوة 1: اذهب إلى المخزون...',
            'category'     => 'inventory',
            'is_published' => true,
            'sort_order'   => 5,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.title', 'How to manage inventory')
            ->assertJsonPath('data.slug', 'manage-inventory');

        $this->assertDatabaseHas('knowledge_base_articles', ['slug' => 'manage-inventory']);
    }

    public function test_create_kb_article_validation(): void
    {
        $this->postJson('/api/v2/admin/support/kb', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'title_ar', 'slug', 'body', 'body_ar', 'category']);
    }

    public function test_create_kb_article_validates_unique_slug(): void
    {
        KnowledgeBaseArticle::forceCreate([
            'title' => 'A', 'title_ar' => 'أ', 'slug' => 'existing-slug',
            'body' => 'B', 'body_ar' => 'ب', 'category' => 'billing',
            'is_published' => true, 'sort_order' => 0,
        ]);

        $this->postJson('/api/v2/admin/support/kb', [
            'title' => 'C', 'title_ar' => 'ج', 'slug' => 'existing-slug',
            'body' => 'D', 'body_ar' => 'د', 'category' => 'billing',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_show_kb_article(): void
    {
        $article = KnowledgeBaseArticle::forceCreate([
            'title' => 'Delivery Setup', 'title_ar' => 'إعداد التوصيل',
            'slug' => 'delivery-setup', 'body' => 'How to set up delivery',
            'body_ar' => 'كيفية إعداد التوصيل', 'category' => 'delivery',
            'is_published' => true, 'sort_order' => 0,
        ]);

        $this->getJson("/api/v2/admin/support/kb/{$article->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Delivery Setup');
    }

    public function test_show_kb_article_not_found(): void
    {
        $this->getJson('/api/v2/admin/support/kb/nonexistent')
            ->assertNotFound();
    }

    public function test_update_kb_article(): void
    {
        $article = KnowledgeBaseArticle::forceCreate([
            'title' => 'Old Title', 'title_ar' => 'قديم',
            'slug' => 'old-title', 'body' => 'Old body',
            'body_ar' => 'قديم', 'category' => 'billing',
            'is_published' => false, 'sort_order' => 0,
        ]);

        $this->putJson("/api/v2/admin/support/kb/{$article->id}", [
            'title'        => 'Updated Title',
            'is_published' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated Title')
            ->assertJsonPath('data.is_published', true);
    }

    public function test_delete_kb_article(): void
    {
        $article = KnowledgeBaseArticle::forceCreate([
            'title' => 'Delete Me', 'title_ar' => 'حذفني',
            'slug' => 'delete-me', 'body' => 'B', 'body_ar' => 'ب',
            'category' => 'troubleshooting', 'is_published' => false,
            'sort_order' => 0,
        ]);

        $this->deleteJson("/api/v2/admin/support/kb/{$article->id}")
            ->assertOk();

        $this->assertDatabaseMissing('knowledge_base_articles', ['id' => $article->id]);
    }

    public function test_filter_kb_articles_by_category(): void
    {
        KnowledgeBaseArticle::forceCreate([
            'title' => 'A', 'title_ar' => 'أ', 'slug' => 'a-slug',
            'body' => 'B', 'body_ar' => 'ب', 'category' => 'billing',
            'is_published' => true, 'sort_order' => 0,
        ]);
        KnowledgeBaseArticle::forceCreate([
            'title' => 'C', 'title_ar' => 'ج', 'slug' => 'c-slug',
            'body' => 'D', 'body_ar' => 'د', 'category' => 'inventory',
            'is_published' => true, 'sort_order' => 1,
        ]);

        $this->getJson('/api/v2/admin/support/kb?category=billing')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_filter_kb_articles_by_published(): void
    {
        KnowledgeBaseArticle::forceCreate([
            'title' => 'Published', 'title_ar' => 'منشور', 'slug' => 'pub',
            'body' => 'B', 'body_ar' => 'ب', 'category' => 'billing',
            'is_published' => true, 'sort_order' => 0,
        ]);
        KnowledgeBaseArticle::forceCreate([
            'title' => 'Draft', 'title_ar' => 'مسودة', 'slug' => 'draft',
            'body' => 'D', 'body_ar' => 'د', 'category' => 'billing',
            'is_published' => false, 'sort_order' => 1,
        ]);

        $this->getJson('/api/v2/admin/support/kb?is_published=true')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
