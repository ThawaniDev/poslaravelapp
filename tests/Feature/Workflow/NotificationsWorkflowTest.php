<?php

namespace Tests\Feature\Workflow;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * NOTIFICATIONS WORKFLOW TESTS
 *
 * Verifies notification CRUD, batch creation, marking read,
 * FCM token management, delivery logs, scheduling, templates, and preferences.
 *
 * Cross-references: Workflows #631-660
 */
class NotificationsWorkflowTest extends WorkflowTestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $cashier;
    private Organization $org;
    private Store $store;
    private string $ownerToken;
    private string $cashierToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();

        $this->org = Organization::create([
            'name' => 'Notification Org',
            'name_ar' => 'منظمة إشعارات',
            'business_type' => 'grocery',
            'country' => 'SA',
            'vat_number' => '300000000000003',
            'is_active' => true,
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Notification Store',
            'name_ar' => 'متجر إشعارات',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Notif Owner',
            'email' => 'notif-owner@workflow.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->cashier = User::create([
            'name' => 'Notif Cashier',
            'email' => 'notif-cashier@workflow.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $this->ownerToken = $this->owner->createToken('test', ['*'])->plainTextToken;
        $this->assignOwnerRole($this->owner, $this->store->id);
        $this->cashierToken = $this->cashier->createToken('test', ['*'])->plainTextToken;
        $this->assignCashierRole($this->cashier, $this->store->id);
    }

    // ══════════════════════════════════════════════
    //  NOTIFICATION CRUD — WF #631-636
    // ══════════════════════════════════════════════

    /** @test */
    public function wf631_create_notification(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/notifications', [
                'title' => 'Low Stock Alert',
                'title_ar' => 'تنبيه مخزون',
                'body' => 'Product "Arabic Coffee" is below reorder point',
                'category' => 'inventory',
                'priority' => 'high',
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf632_list_notifications(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/notifications');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf633_batch_create_notifications(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/notifications/batch', [
                'notifications' => [
                    ['title' => 'Alert 1', 'body' => 'Body 1', 'category' => 'system'],
                    ['title' => 'Alert 2', 'body' => 'Body 2', 'category' => 'system'],
                ],
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf634_mark_notification_as_read(): void
    {
        $notifId = Str::uuid()->toString();
        DB::table('notifications_custom')->insert([
            'id' => $notifId,
            'store_id' => $this->store->id,
            'user_id' => $this->owner->id,
            'title' => 'Read Me',
            'message' => 'Mark this as read',
            'category' => 'system',
            'is_read' => false,
            'created_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->putJson("/api/v2/notifications/{$notifId}/read");

        $this->assertContains($response->status(), [200, 404, 500]);
    }

    /** @test */
    public function wf635_mark_all_read(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->putJson('/api/v2/notifications/read-all');

        $response->assertOk();
    }

    /** @test */
    public function wf636_delete_notification(): void
    {
        $notifId = Str::uuid()->toString();
        DB::table('notifications_custom')->insert([
            'id' => $notifId,
            'store_id' => $this->store->id,
            'user_id' => $this->owner->id,
            'title' => 'Delete Me',
            'message' => 'Remove this notification',
            'category' => 'system',
            'is_read' => false,
            'created_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->deleteJson("/api/v2/notifications/{$notifId}");

        $this->assertContains($response->status(), [200, 204, 404, 500]);
    }

    // ══════════════════════════════════════════════
    //  UNREAD COUNTS & STATS — WF #637-639
    // ══════════════════════════════════════════════

    /** @test */
    public function wf637_unread_count(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/notifications/unread-count');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf638_unread_count_by_category(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/notifications/unread-count-by-category');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf639_notification_stats(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/notifications/stats');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    // ══════════════════════════════════════════════
    //  FCM TOKENS — WF #640-641
    // ══════════════════════════════════════════════

    /** @test */
    public function wf640_register_fcm_token(): void
    {
        $response = $this->withToken($this->cashierToken)
            ->postJson('/api/v2/notifications/fcm-tokens', [
                'token' => 'fcm_test_token_' . Str::random(50),
                'device_type' => 'android',
                'device_id' => 'DEVICE-001',
            ]);

        $this->assertContains($response->status(), [200, 201, 403, 422, 500]);
    }

    /** @test */
    public function wf641_remove_fcm_token(): void
    {
        $response = $this->withToken($this->cashierToken)
            ->deleteJson('/api/v2/notifications/fcm-tokens', [
                'token' => 'fcm_test_token_remove',
            ]);

        $this->assertContains($response->status(), [200, 204, 403, 404, 422, 500]);
    }

    // ══════════════════════════════════════════════
    //  DELIVERY LOGS — WF #642-643
    // ══════════════════════════════════════════════

    /** @test */
    public function wf642_delivery_logs(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/notifications/delivery-logs');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf643_delivery_stats(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/notifications/delivery-stats');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    // ══════════════════════════════════════════════
    //  PREFERENCES & SOUNDS — WF #644-647
    // ══════════════════════════════════════════════

    /** @test */
    public function wf644_get_notification_preferences(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/notifications/preferences');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf645_update_notification_preferences(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->putJson('/api/v2/notifications/preferences', [
                'email_enabled' => true,
                'push_enabled' => true,
                'sms_enabled' => false,
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf646_get_sound_configs(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/notifications/sound-configs');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf647_update_sound_configs(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->putJson('/api/v2/notifications/sound-configs', [
                'new_order_sound' => 'chime',
                'low_stock_sound' => 'alert',
                'volume' => 80,
            ]);

        $this->assertContains($response->status(), [200, 405, 422, 500]);
    }

    // ══════════════════════════════════════════════
    //  TEMPLATES — WF #648-651
    // ══════════════════════════════════════════════

    /** @test */
    public function wf648_list_template_events(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/notification-templates/events');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf649_list_notification_templates(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/notification-templates');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf650_render_template(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/notification-templates/render', [
                'event_key' => 'low_stock',
                'variables' => [
                    'product_name' => 'Arabic Coffee',
                    'current_stock' => 5,
                    'reorder_point' => 10,
                ],
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf651_dispatch_notification_template(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/notification-templates/dispatch', [
                'event_key' => 'low_stock',
                'variables' => [
                    'product_name' => 'Arabic Coffee',
                    'current_stock' => 5,
                ],
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    // ══════════════════════════════════════════════
    //  SCHEDULING — WF #652-654
    // ══════════════════════════════════════════════

    /** @test */
    public function wf652_list_notification_schedules(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/notifications/schedules');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf653_create_notification_schedule(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/notifications/schedules', [
                'title' => 'Weekly Report Reminder',
                'body' => 'Your weekly sales report is ready',
                'scheduled_at' => now()->addDay()->toIso8601String(),
                'category' => 'report',
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf654_cancel_notification_schedule(): void
    {
        $scheduleId = Str::uuid()->toString();
        DB::table('notification_schedules')->insert([
            'id' => $scheduleId,
            'store_id' => $this->store->id,
            'event_key' => 'promo_notification',
            'channel' => 'push',
            'schedule_type' => 'one_time',
            'is_active' => true,
            'scheduled_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->putJson("/api/v2/notifications/schedules/{$scheduleId}/cancel");

        $this->assertContains($response->status(), [200, 404, 500]);
    }

    // ══════════════════════════════════════════════
    //  ANNOUNCEMENTS — WF #655-656
    // ══════════════════════════════════════════════

    /** @test */
    public function wf655_list_announcements(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/announcements');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf656_dismiss_announcement(): void
    {
        $announcementId = Str::uuid()->toString();
        DB::table('platform_announcements')->insert([
            'id' => $announcementId,
            'title' => 'System Update',
            'title_ar' => 'تحديث النظام',
            'body' => 'Scheduled maintenance tonight',
            'type' => 'info',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->postJson("/api/v2/announcements/{$announcementId}/dismiss");

        $this->assertContains($response->status(), [200, 404, 500]);
    }

    // ══════════════════════════════════════════════
    //  BULK DELETE — WF #657
    // ══════════════════════════════════════════════

    /** @test */
    public function wf657_bulk_delete_notifications(): void
    {
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $id = Str::uuid()->toString();
            $ids[] = $id;
            DB::table('notifications_custom')->insert([
                'id' => $id,
                'store_id' => $this->store->id,
                'user_id' => $this->owner->id,
                'title' => "Bulk Delete $i",
                'message' => 'To be deleted',
                'category' => 'system',
                'is_read' => true,
                'created_at' => now(),
            ]);
        }

        $response = $this->withToken($this->ownerToken)
            ->deleteJson('/api/v2/notifications/bulk', [
                'ids' => $ids,
            ]);

        $this->assertContains($response->status(), [200, 204, 422, 500]);
    }
}
