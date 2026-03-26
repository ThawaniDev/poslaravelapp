<?php

namespace Tests\Feature\Domain\Support;

use App\Domain\Auth\Models\User;
use App\Domain\ContentOnboarding\Models\KnowledgeBaseArticle;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Models\SupportTicketMessage;
use App\Domain\Support\Enums\TicketStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Store $store;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::create([
            'name'          => 'Test Org',
            'business_type' => 'grocery',
            'country'       => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $org->id,
            'name'            => 'Test Store',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
        ]);

        $this->owner = User::create([
            'name'            => 'Owner',
            'email'           => 'owner@support-test.com',
            'password_hash'   => bcrypt('password'),
            'store_id'        => $this->store->id,
            'organization_id' => $org->id,
            'role'            => 'owner',
            'is_active'       => true,
        ]);

        $this->token = $this->owner->createToken('test', ['*'])->plainTextToken;
    }

    // ─── Authentication ───────────────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v2/support/stats')->assertUnauthorized();
        $this->getJson('/api/v2/support/tickets')->assertUnauthorized();
    }

    // ─── Stats ────────────────────────────────────────────────────

    public function test_can_get_stats(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/support/stats');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data' => ['total', 'open', 'in_progress', 'resolved', 'closed']]);
    }

    public function test_stats_counts_are_correct(): void
    {
        SupportTicket::create([
            'ticket_number' => 'TKT-TEST0001',
            'user_id'       => $this->owner->id,
            'store_id'      => $this->store->id,
            'organization_id' => $this->store->organization_id,
            'category'      => 'billing',
            'priority'      => 'medium',
            'status'        => TicketStatus::Open,
            'subject'       => 'Test ticket',
            'description'   => 'Description',
        ]);

        SupportTicket::create([
            'ticket_number' => 'TKT-TEST0002',
            'user_id'       => $this->owner->id,
            'store_id'      => $this->store->id,
            'organization_id' => $this->store->organization_id,
            'category'      => 'technical',
            'priority'      => 'high',
            'status'        => TicketStatus::Closed,
            'subject'       => 'Closed ticket',
            'description'   => 'Already resolved',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/support/stats');

        $response->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.open', 1)
            ->assertJsonPath('data.closed', 1);
    }

    // ─── List Tickets ─────────────────────────────────────────────

    public function test_can_list_tickets(): void
    {
        SupportTicket::create([
            'ticket_number' => 'TKT-LIST0001',
            'user_id'       => $this->owner->id,
            'store_id'      => $this->store->id,
            'organization_id' => $this->store->organization_id,
            'category'      => 'billing',
            'priority'      => 'low',
            'status'        => TicketStatus::Open,
            'subject'       => 'Billing issue',
            'description'   => 'Need help with invoice',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/support/tickets');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_can_filter_tickets_by_status(): void
    {
        SupportTicket::create([
            'ticket_number' => 'TKT-FILT0001',
            'user_id'       => $this->owner->id,
            'store_id'      => $this->store->id,
            'organization_id' => $this->store->organization_id,
            'category'      => 'billing',
            'priority'      => 'low',
            'status'        => TicketStatus::Open,
            'subject'       => 'Open ticket',
            'description'   => 'Desc',
        ]);

        SupportTicket::create([
            'ticket_number' => 'TKT-FILT0002',
            'user_id'       => $this->owner->id,
            'store_id'      => $this->store->id,
            'organization_id' => $this->store->organization_id,
            'category'      => 'billing',
            'priority'      => 'low',
            'status'        => TicketStatus::Closed,
            'subject'       => 'Closed ticket',
            'description'   => 'Desc',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/support/tickets?status=open');

        $response->assertOk();
    }

    // ─── Create Ticket ────────────────────────────────────────────

    public function test_can_create_ticket(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/support/tickets', [
                'category'    => 'billing',
                'priority'    => 'high',
                'subject'     => 'Invoice not generated',
                'description' => 'My monthly invoice was not generated properly.',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('support_tickets', [
            'user_id'  => $this->owner->id,
            'store_id' => $this->store->id,
            'category' => 'billing',
            'priority' => 'high',
            'subject'  => 'Invoice not generated',
        ]);
    }

    public function test_create_ticket_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/support/tickets', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['category', 'subject', 'description']);
    }

    public function test_create_ticket_validates_category(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/support/tickets', [
                'category'    => 'invalid_category',
                'subject'     => 'Test',
                'description' => 'Test desc',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);
    }

    public function test_create_ticket_generates_ticket_number(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/support/tickets', [
                'category'    => 'technical',
                'subject'     => 'Tech issue',
                'description' => 'Something broke.',
            ]);

        $response->assertCreated();

        $ticket = SupportTicket::where('user_id', $this->owner->id)->first();
        $this->assertNotNull($ticket);
        $this->assertStringStartsWith('TKT-', $ticket->ticket_number);
    }

    // ─── Show Ticket ──────────────────────────────────────────────

    public function test_can_show_ticket(): void
    {
        $ticket = SupportTicket::create([
            'ticket_number' => 'TKT-SHOW0001',
            'user_id'       => $this->owner->id,
            'store_id'      => $this->store->id,
            'organization_id' => $this->store->organization_id,
            'category'      => 'general',
            'priority'      => 'medium',
            'status'        => TicketStatus::Open,
            'subject'       => 'General question',
            'description'   => 'How do I...?',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/support/tickets/{$ticket->id}");

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_show_returns_404_for_other_users_ticket(): void
    {
        $otherOrg = Organization::create([
            'name'          => 'Other Org',
            'business_type' => 'grocery',
            'country'       => 'OM',
        ]);

        $otherStore = Store::create([
            'organization_id' => $otherOrg->id,
            'name'            => 'Other Store',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
        ]);

        $otherUser = User::create([
            'name'            => 'Other',
            'email'           => 'other@support-test.com',
            'password_hash'   => bcrypt('password'),
            'store_id'        => $otherStore->id,
            'organization_id' => $otherOrg->id,
            'role'            => 'owner',
            'is_active'       => true,
        ]);

        $ticket = SupportTicket::create([
            'ticket_number' => 'TKT-OTHR0001',
            'user_id'       => $otherUser->id,
            'store_id'      => $otherStore->id,
            'organization_id' => $otherOrg->id,
            'category'      => 'billing',
            'priority'      => 'low',
            'status'        => TicketStatus::Open,
            'subject'       => 'Their issue',
            'description'   => 'Not mine',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/support/tickets/{$ticket->id}");

        $response->assertNotFound();
    }

    // ─── Add Message ──────────────────────────────────────────────

    public function test_can_add_message_to_ticket(): void
    {
        $ticket = SupportTicket::create([
            'ticket_number' => 'TKT-MSG00001',
            'user_id'       => $this->owner->id,
            'store_id'      => $this->store->id,
            'organization_id' => $this->store->organization_id,
            'category'      => 'general',
            'priority'      => 'medium',
            'status'        => TicketStatus::Open,
            'subject'       => 'Question',
            'description'   => 'Hello',
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/support/tickets/{$ticket->id}/messages", [
                'message' => 'Can you help me with this?',
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('support_ticket_messages', [
            'support_ticket_id' => $ticket->id,
            'sender_type'       => 'provider',
            'sender_id'         => $this->owner->id,
        ]);
    }

    public function test_add_message_validates_message_field(): void
    {
        $ticket = SupportTicket::create([
            'ticket_number' => 'TKT-VALM0001',
            'user_id'       => $this->owner->id,
            'store_id'      => $this->store->id,
            'organization_id' => $this->store->organization_id,
            'category'      => 'general',
            'priority'      => 'medium',
            'status'        => TicketStatus::Open,
            'subject'       => 'Validation test',
            'description'   => 'Desc',
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/support/tickets/{$ticket->id}/messages", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['message']);
    }

    // ─── Close Ticket ─────────────────────────────────────────────

    public function test_can_close_ticket(): void
    {
        $ticket = SupportTicket::create([
            'ticket_number' => 'TKT-CLSE0001',
            'user_id'       => $this->owner->id,
            'store_id'      => $this->store->id,
            'organization_id' => $this->store->organization_id,
            'category'      => 'general',
            'priority'      => 'medium',
            'status'        => TicketStatus::Open,
            'subject'       => 'To close',
            'description'   => 'Will be closed',
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/support/tickets/{$ticket->id}/close");

        $response->assertOk();

        $ticket->refresh();
        $this->assertEquals('closed', $ticket->status->value);
    }

    public function test_close_returns_404_for_nonexistent_ticket(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000000';

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/support/tickets/{$fakeId}/close");

        $response->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════
    //  HARDWARE CATEGORY
    // ═══════════════════════════════════════════════════════════

    public function test_can_create_ticket_with_hardware_category(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/support/tickets', [
                'category'    => 'hardware',
                'subject'     => 'POS terminal not working',
                'description' => 'Screen is unresponsive.',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('support_tickets', [
            'category' => 'hardware',
            'subject'  => 'POS terminal not working',
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  INTERNAL NOTES EXCLUSION
    // ═══════════════════════════════════════════════════════════

    public function test_show_ticket_excludes_internal_notes(): void
    {
        $ticket = SupportTicket::create([
            'ticket_number'   => 'TKT-INTR0001',
            'user_id'         => $this->owner->id,
            'store_id'        => $this->store->id,
            'organization_id' => $this->store->organization_id,
            'category'        => 'general',
            'priority'        => 'medium',
            'status'          => TicketStatus::Open,
            'subject'         => 'Test internal notes',
            'description'     => 'Desc',
        ]);

        // Provider message (visible)
        SupportTicketMessage::forceCreate([
            'support_ticket_id' => $ticket->id,
            'sender_type'       => 'provider',
            'sender_id'         => $this->owner->id,
            'message_text'      => 'Hello, I need help',
            'is_internal_note'  => false,
            'sent_at'           => now(),
        ]);

        // Admin internal note (should be hidden)
        SupportTicketMessage::forceCreate([
            'support_ticket_id' => $ticket->id,
            'sender_type'       => 'admin',
            'sender_id'         => '00000000-0000-0000-0000-000000000001',
            'message_text'      => 'Internal: escalate to engineering',
            'is_internal_note'  => true,
            'sent_at'           => now(),
        ]);

        // Admin public reply (visible)
        SupportTicketMessage::forceCreate([
            'support_ticket_id' => $ticket->id,
            'sender_type'       => 'admin',
            'sender_id'         => '00000000-0000-0000-0000-000000000001',
            'message_text'      => 'We are looking into this',
            'is_internal_note'  => false,
            'sent_at'           => now(),
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/support/tickets/{$ticket->id}");

        $response->assertOk();
        $messages = $response->json('data.messages');
        $this->assertNotNull($messages, 'Messages key should exist in response');
        $this->assertCount(2, $messages);

        $messageTexts = array_column($messages, 'message_text');
        $this->assertContains('Hello, I need help', $messageTexts);
        $this->assertContains('We are looking into this', $messageTexts);
        $this->assertNotContains('Internal: escalate to engineering', $messageTexts);
    }

    // ═══════════════════════════════════════════════════════════
    //  KNOWLEDGE BASE (Provider-facing)
    // ═══════════════════════════════════════════════════════════

    public function test_can_list_published_kb_articles(): void
    {
        KnowledgeBaseArticle::forceCreate([
            'title' => 'Published Guide', 'title_ar' => 'دليل منشور',
            'slug' => 'published-guide', 'body' => 'Content',
            'body_ar' => 'محتوى', 'category' => 'getting_started',
            'is_published' => true, 'sort_order' => 1,
        ]);

        KnowledgeBaseArticle::forceCreate([
            'title' => 'Draft Article', 'title_ar' => 'مسودة',
            'slug' => 'draft-article', 'body' => 'Draft content',
            'body_ar' => 'مسودة', 'category' => 'billing',
            'is_published' => false, 'sort_order' => 2,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/support/kb');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Published Guide', $response->json('data.0.title'));
    }

    public function test_can_filter_kb_articles_by_category(): void
    {
        KnowledgeBaseArticle::forceCreate([
            'title' => 'POS Guide', 'title_ar' => 'دليل',
            'slug' => 'pos-guide', 'body' => 'B', 'body_ar' => 'ب',
            'category' => 'pos_usage', 'is_published' => true, 'sort_order' => 0,
        ]);
        KnowledgeBaseArticle::forceCreate([
            'title' => 'Billing FAQ', 'title_ar' => 'أسئلة',
            'slug' => 'billing-faq', 'body' => 'B', 'body_ar' => 'ب',
            'category' => 'billing', 'is_published' => true, 'sort_order' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/support/kb?category=pos_usage');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_can_search_kb_articles(): void
    {
        KnowledgeBaseArticle::forceCreate([
            'title' => 'How to set up delivery', 'title_ar' => 'التوصيل',
            'slug' => 'delivery-setup', 'body' => 'Configure Talabat...',
            'body_ar' => 'إعداد طلبات', 'category' => 'delivery',
            'is_published' => true, 'sort_order' => 0,
        ]);
        KnowledgeBaseArticle::forceCreate([
            'title' => 'Inventory tips', 'title_ar' => 'نصائح المخزون',
            'slug' => 'inventory-tips', 'body' => 'Stock management...',
            'body_ar' => 'إدارة المخزون', 'category' => 'inventory',
            'is_published' => true, 'sort_order' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/support/kb?search=delivery');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_can_show_kb_article_by_slug(): void
    {
        KnowledgeBaseArticle::forceCreate([
            'title' => 'Getting Started', 'title_ar' => 'البدء',
            'slug' => 'getting-started', 'body' => 'Welcome!',
            'body_ar' => 'مرحبا!', 'category' => 'getting_started',
            'is_published' => true, 'sort_order' => 0,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/support/kb/getting-started');

        $response->assertOk()
            ->assertJsonPath('data.title', 'Getting Started')
            ->assertJsonPath('data.body', 'Welcome!');
    }

    public function test_kb_show_returns_404_for_unpublished(): void
    {
        KnowledgeBaseArticle::forceCreate([
            'title' => 'Draft', 'title_ar' => 'مسودة',
            'slug' => 'draft-only', 'body' => 'B', 'body_ar' => 'ب',
            'category' => 'billing', 'is_published' => false, 'sort_order' => 0,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v2/support/kb/draft-only')
            ->assertNotFound();
    }

    public function test_kb_show_returns_404_for_nonexistent(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v2/support/kb/nonexistent-slug')
            ->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════
    //  SLA AUTO-CALCULATION
    // ═══════════════════════════════════════════════════════════

    public function test_create_ticket_auto_sets_sla_deadline(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/support/tickets', [
                'category'    => 'technical',
                'priority'    => 'critical',
                'subject'     => 'System down',
                'description' => 'Everything is broken',
            ]);

        $response->assertCreated();

        $ticket = SupportTicket::where('subject', 'System down')->first();
        $this->assertNotNull($ticket);
        $this->assertNotNull($ticket->sla_deadline_at);
    }

    // ═══════════════════════════════════════════════════════════
    //  AUTO-REOPEN ON MESSAGE
    // ═══════════════════════════════════════════════════════════

    public function test_adding_message_to_resolved_ticket_reopens_it(): void
    {
        $ticket = SupportTicket::create([
            'ticket_number'   => 'TKT-REOPEN01',
            'user_id'         => $this->owner->id,
            'store_id'        => $this->store->id,
            'organization_id' => $this->store->organization_id,
            'category'        => 'general',
            'priority'        => 'medium',
            'status'          => TicketStatus::Resolved,
            'subject'         => 'Resolved ticket',
            'description'     => 'Was resolved',
            'resolved_at'     => now(),
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v2/support/tickets/{$ticket->id}/messages", [
                'message' => 'Actually the issue is back',
            ])
            ->assertCreated();

        $ticket->refresh();
        $this->assertEquals('open', $ticket->status->value);
    }
}
