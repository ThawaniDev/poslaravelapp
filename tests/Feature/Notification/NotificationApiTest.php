<?php

namespace Tests\Feature\Notification;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Notification\Models\FcmToken;
use App\Domain\Notification\Models\NotificationCustom;
use App\Domain\Notification\Models\UserNotificationPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;

    private User $otherUser;
    private Organization $otherOrg;
    private Store $otherStore;
    private string $otherToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Notification Org',
            'business_type' => 'retail',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Main Store',
            'business_type' => 'retail',
            'currency' => 'OMR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Admin',
            'email' => 'admin@notif.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;

        // Other user for isolation tests
        $this->otherOrg = Organization::create([
            'name' => 'Other Org',
            'business_type' => 'retail',
            'country' => 'OM',
        ]);
        $this->otherStore = Store::create([
            'organization_id' => $this->otherOrg->id,
            'name' => 'Other Store',
            'business_type' => 'retail',
            'currency' => 'OMR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);
        $this->otherUser = User::create([
            'name' => 'Other',
            'email' => 'other@notif.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->otherStore->id,
            'organization_id' => $this->otherOrg->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $this->otherToken = $this->otherUser->createToken('test', ['*'])->plainTextToken;
    }

    // ─── Authentication ──────────────────────────────────

    public function test_endpoints_require_authentication(): void
    {
        $endpoints = [
            ['GET', '/api/v2/notifications'],
            ['POST', '/api/v2/notifications'],
            ['GET', '/api/v2/notifications/unread-count'],
            ['PUT', '/api/v2/notifications/read-all'],
            ['GET', '/api/v2/notifications/preferences'],
            ['PUT', '/api/v2/notifications/preferences'],
            ['POST', '/api/v2/notifications/fcm-tokens'],
        ];

        foreach ($endpoints as [$method, $url]) {
            $response = $this->json($method, $url);
            $response->assertStatus(401);
        }
    }

    // ─── List Notifications ──────────────────────────────

    public function test_list_returns_empty_when_no_notifications(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/notifications');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data');
    }

    public function test_list_returns_user_notifications_newest_first(): void
    {
        $this->createNotification(['title' => 'First', 'created_at' => '2024-01-01 10:00:00']);
        $this->createNotification(['title' => 'Second', 'created_at' => '2024-01-02 10:00:00']);
        $this->createNotification(['title' => 'Third', 'created_at' => '2024-01-03 10:00:00']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/notifications');

        $response->assertOk()
            ->assertJsonCount(3, 'data');

        $titles = array_column($response->json('data'), 'title');
        $this->assertEquals(['Third', 'Second', 'First'], $titles);
    }

    public function test_list_filters_by_category(): void
    {
        $this->createNotification(['category' => 'order']);
        $this->createNotification(['category' => 'order']);
        $this->createNotification(['category' => 'system']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/notifications?category=order');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_list_filters_by_read_status(): void
    {
        $this->createNotification(['is_read' => false]);
        $this->createNotification(['is_read' => true]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/notifications?is_read=0');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
        $this->assertFalse($response->json('data.0.is_read'));
    }

    public function test_list_respects_limit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createNotification(['title' => "Notif $i"]);
        }

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/notifications?limit=3');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_list_user_isolation(): void
    {
        $this->createNotification(['title' => 'My Notification']);
        $this->createNotification(['title' => 'Other User', 'user_id' => $this->otherUser->id]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/notifications');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'My Notification');
    }

    // ─── Create Notification ─────────────────────────────

    public function test_create_notification(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/notifications', [
                'category' => 'order',
                'title' => 'New Order',
                'message' => 'You have a new order #123',
                'action_url' => '/orders/123',
                'reference_type' => 'order',
                'reference_id' => Str::uuid()->toString(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.category', 'order')
            ->assertJsonPath('data.title', 'New Order')
            ->assertJsonPath('data.is_read', false);

        $this->assertDatabaseCount('notifications_custom', 1);
    }

    public function test_create_notification_requires_category(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/notifications', [
                'title' => 'No Category',
                'message' => 'Missing required category',
            ]);

        $response->assertStatus(422);
    }

    public function test_create_notification_validates_category_values(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/notifications', [
                'category' => 'invalid_category',
                'title' => 'Bad Category',
                'message' => 'Invalid category value',
            ]);

        $response->assertStatus(422);
    }

    // ─── Unread Count ────────────────────────────────────

    public function test_unread_count_returns_zero_when_empty(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/notifications/unread-count');

        $response->assertOk()
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_unread_count_only_counts_unread(): void
    {
        $this->createNotification(['is_read' => false]);
        $this->createNotification(['is_read' => false]);
        $this->createNotification(['is_read' => true]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/notifications/unread-count');

        $response->assertOk()
            ->assertJsonPath('data.unread_count', 2);
    }

    public function test_unread_count_user_isolation(): void
    {
        $this->createNotification(['is_read' => false]);
        $this->createNotification(['is_read' => false, 'user_id' => $this->otherUser->id]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/notifications/unread-count');

        $response->assertOk()
            ->assertJsonPath('data.unread_count', 1);
    }

    // ─── Mark As Read ────────────────────────────────────

    public function test_mark_single_notification_as_read(): void
    {
        $notif = $this->createNotification(['is_read' => false]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/notifications/{$notif->id}/read");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue(
            NotificationCustom::find($notif->id)->is_read
        );
    }

    public function test_mark_as_read_returns_404_for_other_user_notification(): void
    {
        $notif = $this->createNotification(['user_id' => $this->otherUser->id]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/notifications/{$notif->id}/read");

        $response->assertStatus(404);
    }

    public function test_mark_all_as_read(): void
    {
        $this->createNotification(['is_read' => false]);
        $this->createNotification(['is_read' => false]);
        $this->createNotification(['is_read' => true]);

        $response = $this->withToken($this->token)
            ->putJson('/api/v2/notifications/read-all');

        $response->assertOk()
            ->assertJsonPath('data.marked_count', 2);

        $unreadCount = NotificationCustom::forUser($this->user->id)->unread()->count();
        $this->assertEquals(0, $unreadCount);
    }

    // ─── Delete Notification ─────────────────────────────

    public function test_delete_notification(): void
    {
        $notif = $this->createNotification();

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/notifications/{$notif->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('notifications_custom', ['id' => $notif->id]);
    }

    public function test_delete_returns_404_for_other_user_notification(): void
    {
        $notif = $this->createNotification(['user_id' => $this->otherUser->id]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/notifications/{$notif->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('notifications_custom', ['id' => $notif->id]);
    }

    // ─── Preferences ─────────────────────────────────────

    public function test_get_default_preferences_when_none_set(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/notifications/preferences');

        $response->assertOk()
            ->assertJsonPath('data.user_id', $this->user->id);

        // Should return default preferences
        $prefs = $response->json('data.preferences');
        $this->assertArrayHasKey('order_updates', $prefs);
        $this->assertArrayHasKey('promotions', $prefs);
        $this->assertArrayHasKey('inventory_alerts', $prefs);
        $this->assertArrayHasKey('system_updates', $prefs);
    }

    public function test_update_preferences(): void
    {
        $response = $this->withToken($this->token)
            ->putJson('/api/v2/notifications/preferences', [
                'preferences' => [
                    'order_updates' => ['in_app' => true, 'push' => false],
                    'promotions' => ['in_app' => false, 'push' => false],
                ],
                'quiet_hours_start' => '22:00',
                'quiet_hours_end' => '07:00',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.quiet_hours_start', '22:00')
            ->assertJsonPath('data.quiet_hours_end', '07:00');

        $prefs = $response->json('data.preferences');
        $this->assertFalse($prefs['order_updates']['push']);
        $this->assertFalse($prefs['promotions']['in_app']);
    }

    public function test_update_preferences_persists(): void
    {
        $this->withToken($this->token)
            ->putJson('/api/v2/notifications/preferences', [
                'preferences' => [
                    'order_updates' => ['in_app' => true, 'push' => true],
                ],
                'quiet_hours_start' => '23:00',
                'quiet_hours_end' => '06:00',
            ]);

        // Retrieve and verify persistence
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/notifications/preferences');

        $response->assertOk()
            ->assertJsonPath('data.quiet_hours_start', '23:00')
            ->assertJsonPath('data.quiet_hours_end', '06:00');

        $prefs = $response->json('data.preferences');
        $this->assertTrue($prefs['order_updates']['push']);
    }

    public function test_preferences_validates_quiet_hours_format(): void
    {
        $response = $this->withToken($this->token)
            ->putJson('/api/v2/notifications/preferences', [
                'quiet_hours_start' => 'invalid',
            ]);

        $response->assertStatus(422);
    }

    public function test_preferences_user_isolation(): void
    {
        // User sets preferences
        $this->withToken($this->token)
            ->putJson('/api/v2/notifications/preferences', [
                'quiet_hours_start' => '22:00',
                'quiet_hours_end' => '07:00',
            ]);

        // Reset auth guard to avoid caching between requests
        $this->app['auth']->forgetGuards();

        // Other user should get defaults
        $response = $this->withToken($this->otherToken)
            ->getJson('/api/v2/notifications/preferences');

        $response->assertOk();
        $this->assertEquals($this->otherUser->id, $response->json('data.user_id'));
        $this->assertNull($response->json('data.quiet_hours_start'));
    }

    // ─── FCM Tokens ──────────────────────────────────────

    public function test_register_fcm_token(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/notifications/fcm-tokens', [
                'token' => 'fcm_token_abc123',
                'device_type' => 'ios',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.token', 'fcm_token_abc123')
            ->assertJsonPath('data.device_type', 'ios');

        $this->assertDatabaseHas('fcm_tokens', [
            'user_id' => $this->user->id,
            'token' => 'fcm_token_abc123',
        ]);
    }

    public function test_register_fcm_token_idempotent(): void
    {
        // Register same token twice
        $this->withToken($this->token)
            ->postJson('/api/v2/notifications/fcm-tokens', [
                'token' => 'same_token_xyz',
                'device_type' => 'android',
            ]);

        $this->withToken($this->token)
            ->postJson('/api/v2/notifications/fcm-tokens', [
                'token' => 'same_token_xyz',
                'device_type' => 'ios',  // Update device type
            ]);

        // Should only have one record
        $this->assertEquals(
            1,
            FcmToken::where('user_id', $this->user->id)
                ->where('token', 'same_token_xyz')
                ->count()
        );

        // Should have updated device_type
        $this->assertDatabaseHas('fcm_tokens', [
            'token' => 'same_token_xyz',
            'device_type' => 'ios',
        ]);
    }

    public function test_register_fcm_token_requires_token(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/notifications/fcm-tokens', [
                'device_type' => 'ios',
            ]);

        $response->assertStatus(422);
    }

    public function test_register_fcm_token_validates_device_type(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/notifications/fcm-tokens', [
                'token' => 'fcm_token_123',
                'device_type' => 'windows',
            ]);

        $response->assertStatus(422);
    }

    public function test_remove_fcm_token(): void
    {
        // Register via API first
        $this->withToken($this->token)
            ->postJson('/api/v2/notifications/fcm-tokens', [
                'token' => 'token_to_remove',
                'device_type' => 'android',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('fcm_tokens', [
            'user_id' => $this->user->id,
            'token' => 'token_to_remove',
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v2/notifications/fcm-tokens', [
                'token' => 'token_to_remove',
            ]);

        $response->assertOk();
        $this->assertDatabaseMissing('fcm_tokens', [
            'user_id' => $this->user->id,
            'token' => 'token_to_remove',
        ]);
    }

    public function test_remove_fcm_token_not_found(): void
    {
        $response = $this->withToken($this->token)
            ->deleteJson('/api/v2/notifications/fcm-tokens', [
                'token' => 'nonexistent_token',
            ]);

        $response->assertStatus(404);
    }

    public function test_remove_fcm_token_user_isolation(): void
    {
        FcmToken::forceCreate([
            'id' => Str::uuid()->toString(),
            'user_id' => $this->otherUser->id,
            'token' => 'other_user_token',
            'device_type' => 'ios',
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v2/notifications/fcm-tokens', [
                'token' => 'other_user_token',
            ]);

        $response->assertStatus(404);
        $this->assertDatabaseHas('fcm_tokens', ['token' => 'other_user_token']);
    }

    // ─── Full Workflow ───────────────────────────────────

    public function test_full_notification_workflow(): void
    {
        // 1. Create a notification
        $create = $this->withToken($this->token)
            ->postJson('/api/v2/notifications', [
                'category' => 'order',
                'title' => 'New Order',
                'message' => 'Order #456 received',
            ]);
        $create->assertStatus(201);
        $notifId = $create->json('data.id');

        // 2. Check unread count
        $this->withToken($this->token)
            ->getJson('/api/v2/notifications/unread-count')
            ->assertJsonPath('data.unread_count', 1);

        // 3. List notifications
        $this->withToken($this->token)
            ->getJson('/api/v2/notifications')
            ->assertJsonCount(1, 'data');

        // 4. Mark as read
        $this->withToken($this->token)
            ->putJson("/api/v2/notifications/{$notifId}/read")
            ->assertOk();

        // 5. Unread count should be 0
        $this->withToken($this->token)
            ->getJson('/api/v2/notifications/unread-count')
            ->assertJsonPath('data.unread_count', 0);

        // 6. Delete
        $this->withToken($this->token)
            ->deleteJson("/api/v2/notifications/{$notifId}")
            ->assertOk();

        // 7. List should be empty
        $this->withToken($this->token)
            ->getJson('/api/v2/notifications')
            ->assertJsonCount(0, 'data');
    }

    // ─── Helpers ─────────────────────────────────────────

    private function createNotification(array $overrides = []): NotificationCustom
    {
        return NotificationCustom::forceCreate(array_merge([
            'id' => Str::uuid()->toString(),
            'user_id' => $this->user->id,
            'store_id' => $this->store->id,
            'category' => 'order',
            'title' => 'Test Notification',
            'message' => 'This is a test notification',
            'is_read' => false,
            'created_at' => now(),
        ], $overrides));
    }
}
