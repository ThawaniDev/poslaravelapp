<?php

namespace Tests\Feature\Notification;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Notification\Models\NotificationCustom;
use App\Domain\Notification\Services\NotificationDispatcher;
use App\Domain\Notification\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Integration tests for the full notification flow:
 *  - Observer triggers dispatcher → in-app notification is created
 *  - Permission levels (view / manage / schedules)
 *  - Delivery logs and stats endpoints
 *  - User stats endpoint
 *  - Quiet hours preference enforcement (API round-trip)
 */
class NotificationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $manager;
    private User $cashier;
    private Organization $org;
    private Store $store;
    private string $ownerToken;
    private string $managerToken;
    private string $cashierToken;

    protected function tearDown(): void
    {
        // Restore bypass middleware so subsequent test classes are not affected
        app('router')->aliasMiddleware('permission', \App\Http\Middleware\BypassPermissionMiddleware::class);
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Restore real permission middleware for this test class
        app('router')->aliasMiddleware('permission', \App\Http\Middleware\CheckPermission::class);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        Mail::fake();

        $this->org = Organization::create([
            'name' => 'Integration Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@integration.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $this->ownerToken = $this->owner->createToken('test')->plainTextToken;

        // Manager has notifications.view + notifications.manage (not schedules)
        $this->manager = User::create([
            'name' => 'Manager',
            'email' => 'manager@integration.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'branch_manager',
            'is_active' => true,
        ]);
        $this->managerToken = $this->manager->createToken('test')->plainTextToken;
        $this->grantPermissions($this->manager, ['notifications.view', 'notifications.manage']);

        // Cashier has only notifications.view
        $this->cashier = User::create([
            'name' => 'Cashier',
            'email' => 'cashier@integration.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);
        $this->cashierToken = $this->cashier->createToken('test')->plainTextToken;
        $this->grantPermissions($this->cashier, ['notifications.view']);
    }

    /**
     * Helper: grant Spatie permissions to a non-owner user via a temporary role.
     */
    private function grantPermissions(User $user, array $permissionNames): void
    {
        $guard = 'sanctum';

        $role = Role::create([
            'name' => 'test_role_' . $user->id,
            'guard_name' => $guard,
            'store_id' => $this->store->id,
        ]);

        foreach ($permissionNames as $name) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
            $role->givePermissionTo($permission);
        }

        $user->assignRole($role);
    }

    // ─── Permission Level Tests ──────────────────────────────

    /** @test */
    public function owner_can_access_all_notification_endpoints(): void
    {
        // view tier
        $this->getJson('/api/v2/notifications', ['Authorization' => "Bearer {$this->ownerToken}"])
            ->assertStatus(200);

        // manage tier
        $this->getJson('/api/v2/notifications/preferences', ['Authorization' => "Bearer {$this->ownerToken}"])
            ->assertStatus(200);

        // schedules tier
        $this->getJson('/api/v2/notifications/schedules', ['Authorization' => "Bearer {$this->ownerToken}"])
            ->assertStatus(200);
    }

    /** @test */
    public function cashier_can_access_view_endpoints(): void
    {
        $this->getJson('/api/v2/notifications', ['Authorization' => "Bearer {$this->cashierToken}"])
            ->assertStatus(200);

        $this->getJson('/api/v2/notifications/unread-count', ['Authorization' => "Bearer {$this->cashierToken}"])
            ->assertStatus(200);

        $this->getJson('/api/v2/notifications/stats', ['Authorization' => "Bearer {$this->cashierToken}"])
            ->assertStatus(200);
    }

    /** @test */
    public function cashier_cannot_access_manage_endpoints(): void
    {
        // GET preferences
        $this->getJson('/api/v2/notifications/preferences', ['Authorization' => "Bearer {$this->cashierToken}"])
            ->assertStatus(403);

        // GET delivery-logs
        $this->getJson('/api/v2/notifications/delivery-logs', ['Authorization' => "Bearer {$this->cashierToken}"])
            ->assertStatus(403);

        // DELETE notification
        $notif = NotificationCustom::create([
            'user_id' => $this->cashier->id,
            'store_id' => $this->store->id,
            'category' => 'order',
            'title' => 'Test',
            'message' => 'Body',
            'priority' => 'normal',
        ]);
        $this->deleteJson("/api/v2/notifications/{$notif->id}", [], ['Authorization' => "Bearer {$this->cashierToken}"])
            ->assertStatus(403);
    }

    /** @test */
    public function cashier_cannot_access_schedule_endpoints(): void
    {
        $this->getJson('/api/v2/notifications/schedules', ['Authorization' => "Bearer {$this->cashierToken}"])
            ->assertStatus(403);

        $this->postJson('/api/v2/notifications/schedules', [
            'channel' => 'in_app',
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'category' => 'system',
            'title' => 'Test',
            'message' => 'Body',
        ], ['Authorization' => "Bearer {$this->cashierToken}"])
            ->assertStatus(403);
    }

    /** @test */
    public function manager_can_access_manage_endpoints_but_not_schedules(): void
    {
        $this->getJson('/api/v2/notifications/preferences', ['Authorization' => "Bearer {$this->managerToken}"])
            ->assertStatus(200);

        $this->getJson('/api/v2/notifications/delivery-logs', ['Authorization' => "Bearer {$this->managerToken}"])
            ->assertStatus(200);

        // But cannot access schedules
        $this->getJson('/api/v2/notifications/schedules', ['Authorization' => "Bearer {$this->managerToken}"])
            ->assertStatus(403);
    }

    // ─── Dispatcher → In-App Notification ───────────────────

    /** @test */
    public function dispatcher_creates_in_app_notification_for_owner(): void
    {
        $dispatcher = app(NotificationDispatcher::class);

        $dispatcher->quickSend(
            userId: $this->owner->id,
            title: 'New Order',
            body: 'Order #123 received.',
            category: 'order',
        );

        $this->assertDatabaseHas('notifications_custom', [
            'user_id' => $this->owner->id,
            'title' => 'New Order',
            'message' => 'Order #123 received.',
            'category' => 'order',
        ]);
    }

    /** @test */
    public function api_lists_notification_created_by_dispatcher(): void
    {
        $dispatcher = app(NotificationDispatcher::class);
        $dispatcher->quickSend(
            userId: $this->owner->id,
            title: 'Stock Alert',
            body: 'Coffee is low.',
            category: 'inventory',
        );

        $response = $this->getJson('/api/v2/notifications', ['Authorization' => "Bearer {$this->ownerToken}"]);
        $response->assertStatus(200);
        $response->assertJsonFragment(['title' => 'Stock Alert']);
    }

    /** @test */
    public function dispatched_notification_appears_in_unread_count(): void
    {
        $dispatcher = app(NotificationDispatcher::class);
        $dispatcher->quickSend(
            userId: $this->owner->id,
            title: 'Unread Test',
            body: 'Not yet read',
            category: 'system',
        );

        $response = $this->getJson('/api/v2/notifications/unread-count', ['Authorization' => "Bearer {$this->ownerToken}"]);
        $response->assertStatus(200)
            ->assertJsonPath('data.unread_count', 1);
    }

    /** @test */
    public function marking_notification_as_read_removes_it_from_unread_count(): void
    {
        // Create 2 notifications
        $n1 = NotificationCustom::create([
            'user_id' => $this->owner->id,
            'store_id' => $this->store->id,
            'category' => 'order',
            'title' => 'N1',
            'message' => 'M1',
            'priority' => 'normal',
        ]);
        NotificationCustom::create([
            'user_id' => $this->owner->id,
            'store_id' => $this->store->id,
            'category' => 'order',
            'title' => 'N2',
            'message' => 'M2',
            'priority' => 'normal',
        ]);

        $before = $this->getJson('/api/v2/notifications/unread-count', ['Authorization' => "Bearer {$this->ownerToken}"]);
        $before->assertJsonPath('data.unread_count', 2);

        $this->putJson("/api/v2/notifications/{$n1->id}/read", [], ['Authorization' => "Bearer {$this->ownerToken}"])
            ->assertStatus(200);

        $after = $this->getJson('/api/v2/notifications/unread-count', ['Authorization' => "Bearer {$this->ownerToken}"]);
        $after->assertJsonPath('data.unread_count', 1);
    }

    // ─── Delivery Logs & Stats ───────────────────────────────

    /** @test */
    public function delivery_logs_endpoint_returns_paginated_list(): void
    {
        $response = $this->getJson('/api/v2/notifications/delivery-logs', ['Authorization' => "Bearer {$this->ownerToken}"]);
        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }

    /** @test */
    public function delivery_stats_endpoint_returns_counts(): void
    {
        $response = $this->getJson('/api/v2/notifications/delivery-stats', ['Authorization' => "Bearer {$this->ownerToken}"]);
        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }

    // ─── User Stats ───────────────────────────────────────────

    /** @test */
    public function stats_endpoint_returns_user_notification_counts(): void
    {
        NotificationCustom::create([
            'user_id' => $this->owner->id,
            'store_id' => $this->store->id,
            'category' => 'order',
            'title' => 'T1',
            'message' => 'M1',
            'priority' => 'normal',
            'is_read' => true,
            'read_at' => now(),
        ]);
        NotificationCustom::create([
            'user_id' => $this->owner->id,
            'store_id' => $this->store->id,
            'category' => 'inventory',
            'title' => 'T2',
            'message' => 'M2',
            'priority' => 'high',
        ]);

        $response = $this->getJson('/api/v2/notifications/stats', ['Authorization' => "Bearer {$this->ownerToken}"]);
        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['total', 'unread', 'read']]);

        $data = $response->json('data');
        $this->assertEquals(2, $data['total']);
        $this->assertEquals(1, $data['unread']);
        $this->assertEquals(1, $data['read']);
    }

    // ─── Preference Quiet Hours ───────────────────────────────

    /** @test */
    public function preferences_quiet_hours_stored_and_retrieved_correctly(): void
    {
        $this->putJson('/api/v2/notifications/preferences', [
            'quiet_hours_start' => '22:00',
            'quiet_hours_end' => '07:00',
        ], ['Authorization' => "Bearer {$this->ownerToken}"])->assertStatus(200);

        $get = $this->getJson('/api/v2/notifications/preferences', ['Authorization' => "Bearer {$this->ownerToken}"]);
        $get->assertStatus(200)
            ->assertJsonPath('data.quiet_hours_start', '22:00')
            ->assertJsonPath('data.quiet_hours_end', '07:00');
    }

    /** @test */
    public function preferences_quiet_hours_validates_time_format(): void
    {
        $this->putJson('/api/v2/notifications/preferences', [
            'quiet_hours_start' => '25:99',  // invalid
        ], ['Authorization' => "Bearer {$this->ownerToken}"])->assertStatus(422);
    }

    // ─── Schedules ───────────────────────────────────────────

    /** @test */
    public function schedule_requires_scheduled_at_to_be_in_future(): void
    {
        $this->postJson('/api/v2/notifications/schedules', [
            'channel' => 'in_app',
            'scheduled_at' => now()->subHour()->toIso8601String(), // past
            'category' => 'system',
            'title' => 'Test',
            'message' => 'Body',
        ], ['Authorization' => "Bearer {$this->ownerToken}"])
            ->assertStatus(422);
    }

    /** @test */
    public function owner_can_create_and_cancel_schedule(): void
    {
        $create = $this->postJson('/api/v2/notifications/schedules', [
            'event_key' => 'system.maintenance',
            'channel' => 'in_app',
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'schedule_type' => 'once',
            'category' => 'system',
            'title' => 'Scheduled Alert',
            'message' => 'This is scheduled.',
        ], ['Authorization' => "Bearer {$this->ownerToken}"]);

        $create->assertStatus(201);
        $scheduleId = $create->json('data.id');

        $cancel = $this->putJson("/api/v2/notifications/schedules/{$scheduleId}/cancel", [], ['Authorization' => "Bearer {$this->ownerToken}"]);
        $cancel->assertStatus(200);

        $this->assertDatabaseHas('notification_schedules', [
            'id' => $scheduleId,
            'is_active' => false,
        ]);
    }

    /** @test */
    public function cancel_nonexistent_schedule_returns_404(): void
    {
        $fakeId = \Illuminate\Support\Str::uuid()->toString();
        $this->putJson("/api/v2/notifications/schedules/{$fakeId}/cancel", [], ['Authorization' => "Bearer {$this->ownerToken}"])
            ->assertStatus(404);
    }

    // ─── Sound Config ─────────────────────────────────────────

    /** @test */
    public function owner_can_update_and_retrieve_sound_config(): void
    {
        $this->putJson('/api/v2/notifications/sound-configs/order.new', [
            'is_enabled' => true,
            'sound_file' => 'chime.mp3',
            'volume' => 80,
        ], ['Authorization' => "Bearer {$this->ownerToken}"])->assertStatus(200);

        $get = $this->getJson('/api/v2/notifications/sound-configs', ['Authorization' => "Bearer {$this->ownerToken}"]);
        $get->assertStatus(200);

        $configs = $get->json('data');
        $found = collect($configs)->firstWhere('event_key', 'order.new');
        $this->assertNotNull($found);
        $this->assertEquals('chime.mp3', $found['sound_file'] ?? null);
    }

    // ─── FCM Tokens ───────────────────────────────────────────

    /** @test */
    public function cashier_can_register_fcm_token(): void
    {
        $this->postJson('/api/v2/notifications/fcm-tokens', [
            'token' => 'cashier-fcm-device-token',
            'device_type' => 'android',
        ], ['Authorization' => "Bearer {$this->cashierToken}"])
            ->assertStatus(201);

        $this->assertDatabaseHas('fcm_tokens', [
            'user_id' => $this->cashier->id,
            'token' => 'cashier-fcm-device-token',
        ]);
    }

    /** @test */
    public function cashier_can_remove_own_fcm_token(): void
    {
        $this->postJson('/api/v2/notifications/fcm-tokens', [
            'token' => 'remove-me-token',
            'device_type' => 'ios',
        ], ['Authorization' => "Bearer {$this->cashierToken}"]);

        $this->deleteJson('/api/v2/notifications/fcm-tokens', [
            'token' => 'remove-me-token',
        ], ['Authorization' => "Bearer {$this->cashierToken}"])
            ->assertStatus(200);

        $this->assertDatabaseMissing('fcm_tokens', ['token' => 'remove-me-token']);
    }

    // ─── Unread By Category ───────────────────────────────────

    /** @test */
    public function unread_count_by_category_returns_breakdown(): void
    {
        foreach (['order', 'order', 'inventory'] as $cat) {
            NotificationCustom::create([
                'user_id' => $this->owner->id,
                'store_id' => $this->store->id,
                'category' => $cat,
                'title' => 'T',
                'message' => 'M',
                'priority' => 'normal',
            ]);
        }

        $response = $this->getJson('/api/v2/notifications/unread-count-by-category', ['Authorization' => "Bearer {$this->ownerToken}"]);
        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);

        $data = $response->json('data');
        // unreadCountByCategory returns { category => count } keyed object
        $this->assertEquals(2, $data['order'] ?? 0);
        $this->assertEquals(1, $data['inventory'] ?? 0);
    }
}
