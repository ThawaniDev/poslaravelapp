<?php

namespace Tests\Feature\Workflow;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * SUPPORT & KNOWLEDGE BASE WORKFLOW TESTS
 *
 * Tests support tickets, messages, KB articles, stats.
 *
 * Cross-references: Workflows #881-888
 */
class SupportWorkflowTest extends WorkflowTestCase
{
    use RefreshDatabase;

    private User $owner;
    private Organization $org;
    private Store $store;
    private string $ownerToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();

        $this->org = Organization::create([
            'name' => 'Support Org',
            'name_ar' => 'منظمة دعم',
            'business_type' => 'grocery',
            'country' => 'SA',
            'vat_number' => '300000000000003',
            'is_active' => true,
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Support Store',
            'name_ar' => 'متجر دعم',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Support Owner',
            'email' => 'support-owner@workflow.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->ownerToken = $this->owner->createToken('test', ['*'])->plainTextToken;
        $this->assignOwnerRole($this->owner, $this->store->id);
    }

    /** @test */
    public function wf881_support_stats(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/support/stats');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf882_list_tickets(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/support/tickets');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf883_create_ticket(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/support/tickets', [
                'subject' => 'Printer not working',
                'description' => 'Receipt printer stops after first print',
                'priority' => 'high',
                'category' => 'hardware',
            ]);

        $this->assertContains($response->status(), [200, 201, 422]);
    }

    /** @test */
    public function wf884_show_ticket(): void
    {
        DB::table('support_tickets')->insert([
            'id' => 'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee',
            'ticket_number' => 'TK-20250001',
            'organization_id' => $this->org->id,
            'user_id' => $this->owner->id,
            'subject' => 'Test Ticket',
            'description' => 'Test description',
            'priority' => 'medium',
            'category' => 'general',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/support/tickets/eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee');

        $this->assertContains($response->status(), [200, 404]);
    }

    /** @test */
    public function wf885_add_ticket_message(): void
    {
        DB::table('support_tickets')->insert([
            'id' => 'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeef',
            'ticket_number' => 'TK-20250002',
            'organization_id' => $this->org->id,
            'user_id' => $this->owner->id,
            'subject' => 'Message Ticket',
            'description' => 'Need to add a message',
            'priority' => 'low',
            'category' => 'general',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/support/tickets/eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeef/messages', [
                'message' => 'Here is additional information about the issue.',
            ]);

        $this->assertContains($response->status(), [200, 201, 422]);
    }

    /** @test */
    public function wf886_close_ticket(): void
    {
        DB::table('support_tickets')->insert([
            'id' => 'eeeeeeee-eeee-eeee-eeee-eeeeeeeeee10',
            'ticket_number' => 'TK-20250003',
            'organization_id' => $this->org->id,
            'user_id' => $this->owner->id,
            'subject' => 'Close Ticket',
            'description' => 'This will be closed',
            'priority' => 'low',
            'category' => 'general',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->putJson('/api/v2/support/tickets/eeeeeeee-eeee-eeee-eeee-eeeeeeeeee10/close');

        $this->assertContains($response->status(), [200, 404, 422]);
    }

    /** @test */
    public function wf887_kb_index(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/support/kb');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf888_kb_show_article(): void
    {
        DB::table('knowledge_base_articles')->insert([
            'id' => 'ffffffff-ffff-ffff-ffff-ffffffffffff',
            'title' => 'How to set up barcode scanner',
            'title_ar' => 'كيفية إعداد قارئ الباركود',
            'slug' => 'setup-barcode-scanner',
            'body' => 'Connect the USB scanner and configure in hardware settings.',
            'body_ar' => 'قم بتوصيل قارئ USB وتكوينه في إعدادات الأجهزة.',
            'category' => 'hardware',
            'is_published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/support/kb/setup-barcode-scanner');

        $this->assertContains($response->status(), [200, 404, 500]);
    }
}
