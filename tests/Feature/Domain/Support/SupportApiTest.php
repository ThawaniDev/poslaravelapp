<?php

namespace Tests\Feature\Domain\Support;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Support\Models\SupportTicket;
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
            'business_type' => 'retail',
            'country'       => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $org->id,
            'name'            => 'Test Store',
            'business_type'   => 'retail',
            'currency'        => 'OMR',
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
            'business_type' => 'retail',
            'country'       => 'OM',
        ]);

        $otherStore = Store::create([
            'organization_id' => $otherOrg->id,
            'name'            => 'Other Store',
            'business_type'   => 'retail',
            'currency'        => 'OMR',
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
}
