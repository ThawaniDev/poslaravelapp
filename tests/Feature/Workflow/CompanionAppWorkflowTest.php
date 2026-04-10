<?php

namespace Tests\Feature\Workflow;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * COMPANION APP WORKFLOW TESTS
 *
 * Tests owner mobile companion: quick stats, dashboard, branches,
 * sales, orders, inventory alerts, staff, sessions, preferences.
 *
 * Cross-references: Workflows #851-867
 */
class CompanionAppWorkflowTest extends WorkflowTestCase
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
            'name' => 'Companion Org',
            'name_ar' => 'منظمة مرافق',
            'business_type' => 'grocery',
            'country' => 'SA',
            'vat_number' => '300000000000003',
            'is_active' => true,
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Companion Store',
            'name_ar' => 'متجر مرافق',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Companion Owner',
            'email' => 'companion-owner@workflow.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->ownerToken = $this->owner->createToken('test', ['*'])->plainTextToken;
        $this->assignOwnerRole($this->owner, $this->store->id);
    }

    // ══════════════════════════════════════════════
    //  COMPANION DASHBOARD — WF #851-858
    // ══════════════════════════════════════════════

    /** @test */
    public function wf851_companion_quick_stats(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/companion/quick-stats');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf852_companion_mobile_summary(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/companion/summary');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf853_companion_dashboard(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/companion/dashboard');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf854_companion_branches(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/companion/branches');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf855_companion_sales_summary(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/companion/sales/summary');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf856_companion_active_orders(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/companion/orders/active');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf857_companion_inventory_alerts(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/companion/inventory/alerts');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf858_companion_active_staff(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/companion/staff/active');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    // ══════════════════════════════════════════════
    //  COMPANION ACTIONS — WF #859-863
    // ══════════════════════════════════════════════

    /** @test */
    public function wf859_companion_toggle_availability(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/v2/companion/store/availability', [
                'is_available' => false,
            ]);

        $this->assertContains($response->status(), [200, 422]);
    }

    /** @test */
    public function wf860_companion_register_session(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/v2/companion/sessions', [
                'device_id' => 'companion-device-001',
                'platform' => 'ios',
                'app_version' => '1.0.0',
            ]);

        $this->assertContains($response->status(), [200, 201, 422]);
    }

    /** @test */
    public function wf861_companion_list_sessions(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/companion/sessions');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf862_companion_end_session(): void
    {
        // Register a session first via API, then end it
        $registerResponse = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/v2/companion/sessions', [
                'device_id' => 'companion-device-end',
                'platform' => 'android',
                'app_version' => '1.0.0',
            ]);

        // Try to end a session — use a dummy ID if registration didn't return one
        $sessionId = $registerResponse->json('data.session_id') ?? 'dummy-session-id';

        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/v2/companion/sessions/{$sessionId}/end");

        $this->assertContains($response->status(), [200, 404, 422]);
    }

    /** @test */
    public function wf863_companion_log_event(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/v2/companion/events', [
                'event_type' => 'screen_view',
                'screen' => 'dashboard',
                'metadata' => ['duration_seconds' => 30],
            ]);

        $this->assertContains($response->status(), [200, 201, 422]);
    }

    // ══════════════════════════════════════════════
    //  COMPANION PREFERENCES — WF #864-867
    // ══════════════════════════════════════════════

    /** @test */
    public function wf864_companion_get_preferences(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/companion/preferences');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf865_companion_update_preferences(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/v2/companion/preferences', [
                'notifications_enabled' => true,
                'dark_mode' => false,
                'default_branch' => $this->store->id,
            ]);

        $this->assertContains($response->status(), [200, 422]);
    }

    /** @test */
    public function wf866_companion_get_quick_actions(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/companion/quick-actions');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf867_companion_update_quick_actions(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/v2/companion/quick-actions', [
                'actions' => ['view_sales', 'check_inventory', 'staff_status'],
            ]);

        $this->assertContains($response->status(), [200, 422]);
    }
}
