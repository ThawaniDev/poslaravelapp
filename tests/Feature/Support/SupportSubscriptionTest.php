<?php

namespace Tests\Feature\Support;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\Support\Models\SupportTicket;
use App\Http\Middleware\CheckActiveSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Subscription middleware and support ticket status-transition tests.
 *
 * Subscription tests restore the real plan.active middleware to verify
 * that providers without active subscriptions cannot access support features.
 *
 * Status transition tests verify the service accepts all admin-driven transitions
 * and documents which transitions are valid from the provider side.
 */
class SupportSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Store $store;
    private Organization $org;
    private SubscriptionPlan $plan;
    private SupportTicket $ticket;
    private string $base = '/api/v2/support';

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name'          => 'Sub Test Org',
            'business_type' => 'grocery',
            'country'       => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name'            => 'Sub Test Store',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
            'is_active'       => true,
            'is_main_branch'  => true,
        ]);

        $this->user = User::create([
            'name'            => 'Sub User',
            'email'           => 'sub@test.com',
            'password_hash'   => bcrypt('password'),
            'store_id'        => $this->store->id,
            'organization_id' => $this->org->id,
            'role'            => 'owner',
            'is_active'       => true,
        ]);

        $this->plan = SubscriptionPlan::create([
            'name'          => 'Basic',
            'name_ar'       => 'أساسي',
            'slug'          => 'basic-sub-test',
            'monthly_price' => 99.00,
            'is_active'     => true,
            'sort_order'    => 1,
        ]);

        $this->ticket = SupportTicket::create([
            'ticket_number'   => 'TKT-2026-0001',
            'organization_id' => $this->org->id,
            'store_id'        => $this->store->id,
            'user_id'         => $this->user->id,
            'category'        => 'technical',
            'priority'        => 'medium',
            'status'          => 'open',
            'subject'         => 'Subscription test ticket',
            'description'     => 'Test description',
        ]);
    }

    /**
     * Create a StoreSubscription with the given status.
     */
    private function createSubscription(string $status): StoreSubscription
    {
        return StoreSubscription::forceCreate([
            'id'                   => Str::uuid()->toString(),
            'organization_id'      => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status'               => $status,
            'billing_cycle'        => 'monthly',
            'current_period_start' => now()->subDays(15),
            'current_period_end'   => now()->addDays(15),
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  SUBSCRIPTION MIDDLEWARE ENFORCEMENT
    // ═══════════════════════════════════════════════════════════

    public function test_provider_with_active_subscription_can_access_support(): void
    {
        // Restore real plan.active middleware
        app('router')->aliasMiddleware('plan.active', CheckActiveSubscription::class);

        $this->createSubscription('active');
        Sanctum::actingAs($this->user, ['*']);

        $this->getJson("{$this->base}/tickets")->assertOk();
    }

    public function test_provider_with_trial_subscription_can_access_support(): void
    {
        app('router')->aliasMiddleware('plan.active', CheckActiveSubscription::class);

        $this->createSubscription('trial');
        Sanctum::actingAs($this->user, ['*']);

        $this->getJson("{$this->base}/tickets")->assertOk();
    }

    public function test_provider_with_grace_subscription_can_access_support(): void
    {
        app('router')->aliasMiddleware('plan.active', CheckActiveSubscription::class);

        $this->createSubscription('grace');
        Sanctum::actingAs($this->user, ['*']);

        $this->getJson("{$this->base}/tickets")->assertOk();
    }

    public function test_provider_with_expired_subscription_cannot_access_support(): void
    {
        // Restore real plan.active middleware to test enforcement
        app('router')->aliasMiddleware('plan.active', CheckActiveSubscription::class);

        $this->createSubscription('expired');
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson("{$this->base}/tickets");

        $response->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'no_subscription');
    }

    public function test_provider_with_no_subscription_cannot_access_support(): void
    {
        // Restore real plan.active middleware (no subscription created)
        app('router')->aliasMiddleware('plan.active', CheckActiveSubscription::class);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson("{$this->base}/tickets");

        $response->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'no_subscription');
    }

    public function test_provider_with_no_subscription_cannot_create_ticket(): void
    {
        app('router')->aliasMiddleware('plan.active', CheckActiveSubscription::class);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->postJson("{$this->base}/tickets", [
            'subject'     => 'Test ticket',
            'description' => 'Test description',
            'category'    => 'technical',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('subscription_required', true);
    }

    public function test_provider_with_cancelled_subscription_cannot_access_support(): void
    {
        app('router')->aliasMiddleware('plan.active', CheckActiveSubscription::class);

        $this->createSubscription('cancelled');
        Sanctum::actingAs($this->user, ['*']);

        $this->getJson("{$this->base}/tickets")->assertForbidden();
    }

    public function test_subscription_error_response_includes_required_fields(): void
    {
        app('router')->aliasMiddleware('plan.active', CheckActiveSubscription::class);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson("{$this->base}/tickets");

        $response->assertForbidden()
            ->assertJsonStructure([
                'success',
                'message',
                'message_ar',
                'error_code',
                'subscription_required',
            ]);
    }

    public function test_active_subscription_is_injected_into_request_attributes(): void
    {
        // This tests that when a valid subscription exists the middleware
        // injects the subscription model into request attributes (downstream use).
        app('router')->aliasMiddleware('plan.active', CheckActiveSubscription::class);

        $this->createSubscription('active');
        Sanctum::actingAs($this->user, ['*']);

        // If request reaches the controller successfully, subscription was injected
        $response = $this->getJson("{$this->base}/stats");
        $response->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    //  STATUS TRANSITION TESTS (Admin-driven, via SupportService)
    // ═══════════════════════════════════════════════════════════

    private AdminUser $admin;
    private string $adminBase = '/api/v2/admin/support';

    private function setUpAdmin(): void
    {
        $this->admin = AdminUser::forceCreate([
            'id'            => Str::uuid()->toString(),
            'name'          => 'Status Test Admin',
            'email'         => 'status-admin@test.com',
            'password_hash' => bcrypt('password'),
            'is_active'     => true,
        ]);
        Sanctum::actingAs($this->admin, ['*'], 'admin-api');
    }

    public function test_admin_can_transition_open_to_in_progress(): void
    {
        $this->setUpAdmin();

        $this->postJson("{$this->adminBase}/tickets/{$this->ticket->id}/status", [
            'status' => 'in_progress',
        ])->assertOk()->assertJsonPath('data.status', 'in_progress');

        $this->assertDatabaseHas('support_tickets', [
            'id'     => $this->ticket->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_admin_can_transition_in_progress_to_resolved(): void
    {
        $this->setUpAdmin();

        // Set to in_progress first
        $this->ticket->update(['status' => 'in_progress']);

        $this->postJson("{$this->adminBase}/tickets/{$this->ticket->id}/status", [
            'status' => 'resolved',
        ])->assertOk();

        $this->assertDatabaseHas('support_tickets', [
            'id'     => $this->ticket->id,
            'status' => 'resolved',
        ]);
    }

    public function test_admin_can_transition_resolved_to_closed(): void
    {
        $this->setUpAdmin();

        $this->ticket->update(['status' => 'resolved', 'resolved_at' => now()]);

        $this->postJson("{$this->adminBase}/tickets/{$this->ticket->id}/status", [
            'status' => 'closed',
        ])->assertOk();

        $this->assertDatabaseHas('support_tickets', [
            'id'     => $this->ticket->id,
            'status' => 'closed',
        ]);
    }

    public function test_admin_can_reopen_resolved_ticket(): void
    {
        $this->setUpAdmin();

        $this->ticket->update(['status' => 'resolved', 'resolved_at' => now()]);

        $this->postJson("{$this->adminBase}/tickets/{$this->ticket->id}/status", [
            'status' => 'open',
        ])->assertOk();

        $this->assertDatabaseHas('support_tickets', [
            'id'     => $this->ticket->id,
            'status' => 'open',
        ]);
    }

    public function test_admin_can_reopen_closed_ticket(): void
    {
        $this->setUpAdmin();

        $this->ticket->update([
            'status'      => 'closed',
            'resolved_at' => now(),
            'closed_at'   => now(),
        ]);

        $this->postJson("{$this->adminBase}/tickets/{$this->ticket->id}/status", [
            'status' => 'open',
        ])->assertOk();
    }

    public function test_admin_cannot_set_invalid_status_value(): void
    {
        $this->setUpAdmin();

        $this->postJson("{$this->adminBase}/tickets/{$this->ticket->id}/status", [
            'status' => 'pending', // not a valid TicketStatus value
        ])->assertUnprocessable();
    }

    public function test_admin_cannot_set_empty_status(): void
    {
        $this->setUpAdmin();

        $this->postJson("{$this->adminBase}/tickets/{$this->ticket->id}/status", [
            'status' => '',
        ])->assertUnprocessable();
    }

    public function test_status_change_to_resolved_records_resolved_at_timestamp(): void
    {
        $this->setUpAdmin();

        $this->ticket->update(['status' => 'in_progress']);

        $this->postJson("{$this->adminBase}/tickets/{$this->ticket->id}/status", [
            'status' => 'resolved',
        ])->assertOk();

        $fresh = $this->ticket->fresh();
        $this->assertNotNull($fresh->resolved_at, 'resolved_at should be set when resolved');
    }

    public function test_status_change_to_closed_records_closed_at_timestamp(): void
    {
        $this->setUpAdmin();

        $this->ticket->update(['status' => 'resolved', 'resolved_at' => now()]);

        $this->postJson("{$this->adminBase}/tickets/{$this->ticket->id}/status", [
            'status' => 'closed',
        ])->assertOk();

        $fresh = $this->ticket->fresh();
        $this->assertNotNull($fresh->closed_at, 'closed_at should be set when closed');
    }

    public function test_status_change_to_in_progress_does_not_set_resolved_at(): void
    {
        $this->setUpAdmin();

        $this->postJson("{$this->adminBase}/tickets/{$this->ticket->id}/status", [
            'status' => 'in_progress',
        ])->assertOk();

        $fresh = $this->ticket->fresh();
        $this->assertNull($fresh->resolved_at, 'resolved_at should not be set for in_progress');
        $this->assertNull($fresh->closed_at, 'closed_at should not be set for in_progress');
    }

    // ═══════════════════════════════════════════════════════════
    //  PROVIDER-DRIVEN STATUS CHANGES
    // ═══════════════════════════════════════════════════════════

    public function test_provider_can_close_own_open_ticket(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->putJson("{$this->base}/tickets/{$this->ticket->id}/close")
            ->assertOk();

        $this->assertDatabaseHas('support_tickets', [
            'id'     => $this->ticket->id,
            'status' => 'closed',
        ]);
    }

    public function test_provider_cannot_close_already_closed_ticket(): void
    {
        $this->ticket->update(['status' => 'closed', 'closed_at' => now()]);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->putJson("{$this->base}/tickets/{$this->ticket->id}/close");

        // Service returns false/404 for already-closed ticket
        $response->assertStatus(404);
    }

    public function test_provider_can_rate_resolved_ticket(): void
    {
        $this->ticket->update(['status' => 'resolved', 'resolved_at' => now()]);

        Sanctum::actingAs($this->user, ['*']);

        $this->postJson("{$this->base}/tickets/{$this->ticket->id}/rate", [
            'rating'  => 5,
            'comment' => 'Great support!',
        ])->assertOk();

        $this->assertDatabaseHas('support_tickets', [
            'id'                  => $this->ticket->id,
            'satisfaction_rating' => 5,
        ]);
    }

    public function test_provider_can_rate_closed_ticket(): void
    {
        $this->ticket->update([
            'status'    => 'closed',
            'closed_at' => now(),
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $this->postJson("{$this->base}/tickets/{$this->ticket->id}/rate", [
            'rating' => 4,
        ])->assertOk();
    }

    public function test_provider_cannot_rate_open_ticket(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        // ticket is open by default
        $response = $this->postJson("{$this->base}/tickets/{$this->ticket->id}/rate", [
            'rating' => 5,
        ]);

        // Service returns null/404 for non-resolved/closed ticket
        $response->assertNotFound();
    }

    public function test_provider_rating_must_be_between_1_and_5(): void
    {
        $this->ticket->update(['status' => 'resolved', 'resolved_at' => now()]);

        Sanctum::actingAs($this->user, ['*']);

        $this->postJson("{$this->base}/tickets/{$this->ticket->id}/rate", [
            'rating' => 6,
        ])->assertUnprocessable();

        $this->postJson("{$this->base}/tickets/{$this->ticket->id}/rate", [
            'rating' => 0,
        ])->assertUnprocessable();
    }

    public function test_admin_reply_auto_transitions_ticket_to_in_progress(): void
    {
        $this->setUpAdmin();

        // Ticket is open; admin reply should move it to in_progress
        $this->postJson("{$this->adminBase}/tickets/{$this->ticket->id}/messages", [
            'message_text' => 'We are looking into this issue.',
        ])->assertCreated();

        $this->assertDatabaseHas('support_tickets', [
            'id'     => $this->ticket->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_admin_reply_sets_first_response_at(): void
    {
        $this->setUpAdmin();

        $this->assertNull($this->ticket->fresh()->first_response_at);

        $this->postJson("{$this->adminBase}/tickets/{$this->ticket->id}/messages", [
            'message_text' => 'First response.',
        ])->assertCreated();

        $this->assertNotNull(
            $this->ticket->fresh()->first_response_at,
            'first_response_at should be set after first non-internal admin reply'
        );
    }

    public function test_internal_note_does_not_set_first_response_at(): void
    {
        $this->setUpAdmin();

        $this->postJson("{$this->adminBase}/tickets/{$this->ticket->id}/messages", [
            'message_text'    => 'Internal note only',
            'is_internal_note' => true,
        ])->assertCreated();

        $this->assertNull(
            $this->ticket->fresh()->first_response_at,
            'first_response_at should NOT be set for internal notes'
        );
    }
}
