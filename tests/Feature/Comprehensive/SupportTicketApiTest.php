<?php

namespace Tests\Feature\Comprehensive;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupportTicketApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        auth()->forgetGuards();

        $this->org = Organization::create([
            'name' => 'Support Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Support Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Owner',
            'email' => 'support-owner@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;
    }

    // ─── Helpers ─────────────────────────────────────────────

    private function createTicket(array $overrides = []): string
    {
        $id = Str::uuid()->toString();
        DB::table('support_tickets')->insert(array_merge([
            'id' => $id,
            'ticket_number' => 'TKT-' . strtoupper(Str::random(6)),
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'category' => 'technical',
            'priority' => 'medium',
            'status' => 'open',
            'subject' => 'POS not printing receipts',
            'description' => 'The receipt printer stopped working after the last update.',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
        return $id;
    }

    private function createKbArticle(array $overrides = []): string
    {
        $id = Str::uuid()->toString();
        DB::table('knowledge_base_articles')->insert(array_merge([
            'id' => $id,
            'title' => 'How to configure receipt printer',
            'title_ar' => 'كيفية إعداد طابعة الإيصالات',
            'slug' => 'configure-receipt-printer-' . Str::random(4),
            'body' => 'Step 1: Connect the printer via USB...',
            'body_ar' => 'الخطوة 1: قم بتوصيل الطابعة عبر USB...',
            'category' => 'pos_usage',
            'is_published' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
        return $id;
    }

    // ─── Create Ticket ───────────────────────────────────────

    public function test_can_create_support_ticket(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/support/tickets', [
                'category' => 'technical',
                'priority' => 'high',
                'subject' => 'Barcode scanner not working',
                'description' => 'The barcode scanner stopped scanning after firmware update.',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('support_tickets', [
            'user_id' => $this->user->id,
            'category' => 'technical',
            'priority' => 'high',
        ]);
    }

    public function test_create_ticket_requires_subject_and_description(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/support/tickets', [
                'category' => 'technical',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['subject', 'description']);
    }

    public function test_create_ticket_requires_valid_category(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/support/tickets', [
                'category' => 'invalid_category',
                'subject' => 'Test',
                'description' => 'Test desc',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['category']);
    }

    public function test_create_ticket_accepts_all_valid_categories(): void
    {
        $categories = ['billing', 'technical', 'zatca', 'feature_request', 'general', 'hardware'];

        foreach ($categories as $category) {
            $response = $this->withToken($this->token)
                ->postJson('/api/v2/support/tickets', [
                    'category' => $category,
                    'subject' => "Ticket for {$category}",
                    'description' => "Testing {$category} category ticket.",
                ]);

            $response->assertCreated();
        }
    }

    public function test_create_ticket_validates_priority(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/support/tickets', [
                'category' => 'general',
                'priority' => 'invalid',
                'subject' => 'Test',
                'description' => 'Test',
            ]);

        $response->assertUnprocessable();
    }

    public function test_create_ticket_defaults_priority_to_medium(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/support/tickets', [
                'category' => 'general',
                'subject' => 'Default priority test',
                'description' => 'No priority specified.',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('support_tickets', [
            'user_id' => $this->user->id,
            'subject' => 'Default priority test',
            'priority' => 'medium',
        ]);
    }

    // ─── List Tickets ────────────────────────────────────────

    public function test_can_list_tickets(): void
    {
        $this->createTicket();
        $this->createTicket(['ticket_number' => 'TKT-BBB001', 'subject' => 'Second ticket']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/support/tickets');

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_can_filter_tickets_by_status(): void
    {
        $this->createTicket(['status' => 'open']);
        $this->createTicket(['ticket_number' => 'TKT-CCC001', 'status' => 'closed']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/support/tickets?status=open');

        $response->assertOk();
    }

    public function test_can_filter_tickets_by_category(): void
    {
        $this->createTicket(['category' => 'billing']);
        $this->createTicket(['ticket_number' => 'TKT-DDD001', 'category' => 'technical']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/support/tickets?category=billing');

        $response->assertOk();
    }

    public function test_can_filter_tickets_by_priority(): void
    {
        $this->createTicket(['priority' => 'critical']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/support/tickets?priority=critical');

        $response->assertOk();
    }

    // ─── Show Ticket ─────────────────────────────────────────

    public function test_can_show_ticket(): void
    {
        $ticketId = $this->createTicket();

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/support/tickets/{$ticketId}");

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_show_returns_404_for_missing_ticket(): void
    {
        $fakeId = Str::uuid()->toString();

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/support/tickets/{$fakeId}");

        $response->assertNotFound();
    }

    // ─── Add Message ─────────────────────────────────────────

    public function test_can_add_message_to_ticket(): void
    {
        $ticketId = $this->createTicket();

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/support/tickets/{$ticketId}/messages", [
                'message' => 'I tried restarting the printer but it still does not work.',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('support_ticket_messages', [
            'support_ticket_id' => $ticketId,
            'sender_id' => $this->user->id,
        ]);
    }

    public function test_add_message_requires_message_text(): void
    {
        $ticketId = $this->createTicket();

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/support/tickets/{$ticketId}/messages", []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['message']);
    }

    public function test_add_message_returns_404_for_missing_ticket(): void
    {
        $fakeId = Str::uuid()->toString();

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/support/tickets/{$fakeId}/messages", [
                'message' => 'Orphan message',
            ]);

        $response->assertNotFound();
    }

    // ─── Close Ticket ────────────────────────────────────────

    public function test_can_close_ticket(): void
    {
        $ticketId = $this->createTicket();

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/support/tickets/{$ticketId}/close");

        $response->assertOk();
        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticketId,
            'status' => 'closed',
        ]);
    }

    public function test_close_returns_404_for_nonexistent_ticket(): void
    {
        $fakeId = Str::uuid()->toString();

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/support/tickets/{$fakeId}/close");

        $response->assertNotFound();
    }

    // ─── Stats ───────────────────────────────────────────────

    public function test_can_get_support_stats(): void
    {
        $this->createTicket(['status' => 'open']);
        $this->createTicket(['ticket_number' => 'TKT-EEE001', 'status' => 'closed']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/support/stats');

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    // ─── Knowledge Base ──────────────────────────────────────

    public function test_can_list_kb_articles(): void
    {
        $this->createKbArticle();

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/support/kb');

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_can_filter_kb_articles_by_category(): void
    {
        $this->createKbArticle(['category' => 'pos_usage']);
        $this->createKbArticle([
            'slug' => 'billing-article',
            'title' => 'How billing works',
            'category' => 'billing',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/support/kb?category=pos_usage');

        $response->assertOk();
    }

    public function test_can_search_kb_articles(): void
    {
        $this->createKbArticle(['title' => 'How to connect barcode scanner']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/support/kb?search=barcode');

        $response->assertOk();
    }

    public function test_can_show_kb_article_by_slug(): void
    {
        $this->createKbArticle(['slug' => 'setup-printer']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/support/kb/setup-printer');

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_kb_article_returns_404_for_unknown_slug(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/support/kb/nonexistent-slug');

        $response->assertNotFound();
    }

    public function test_kb_does_not_show_unpublished_articles(): void
    {
        $this->createKbArticle(['slug' => 'draft-article', 'is_published' => false]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/support/kb/draft-article');

        $response->assertNotFound();
    }

    // ─── Auth Required ───────────────────────────────────────

    public function test_support_endpoints_require_auth(): void
    {
        $response = $this->getJson('/api/v2/support/tickets');
        $response->assertUnauthorized();

        $response = $this->getJson('/api/v2/support/stats');
        $response->assertUnauthorized();
    }

    // ─── Full Ticket Lifecycle ───────────────────────────────

    public function test_full_ticket_lifecycle(): void
    {
        // Create
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/support/tickets', [
                'category' => 'technical',
                'priority' => 'high',
                'subject' => 'Cash drawer not opening',
                'description' => 'The cash drawer stopped opening after shift change.',
            ]);
        $response->assertCreated();
        $ticketId = $response->json('data.id');

        // Add message
        $response = $this->withToken($this->token)
            ->postJson("/api/v2/support/tickets/{$ticketId}/messages", [
                'message' => 'Update: Only happens on register 2.',
            ]);
        $response->assertCreated();

        // View ticket
        $response = $this->withToken($this->token)
            ->getJson("/api/v2/support/tickets/{$ticketId}");
        $response->assertOk();

        // Close
        $response = $this->withToken($this->token)
            ->putJson("/api/v2/support/tickets/{$ticketId}/close");
        $response->assertOk();

        // Verify closed
        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticketId,
            'status' => 'closed',
        ]);
    }
}
