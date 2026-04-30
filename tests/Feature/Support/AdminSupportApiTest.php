<?php

namespace Tests\Feature\Support;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Auth\Models\User;
use App\Domain\Support\Enums\TicketCategory;
use App\Domain\Support\Enums\TicketPriority;
use App\Domain\Support\Enums\TicketStatus;
use App\Domain\Support\Models\CannedResponse;
use App\Domain\Support\Models\SupportTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Feature tests for the admin-facing Support Ticket API.
 */
class AdminSupportApiTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;
    private Store $store;
    private Organization $org;
    private User $provider;
    private string $base = '/api/v2/admin/support';

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = AdminUser::forceCreate([
            'id'            => Str::uuid()->toString(),
            'name'          => 'Support Admin',
            'email'         => 'support-admin@test.com',
            'password_hash' => bcrypt('password'),
            'is_active'     => true,
        ]);
        Sanctum::actingAs($this->admin, ['*'], 'admin-api');

        $this->org = Organization::create([
            'name'          => 'Test Org',
            'business_type' => 'grocery',
            'country'       => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name'            => 'Test Store',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
            'is_active'       => true,
            'is_main_branch'  => true,
        ]);

        $this->provider = User::create([
            'name'            => 'Store Owner',
            'email'           => 'owner@test.com',
            'password_hash'   => bcrypt('password'),
            'store_id'        => $this->store->id,
            'organization_id' => $this->org->id,
            'role'            => 'owner',
            'is_active'       => true,
        ]);
    }

    // ─── HELPERS ─────────────────────────────────────────────

    private function createTicket(array $overrides = []): SupportTicket
    {
        return SupportTicket::create(array_merge([
            'ticket_number'   => 'TKT-' . now()->year . '-' . Str::random(4),
            'store_id'        => $this->store->id,
            'user_id'         => $this->provider->id,
            'organization_id' => $this->org->id,
            'category'        => TicketCategory::Technical,
            'priority'        => TicketPriority::Medium,
            'status'          => TicketStatus::Open,
            'subject'         => 'Test ticket',
            'description'     => 'Test description',
        ], $overrides));
    }

    // ─── STATS ───────────────────────────────────────────────

    public function test_admin_stats_returns_all_required_fields(): void
    {
        $this->createTicket(['priority' => TicketPriority::Critical]);

        $response = $this->getJson("{$this->base}/stats");

        $response->assertOk()
            ->assertJsonStructure(['data' => [
                'total', 'open', 'in_progress', 'unresolved',
                'sla_breached', 'resolved_today', 'critical',
                'unassigned', 'avg_response_min', 'avg_resolution_min',
            ]]);

        $this->assertGreaterThanOrEqual(1, $response->json('data.critical'));
        $this->assertGreaterThanOrEqual(1, $response->json('data.unassigned'));
    }

    // ─── LIST TICKETS ─────────────────────────────────────────

    public function test_admin_list_tickets(): void
    {
        $this->createTicket(['subject' => 'First ticket']);
        $this->createTicket(['subject' => 'Second ticket']);

        $response = $this->getJson("{$this->base}/tickets");

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_admin_list_tickets_filters_by_priority(): void
    {
        $this->createTicket(['subject' => 'Critical ticket', 'priority' => TicketPriority::Critical]);
        $this->createTicket(['subject' => 'Low ticket', 'priority' => TicketPriority::Low]);

        $response = $this->getJson("{$this->base}/tickets?priority=critical");

        $response->assertOk();
        $tickets = collect($response->json('data.data') ?? $response->json('data'));
        $this->assertTrue($tickets->every(fn ($t) => $t['priority'] === 'critical'));
    }

    // ─── SHOW TICKET ──────────────────────────────────────────

    public function test_admin_show_ticket(): void
    {
        $ticket = $this->createTicket();

        $response = $this->getJson("{$this->base}/tickets/{$ticket->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $ticket->id)
            ->assertJsonStructure(['data' => ['ticket_number', 'status', 'priority', 'category', 'subject']]);
    }

    // ─── ASSIGN ───────────────────────────────────────────────

    public function test_admin_assign_ticket(): void
    {
        $ticket = $this->createTicket();

        $response = $this->postJson("{$this->base}/tickets/{$ticket->id}/assign", [
            'assigned_to' => $this->admin->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('support_tickets', [
            'id'          => $ticket->id,
            'assigned_to' => $this->admin->id,
        ]);
    }

    // ─── CHANGE STATUS ────────────────────────────────────────

    public function test_admin_change_ticket_status_to_resolved(): void
    {
        $ticket = $this->createTicket(['status' => TicketStatus::InProgress]);

        $response = $this->postJson("{$this->base}/tickets/{$ticket->id}/status", [
            'status' => 'resolved',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('support_tickets', [
            'id'     => $ticket->id,
            'status' => 'resolved',
        ]);
    }

    public function test_admin_change_status_rejects_invalid_value(): void
    {
        $ticket = $this->createTicket();

        $response = $this->postJson("{$this->base}/tickets/{$ticket->id}/status", [
            'status' => 'deleted',  // not valid
        ]);

        $response->assertStatus(422);
    }

    // ─── MESSAGES ─────────────────────────────────────────────

    public function test_admin_add_message_to_ticket(): void
    {
        $ticket = $this->createTicket();

        $response = $this->postJson("{$this->base}/tickets/{$ticket->id}/messages", [
            'message_text'     => 'We are looking into this issue.',
            'is_internal_note' => false,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('support_ticket_messages', [
            'support_ticket_id' => $ticket->id,
            'message_text'      => 'We are looking into this issue.',
            'is_internal_note'  => false,
        ]);
    }

    public function test_admin_add_internal_note(): void
    {
        $ticket = $this->createTicket();

        $response = $this->postJson("{$this->base}/tickets/{$ticket->id}/messages", [
            'message_text'     => 'Internal: check with billing team.',
            'is_internal_note' => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('support_ticket_messages', [
            'support_ticket_id' => $ticket->id,
            'is_internal_note'  => true,
        ]);
    }

    public function test_admin_reply_updates_ticket_status_to_in_progress(): void
    {
        $ticket = $this->createTicket(['status' => TicketStatus::Open]);

        $this->postJson("{$this->base}/tickets/{$ticket->id}/messages", [
            'message_text'     => 'Admin reply.',
            'is_internal_note' => false,
        ])->assertStatus(201);

        $this->assertDatabaseHas('support_tickets', [
            'id'     => $ticket->id,
            'status' => 'in_progress',
        ]);
    }

    // ─── CANNED RESPONSES ─────────────────────────────────────

    public function test_create_canned_response(): void
    {
        $response = $this->postJson("{$this->base}/canned-responses", [
            'title'    => 'Thank you response',
            'shortcut' => 'ty',
            'body'     => 'Thank you for reaching out! We will resolve this shortly.',
            'body_ar'  => 'شكراً على التواصل. سنحل هذه المشكلة قريباً.',
            'category' => 'general',
            'is_active' => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('canned_responses', [
            'title'    => 'Thank you response',
            'shortcut' => 'ty',
        ]);
    }

    public function test_list_canned_responses(): void
    {
        CannedResponse::create([
            'title'    => 'Response A',
            'body'     => 'Body A',
            'body_ar'  => 'الجسم أ',
            'is_active' => true,
            'created_at' => now(),
        ]);

        $response = $this->getJson("{$this->base}/canned-responses");

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_toggle_canned_response_active_state(): void
    {
        $cr = CannedResponse::create([
            'title'    => 'Toggle Response',
            'body'     => 'Body',
            'body_ar'  => 'الجسم',
            'is_active' => true,
            'created_at' => now(),
        ]);

        $response = $this->postJson("{$this->base}/canned-responses/{$cr->id}/toggle");

        $response->assertOk();
        $this->assertDatabaseHas('canned_responses', [
            'id'        => $cr->id,
            'is_active' => false,
        ]);
    }

    public function test_delete_canned_response(): void
    {
        $cr = CannedResponse::create([
            'title'    => 'To Delete',
            'body'     => 'Body',
            'body_ar'  => 'الجسم',
            'is_active' => true,
            'created_at' => now(),
        ]);

        $response = $this->deleteJson("{$this->base}/canned-responses/{$cr->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('canned_responses', ['id' => $cr->id]);
    }

    // ─── KB ARTICLES ──────────────────────────────────────────

    public function test_create_kb_article(): void
    {
        $response = $this->postJson("{$this->base}/kb", [
            'title'        => 'How to create a product',
            'title_ar'     => 'كيفية إنشاء منتج',
            'slug'         => 'how-to-create-product',
            'body'         => '<p>To create a product go to Catalog.</p>',
            'body_ar'      => '<p>لإنشاء منتج انتقل إلى الكتالوج.</p>',
            'category'     => 'getting_started',
            'is_published' => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('knowledge_base_articles', [
            'slug'     => 'how-to-create-product',
            'category' => 'getting_started',
        ]);
    }

    public function test_create_kb_article_with_general_category(): void
    {
        $response = $this->postJson("{$this->base}/kb", [
            'title'        => 'General info article',
            'title_ar'     => 'مقال معلومات عامة',
            'slug'         => 'general-info-article',
            'body'         => '<p>General information here.</p>',
            'body_ar'      => '<p>معلومات عامة هنا.</p>',
            'category'     => 'general',
            'is_published' => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('knowledge_base_articles', [
            'slug'     => 'general-info-article',
            'category' => 'general',
        ]);
    }

    public function test_list_kb_articles(): void
    {
        $response = $this->getJson("{$this->base}/kb");

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }
}
