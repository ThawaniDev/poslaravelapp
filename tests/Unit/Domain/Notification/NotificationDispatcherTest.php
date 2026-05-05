<?php

namespace Tests\Unit\Domain\Notification;

use App\Domain\Notification\Models\FcmToken;
use App\Domain\Notification\Models\NotificationCustom;
use App\Domain\Notification\Models\NotificationTemplate;
use App\Domain\Notification\Services\FcmService;
use App\Domain\Notification\Services\NotificationDispatcher;
use App\Domain\Notification\Services\NotificationTemplateService;
use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Unit tests for NotificationDispatcher.
 *
 * Covers:
 * - toUser()  dispatch: template-based title/body, fallback title, FCM call, in-app record
 * - toStore() dispatch: all users in store receive notification
 * - toStoreOwner(): only owner receives notification
 * - quickSend(): direct title/body bypass
 * - Critical event auto-email
 * - In-app notification record correctness
 */
class NotificationDispatcherTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $cashier;
    private Organization $org;
    private Store $store;
    private FcmService $mockFcm;
    private NotificationDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Dispatcher Test Org',
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
            'email' => 'owner@disp.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->cashier = User::create([
            'name' => 'Cashier',
            'email' => 'cashier@disp.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        // Mock FcmService so no real HTTP calls
        $this->mockFcm = $this->createMock(FcmService::class);
        $this->mockFcm->method('sendToUser')->willReturn(['success' => 1, 'failure' => 0]);
        $this->mockFcm->method('sendToUsers')->willReturn(['success' => 2, 'failure' => 0]);
        $this->mockFcm->method('sendToStore')->willReturn(['success' => 1, 'failure' => 0]);

        $this->dispatcher = new NotificationDispatcher(
            $this->mockFcm,
            new NotificationTemplateService(),
        );

        Mail::fake();
    }

    // ─── toUser ─────────────────────────────────────────────

    /** @test */
    public function it_creates_in_app_notification_for_user(): void
    {
        $this->dispatcher->toUser(
            userId: $this->owner->id,
            eventKey: 'order.new',
            variables: ['order_id' => '123', 'total' => '500', 'store_name' => 'Test', 'branch_name' => 'Main'],
            category: 'order',
        );

        $this->assertDatabaseHas('notifications_custom', [
            'user_id' => $this->owner->id,
            'category' => 'order',
        ]);
    }

    /** @test */
    public function it_uses_template_title_when_template_exists(): void
    {
        NotificationTemplate::forceCreate([
            'event_key' => 'order.new',
            'channel' => NotificationChannel::Push,
            'title' => 'New Order {{order_id}}',
            'title_ar' => 'طلب جديد {{order_id}}',
            'body' => 'Total: {{total}}',
            'body_ar' => 'المجموع: {{total}}',
            'available_variables' => ['order_id', 'total'],
            'is_active' => true,
        ]);

        $this->dispatcher->toUser(
            userId: $this->owner->id,
            eventKey: 'order.new',
            variables: ['order_id' => 'ORD-999', 'total' => '750'],
        );

        $notification = NotificationCustom::where('user_id', $this->owner->id)->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('ORD-999', $notification->title);
        $this->assertStringContainsString('750', $notification->message);
    }

    /** @test */
    public function it_falls_back_to_event_description_when_no_template(): void
    {
        // No template in DB; dispatcher should use event catalog description
        $this->dispatcher->toUser(
            userId: $this->owner->id,
            eventKey: 'order.cancelled',
            variables: ['order_id' => 'X1', 'total' => '100', 'reason' => 'OOS', 'store_name' => 'S'],
        );

        $notification = NotificationCustom::where('user_id', $this->owner->id)->first();
        $this->assertNotNull($notification);
        // Title should be the event description from catalog
        $this->assertNotEmpty($notification->title);
    }

    /** @test */
    public function it_calls_fcm_for_user(): void
    {
        $this->mockFcm->expects($this->once())
            ->method('sendToUser')
            ->with($this->owner->id, $this->anything(), $this->anything(), $this->anything());

        $this->dispatcher->toUser(
            userId: $this->owner->id,
            eventKey: 'inventory.low_stock',
            variables: ['product_name' => 'Coffee', 'current_qty' => '2', 'reorder_point' => '10', 'store_name' => 'S'],
        );
    }

    /** @test */
    public function it_sets_correct_category_from_event_key(): void
    {
        $this->dispatcher->toUser(
            userId: $this->owner->id,
            eventKey: 'finance.daily_summary',
            variables: ['date' => '2026-05-05', 'total_sales' => '1000', 'total_transactions' => '50', 'store_name' => 'S'],
        );

        $this->assertDatabaseHas('notifications_custom', [
            'user_id' => $this->owner->id,
            'category' => 'finance',
        ]);
    }

    /** @test */
    public function it_sends_email_for_critical_events(): void
    {
        $this->dispatcher->toUser(
            userId: $this->owner->id,
            eventKey: 'order.payment_failed',
            variables: ['order_id' => 'ORD-FAIL', 'total' => '200', 'store_name' => 'S'],
        );

        // Critical events should always email
        Mail::assertQueued(\App\Domain\Notification\Mail\NotificationMail::class);
    }

    /** @test */
    public function it_does_not_email_for_non_critical_events_without_flag(): void
    {
        $this->dispatcher->toUser(
            userId: $this->owner->id,
            eventKey: 'order.completed',
            variables: ['order_id' => 'ORD-DONE', 'total' => '100', 'store_name' => 'S'],
            alsoEmail: false,
        );

        Mail::assertNotQueued(\App\Domain\Notification\Mail\NotificationMail::class);
    }

    /** @test */
    public function it_sends_email_when_alsoEmail_is_true(): void
    {
        $this->dispatcher->toUser(
            userId: $this->owner->id,
            eventKey: 'order.completed',
            variables: ['order_id' => 'ORD-DONE', 'total' => '100', 'store_name' => 'S'],
            alsoEmail: true,
        );

        Mail::assertQueued(\App\Domain\Notification\Mail\NotificationMail::class);
    }

    /** @test */
    public function it_sets_priority_on_in_app_notification(): void
    {
        $this->dispatcher->toUser(
            userId: $this->owner->id,
            eventKey: 'order.new',
            variables: ['order_id' => '1', 'total' => '50', 'store_name' => 'S', 'branch_name' => 'B'],
            priority: 'high',
        );

        $this->assertDatabaseHas('notifications_custom', [
            'user_id' => $this->owner->id,
            'priority' => 'high',
        ]);
    }

    /** @test */
    public function it_sets_reference_fields_on_in_app_notification(): void
    {
        $orderId = \Illuminate\Support\Str::uuid()->toString();

        $this->dispatcher->toUser(
            userId: $this->owner->id,
            eventKey: 'order.new',
            variables: ['order_id' => $orderId, 'total' => '50', 'store_name' => 'S', 'branch_name' => 'B'],
            referenceId: $orderId,
            referenceType: 'order',
        );

        $this->assertDatabaseHas('notifications_custom', [
            'user_id' => $this->owner->id,
            'reference_type' => 'order',
            'reference_id' => $orderId,
        ]);
    }

    // ─── toStore ──────────────────────────────────────────────

    /** @test */
    public function it_creates_notifications_for_all_store_users(): void
    {
        $this->dispatcher->toStore(
            storeId: $this->store->id,
            eventKey: 'system.printer_error',
            variables: ['printer_name' => 'Epson1', 'store_name' => 'Test Store'],
        );

        // Both owner and cashier should have notifications
        $this->assertDatabaseHas('notifications_custom', ['user_id' => $this->owner->id]);
        $this->assertDatabaseHas('notifications_custom', ['user_id' => $this->cashier->id]);
    }

    /** @test */
    public function it_does_not_notify_inactive_store_users(): void
    {
        $inactive = User::create([
            'name' => 'Inactive',
            'email' => 'inactive@disp.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'cashier',
            'is_active' => false,
        ]);

        $this->dispatcher->toStore(
            storeId: $this->store->id,
            eventKey: 'system.printer_error',
            variables: ['printer_name' => 'Epson1', 'store_name' => 'Test Store'],
        );

        // Active users (owner + cashier) notified; inactive user is not
        $this->assertDatabaseHas('notifications_custom', ['user_id' => $this->owner->id]);
        $this->assertDatabaseMissing('notifications_custom', ['user_id' => $inactive->id]);
    }

    // ─── toStoreOwner ─────────────────────────────────────────

    /** @test */
    public function it_notifies_only_the_store_owner(): void
    {
        $this->dispatcher->toStoreOwner(
            storeId: $this->store->id,
            eventKey: 'finance.cash_discrepancy',
            variables: ['cashier_name' => 'Ali', 'expected' => '1000', 'actual' => '950', 'difference' => '50', 'store_name' => 'S'],
            category: 'finance',
        );

        $this->assertDatabaseHas('notifications_custom', ['user_id' => $this->owner->id]);
        $this->assertDatabaseMissing('notifications_custom', ['user_id' => $this->cashier->id]);
    }

    // ─── quickSend ────────────────────────────────────────────

    /** @test */
    public function quick_send_creates_notification_with_given_title_and_body(): void
    {
        $this->dispatcher->quickSend(
            userId: $this->owner->id,
            title: 'Alert!',
            body: 'Something important happened.',
            category: 'system',
        );

        $this->assertDatabaseHas('notifications_custom', [
            'user_id' => $this->owner->id,
            'title' => 'Alert!',
            'message' => 'Something important happened.',
            'category' => 'system',
        ]);
    }

    /** @test */
    public function quick_send_does_not_require_event_key(): void
    {
        $this->dispatcher->quickSend(
            userId: $this->cashier->id,
            title: 'Hello',
            body: 'Direct message',
            category: 'staff',
        );

        $this->assertDatabaseCount('notifications_custom', 1);
    }
}
