<?php

namespace Tests\Unit\Domain\Support;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Support\Enums\TicketCategory;
use App\Domain\Support\Enums\TicketPriority;
use App\Domain\Support\Enums\TicketStatus;
use App\Domain\Support\Models\CannedResponse;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Models\SupportTicketMessage;
use App\Domain\Support\Services\SupportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Unit tests for SupportService — covers all service methods with all flows.
 *
 * Tests here exercise the service in isolation via the test DB.
 * All DB interactions are real (no mocking) to verify business rules fully.
 */
class SupportServiceTest extends TestCase
{
    use RefreshDatabase;

    private SupportService $service;
    private User $user;
    private Store $store;
    private Organization $org;
    private AdminUser $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SupportService::class);

        $this->org = Organization::create([
            'name'          => 'Unit Test Org',
            'business_type' => 'grocery',
            'country'       => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name'            => 'Unit Test Store',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
            'is_active'       => true,
            'is_main_branch'  => true,
        ]);

        $this->user = User::create([
            'name'            => 'Unit Owner',
            'email'           => 'unit-owner@test.com',
            'password_hash'   => bcrypt('password'),
            'store_id'        => $this->store->id,
            'organization_id' => $this->org->id,
            'role'            => 'owner',
            'is_active'       => true,
        ]);

        $this->admin = AdminUser::forceCreate([
            'id'            => Str::uuid()->toString(),
            'name'          => 'Unit Admin',
            'email'         => 'unit-admin@test.com',
            'password_hash' => bcrypt('password'),
            'is_active'     => true,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  createTicket
    // ═══════════════════════════════════════════════════════════

    public function test_create_ticket_assigns_sequential_ticket_number(): void
    {
        $year = now()->year;

        $t1 = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category'    => 'technical',
            'priority'    => 'medium',
            'subject'     => 'First ticket',
            'description' => 'First description',
        ]);

        $t2 = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category'    => 'billing',
            'priority'    => 'low',
            'subject'     => 'Second ticket',
            'description' => 'Second description',
        ]);

        $this->assertEquals("TKT-{$year}-0001", $t1->ticket_number);
        $this->assertEquals("TKT-{$year}-0002", $t2->ticket_number);
    }

    public function test_create_ticket_auto_sets_sla_deadline_for_each_priority(): void
    {
        $cases = [
            'critical' => 240,
            'high'     => 1440,
            'medium'   => 2880,
            'low'      => 7200,
        ];

        foreach ($cases as $priority => $expectedMinutes) {
            $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
                'category'    => 'general',
                'priority'    => $priority,
                'subject'     => "Ticket {$priority}",
                'description' => 'Description',
            ]);

            $this->assertNotNull($ticket->sla_deadline_at);
            $diffMinutes = now()->diffInMinutes($ticket->sla_deadline_at);
            // Allow ±2 minutes for test execution time
            $this->assertEqualsWithDelta($expectedMinutes, $diffMinutes, 2,
                "SLA deadline for priority '{$priority}' should be ~{$expectedMinutes} minutes");
        }
    }

    public function test_create_ticket_defaults_to_open_status(): void
    {
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category'    => 'technical',
            'priority'    => 'medium',
            'subject'     => 'Status test',
            'description' => 'Description',
        ]);

        $this->assertEquals(TicketStatus::Open, $ticket->status);
    }

    public function test_create_ticket_stores_correct_fields(): void
    {
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category'    => 'billing',
            'priority'    => 'high',
            'subject'     => 'Billing issue',
            'description' => 'Invoice number is wrong.',
        ]);

        $this->assertEquals($this->user->id, $ticket->user_id);
        $this->assertEquals($this->store->id, $ticket->store_id);
        $this->assertEquals($this->org->id, $ticket->organization_id);
        $this->assertEquals(TicketCategory::Billing, $ticket->category);
        $this->assertEquals(TicketPriority::High, $ticket->priority);
        $this->assertEquals('Billing issue', $ticket->subject);
        $this->assertEquals('Invoice number is wrong.', $ticket->description);
    }

    public function test_create_ticket_allows_null_organization_id(): void
    {
        // When null org is passed, the service falls back to the store's organization
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, null, [
            'category'    => 'general',
            'priority'    => 'low',
            'subject'     => 'No org',
            'description' => 'Desc',
        ]);

        // The service should resolve organization_id from the store automatically
        $this->assertNotNull($ticket->id);
        $this->assertEquals($this->org->id, $ticket->organization_id);
    }

    // ═══════════════════════════════════════════════════════════
    //  listTickets
    // ═══════════════════════════════════════════════════════════

    public function test_list_tickets_returns_only_store_tickets(): void
    {
        // Create ticket for this store
        $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium',
            'subject' => 'My store ticket', 'description' => 'Desc',
        ]);

        // Create ticket for another store
        $org2 = Organization::create(['name' => 'Org2', 'business_type' => 'grocery', 'country' => 'SA']);
        $store2 = Store::create([
            'organization_id' => $org2->id, 'name' => 'Other Store',
            'business_type' => 'grocery', 'currency' => 'SAR',
            'is_active' => true, 'is_main_branch' => true,
        ]);
        $user2 = User::create([
            'name' => 'Other Owner', 'email' => 'other@test.com',
            'password_hash' => bcrypt('p'), 'store_id' => $store2->id,
            'organization_id' => $org2->id, 'role' => 'owner', 'is_active' => true,
        ]);
        $this->service->createTicket($user2->id, $store2->id, $org2->id, [
            'category' => 'billing', 'priority' => 'low',
            'subject' => 'Other store ticket', 'description' => 'Desc',
        ]);

        $result = $this->service->listTickets($this->user->id, $this->store->id);

        $this->assertEquals(1, $result['total']);
        $this->assertEquals('My store ticket', $result['data'][0]['subject']);
    }

    public function test_list_tickets_filters_by_status(): void
    {
        $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium',
            'subject' => 'Open ticket', 'description' => 'Desc',
        ]);

        // Create and close another
        $t2 = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'billing', 'priority' => 'low',
            'subject' => 'Closed ticket', 'description' => 'Desc',
        ]);
        $this->service->closeTicket($t2->id, $this->user->id, $this->store->id);

        $openResult = $this->service->listTickets($this->user->id, $this->store->id, ['status' => 'open']);
        $closedResult = $this->service->listTickets($this->user->id, $this->store->id, ['status' => 'closed']);

        $this->assertEquals(1, $openResult['total']);
        $this->assertEquals(1, $closedResult['total']);
        $this->assertEquals('Open ticket', $openResult['data'][0]['subject']);
        $this->assertEquals('Closed ticket', $closedResult['data'][0]['subject']);
    }

    public function test_list_tickets_filters_by_category(): void
    {
        $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium', 'subject' => 'Tech', 'description' => 'Desc',
        ]);
        $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'billing', 'priority' => 'medium', 'subject' => 'Bill', 'description' => 'Desc',
        ]);

        $result = $this->service->listTickets($this->user->id, $this->store->id, ['category' => 'technical']);
        $this->assertEquals(1, $result['total']);
        $this->assertEquals('Tech', $result['data'][0]['subject']);
    }

    public function test_list_tickets_filters_by_priority(): void
    {
        $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'critical', 'subject' => 'Crit', 'description' => 'Desc',
        ]);
        $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'low', 'subject' => 'Low', 'description' => 'Desc',
        ]);

        $result = $this->service->listTickets($this->user->id, $this->store->id, ['priority' => 'critical']);
        $this->assertEquals(1, $result['total']);
        $this->assertEquals('Crit', $result['data'][0]['subject']);
    }

    public function test_list_tickets_filters_by_search_subject(): void
    {
        $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium',
            'subject' => 'POS not printing', 'description' => 'Description',
        ]);
        $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'billing', 'priority' => 'medium',
            'subject' => 'Invoice question', 'description' => 'Description',
        ]);

        $result = $this->service->listTickets($this->user->id, $this->store->id, ['search' => 'POS']);
        $this->assertEquals(1, $result['total']);
        $this->assertEquals('POS not printing', $result['data'][0]['subject']);
    }

    public function test_list_tickets_searches_ticket_number(): void
    {
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium',
            'subject' => 'Some ticket', 'description' => 'Desc',
        ]);

        $result = $this->service->listTickets($this->user->id, $this->store->id, [
            'search' => $ticket->ticket_number,
        ]);

        $this->assertEquals(1, $result['total']);
    }

    // ═══════════════════════════════════════════════════════════
    //  getTicket
    // ═══════════════════════════════════════════════════════════

    public function test_get_ticket_returns_ticket_with_public_messages(): void
    {
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium',
            'subject' => 'Get ticket test', 'description' => 'Desc',
        ]);

        // Add public message
        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type'       => 'admin',
            'sender_id'         => $this->admin->id,
            'message_text'      => 'Public admin reply',
            'is_internal_note'  => false,
            'sent_at'           => now(),
        ]);

        // Add internal note
        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type'       => 'admin',
            'sender_id'         => $this->admin->id,
            'message_text'      => 'Internal note only',
            'is_internal_note'  => true,
            'sent_at'           => now(),
        ]);

        $result = $this->service->getTicket($ticket->id, $this->user->id, $this->store->id);

        $this->assertNotNull($result);
        $this->assertCount(1, $result->messages); // Only public message
        $this->assertEquals('Public admin reply', $result->messages->first()->message_text);
    }

    public function test_get_ticket_returns_null_for_other_store(): void
    {
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium',
            'subject' => 'IDOR test', 'description' => 'Desc',
        ]);

        $fakeUserId = Str::uuid()->toString();
        $fakeStoreId = Str::uuid()->toString();

        $result = $this->service->getTicket($ticket->id, $fakeUserId, $fakeStoreId);

        $this->assertNull($result);
    }

    // ═══════════════════════════════════════════════════════════
    //  addMessage (provider)
    // ═══════════════════════════════════════════════════════════

    public function test_add_message_creates_message_for_own_ticket(): void
    {
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium',
            'subject' => 'Ticket', 'description' => 'Desc',
        ]);

        $message = $this->service->addMessage($ticket->id, $this->user->id, $this->store->id, [
            'message' => 'Hello support!',
        ]);

        $this->assertNotNull($message);
        $this->assertEquals('Hello support!', $message->message_text);
        $this->assertEquals('provider', $message->sender_type->value);
        $this->assertFalse($message->is_internal_note);
    }

    public function test_add_message_returns_null_for_other_store(): void
    {
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium',
            'subject' => 'Ticket', 'description' => 'Desc',
        ]);

        $result = $this->service->addMessage($ticket->id, Str::uuid(), Str::uuid(), [
            'message' => 'Hacker message',
        ]);

        $this->assertNull($result);
    }

    public function test_add_message_reopens_resolved_ticket(): void
    {
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium',
            'subject' => 'Ticket', 'description' => 'Desc',
        ]);

        // Mark as resolved
        $this->service->changeStatus($ticket, TicketStatus::Resolved, $this->admin->id);
        $this->assertEquals(TicketStatus::Resolved, $ticket->fresh()->status);

        // Provider replies — should reopen
        $this->service->addMessage($ticket->id, $this->user->id, $this->store->id, [
            'message' => 'Still having the issue',
        ]);

        $this->assertEquals(TicketStatus::Open, $ticket->fresh()->status);
    }

    // ═══════════════════════════════════════════════════════════
    //  closeTicket
    // ═══════════════════════════════════════════════════════════

    public function test_close_ticket_sets_status_and_closed_at(): void
    {
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium',
            'subject' => 'Close test', 'description' => 'Desc',
        ]);

        $result = $this->service->closeTicket($ticket->id, $this->user->id, $this->store->id);

        $this->assertTrue($result);
        $fresh = $ticket->fresh();
        $this->assertEquals(TicketStatus::Closed, $fresh->status);
        $this->assertNotNull($fresh->closed_at);
    }

    public function test_close_ticket_returns_false_for_nonexistent_ticket(): void
    {
        $result = $this->service->closeTicket(Str::uuid()->toString(), $this->user->id, $this->store->id);
        $this->assertFalse($result);
    }

    public function test_close_ticket_prevents_idor_from_other_store(): void
    {
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium',
            'subject' => 'IDOR Close', 'description' => 'Desc',
        ]);

        $result = $this->service->closeTicket($ticket->id, Str::uuid(), Str::uuid());
        $this->assertFalse($result);
        $this->assertEquals(TicketStatus::Open, $ticket->fresh()->status);
    }

    // ═══════════════════════════════════════════════════════════
    //  getStats (provider)
    // ═══════════════════════════════════════════════════════════

    public function test_get_stats_returns_correct_counts_per_status(): void
    {
        // Create 3 tickets
        $t1 = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium', 'subject' => 'T1', 'description' => 'D',
        ]);
        $t2 = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium', 'subject' => 'T2', 'description' => 'D',
        ]);
        $t3 = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium', 'subject' => 'T3', 'description' => 'D',
        ]);

        // Close t3
        $this->service->closeTicket($t3->id, $this->user->id, $this->store->id);
        // Resolve t2
        $this->service->changeStatus($t2, TicketStatus::Resolved, $this->admin->id);

        $stats = $this->service->getStats($this->user->id, $this->store->id);

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(1, $stats['open']);
        $this->assertEquals(0, $stats['in_progress']);
        $this->assertEquals(1, $stats['resolved']);
        $this->assertEquals(1, $stats['closed']);
    }

    public function test_get_stats_scoped_to_store_only(): void
    {
        // Create tickets for our store
        $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium', 'subject' => 'My ticket', 'description' => 'D',
        ]);

        // Create tickets for another store — should not be counted
        $org2 = Organization::create(['name' => 'Org2', 'business_type' => 'grocery', 'country' => 'SA']);
        $store2 = Store::create([
            'organization_id' => $org2->id, 'name' => 'Other', 'business_type' => 'grocery',
            'currency' => 'SAR', 'is_active' => true, 'is_main_branch' => true,
        ]);
        $user2 = User::create([
            'name' => 'Other', 'email' => 'o2@test.com', 'password_hash' => bcrypt('p'),
            'store_id' => $store2->id, 'organization_id' => $org2->id, 'role' => 'owner', 'is_active' => true,
        ]);
        $this->service->createTicket($user2->id, $store2->id, $org2->id, [
            'category' => 'billing', 'priority' => 'low', 'subject' => 'Other store ticket', 'description' => 'D',
        ]);

        $stats = $this->service->getStats($this->user->id, $this->store->id);
        $this->assertEquals(1, $stats['total']);
    }

    // ═══════════════════════════════════════════════════════════
    //  adminAddMessage
    // ═══════════════════════════════════════════════════════════

    public function test_admin_add_message_sets_first_response_at_on_first_non_internal_reply(): void
    {
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium',
            'subject' => 'First response test', 'description' => 'Desc',
        ]);

        $this->assertNull($ticket->fresh()->first_response_at);

        $this->service->adminAddMessage($ticket, $this->admin->id, 'First reply', false);

        $this->assertNotNull($ticket->fresh()->first_response_at);
    }

    public function test_admin_add_internal_note_does_not_set_first_response_at(): void
    {
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium',
            'subject' => 'Internal note test', 'description' => 'Desc',
        ]);

        $this->service->adminAddMessage($ticket, $this->admin->id, 'Internal only', true);

        $this->assertNull($ticket->fresh()->first_response_at);
    }

    public function test_admin_add_message_does_not_overwrite_first_response_at(): void
    {
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium',
            'subject' => 'First response test', 'description' => 'Desc',
        ]);

        $this->service->adminAddMessage($ticket, $this->admin->id, 'First reply', false);
        $firstResponseAt = $ticket->fresh()->first_response_at;

        // Wait a tiny bit then send second reply
        sleep(1);
        $this->service->adminAddMessage($ticket, $this->admin->id, 'Second reply', false);

        $this->assertEquals(
            $firstResponseAt->toDateTimeString(),
            $ticket->fresh()->first_response_at->toDateTimeString(),
            'first_response_at should not be overwritten on second reply'
        );
    }

    public function test_admin_add_message_auto_transitions_open_to_in_progress(): void
    {
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium',
            'subject' => 'Status auto-transition', 'description' => 'Desc',
        ]);

        $this->assertEquals(TicketStatus::Open, $ticket->status);

        $this->service->adminAddMessage($ticket, $this->admin->id, 'Working on it', false);

        $this->assertEquals(TicketStatus::InProgress, $ticket->fresh()->status);
    }

    public function test_admin_internal_note_does_not_auto_transition_status(): void
    {
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium',
            'subject' => 'Internal note no transition', 'description' => 'Desc',
        ]);

        $this->service->adminAddMessage($ticket, $this->admin->id, 'Only agents see this', true);

        $this->assertEquals(TicketStatus::Open, $ticket->fresh()->status);
    }

    public function test_admin_add_message_marks_message_as_admin_sender_type(): void
    {
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium', 'subject' => 'T', 'description' => 'D',
        ]);

        $message = $this->service->adminAddMessage($ticket, $this->admin->id, 'Hello', false);

        $this->assertEquals('admin', $message->sender_type->value);
        $this->assertEquals($this->admin->id, $message->sender_id);
    }

    public function test_admin_add_message_stores_attachments(): void
    {
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium', 'subject' => 'T', 'description' => 'D',
        ]);

        $attachments = [
            ['url' => 'https://s3.example.com/file.pdf', 'filename' => 'file.pdf', 'size' => 1024],
        ];

        $message = $this->service->adminAddMessage(
            $ticket, $this->admin->id, 'See attachment', false, $attachments
        );

        $this->assertNotNull($message->attachments);
        $this->assertCount(1, $message->attachments);
        $this->assertEquals('file.pdf', $message->attachments[0]['filename']);
    }

    // ═══════════════════════════════════════════════════════════
    //  changeStatus
    // ═══════════════════════════════════════════════════════════

    public function test_change_status_to_resolved_sets_resolved_at(): void
    {
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium',
            'subject' => 'Resolve test', 'description' => 'Desc',
        ]);

        $this->service->changeStatus($ticket, TicketStatus::Resolved, $this->admin->id);

        $fresh = $ticket->fresh();
        $this->assertEquals(TicketStatus::Resolved, $fresh->status);
        $this->assertNotNull($fresh->resolved_at);
    }

    public function test_change_status_to_closed_sets_closed_at(): void
    {
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium',
            'subject' => 'Close test', 'description' => 'Desc',
        ]);

        $this->service->changeStatus($ticket, TicketStatus::Closed, $this->admin->id);

        $fresh = $ticket->fresh();
        $this->assertEquals(TicketStatus::Closed, $fresh->status);
        $this->assertNotNull($fresh->closed_at);
    }

    public function test_change_status_to_in_progress_does_not_set_resolution_timestamps(): void
    {
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium',
            'subject' => 'In progress test', 'description' => 'Desc',
        ]);

        $this->service->changeStatus($ticket, TicketStatus::InProgress, $this->admin->id);

        $fresh = $ticket->fresh();
        $this->assertEquals(TicketStatus::InProgress, $fresh->status);
        $this->assertNull($fresh->resolved_at);
        $this->assertNull($fresh->closed_at);
    }

    public function test_change_status_logs_admin_activity(): void
    {
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium',
            'subject' => 'Activity log test', 'description' => 'Desc',
        ]);

        $this->service->changeStatus($ticket, TicketStatus::Resolved, $this->admin->id);

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $this->admin->id,
            'action'        => 'change_ticket_status',
            'entity_type'   => 'support_ticket',
            'entity_id'     => $ticket->id,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  assignTicket
    // ═══════════════════════════════════════════════════════════

    public function test_assign_ticket_updates_assigned_to(): void
    {
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium',
            'subject' => 'Assign test', 'description' => 'Desc',
        ]);

        $this->assertNull($ticket->assigned_to);

        $this->service->assignTicket($ticket, $this->admin->id, $this->admin->id);

        $this->assertEquals($this->admin->id, $ticket->fresh()->assigned_to);
    }

    public function test_assign_ticket_logs_admin_activity(): void
    {
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium',
            'subject' => 'Assign log test', 'description' => 'Desc',
        ]);

        $this->service->assignTicket($ticket, $this->admin->id, $this->admin->id);

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $this->admin->id,
            'action'        => 'assign_ticket',
            'entity_type'   => 'support_ticket',
            'entity_id'     => $ticket->id,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  rateTicket
    // ═══════════════════════════════════════════════════════════

    public function test_rate_ticket_succeeds_on_resolved_ticket(): void
    {
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium',
            'subject' => 'Rate test', 'description' => 'Desc',
        ]);

        $this->service->changeStatus($ticket, TicketStatus::Resolved, $this->admin->id);

        $result = $this->service->rateTicket($ticket->id, $this->user->id, $this->store->id, 5, 'Excellent!');

        $this->assertTrue($result);
        $fresh = $ticket->fresh();
        $this->assertEquals(5, $fresh->satisfaction_rating);
        $this->assertEquals('Excellent!', $fresh->satisfaction_comment);
    }

    public function test_rate_ticket_succeeds_on_closed_ticket(): void
    {
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium',
            'subject' => 'Rate closed test', 'description' => 'Desc',
        ]);

        $this->service->changeStatus($ticket, TicketStatus::Closed, $this->admin->id);

        $result = $this->service->rateTicket($ticket->id, $this->user->id, $this->store->id, 3, null);

        $this->assertTrue($result);
        $this->assertEquals(3, $ticket->fresh()->satisfaction_rating);
    }

    public function test_rate_ticket_returns_false_for_open_ticket(): void
    {
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium',
            'subject' => 'Not resolved', 'description' => 'Desc',
        ]);

        $result = $this->service->rateTicket($ticket->id, $this->user->id, $this->store->id, 5, null);

        $this->assertFalse($result);
    }

    public function test_rate_ticket_returns_false_for_other_store(): void
    {
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium',
            'subject' => 'Rate IDOR', 'description' => 'Desc',
        ]);
        $this->service->changeStatus($ticket, TicketStatus::Resolved, $this->admin->id);

        $result = $this->service->rateTicket($ticket->id, Str::uuid(), Str::uuid(), 5, null);

        $this->assertFalse($result);
    }

    // ═══════════════════════════════════════════════════════════
    //  getAdminStats
    // ═══════════════════════════════════════════════════════════

    public function test_admin_stats_returns_all_required_fields(): void
    {
        $stats = $this->service->getAdminStats();

        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('open', $stats);
        $this->assertArrayHasKey('in_progress', $stats);
        $this->assertArrayHasKey('unresolved', $stats);
        $this->assertArrayHasKey('sla_breached', $stats);
        $this->assertArrayHasKey('resolved_today', $stats);
        $this->assertArrayHasKey('critical', $stats);
        $this->assertArrayHasKey('unassigned', $stats);
        $this->assertArrayHasKey('avg_response_min', $stats);
        $this->assertArrayHasKey('avg_resolution_min', $stats);
    }

    public function test_admin_stats_critical_counts_only_unresolved_critical_tickets(): void
    {
        // Create critical ticket (unresolved)
        $t1 = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'critical', 'subject' => 'Crit1', 'description' => 'D',
        ]);
        // Create critical ticket (resolved)
        $t2 = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'critical', 'subject' => 'Crit2', 'description' => 'D',
        ]);
        $this->service->changeStatus($t2, TicketStatus::Resolved, $this->admin->id);

        $stats = $this->service->getAdminStats();

        $this->assertEquals(1, $stats['critical']); // Only unresolved critical
    }

    public function test_admin_stats_unassigned_counts_only_unresolved_unassigned_tickets(): void
    {
        // Unassigned open ticket
        $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium', 'subject' => 'Unassigned', 'description' => 'D',
        ]);

        // Assigned open ticket
        $t2 = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium', 'subject' => 'Assigned', 'description' => 'D',
        ]);
        $this->service->assignTicket($t2, $this->admin->id, $this->admin->id);

        $stats = $this->service->getAdminStats();

        $this->assertEquals(1, $stats['unassigned']);
    }

    public function test_admin_stats_avg_response_computed_correctly(): void
    {
        // Create a ticket and set first_response_at manually to 60 minutes after creation
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium', 'subject' => 'Avg response', 'description' => 'D',
        ]);

        // Manually set first_response_at to 60 minutes after created_at
        $ticket->update([
            'first_response_at' => $ticket->created_at->addMinutes(60),
        ]);

        $stats = $this->service->getAdminStats();

        // Average should be ~60 minutes (±2 for any precision issues)
        $this->assertEqualsWithDelta(60, $stats['avg_response_min'], 2);
    }

    // ═══════════════════════════════════════════════════════════
    //  SLA breached scope
    // ═══════════════════════════════════════════════════════════

    public function test_sla_breach_scope_identifies_overdue_open_tickets(): void
    {
        // Create ticket with past SLA deadline
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium', 'subject' => 'Overdue', 'description' => 'D',
        ]);
        $ticket->update(['sla_deadline_at' => now()->subHour()]);

        // Create ticket with future SLA
        $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'high', 'subject' => 'On track', 'description' => 'D',
        ]);

        $breached = SupportTicket::slaBreach()->count();
        $this->assertEquals(1, $breached);
    }

    public function test_sla_breach_scope_excludes_resolved_tickets(): void
    {
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium', 'subject' => 'Resolved', 'description' => 'D',
        ]);
        $ticket->update(['sla_deadline_at' => now()->subHour()]);
        $this->service->changeStatus($ticket, TicketStatus::Resolved, $this->admin->id);

        $breached = SupportTicket::slaBreach()->count();
        $this->assertEquals(0, $breached);
    }

    // ═══════════════════════════════════════════════════════════
    //  Model helpers
    // ═══════════════════════════════════════════════════════════

    public function test_ticket_model_is_sla_breached_returns_correct_boolean(): void
    {
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium', 'subject' => 'SLA', 'description' => 'D',
        ]);

        $ticket->update(['sla_deadline_at' => now()->subMinute()]);
        $this->assertTrue($ticket->fresh()->isSlaBreached());

        $ticket->update(['sla_deadline_at' => now()->addHour()]);
        $this->assertFalse($ticket->fresh()->isSlaBreached());
    }

    public function test_ticket_model_sla_badge_returns_correct_value(): void
    {
        $ticket = $this->service->createTicket($this->user->id, $this->store->id, $this->org->id, [
            'category' => 'technical', 'priority' => 'medium', 'subject' => 'Badge', 'description' => 'D',
        ]);

        // On track
        $ticket->update(['sla_deadline_at' => now()->addHour()]);
        $this->assertEquals('on_track', $ticket->fresh()->sla_badge);

        // Breached
        $ticket->update(['sla_deadline_at' => now()->subMinute()]);
        $this->assertEquals('breached', $ticket->fresh()->sla_badge);

        // Met (resolved)
        $this->service->changeStatus($ticket->fresh(), TicketStatus::Resolved, $this->admin->id);
        $this->assertEquals('met', $ticket->fresh()->sla_badge);
    }

    // ═══════════════════════════════════════════════════════════
    //  Enum tests
    // ═══════════════════════════════════════════════════════════

    public function test_ticket_priority_sla_minutes_are_correct(): void
    {
        $this->assertEquals(30, TicketPriority::Critical->slaFirstResponseMinutes());
        $this->assertEquals(120, TicketPriority::High->slaFirstResponseMinutes());
        $this->assertEquals(480, TicketPriority::Medium->slaFirstResponseMinutes());
        $this->assertEquals(1440, TicketPriority::Low->slaFirstResponseMinutes());

        $this->assertEquals(240, TicketPriority::Critical->slaResolutionMinutes());
        $this->assertEquals(1440, TicketPriority::High->slaResolutionMinutes());
        $this->assertEquals(2880, TicketPriority::Medium->slaResolutionMinutes());
        $this->assertEquals(7200, TicketPriority::Low->slaResolutionMinutes());
    }

    public function test_all_ticket_categories_have_labels_and_colors(): void
    {
        foreach (TicketCategory::cases() as $category) {
            $this->assertNotEmpty($category->label(), "Category {$category->value} must have a label");
            $this->assertNotEmpty($category->color(), "Category {$category->value} must have a color");
        }
    }

    public function test_all_ticket_statuses_have_labels_and_colors(): void
    {
        foreach (TicketStatus::cases() as $status) {
            $this->assertNotEmpty($status->label(), "Status {$status->value} must have a label");
            $this->assertNotEmpty($status->color(), "Status {$status->value} must have a color");
        }
    }

    public function test_all_ticket_priorities_have_labels_colors_and_icons(): void
    {
        foreach (TicketPriority::cases() as $priority) {
            $this->assertNotEmpty($priority->label());
            $this->assertNotEmpty($priority->color());
            $this->assertNotEmpty($priority->icon());
        }
    }
}
