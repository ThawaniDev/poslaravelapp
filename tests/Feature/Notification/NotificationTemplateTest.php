<?php

namespace Tests\Feature\Notification;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationDeliveryStatus;
use App\Domain\Notification\Enums\NotificationProvider;
use App\Domain\Notification\Jobs\DispatchNotificationJob;
use App\Domain\Notification\Models\NotificationDeliveryLog;
use App\Domain\Notification\Models\NotificationProviderStatus;
use App\Domain\Notification\Models\NotificationTemplate;
use App\Domain\Notification\Services\NotificationTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationTemplateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;
    private NotificationTemplateService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Template Test Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Main Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Admin',
            'email' => 'admin@template-test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;

        $this->service = new NotificationTemplateService();
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // Event Catalog
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    public function test_event_catalog_has_all_categories(): void
    {
        $catalog = NotificationTemplateService::eventCatalog();

        $this->assertArrayHasKey('order', $catalog);
        $this->assertArrayHasKey('inventory', $catalog);
        $this->assertArrayHasKey('finance', $catalog);
        $this->assertArrayHasKey('system', $catalog);
        $this->assertArrayHasKey('staff', $catalog);
    }

    public function test_event_catalog_has_expected_event_count(): void
    {
        $events = NotificationTemplateService::allEvents();
        $this->assertGreaterThanOrEqual(26, count($events));
    }

    public function test_all_events_have_required_fields(): void
    {
        $events = NotificationTemplateService::allEvents();

        foreach ($events as $key => $event) {
            $this->assertArrayHasKey('description', $event, "Missing description for {$key}");
            $this->assertArrayHasKey('variables', $event, "Missing variables for {$key}");
            $this->assertArrayHasKey('is_critical', $event, "Missing is_critical for {$key}");
            $this->assertArrayHasKey('category', $event, "Missing category for {$key}");
            $this->assertIsArray($event['variables'], "Variables should be array for {$key}");
        }
    }

    public function test_get_available_variables_returns_correct_variables(): void
    {
        $vars = NotificationTemplateService::getAvailableVariables('order.new');
        $this->assertContains('order_id', $vars);
        $this->assertContains('total', $vars);
        $this->assertContains('store_name', $vars);
    }

    public function test_get_available_variables_returns_empty_for_unknown_event(): void
    {
        $vars = NotificationTemplateService::getAvailableVariables('unknown.event');
        $this->assertEmpty($vars);
    }

    public function test_event_select_options_are_grouped(): void
    {
        $options = NotificationTemplateService::eventSelectOptions();

        $this->assertIsArray($options);
        $this->assertArrayHasKey('Order Events', $options);
        $this->assertArrayHasKey('Inventory Events', $options);
        $this->assertNotEmpty($options['Order Events']);
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // Template Rendering
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    public function test_render_interpolates_variables(): void
    {
        NotificationTemplate::create([
            'event_key' => 'order.new',
            'channel' => NotificationChannel::Push,
            'title' => 'New Order #{{order_id}}',
            'title_ar' => 'طلب جديد #{{order_id}}',
            'body' => 'Order total: {{total}} at {{store_name}}',
            'body_ar' => 'إجمالي الطلب: {{total}} في {{store_name}}',
            'available_variables' => ['order_id', 'total', 'store_name'],
            'is_active' => true,
        ]);

        Cache::flush();

        $result = $this->service->render('order.new', NotificationChannel::Push, [
            'order_id' => 'ORD-001',
            'total' => '50.00 SAR',
            'store_name' => 'Test Store',
        ]);

        $this->assertNotNull($result);
        $this->assertEquals('New Order #ORD-001', $result['title']);
        $this->assertEquals('Order total: 50.00 SAR at Test Store', $result['body']);
    }

    public function test_render_arabic_locale(): void
    {
        NotificationTemplate::create([
            'event_key' => 'order.new',
            'channel' => NotificationChannel::Push,
            'title' => 'New Order #{{order_id}}',
            'title_ar' => 'طلب جديد #{{order_id}}',
            'body' => 'Total: {{total}}',
            'body_ar' => 'الإجمالي: {{total}}',
            'available_variables' => ['order_id', 'total'],
            'is_active' => true,
        ]);

        Cache::flush();

        $result = $this->service->render('order.new', NotificationChannel::Push, [
            'order_id' => 'ORD-001',
            'total' => '50.00 SAR',
        ], 'ar');

        $this->assertNotNull($result);
        $this->assertEquals('طلب جديد #ORD-001', $result['title']);
        $this->assertEquals('الإجمالي: 50.00 SAR', $result['body']);
    }

    public function test_render_returns_null_for_inactive_template(): void
    {
        NotificationTemplate::create([
            'event_key' => 'order.new',
            'channel' => NotificationChannel::Push,
            'title' => 'Title',
            'body' => 'Body',
            'is_active' => false,
        ]);

        Cache::flush();

        $result = $this->service->render('order.new', NotificationChannel::Push, []);
        $this->assertNull($result);
    }

    public function test_render_returns_null_for_missing_template(): void
    {
        $result = $this->service->render('nonexistent.event', NotificationChannel::Push, []);
        $this->assertNull($result);
    }

    public function test_render_replaces_undefined_variables_with_empty_string(): void
    {
        NotificationTemplate::create([
            'event_key' => 'test.event',
            'channel' => NotificationChannel::InApp,
            'title' => 'Hello {{name}} from {{city}}',
            'body' => 'Details: {{info}}',
            'is_active' => true,
        ]);

        Cache::flush();

        $result = $this->service->render('test.event', NotificationChannel::InApp, [
            'name' => 'John',
            // city and info are missing
        ]);

        $this->assertEquals('Hello John from ', $result['title']);
        $this->assertEquals('Details: ', $result['body']);
    }

    public function test_render_preview_uses_sample_data(): void
    {
        $template = NotificationTemplate::create([
            'event_key' => 'order.new',
            'channel' => NotificationChannel::Push,
            'title' => 'Order #{{order_id}}',
            'title_ar' => 'طلب #{{order_id}}',
            'body' => 'Total: {{total}}',
            'body_ar' => 'الإجمالي: {{total}}',
            'available_variables' => ['order_id', 'total'],
            'is_active' => true,
        ]);

        $preview = $this->service->renderPreview($template);

        $this->assertNotEmpty($preview['title']);
        $this->assertStringNotContainsString('{{order_id}}', $preview['title']);
        $this->assertArrayHasKey('sample_data', $preview);
        $this->assertArrayHasKey('order_id', $preview['sample_data']);
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // Dispatch & Fallback
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    public function test_dispatch_creates_delivery_log_on_success(): void
    {
        NotificationTemplate::create([
            'event_key' => 'order.new',
            'channel' => NotificationChannel::Sms,
            'title' => 'New Order',
            'body' => 'Order placed',
            'is_active' => true,
        ]);

        NotificationProviderStatus::create([
            'provider' => NotificationProvider::Unifonic,
            'channel' => NotificationChannel::Sms,
            'priority' => 1,
            'is_enabled' => true,
            'is_healthy' => true,
            'failure_count_24h' => 0,
            'success_count_24h' => 0,
        ]);

        Cache::flush();

        $log = $this->service->dispatch(
            'order.new',
            NotificationChannel::Sms,
            '+96890000000',
            [],
        );

        $this->assertInstanceOf(NotificationDeliveryLog::class, $log);
        $this->assertEquals(NotificationDeliveryStatus::Sent, $log->status);
        $this->assertNotNull($log->provider_message_id);
        $this->assertFalse($log->is_fallback);
    }

    public function test_dispatch_fails_when_no_providers_available(): void
    {
        NotificationTemplate::create([
            'event_key' => 'order.new',
            'channel' => NotificationChannel::Sms,
            'title' => 'New Order',
            'body' => 'Order placed',
            'is_active' => true,
        ]);

        Cache::flush();

        $log = $this->service->dispatch(
            'order.new',
            NotificationChannel::Sms,
            '+96890000000',
            [],
        );

        $this->assertEquals(NotificationDeliveryStatus::Failed, $log->status);
        $this->assertStringContains('No providers', $log->error_message);
    }

    public function test_dispatch_fails_when_no_template(): void
    {
        Cache::flush();

        $log = $this->service->dispatch(
            'nonexistent.event',
            NotificationChannel::Sms,
            '+96890000000',
            [],
        );

        $this->assertEquals(NotificationDeliveryStatus::Failed, $log->status);
        $this->assertStringContains('No active template', $log->error_message);
    }

    public function test_dispatch_updates_provider_success_stats(): void
    {
        NotificationTemplate::create([
            'event_key' => 'order.new',
            'channel' => NotificationChannel::Email,
            'title' => 'New Order',
            'body' => 'Order placed',
            'is_active' => true,
        ]);

        $provider = NotificationProviderStatus::create([
            'provider' => NotificationProvider::Mailgun,
            'channel' => NotificationChannel::Email,
            'priority' => 1,
            'is_enabled' => true,
            'is_healthy' => true,
            'failure_count_24h' => 0,
            'success_count_24h' => 0,
        ]);

        Cache::flush();

        $this->service->dispatch('order.new', NotificationChannel::Email, 'test@test.com', []);

        $provider->refresh();
        $this->assertEquals(1, $provider->success_count_24h);
        $this->assertNotNull($provider->last_success_at);
        $this->assertTrue($provider->is_healthy);
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // Cache Management
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    public function test_flush_template_cache(): void
    {
        $template = NotificationTemplate::create([
            'event_key' => 'order.new',
            'channel' => NotificationChannel::Push,
            'title' => 'Old Title',
            'body' => 'Old Body',
            'is_active' => true,
        ]);

        // Prime the cache
        $this->service->render('order.new', NotificationChannel::Push, []);

        // Update the template
        $template->update(['title' => 'New Title']);

        // Without flush, cache returns old value
        // Flush and check
        $this->service->flushTemplateCache($template);

        $result = $this->service->render('order.new', NotificationChannel::Push, []);
        $this->assertEquals('New Title', $result['title']);
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // API Endpoints
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    public function test_api_list_templates(): void
    {
        NotificationTemplate::create([
            'event_key' => 'order.new',
            'channel' => NotificationChannel::Push,
            'title' => 'New Order',
            'body' => 'Body',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/notification-templates');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_api_list_templates_filter_by_event_key(): void
    {
        NotificationTemplate::create([
            'event_key' => 'order.new',
            'channel' => NotificationChannel::Push,
            'title' => 'New Order',
            'body' => 'Body',
            'is_active' => true,
        ]);
        NotificationTemplate::create([
            'event_key' => 'inventory.low_stock',
            'channel' => NotificationChannel::Push,
            'title' => 'Low Stock',
            'body' => 'Body',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/notification-templates?event_key=order.new');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
    }

    public function test_api_list_templates_filter_by_channel(): void
    {
        NotificationTemplate::create([
            'event_key' => 'order.new',
            'channel' => NotificationChannel::Push,
            'title' => 'Push',
            'body' => 'Body',
            'is_active' => true,
        ]);
        NotificationTemplate::create([
            'event_key' => 'order.new',
            'channel' => NotificationChannel::Sms,
            'title' => 'SMS',
            'body' => 'Body',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/notification-templates?channel=sms');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_api_show_template(): void
    {
        $template = NotificationTemplate::create([
            'event_key' => 'order.new',
            'channel' => NotificationChannel::Push,
            'title' => 'New Order',
            'title_ar' => 'طلب جديد',
            'body' => 'Body',
            'body_ar' => 'المحتوى',
            'available_variables' => ['order_id', 'total'],
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/notification-templates/{$template->id}");

        $response->assertOk()
            ->assertJsonPath('data.event_key', 'order.new')
            ->assertJsonPath('data.channel', 'push')
            ->assertJsonPath('data.title', 'New Order')
            ->assertJsonPath('data.title_ar', 'طلب جديد');
    }

    public function test_api_show_template_not_found(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/notification-templates/nonexistent-id');

        $response->assertNotFound();
    }

    public function test_api_render_template(): void
    {
        NotificationTemplate::create([
            'event_key' => 'order.new',
            'channel' => NotificationChannel::Push,
            'title' => 'Order #{{order_id}}',
            'body' => 'Total: {{total}}',
            'is_active' => true,
        ]);

        Cache::flush();

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/notification-templates/render', [
                'event_key' => 'order.new',
                'channel' => 'push',
                'variables' => ['order_id' => 'ORD-999', 'total' => '100 SAR'],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Order #ORD-999')
            ->assertJsonPath('data.body', 'Total: 100 SAR');
    }

    public function test_api_render_invalid_channel(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/notification-templates/render', [
                'event_key' => 'order.new',
                'channel' => 'invalid_channel',
                'variables' => [],
            ]);

        $response->assertStatus(422);
    }

    public function test_api_dispatch_queues_job(): void
    {
        Queue::fake();

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/notification-templates/dispatch', [
                'event_key' => 'order.new',
                'channel' => 'push',
                'recipient' => 'user-123',
                'variables' => ['order_id' => 'ORD-001'],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        Queue::assertPushed(DispatchNotificationJob::class, function ($job) {
            return $job->eventKey === 'order.new'
                && $job->channel === NotificationChannel::Push
                && $job->recipient === 'user-123';
        });
    }

    public function test_api_dispatch_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/notification-templates/dispatch', []);

        $response->assertStatus(422);
    }

    public function test_api_events_catalog(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/notification-templates/events');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertArrayHasKey('order', $data);
        $this->assertArrayHasKey('inventory', $data);
    }

    public function test_api_event_variables(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/notification-templates/events/order.new');

        $response->assertOk()
            ->assertJsonPath('data.event_key', 'order.new');

        $this->assertContains('order_id', $response->json('data.variables'));
    }

    public function test_api_event_variables_not_found(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/notification-templates/events/unknown.event');

        $response->assertNotFound();
    }

    public function test_template_api_requires_authentication(): void
    {
        $endpoints = [
            ['GET', '/api/v2/notification-templates'],
            ['GET', '/api/v2/notification-templates/events'],
            ['POST', '/api/v2/notification-templates/render'],
            ['POST', '/api/v2/notification-templates/dispatch'],
        ];

        foreach ($endpoints as [$method, $url]) {
            $response = $this->json($method, $url);
            $response->assertStatus(401, "Expected 401 for {$method} {$url}");
        }
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // Helpers
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /**
     * @param string $haystack
     * @param string $needle
     */
    private function assertStringContains(string $needle, ?string $haystack): void
    {
        $this->assertNotNull($haystack);
        $this->assertStringContainsString($needle, $haystack);
    }
}
