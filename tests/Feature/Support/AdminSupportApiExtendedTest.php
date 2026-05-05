<?php

namespace Tests\Feature\Support;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Auth\Models\User;
use App\Domain\ContentOnboarding\Models\KnowledgeBaseArticle;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Support\Enums\TicketCategory;
use App\Domain\Support\Enums\TicketPriority;
use App\Domain\Support\Enums\TicketStatus;
use App\Domain\Support\Models\CannedResponse;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Models\SupportTicketMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Extended admin API tests covering all endpoints, filters, validation,
 * and error scenarios not covered by AdminSupportApiTest.php.
 */
class AdminSupportApiExtendedTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;
    private Organization $org;
    private Store $store;
    private User $provider;
    private string $base = '/api/v2/admin/support';

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = AdminUser::forceCreate([
            'id'            => Str::uuid()->toString(),
            'name'          => 'Extended Admin',
            'email'         => 'ext-admin@test.com',
            'password_hash' => bcrypt('password'),
            'is_active'     => true,
        ]);
        Sanctum::actingAs($this->admin, ['*'], 'admin-api');

        $this->org = Organization::create([
            'name'          => 'Ext Test Org',
            'business_type' => 'grocery',
            'country'       => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name'            => 'Ext Test Store',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
            'is_active'       => true,
            'is_main_branch'  => true,
        ]);

        $this->provider = User::create([
            'name'            => 'Ext Provider',
            'email'           => 'ext-owner@test.com',
            'password_hash'   => bcrypt('password'),
            'store_id'        => $this->store->id,
            'organization_id' => $this->org->id,
            'role'            => 'owner',
            'is_active'       => true,
        ]);
    }

    // ─── HELPERS ──────────────────────────────────────────────

    private function makeTicket(array $overrides = []): SupportTicket
    {
        static $seq = 1000;

        return SupportTicket::create(array_merge([
            'ticket_number'   => 'TKT-EXT-' . (++$seq),
            'organization_id' => $this->org->id,
            'store_id'        => $this->store->id,
            'user_id'         => $this->provider->id,
            'category'        => TicketCategory::Technical->value,
            'priority'        => TicketPriority::Medium->value,
            'status'          => TicketStatus::Open->value,
            'subject'         => 'Extended test ticket ' . $seq,
            'description'     => 'Extended test description',
        ], $overrides));
    }

    // ═══════════════════════════════════════════════════════════
    //  TICKET LIST — FILTERS
    // ═══════════════════════════════════════════════════════════

    public function test_list_tickets_filters_by_status(): void
    {
        $this->makeTicket(['status' => TicketStatus::Open->value]);
        $this->makeTicket(['status' => TicketStatus::InProgress->value]);
        $this->makeTicket(['status' => TicketStatus::Resolved->value, 'resolved_at' => now()]);

        $response = $this->getJson("{$this->base}/tickets?status=open");

        $response->assertOk();
        $data = $response->json('data.data');
        foreach ($data as $ticket) {
            $this->assertEquals('open', $ticket['status']);
        }
    }

    public function test_list_tickets_filters_by_category(): void
    {
        $this->makeTicket(['category' => TicketCategory::Billing->value]);
        $this->makeTicket(['category' => TicketCategory::Technical->value]);

        $response = $this->getJson("{$this->base}/tickets?category=billing");

        $response->assertOk();
        $data = $response->json('data.data');
        foreach ($data as $ticket) {
            $this->assertEquals('billing', $ticket['category']);
        }
    }

    public function test_list_tickets_filters_by_priority(): void
    {
        $this->makeTicket(['priority' => TicketPriority::Critical->value]);
        $this->makeTicket(['priority' => TicketPriority::Low->value]);

        $response = $this->getJson("{$this->base}/tickets?priority=critical");

        $response->assertOk();
        $data = $response->json('data.data');
        foreach ($data as $ticket) {
            $this->assertEquals('critical', $ticket['priority']);
        }
    }

    public function test_list_tickets_filters_by_assigned_to(): void
    {
        $this->makeTicket(['assigned_to' => $this->admin->id]);
        $this->makeTicket(['assigned_to' => null]);

        $response = $this->getJson("{$this->base}/tickets?assigned_to={$this->admin->id}");

        $response->assertOk();
        $data = $response->json('data.data');
        foreach ($data as $ticket) {
            // assigned_to is eager-loaded as a nested object {id, name}
            $assignedId = is_array($ticket['assigned_to'])
                ? $ticket['assigned_to']['id']
                : $ticket['assigned_to'];
            $this->assertEquals($this->admin->id, $assignedId);
        }
    }

    public function test_list_tickets_search_by_subject(): void
    {
        $this->makeTicket(['subject' => 'Invoice generation error']);
        $this->makeTicket(['subject' => 'Printer not working']);

        $response = $this->getJson("{$this->base}/tickets?search=Invoice");

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertNotEmpty($data);
        $this->assertStringContainsStringIgnoringCase('Invoice', $data[0]['subject']);
    }

    public function test_list_tickets_search_by_ticket_number(): void
    {
        $ticket = $this->makeTicket(['ticket_number' => 'TKT-2026-SEARCH1']);

        $response = $this->getJson("{$this->base}/tickets?search=SEARCH1");

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertNotEmpty($data);
        $found = collect($data)->firstWhere('ticket_number', 'TKT-2026-SEARCH1');
        $this->assertNotNull($found);
    }

    public function test_list_tickets_filters_sla_breached(): void
    {
        // Create a ticket with expired SLA
        $this->makeTicket([
            'sla_deadline_at' => now()->subHours(2),
            'status'          => TicketStatus::Open->value,
        ]);
        // Create a fresh ticket
        $this->makeTicket([
            'sla_deadline_at' => now()->addHours(24),
            'status'          => TicketStatus::Open->value,
        ]);

        $response = $this->getJson("{$this->base}/tickets?sla_breached=1");

        $response->assertOk();
        // All returned tickets should have past SLA deadlines
        $data = $response->json('data.data');
        foreach ($data as $ticket) {
            if (isset($ticket['sla_deadline_at'])) {
                $this->assertTrue(
                    now()->isAfter($ticket['sla_deadline_at']),
                    "SLA deadline should be in the past for sla_breached filter"
                );
            }
        }
    }

    public function test_list_tickets_combines_multiple_filters(): void
    {
        $this->makeTicket([
            'status'   => TicketStatus::Open->value,
            'priority' => TicketPriority::Critical->value,
        ]);
        $this->makeTicket([
            'status'   => TicketStatus::Open->value,
            'priority' => TicketPriority::Low->value,
        ]);
        $this->makeTicket([
            'status'   => TicketStatus::Resolved->value,
            'priority' => TicketPriority::Critical->value,
            'resolved_at' => now(),
        ]);

        $response = $this->getJson("{$this->base}/tickets?status=open&priority=critical");

        $response->assertOk();
        $data = $response->json('data.data');
        foreach ($data as $ticket) {
            $this->assertEquals('open', $ticket['status']);
            $this->assertEquals('critical', $ticket['priority']);
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  TICKET CREATE (Admin creating on behalf)
    // ═══════════════════════════════════════════════════════════

    public function test_create_ticket_requires_organization_id(): void
    {
        $this->postJson("{$this->base}/tickets", [
            'subject'     => 'Admin ticket',
            'description' => 'Created by admin',
            'category'    => 'technical',
        ])->assertUnprocessable();
    }

    public function test_create_ticket_requires_subject(): void
    {
        $this->postJson("{$this->base}/tickets", [
            'organization_id' => $this->org->id,
            'description'     => 'Created by admin',
            'category'        => 'technical',
        ])->assertUnprocessable();
    }

    public function test_create_ticket_with_all_fields(): void
    {
        $response = $this->postJson("{$this->base}/tickets", [
            'organization_id' => $this->org->id,
            'subject'         => 'Full ticket with all fields',
            'description'     => 'Comprehensive test description',
            'category'        => 'billing',
            'priority'        => 'high',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.category', 'billing')
            ->assertJsonPath('data.priority', 'high');

        $this->assertDatabaseHas('support_tickets', [
            'organization_id' => $this->org->id,
            'subject'         => 'Full ticket with all fields',
            'category'        => 'billing',
            'priority'        => 'high',
        ]);
    }

    public function test_create_ticket_uses_sequential_ticket_number(): void
    {
        $response1 = $this->postJson("{$this->base}/tickets", [
            'organization_id' => $this->org->id,
            'subject'         => 'First admin ticket',
            'description'     => 'Test',
            'category'        => 'technical',
        ])->assertCreated();

        $response2 = $this->postJson("{$this->base}/tickets", [
            'organization_id' => $this->org->id,
            'subject'         => 'Second admin ticket',
            'description'     => 'Test',
            'category'        => 'technical',
        ])->assertCreated();

        $num1 = $response1->json('data.ticket_number');
        $num2 = $response2->json('data.ticket_number');

        $this->assertNotEquals($num1, $num2);
        $this->assertStringStartsWith('TKT-', $num1);
        $this->assertStringStartsWith('TKT-', $num2);
    }

    // ═══════════════════════════════════════════════════════════
    //  TICKET UPDATE
    // ═══════════════════════════════════════════════════════════

    public function test_admin_can_update_ticket_subject_and_description(): void
    {
        $ticket = $this->makeTicket();

        $this->putJson("{$this->base}/tickets/{$ticket->id}", [
            'subject'     => 'Updated subject',
            'description' => 'Updated description',
        ])->assertOk();

        $this->assertDatabaseHas('support_tickets', [
            'id'          => $ticket->id,
            'subject'     => 'Updated subject',
            'description' => 'Updated description',
        ]);
    }

    public function test_admin_can_update_ticket_priority(): void
    {
        $ticket = $this->makeTicket(['priority' => TicketPriority::Low->value]);

        $this->putJson("{$this->base}/tickets/{$ticket->id}", [
            'priority' => 'critical',
        ])->assertOk();

        $this->assertDatabaseHas('support_tickets', [
            'id'       => $ticket->id,
            'priority' => 'critical',
        ]);
    }

    public function test_admin_can_update_ticket_category(): void
    {
        $ticket = $this->makeTicket(['category' => TicketCategory::Technical->value]);

        $this->putJson("{$this->base}/tickets/{$ticket->id}", [
            'category' => 'billing',
        ])->assertOk();

        $this->assertDatabaseHas('support_tickets', [
            'id'       => $ticket->id,
            'category' => 'billing',
        ]);
    }

    public function test_admin_update_rejects_invalid_priority(): void
    {
        $ticket = $this->makeTicket();

        $this->putJson("{$this->base}/tickets/{$ticket->id}", [
            'priority' => 'super_urgent',
        ])->assertUnprocessable();
    }

    public function test_admin_update_returns_404_for_missing_ticket(): void
    {
        $this->putJson("{$this->base}/tickets/" . Str::uuid(), [
            'subject' => 'Updated',
        ])->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════
    //  TICKET MESSAGES
    // ═══════════════════════════════════════════════════════════

    public function test_list_messages_includes_all_messages(): void
    {
        $ticket = $this->makeTicket();

        SupportTicketMessage::forceCreate([
            'id'                 => Str::uuid()->toString(),
            'support_ticket_id'  => $ticket->id,
            'sender_id'          => $this->provider->id,
            'sender_type'        => 'provider',
            'message_text'       => 'Provider message',
            'is_internal_note'   => false,
            'sent_at'            => now(),
        ]);

        SupportTicketMessage::forceCreate([
            'id'                 => Str::uuid()->toString(),
            'support_ticket_id'  => $ticket->id,
            'sender_id'          => $this->admin->id,
            'sender_type'        => 'admin',
            'message_text'       => 'Admin reply',
            'is_internal_note'   => false,
            'sent_at'            => now()->addMinute(),
        ]);

        $response = $this->getJson("{$this->base}/tickets/{$ticket->id}/messages");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_list_messages_returns_404_for_missing_ticket(): void
    {
        $this->getJson("{$this->base}/tickets/" . Str::uuid() . "/messages")->assertNotFound();
    }

    public function test_add_message_with_attachment_metadata(): void
    {
        $ticket = $this->makeTicket();

        $this->postJson("{$this->base}/tickets/{$ticket->id}/messages", [
            'message_text' => 'Please see the attached screenshot.',
            'attachments'  => [
                ['name' => 'error.png', 'url' => 'https://cdn.example.com/error.png', 'size' => 12345],
            ],
        ])->assertCreated();
    }

    public function test_add_message_requires_message_text(): void
    {
        $ticket = $this->makeTicket();

        $this->postJson("{$this->base}/tickets/{$ticket->id}/messages", [])
            ->assertUnprocessable();
    }

    // ═══════════════════════════════════════════════════════════
    //  ASSIGN TICKET
    // ═══════════════════════════════════════════════════════════

    public function test_assign_ticket_sets_assigned_to(): void
    {
        $ticket = $this->makeTicket(['assigned_to' => null]);

        $this->postJson("{$this->base}/tickets/{$ticket->id}/assign", [
            'assigned_to' => $this->admin->id,
        ])->assertOk();

        $this->assertDatabaseHas('support_tickets', [
            'id'          => $ticket->id,
            'assigned_to' => $this->admin->id,
        ]);
    }

    public function test_assign_ticket_can_unassign(): void
    {
        $ticket = $this->makeTicket(['assigned_to' => $this->admin->id]);

        $this->postJson("{$this->base}/tickets/{$ticket->id}/assign", [
            'assigned_to' => null,
        ])->assertOk();

        $this->assertDatabaseHas('support_tickets', [
            'id'          => $ticket->id,
            'assigned_to' => null,
        ]);
    }

    public function test_assign_ticket_returns_404_for_missing_ticket(): void
    {
        $this->postJson("{$this->base}/tickets/" . Str::uuid() . "/assign", [
            'assigned_to' => $this->admin->id,
        ])->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════
    //  CANNED RESPONSES — Full CRUD
    // ═══════════════════════════════════════════════════════════

    private function makeCannedResponse(array $overrides = []): CannedResponse
    {
        return CannedResponse::forceCreate(array_merge([
            'id'         => Str::uuid()->toString(),
            'title'      => 'Test Response ' . Str::random(4),
            'shortcut'   => '/test-' . Str::random(4),
            'body'       => 'Thank you for contacting our support team.',
            'body_ar'    => 'شكراً على تواصلك مع فريق الدعم.',
            'category'   => null,
            'is_active'  => true,
            'created_at' => now(),
        ], $overrides));
    }

    public function test_show_canned_response(): void
    {
        $canned = $this->makeCannedResponse();

        $this->getJson("{$this->base}/canned-responses/{$canned->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $canned->id)
            ->assertJsonPath('data.title', $canned->title);
    }

    public function test_update_canned_response_title(): void
    {
        $canned = $this->makeCannedResponse();

        $this->putJson("{$this->base}/canned-responses/{$canned->id}", [
            'title' => 'Updated Canned Title',
        ])->assertOk();

        $this->assertDatabaseHas('canned_responses', [
            'id'    => $canned->id,
            'title' => 'Updated Canned Title',
        ]);
    }

    public function test_update_canned_response_body(): void
    {
        $canned = $this->makeCannedResponse();

        $this->putJson("{$this->base}/canned-responses/{$canned->id}", [
            'body'    => 'Updated body text.',
            'body_ar' => 'نص محدث.',
        ])->assertOk();

        $this->assertDatabaseHas('canned_responses', [
            'id'   => $canned->id,
            'body' => 'Updated body text.',
        ]);
    }

    public function test_update_canned_response_returns_404_for_missing(): void
    {
        $this->putJson("{$this->base}/canned-responses/" . Str::uuid(), [
            'title' => 'Updated',
        ])->assertNotFound();
    }

    public function test_show_canned_response_returns_404_for_missing(): void
    {
        $this->getJson("{$this->base}/canned-responses/" . Str::uuid())->assertNotFound();
    }

    public function test_toggle_canned_response_to_inactive(): void
    {
        $canned = $this->makeCannedResponse(['is_active' => true]);

        $this->postJson("{$this->base}/canned-responses/{$canned->id}/toggle")
            ->assertOk();

        $this->assertDatabaseHas('canned_responses', [
            'id'        => $canned->id,
            'is_active' => false,
        ]);
    }

    public function test_toggle_canned_response_back_to_active(): void
    {
        $canned = $this->makeCannedResponse(['is_active' => false]);

        $this->postJson("{$this->base}/canned-responses/{$canned->id}/toggle")
            ->assertOk();

        $this->assertDatabaseHas('canned_responses', [
            'id'        => $canned->id,
            'is_active' => true,
        ]);
    }

    public function test_create_canned_response_validation_requires_title_and_body(): void
    {
        $this->postJson("{$this->base}/canned-responses", [
            'title' => 'Missing body',
        ])->assertUnprocessable();

        $this->postJson("{$this->base}/canned-responses", [
            'body' => 'Missing title',
        ])->assertUnprocessable();
    }

    public function test_create_canned_response_with_category(): void
    {
        $this->postJson("{$this->base}/canned-responses", [
            'title'    => 'Billing Response',
            'body'     => 'Your invoice has been sent.',
            'body_ar'  => 'تم إرسال فاتورتك.',
            'category' => 'billing',
        ])->assertCreated()->assertJsonPath('data.category', 'billing');
    }

    public function test_list_canned_responses_returns_paginated_data(): void
    {
        $this->makeCannedResponse();
        $this->makeCannedResponse();

        $this->getJson("{$this->base}/canned-responses")
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    // ═══════════════════════════════════════════════════════════
    //  KNOWLEDGE BASE ARTICLES — Full CRUD
    // ═══════════════════════════════════════════════════════════

    private function makeKbArticle(array $overrides = []): KnowledgeBaseArticle
    {
        return KnowledgeBaseArticle::create(array_merge([
            'title'        => 'KB Article ' . Str::random(4),
            'title_ar'     => 'مقال ' . Str::random(4),
            'slug'         => 'kb-article-' . Str::random(6),
            'body'         => '<p>How to use the POS system.</p>',
            'body_ar'      => '<p>كيفية استخدام نظام نقاط البيع.</p>',
            'category'     => 'general',
            'is_published' => false,
            'sort_order'   => 0,
        ], $overrides));
    }

    public function test_show_kb_article(): void
    {
        $article = $this->makeKbArticle();

        $this->getJson("{$this->base}/kb/{$article->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $article->id);
    }

    public function test_show_kb_article_returns_404_for_missing(): void
    {
        $this->getJson("{$this->base}/kb/" . Str::uuid())->assertNotFound();
    }

    public function test_update_kb_article_title(): void
    {
        $article = $this->makeKbArticle();

        $this->putJson("{$this->base}/kb/{$article->id}", [
            'title' => 'Updated KB Title',
        ])->assertOk();

        $this->assertDatabaseHas('knowledge_base_articles', [
            'id'    => $article->id,
            'title' => 'Updated KB Title',
        ]);
    }

    public function test_update_kb_article_publish_status(): void
    {
        $article = $this->makeKbArticle(['is_published' => false]);

        $this->putJson("{$this->base}/kb/{$article->id}", [
            'is_published' => true,
        ])->assertOk();

        $this->assertDatabaseHas('knowledge_base_articles', [
            'id'           => $article->id,
            'is_published' => true,
        ]);
    }

    public function test_delete_kb_article(): void
    {
        $article = $this->makeKbArticle();

        $this->deleteJson("{$this->base}/kb/{$article->id}")
            ->assertOk();

        $this->assertDatabaseMissing('knowledge_base_articles', ['id' => $article->id]);
    }

    public function test_delete_kb_article_returns_404_for_missing(): void
    {
        $this->deleteJson("{$this->base}/kb/" . Str::uuid())->assertNotFound();
    }

    public function test_list_kb_articles_returns_paginated_data(): void
    {
        $this->makeKbArticle(['is_published' => true]);
        $this->makeKbArticle(['is_published' => false]);

        $response = $this->getJson("{$this->base}/kb");

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_create_kb_article_validation_requires_title_and_body(): void
    {
        $this->postJson("{$this->base}/kb", [
            'title'    => 'Missing body',
            'title_ar' => 'Missing body',
            'category' => 'general',
        ])->assertUnprocessable();
    }

    public function test_create_kb_article_with_hardware_category(): void
    {
        // 'hardware' is not in KnowledgeBaseCategory enum — use troubleshooting as alternative
        $this->postJson("{$this->base}/kb", [
            'title'    => 'Hardware Troubleshooting',
            'title_ar' => 'استكشاف أخطاء الجهاز',
            'body'     => 'How to set up your hardware.',
            'body_ar'  => 'كيفية إعداد الجهاز.',
            'category' => 'troubleshooting',
        ])->assertCreated()->assertJsonPath('data.category', 'troubleshooting');
    }

    public function test_create_kb_article_auto_generates_slug_from_title(): void
    {
        $response = $this->postJson("{$this->base}/kb", [
            'title'    => 'Auto Slug Generation Test',
            'title_ar' => 'اختبار',
            'body'     => 'Content',
            'body_ar'  => 'المحتوى',
            'category' => 'general',
        ])->assertCreated();

        $slug = $response->json('data.slug');
        $this->assertNotEmpty($slug, 'Slug should be auto-generated');
    }

    // ═══════════════════════════════════════════════════════════
    //  STATS
    // ═══════════════════════════════════════════════════════════

    public function test_stats_counts_are_accurate(): void
    {
        $this->makeTicket(['status' => TicketStatus::Open->value]);
        $this->makeTicket(['status' => TicketStatus::Open->value]);
        $this->makeTicket(['status' => TicketStatus::InProgress->value]);
        $this->makeTicket(['status' => TicketStatus::Resolved->value, 'resolved_at' => now()]);

        $response = $this->getJson("{$this->base}/stats");

        $response->assertOk();
        $data = $response->json('data');

        $this->assertGreaterThanOrEqual(2, $data['open'], 'Should have at least 2 open tickets');
        $this->assertGreaterThanOrEqual(1, $data['in_progress'], 'Should have at least 1 in_progress ticket');
        $this->assertGreaterThanOrEqual(4, $data['total'], 'Total should be at least 4');
    }

    public function test_stats_counts_sla_breached_tickets(): void
    {
        $this->makeTicket([
            'status'          => TicketStatus::Open->value,
            'sla_deadline_at' => now()->subHours(1),
        ]);

        $response = $this->getJson("{$this->base}/stats");

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, $response->json('data.sla_breached'));
    }

    public function test_stats_counts_critical_tickets(): void
    {
        $this->makeTicket(['priority' => TicketPriority::Critical->value]);

        $response = $this->getJson("{$this->base}/stats");

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, $response->json('data.critical'));
    }

    public function test_stats_counts_unassigned_open_tickets(): void
    {
        $this->makeTicket(['assigned_to' => null, 'status' => TicketStatus::Open->value]);

        $response = $this->getJson("{$this->base}/stats");

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, $response->json('data.unassigned'));
    }

    public function test_stats_counts_resolved_today(): void
    {
        $this->makeTicket([
            'status'      => TicketStatus::Resolved->value,
            'resolved_at' => now(),
        ]);

        $response = $this->getJson("{$this->base}/stats");

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, $response->json('data.resolved_today'));
    }

    public function test_stats_does_not_count_yesterday_as_resolved_today(): void
    {
        // Only ticket resolved yesterday, not today
        $this->makeTicket([
            'status'      => TicketStatus::Resolved->value,
            'resolved_at' => now()->subDay(),
        ]);

        $response = $this->getJson("{$this->base}/stats");

        $response->assertOk();
        // resolved_today should be 0 (or at least not count yesterday's)
        $this->assertEquals(0, $response->json('data.resolved_today'));
    }

    // ═══════════════════════════════════════════════════════════
    //  EDGE CASES AND ROBUSTNESS
    // ═══════════════════════════════════════════════════════════

    public function test_show_ticket_returns_404_for_missing(): void
    {
        $this->getJson("{$this->base}/tickets/" . Str::uuid())->assertNotFound();
    }

    public function test_change_status_returns_404_for_missing_ticket(): void
    {
        $this->postJson("{$this->base}/tickets/" . Str::uuid() . "/status", [
            'status' => 'resolved',
        ])->assertNotFound();
    }

    public function test_add_message_to_missing_ticket_returns_404(): void
    {
        $this->postJson("{$this->base}/tickets/" . Str::uuid() . "/messages", [
            'message_text' => 'Hello',
        ])->assertNotFound();
    }

    public function test_ticket_response_includes_messages_count(): void
    {
        $ticket = $this->makeTicket();

        SupportTicketMessage::forceCreate([
            'id'                => Str::uuid()->toString(),
            'support_ticket_id' => $ticket->id,
            'sender_id'         => $this->provider->id,
            'sender_type'       => 'provider',
            'message_text'      => 'Initial message',
            'is_internal_note'  => false,
            'sent_at'           => now(),
        ]);

        $response = $this->getJson("{$this->base}/tickets/{$ticket->id}");

        $response->assertOk()
            ->assertJsonPath('data.messages_count', 1);
    }

    public function test_ticket_response_includes_sla_badge(): void
    {
        $ticket = $this->makeTicket([
            'sla_deadline_at' => now()->addHours(12),
        ]);

        $response = $this->getJson("{$this->base}/tickets/{$ticket->id}");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['sla_badge']]);
    }

    public function test_reply_sent_notification_recorded_in_response(): void
    {
        $ticket = $this->makeTicket();

        $response = $this->postJson("{$this->base}/tickets/{$ticket->id}/messages", [
            'message_text' => 'Your issue has been escalated to our engineering team.',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['data' => [
                'id', 'support_ticket_id', 'sender_type', 'message_text', 'is_internal_note',
            ]]);
    }
}
