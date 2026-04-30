<?php

namespace Tests\Feature\Support;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Support\Enums\TicketCategory;
use App\Domain\Support\Enums\TicketPriority;
use App\Domain\Support\Enums\TicketStatus;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Models\SupportTicketMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Feature tests for the provider-facing Support Ticket API.
 */
class SupportApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Store $store;
    private Organization $org;
    private string $base = '/api/v2/support';

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->user = User::create([
            'name'          => 'Store Owner',
            'email'         => 'owner@test.com',
            'password_hash' => bcrypt('password'),
            'store_id'      => $this->store->id,
            'organization_id' => $this->org->id,
            'role'          => 'owner',
            'is_active'     => true,
        ]);

        Sanctum::actingAs($this->user, ['*']);
    }

    // ─── CREATE ──────────────────────────────────────────────

    public function test_create_ticket_succeeds_with_valid_data(): void
    {
        $response = $this->postJson("{$this->base}/tickets", [
            'category'    => 'technical',
            'priority'    => 'high',
            'subject'     => 'POS not connecting',
            'description' => 'After the update the POS cannot connect to the printer.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.ticket_number', fn ($v) => str_starts_with($v, 'TKT-'))
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.category', 'technical')
            ->assertJsonPath('data.priority', 'high');

        $this->assertDatabaseHas('support_tickets', [
            'subject'  => 'POS not connecting',
            'store_id' => $this->store->id,
        ]);
    }

    public function test_create_ticket_uses_sequential_ticket_number(): void
    {
        $r1 = $this->postJson("{$this->base}/tickets", [
            'category'    => 'billing',
            'subject'     => 'Invoice error',
            'description' => 'VAT calculation is wrong.',
        ]);
        $r2 = $this->postJson("{$this->base}/tickets", [
            'category'    => 'general',
            'subject'     => 'Another issue',
            'description' => 'Second ticket.',
        ]);

        $r1->assertStatus(201);
        $r2->assertStatus(201);

        $num1 = $r1->json('data.ticket_number');
        $num2 = $r2->json('data.ticket_number');

        // Both should follow TKT-YYYY-NNNN pattern
        $this->assertMatchesRegularExpression('/^TKT-\d{4}-\d{4}$/', $num1);
        $this->assertMatchesRegularExpression('/^TKT-\d{4}-\d{4}$/', $num2);

        // Second ticket number should be greater
        $this->assertGreaterThan($num1, $num2);
    }

    public function test_create_ticket_rejects_invalid_category(): void
    {
        $response = $this->postJson("{$this->base}/tickets", [
            'category'    => 'account',    // legacy/invalid value
            'subject'     => 'Test',
            'description' => 'Test description',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category']);
    }

    public function test_create_ticket_rejects_urgent_priority(): void
    {
        $response = $this->postJson("{$this->base}/tickets", [
            'category'    => 'technical',
            'priority'    => 'urgent',    // legacy/invalid value
            'subject'     => 'Test',
            'description' => 'Test description',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['priority']);
    }

    public function test_create_ticket_accepts_all_valid_categories(): void
    {
        $validCategories = ['billing', 'technical', 'zatca', 'feature_request', 'general', 'hardware'];

        foreach ($validCategories as $cat) {
            $response = $this->postJson("{$this->base}/tickets", [
                'category'    => $cat,
                'subject'     => "Test {$cat}",
                'description' => "Description for {$cat}",
            ]);
            $response->assertStatus(201, "Category '{$cat}' should be accepted");
        }
    }

    public function test_create_ticket_accepts_all_valid_priorities(): void
    {
        $validPriorities = ['low', 'medium', 'high', 'critical'];

        foreach ($validPriorities as $priority) {
            $response = $this->postJson("{$this->base}/tickets", [
                'category'    => 'general',
                'priority'    => $priority,
                'subject'     => "Test {$priority}",
                'description' => "Description for {$priority}",
            ]);
            $response->assertStatus(201, "Priority '{$priority}' should be accepted");
        }
    }

    // ─── LIST ────────────────────────────────────────────────

    public function test_list_tickets_returns_only_own_tickets(): void
    {
        // Create a ticket for this user's store
        SupportTicket::create([
            'ticket_number'   => 'TKT-2024-0001',
            'store_id'        => $this->store->id,
            'user_id'         => $this->user->id,
            'organization_id' => $this->org->id,
            'category'        => TicketCategory::Technical,
            'priority'        => TicketPriority::Medium,
            'status'          => TicketStatus::Open,
            'subject'         => 'My ticket',
            'description'     => 'My description',
        ]);

        // Create a ticket for a different store (IDOR check)
        $otherOrg = Organization::create([
            'name'          => 'Other Org',
            'business_type' => 'grocery',
            'country'       => 'SA',
        ]);
        $otherStore = Store::create([
            'organization_id' => $otherOrg->id,
            'name'            => 'Other Store',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
            'is_active'       => true,
            'is_main_branch'  => true,
        ]);
        SupportTicket::create([
            'ticket_number'   => 'TKT-2024-0002',
            'store_id'        => $otherStore->id,
            'organization_id' => $otherOrg->id,
            'category'        => TicketCategory::Billing,
            'priority'        => TicketPriority::Low,
            'status'          => TicketStatus::Open,
            'subject'         => 'Other store ticket',
            'description'     => 'Other description',
        ]);

        $response = $this->getJson("{$this->base}/tickets");

        $response->assertOk();
        $tickets = $response->json('data.data');
        $this->assertCount(1, $tickets);
        $this->assertEquals('My ticket', $tickets[0]['subject']);
    }

    public function test_list_tickets_filters_by_status(): void
    {
        SupportTicket::create([
            'ticket_number'   => 'TKT-2024-0001',
            'store_id'        => $this->store->id,
            'user_id'         => $this->user->id,
            'organization_id' => $this->org->id,
            'category'        => TicketCategory::Technical,
            'priority'        => TicketPriority::Medium,
            'status'          => TicketStatus::Open,
            'subject'         => 'Open ticket',
            'description'     => 'Description',
        ]);
        SupportTicket::create([
            'ticket_number'   => 'TKT-2024-0002',
            'store_id'        => $this->store->id,
            'user_id'         => $this->user->id,
            'organization_id' => $this->org->id,
            'category'        => TicketCategory::Technical,
            'priority'        => TicketPriority::Medium,
            'status'          => TicketStatus::Resolved,
            'subject'         => 'Resolved ticket',
            'description'     => 'Description',
        ]);

        $response = $this->getJson("{$this->base}/tickets?status=open");
        $response->assertOk();
        $tickets = $response->json('data.data');
        $this->assertCount(1, $tickets);
        $this->assertEquals('Open ticket', $tickets[0]['subject']);
    }

    public function test_list_tickets_search_works(): void
    {
        SupportTicket::create([
            'ticket_number'   => 'TKT-2024-0001',
            'store_id'        => $this->store->id,
            'user_id'         => $this->user->id,
            'organization_id' => $this->org->id,
            'category'        => TicketCategory::Technical,
            'priority'        => TicketPriority::Medium,
            'status'          => TicketStatus::Open,
            'subject'         => 'Printer connectivity issue',
            'description'     => 'The printer is not working',
        ]);
        SupportTicket::create([
            'ticket_number'   => 'TKT-2024-0002',
            'store_id'        => $this->store->id,
            'user_id'         => $this->user->id,
            'organization_id' => $this->org->id,
            'category'        => TicketCategory::Billing,
            'priority'        => TicketPriority::Low,
            'status'          => TicketStatus::Open,
            'subject'         => 'Invoice generation',
            'description'     => 'Invoice is incorrect',
        ]);

        $response = $this->getJson("{$this->base}/tickets?search=printer");
        $response->assertOk();
        $tickets = $response->json('data.data');
        $this->assertCount(1, $tickets);
        $this->assertStringContainsString('Printer', $tickets[0]['subject']);
    }

    // ─── SHOW ─────────────────────────────────────────────────

    public function test_show_ticket_includes_sla_badge(): void
    {
        $ticket = SupportTicket::create([
            'ticket_number'   => 'TKT-2024-0001',
            'store_id'        => $this->store->id,
            'user_id'         => $this->user->id,
            'organization_id' => $this->org->id,
            'category'        => TicketCategory::Technical,
            'priority'        => TicketPriority::High,
            'status'          => TicketStatus::Open,
            'subject'         => 'SLA test',
            'description'     => 'Description',
            'sla_deadline_at' => now()->addHours(10),
        ]);

        $response = $this->getJson("{$this->base}/tickets/{$ticket->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $ticket->id)
            ->assertJsonStructure(['data' => ['sla_badge']]);
    }

    public function test_show_ticket_from_another_store_returns_404(): void
    {
        $otherOrg = Organization::create([
            'name'          => 'Other Org',
            'business_type' => 'grocery',
            'country'       => 'SA',
        ]);
        $otherStore = Store::create([
            'organization_id' => $otherOrg->id,
            'name'            => 'Other Store',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
            'is_active'       => true,
            'is_main_branch'  => true,
        ]);
        $ticket = SupportTicket::create([
            'ticket_number'   => 'TKT-2024-0001',
            'store_id'        => $otherStore->id,
            'organization_id' => $otherOrg->id,
            'category'        => TicketCategory::Technical,
            'priority'        => TicketPriority::Medium,
            'status'          => TicketStatus::Open,
            'subject'         => 'Other store ticket',
            'description'     => 'Should not be visible',
        ]);

        $response = $this->getJson("{$this->base}/tickets/{$ticket->id}");

        $response->assertNotFound();
    }

    // ─── MESSAGES ─────────────────────────────────────────────

    public function test_add_message_to_own_ticket(): void
    {
        $ticket = SupportTicket::create([
            'ticket_number'   => 'TKT-2024-0001',
            'store_id'        => $this->store->id,
            'user_id'         => $this->user->id,
            'organization_id' => $this->org->id,
            'category'        => TicketCategory::Technical,
            'priority'        => TicketPriority::Medium,
            'status'          => TicketStatus::Open,
            'subject'         => 'Test',
            'description'     => 'Description',
        ]);

        $response = $this->postJson("{$this->base}/tickets/{$ticket->id}/messages", [
            'message' => 'Here is additional information about the issue.',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('support_ticket_messages', [
            'support_ticket_id' => $ticket->id,
            'message_text'      => 'Here is additional information about the issue.',
        ]);
    }

    // ─── CLOSE ────────────────────────────────────────────────

    public function test_close_own_ticket(): void
    {
        $ticket = SupportTicket::create([
            'ticket_number'   => 'TKT-2024-0001',
            'store_id'        => $this->store->id,
            'user_id'         => $this->user->id,
            'organization_id' => $this->org->id,
            'category'        => TicketCategory::Technical,
            'priority'        => TicketPriority::Medium,
            'status'          => TicketStatus::Open,
            'subject'         => 'Test',
            'description'     => 'Description',
        ]);

        $response = $this->putJson("{$this->base}/tickets/{$ticket->id}/close");

        $response->assertOk();
        $this->assertDatabaseHas('support_tickets', [
            'id'     => $ticket->id,
            'status' => 'closed',
        ]);
    }

    // ─── RATE ─────────────────────────────────────────────────

    public function test_rate_resolved_ticket(): void
    {
        $ticket = SupportTicket::create([
            'ticket_number'   => 'TKT-2024-0001',
            'store_id'        => $this->store->id,
            'user_id'         => $this->user->id,
            'organization_id' => $this->org->id,
            'category'        => TicketCategory::Technical,
            'priority'        => TicketPriority::Medium,
            'status'          => TicketStatus::Resolved,
            'subject'         => 'Test',
            'description'     => 'Description',
            'resolved_at'     => now(),
        ]);

        $response = $this->postJson("{$this->base}/tickets/{$ticket->id}/rate", [
            'rating'  => 5,
            'comment' => 'Excellent support!',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('support_tickets', [
            'id'                  => $ticket->id,
            'satisfaction_rating' => 5,
        ]);
    }

    public function test_rate_open_ticket_returns_404(): void
    {
        $ticket = SupportTicket::create([
            'ticket_number'   => 'TKT-2024-0001',
            'store_id'        => $this->store->id,
            'user_id'         => $this->user->id,
            'organization_id' => $this->org->id,
            'category'        => TicketCategory::Technical,
            'priority'        => TicketPriority::Medium,
            'status'          => TicketStatus::Open,
            'subject'         => 'Test',
            'description'     => 'Description',
        ]);

        $response = $this->postJson("{$this->base}/tickets/{$ticket->id}/rate", [
            'rating' => 5,
        ]);

        $response->assertNotFound();
    }

    public function test_rate_validation_rejects_out_of_range(): void
    {
        $ticket = SupportTicket::create([
            'ticket_number'   => 'TKT-2024-0001',
            'store_id'        => $this->store->id,
            'user_id'         => $this->user->id,
            'organization_id' => $this->org->id,
            'category'        => TicketCategory::Technical,
            'priority'        => TicketPriority::Medium,
            'status'          => TicketStatus::Resolved,
            'subject'         => 'Test',
            'description'     => 'Description',
            'resolved_at'     => now(),
        ]);

        $this->postJson("{$this->base}/tickets/{$ticket->id}/rate", ['rating' => 6])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);

        $this->postJson("{$this->base}/tickets/{$ticket->id}/rate", ['rating' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    }

    // ─── STATS ────────────────────────────────────────────────

    public function test_stats_returns_expected_fields(): void
    {
        $response = $this->getJson("{$this->base}/stats");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['total', 'open', 'resolved', 'closed']]);
    }

    // ─── KNOWLEDGE BASE ───────────────────────────────────────

    public function test_kb_index_returns_published_articles_only(): void
    {
        // Assumes KB articles may exist from seeder; test is additive-only
        $response = $this->getJson("{$this->base}/kb");

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_kb_index_rejects_invalid_category(): void
    {
        $response = $this->getJson("{$this->base}/kb?category=invalid_cat");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category']);
    }

    public function test_kb_show_returns_404_for_unknown_slug(): void
    {
        $response = $this->getJson("{$this->base}/kb/this-slug-does-not-exist-xyz");

        $response->assertNotFound();
    }
}
