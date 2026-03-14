<?php

namespace Tests\Feature\Admin;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\AdminPanel\Models\PlatformEventLog;
use App\Domain\AdminPanel\Models\SystemHealthCheck;
use App\Domain\Notification\Models\NotificationEventLog;
use App\Domain\PlatformAnalytics\Models\StoreHealthSnapshot;
use App\Domain\Security\Models\SecurityAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LogMonitoringApiTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = AdminUser::forceCreate([
            'name'          => 'Log Admin',
            'email'         => 'logadmin@test.com',
            'password_hash' => bcrypt('password'),
            'is_active'     => true,
        ]);

        Sanctum::actingAs($this->admin, ['*'], 'admin-api');
    }

    // ─── Authentication ──────────────────────────────────────

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v2/admin/logs/activity')
            ->assertStatus(401);
    }

    // ═══════════════════════════════════════════════════════════
    //  ADMIN ACTIVITY LOGS
    // ═══════════════════════════════════════════════════════════

    public function test_list_activity_logs_returns_paginated_results(): void
    {
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->admin->id,
            'action'        => 'login',
            'entity_type'   => 'session',
            'ip_address'    => '10.0.0.1',
            'created_at'    => now(),
        ]);
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->admin->id,
            'action'        => 'update_settings',
            'entity_type'   => 'settings',
            'ip_address'    => '10.0.0.2',
            'created_at'    => now()->subHour(),
        ]);

        $this->getJson('/api/v2/admin/logs/activity')
            ->assertOk()
            ->assertJsonPath('message', 'Activity logs retrieved')
            ->assertJsonPath('data.total', 2);
    }

    public function test_list_activity_logs_filters_by_action(): void
    {
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->admin->id,
            'action'        => 'login',
            'ip_address'    => '127.0.0.1',
            'created_at'    => now(),
        ]);
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->admin->id,
            'action'        => 'delete_user',
            'ip_address'    => '127.0.0.1',
            'created_at'    => now(),
        ]);

        $this->getJson('/api/v2/admin/logs/activity?action=login')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_list_activity_logs_filters_by_entity_type(): void
    {
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->admin->id,
            'action'        => 'create',
            'entity_type'   => 'store',
            'ip_address'    => '127.0.0.1',
            'created_at'    => now(),
        ]);

        $this->getJson('/api/v2/admin/logs/activity?entity_type=store')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_list_activity_logs_filters_by_date_range(): void
    {
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->admin->id,
            'action'        => 'old_action',
            'ip_address'    => '127.0.0.1',
            'created_at'    => '2024-01-01 00:00:00',
        ]);
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->admin->id,
            'action'        => 'new_action',
            'ip_address'    => '127.0.0.1',
            'created_at'    => '2025-06-01 00:00:00',
        ]);

        $this->getJson('/api/v2/admin/logs/activity?date_from=2025-01-01')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_list_activity_logs_search_by_keyword(): void
    {
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->admin->id,
            'action'        => 'deploy_release',
            'ip_address'    => '127.0.0.1',
            'created_at'    => now(),
        ]);

        $this->getJson('/api/v2/admin/logs/activity?search=deploy')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_show_activity_log_returns_details(): void
    {
        $log = AdminActivityLog::forceCreate([
            'admin_user_id' => $this->admin->id,
            'action'        => 'update_store',
            'entity_type'   => 'store',
            'entity_id'     => 'abc-123',
            'details'       => json_encode(['field' => 'name']),
            'ip_address'    => '192.168.1.1',
            'created_at'    => now(),
        ]);

        $this->getJson("/api/v2/admin/logs/activity/{$log->id}")
            ->assertOk()
            ->assertJsonPath('data.action', 'update_store')
            ->assertJsonPath('data.entity_type', 'store');
    }

    public function test_show_activity_log_not_found(): void
    {
        $this->getJson('/api/v2/admin/logs/activity/nonexistent-id')
            ->assertNotFound();
    }

    public function test_list_activity_logs_filters_by_admin_user_id(): void
    {
        $other = AdminUser::forceCreate([
            'name'          => 'Other',
            'email'         => 'other@test.com',
            'password_hash' => bcrypt('pass'),
            'is_active'     => true,
        ]);

        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->admin->id,
            'action'        => 'action1',
            'ip_address'    => '127.0.0.1',
            'created_at'    => now(),
        ]);
        AdminActivityLog::forceCreate([
            'admin_user_id' => $other->id,
            'action'        => 'action2',
            'ip_address'    => '127.0.0.1',
            'created_at'    => now(),
        ]);

        $this->getJson("/api/v2/admin/logs/activity?admin_user_id={$this->admin->id}")
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    // ═══════════════════════════════════════════════════════════
    //  SECURITY ALERTS
    // ═══════════════════════════════════════════════════════════

    public function test_list_security_alerts_returns_paginated(): void
    {
        SecurityAlert::forceCreate([
            'alert_type'    => 'brute_force',
            'severity'      => 'high',
            'description'   => 'Multiple failed logins',
            'admin_user_id' => $this->admin->id,
            'status'        => 'new',
            'created_at'    => now(),
        ]);

        $this->getJson('/api/v2/admin/logs/security-alerts')
            ->assertOk()
            ->assertJsonPath('message', 'Security alerts retrieved')
            ->assertJsonPath('data.total', 1);
    }

    public function test_list_security_alerts_filters_by_severity(): void
    {
        SecurityAlert::forceCreate([
            'alert_type'  => 'brute_force',
            'severity'    => 'high',
            'description' => 'High sev',
            'status'      => 'new',
            'created_at'  => now(),
        ]);
        SecurityAlert::forceCreate([
            'alert_type'  => 'unusual_ip',
            'severity'    => 'low',
            'description' => 'Low sev',
            'status'      => 'new',
            'created_at'  => now(),
        ]);

        $this->getJson('/api/v2/admin/logs/security-alerts?severity=high')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_list_security_alerts_filters_by_status(): void
    {
        SecurityAlert::forceCreate([
            'alert_type'  => 'brute_force',
            'severity'    => 'medium',
            'description' => 'Open',
            'status'      => 'new',
            'created_at'  => now(),
        ]);
        SecurityAlert::forceCreate([
            'alert_type'  => 'unusual_ip',
            'severity'    => 'low',
            'description' => 'Closed',
            'status'      => 'resolved',
            'resolved_at' => now(),
            'created_at'  => now(),
        ]);

        $this->getJson('/api/v2/admin/logs/security-alerts?status=new')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_list_security_alerts_search(): void
    {
        SecurityAlert::forceCreate([
            'alert_type'  => 'brute_force',
            'severity'    => 'high',
            'description' => 'Brute force from 10.0.0.5',
            'status'      => 'new',
            'created_at'  => now(),
        ]);

        $this->getJson('/api/v2/admin/logs/security-alerts?search=brute')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_show_security_alert(): void
    {
        $alert = SecurityAlert::forceCreate([
            'alert_type'  => 'bulk_export',
            'severity'    => 'critical',
            'description' => 'Mass data export detected',
            'details'     => json_encode(['rows' => 50000]),
            'status'      => 'new',
            'created_at'  => now(),
        ]);

        $this->getJson("/api/v2/admin/logs/security-alerts/{$alert->id}")
            ->assertOk()
            ->assertJsonPath('data.alert_type', 'bulk_export')
            ->assertJsonPath('data.severity', 'critical');
    }

    public function test_show_security_alert_not_found(): void
    {
        $this->getJson('/api/v2/admin/logs/security-alerts/nonexistent')
            ->assertNotFound();
    }

    public function test_resolve_security_alert(): void
    {
        $alert = SecurityAlert::forceCreate([
            'alert_type'  => 'brute_force',
            'severity'    => 'high',
            'description' => 'Attack detected',
            'status'      => 'new',
            'created_at'  => now(),
        ]);

        $this->postJson("/api/v2/admin/logs/security-alerts/{$alert->id}/resolve", [
            'resolution_notes' => 'IP blocked via firewall',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.resolved_by', $this->admin->id);

        $this->assertDatabaseHas('security_alerts', [
            'id'               => $alert->id,
            'status'           => 'resolved',
            'resolution_notes' => 'IP blocked via firewall',
        ]);
    }

    public function test_resolve_already_resolved_alert_fails(): void
    {
        $alert = SecurityAlert::forceCreate([
            'alert_type'  => 'brute_force',
            'severity'    => 'high',
            'description' => 'Already fixed',
            'status'      => 'resolved',
            'resolved_at' => now(),
            'created_at'  => now(),
        ]);

        $this->postJson("/api/v2/admin/logs/security-alerts/{$alert->id}/resolve")
            ->assertStatus(422);
    }

    public function test_resolve_nonexistent_alert_returns_404(): void
    {
        $this->postJson('/api/v2/admin/logs/security-alerts/nonexistent/resolve')
            ->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════
    //  NOTIFICATION LOGS
    // ═══════════════════════════════════════════════════════════

    public function test_list_notification_logs(): void
    {
        NotificationEventLog::forceCreate([
            'notification_id' => '00000000-0000-0000-0000-000000000001',
            'channel'         => 'push',
            'status'          => 'sent',
            'sent_at'         => now(),
        ]);
        NotificationEventLog::forceCreate([
            'notification_id' => '00000000-0000-0000-0000-000000000002',
            'channel'         => 'email',
            'status'          => 'failed',
            'error_message'   => 'SMTP timeout',
            'sent_at'         => now(),
        ]);

        $this->getJson('/api/v2/admin/logs/notifications')
            ->assertOk()
            ->assertJsonPath('message', 'Notification logs retrieved')
            ->assertJsonPath('data.total', 2);
    }

    public function test_list_notification_logs_filter_by_channel(): void
    {
        NotificationEventLog::forceCreate([
            'notification_id' => '00000000-0000-0000-0000-000000000001',
            'channel'         => 'push',
            'status'          => 'sent',
            'sent_at'         => now(),
        ]);
        NotificationEventLog::forceCreate([
            'notification_id' => '00000000-0000-0000-0000-000000000002',
            'channel'         => 'sms',
            'status'          => 'sent',
            'sent_at'         => now(),
        ]);

        $this->getJson('/api/v2/admin/logs/notifications?channel=push')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_list_notification_logs_filter_by_status(): void
    {
        NotificationEventLog::forceCreate([
            'notification_id' => '00000000-0000-0000-0000-000000000001',
            'channel'         => 'push',
            'status'          => 'sent',
            'sent_at'         => now(),
        ]);
        NotificationEventLog::forceCreate([
            'notification_id' => '00000000-0000-0000-0000-000000000002',
            'channel'         => 'email',
            'status'          => 'failed',
            'sent_at'         => now(),
        ]);

        $this->getJson('/api/v2/admin/logs/notifications?status=failed')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_list_notification_logs_search(): void
    {
        NotificationEventLog::forceCreate([
            'notification_id' => '00000000-0000-0000-0000-000000000001',
            'channel'         => 'email',
            'status'          => 'failed',
            'error_message'   => 'Connection refused',
            'sent_at'         => now(),
        ]);

        $this->getJson('/api/v2/admin/logs/notifications?search=refused')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    // ═══════════════════════════════════════════════════════════
    //  PLATFORM EVENTS
    // ═══════════════════════════════════════════════════════════

    public function test_list_platform_events(): void
    {
        PlatformEventLog::forceCreate([
            'event_type'    => 'deployment',
            'level'         => 'info',
            'source'        => 'ci_pipeline',
            'message'       => 'v2.5.0 deployed',
            'admin_user_id' => $this->admin->id,
            'created_at'    => now(),
        ]);

        $this->getJson('/api/v2/admin/logs/events')
            ->assertOk()
            ->assertJsonPath('message', 'Platform events retrieved')
            ->assertJsonPath('data.total', 1);
    }

    public function test_create_platform_event(): void
    {
        $this->postJson('/api/v2/admin/logs/events', [
            'event_type' => 'deployment',
            'message'    => 'Release v3.0.0 deployed to production',
            'level'      => 'info',
            'source'     => 'ci_pipeline',
            'details'    => ['version' => '3.0.0', 'environment' => 'production'],
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.event_type', 'deployment')
            ->assertJsonPath('data.level', 'info')
            ->assertJsonPath('data.admin_user_id', $this->admin->id);

        $this->assertDatabaseHas('platform_event_logs', [
            'event_type' => 'deployment',
            'source'     => 'ci_pipeline',
        ]);
    }

    public function test_create_platform_event_requires_event_type_and_message(): void
    {
        $this->postJson('/api/v2/admin/logs/events', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['event_type', 'message']);
    }

    public function test_create_platform_event_validates_level(): void
    {
        $this->postJson('/api/v2/admin/logs/events', [
            'event_type' => 'test',
            'message'    => 'Test event',
            'level'      => 'invalid_level',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['level']);
    }

    public function test_list_platform_events_filter_by_event_type(): void
    {
        PlatformEventLog::forceCreate([
            'event_type' => 'deployment',
            'message'    => 'Deploy',
            'created_at' => now(),
        ]);
        PlatformEventLog::forceCreate([
            'event_type' => 'config_change',
            'message'    => 'Config updated',
            'created_at' => now(),
        ]);

        $this->getJson('/api/v2/admin/logs/events?event_type=deployment')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_list_platform_events_filter_by_level(): void
    {
        PlatformEventLog::forceCreate([
            'event_type' => 'error',
            'level'      => 'error',
            'message'    => 'DB connection failed',
            'created_at' => now(),
        ]);
        PlatformEventLog::forceCreate([
            'event_type' => 'deployment',
            'level'      => 'info',
            'message'    => 'All good',
            'created_at' => now(),
        ]);

        $this->getJson('/api/v2/admin/logs/events?level=error')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_list_platform_events_filter_by_source(): void
    {
        PlatformEventLog::forceCreate([
            'event_type' => 'cron_job',
            'source'     => 'scheduler',
            'message'    => 'Daily stats ran',
            'created_at' => now(),
        ]);

        $this->getJson('/api/v2/admin/logs/events?source=scheduler')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_list_platform_events_search(): void
    {
        PlatformEventLog::forceCreate([
            'event_type' => 'maintenance',
            'message'    => 'Database migration completed',
            'created_at' => now(),
        ]);

        $this->getJson('/api/v2/admin/logs/events?search=migration')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_show_platform_event(): void
    {
        $event = PlatformEventLog::forceCreate([
            'event_type'    => 'deployment',
            'level'         => 'warning',
            'source'        => 'manual',
            'message'       => 'Hotfix applied',
            'details'       => json_encode(['commit' => 'abc123']),
            'admin_user_id' => $this->admin->id,
            'created_at'    => now(),
        ]);

        $this->getJson("/api/v2/admin/logs/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.event_type', 'deployment')
            ->assertJsonPath('data.level', 'warning');
    }

    public function test_show_platform_event_not_found(): void
    {
        $this->getJson('/api/v2/admin/logs/events/nonexistent')
            ->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════
    //  SYSTEM HEALTH
    // ═══════════════════════════════════════════════════════════

    public function test_health_dashboard_returns_summary(): void
    {
        SystemHealthCheck::forceCreate([
            'service'          => 'api',
            'status'           => 'healthy',
            'response_time_ms' => 45,
            'checked_at'       => now(),
        ]);
        SystemHealthCheck::forceCreate([
            'service'          => 'database',
            'status'           => 'healthy',
            'response_time_ms' => 12,
            'checked_at'       => now(),
        ]);
        SystemHealthCheck::forceCreate([
            'service'          => 'queue',
            'status'           => 'degraded',
            'response_time_ms' => 500,
            'checked_at'       => now(),
        ]);

        $response = $this->getJson('/api/v2/admin/logs/health/dashboard')
            ->assertOk()
            ->assertJsonPath('message', 'Health dashboard retrieved')
            ->assertJsonStructure([
                'data' => [
                    'summary' => ['total_checks', 'healthy', 'degraded', 'down', 'health_score'],
                    'services',
                    'breakdown',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals(3, $data['summary']['total_checks']);
        $this->assertEquals(2, $data['summary']['healthy']);
        $this->assertEquals(1, $data['summary']['degraded']);
        $this->assertEquals(0, $data['summary']['down']);
    }

    public function test_health_dashboard_empty_shows_100_score(): void
    {
        $this->getJson('/api/v2/admin/logs/health/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.health_score', 100);
    }

    public function test_list_health_checks(): void
    {
        SystemHealthCheck::forceCreate([
            'service'          => 'api',
            'status'           => 'healthy',
            'response_time_ms' => 30,
            'checked_at'       => now(),
        ]);

        $this->getJson('/api/v2/admin/logs/health/checks')
            ->assertOk()
            ->assertJsonPath('message', 'Health checks retrieved')
            ->assertJsonPath('data.total', 1);
    }

    public function test_list_health_checks_filter_by_service(): void
    {
        SystemHealthCheck::forceCreate([
            'service'    => 'api',
            'status'     => 'healthy',
            'checked_at' => now(),
        ]);
        SystemHealthCheck::forceCreate([
            'service'    => 'cache',
            'status'     => 'healthy',
            'checked_at' => now(),
        ]);

        $this->getJson('/api/v2/admin/logs/health/checks?service=api')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_list_health_checks_filter_by_status(): void
    {
        SystemHealthCheck::forceCreate([
            'service'    => 'api',
            'status'     => 'healthy',
            'checked_at' => now(),
        ]);
        SystemHealthCheck::forceCreate([
            'service'    => 'queue',
            'status'     => 'down',
            'checked_at' => now(),
        ]);

        $this->getJson('/api/v2/admin/logs/health/checks?status=down')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_create_health_check(): void
    {
        $this->postJson('/api/v2/admin/logs/health/checks', [
            'service'          => 'database',
            'status'           => 'healthy',
            'response_time_ms' => 8,
            'details'          => ['connections' => 42, 'pool_size' => 100],
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.service', 'database')
            ->assertJsonPath('data.status', 'healthy')
            ->assertJsonPath('data.response_time_ms', 8);

        $this->assertDatabaseHas('system_health_checks', [
            'service' => 'database',
            'status'  => 'healthy',
        ]);
    }

    public function test_create_health_check_validates_service(): void
    {
        $this->postJson('/api/v2/admin/logs/health/checks', [
            'service' => 'invalid_service',
            'status'  => 'healthy',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['service']);
    }

    public function test_create_health_check_validates_status(): void
    {
        $this->postJson('/api/v2/admin/logs/health/checks', [
            'service' => 'api',
            'status'  => 'unknown',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_create_health_check_requires_fields(): void
    {
        $this->postJson('/api/v2/admin/logs/health/checks', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['service', 'status']);
    }

    // ═══════════════════════════════════════════════════════════
    //  STORE HEALTH
    // ═══════════════════════════════════════════════════════════

    public function test_list_store_health(): void
    {
        $storeId = $this->createStore();

        StoreHealthSnapshot::forceCreate([
            'store_id'        => $storeId,
            'date'            => '2025-06-01',
            'sync_status'     => 'ok',
            'zatca_compliance'=> true,
            'error_count'     => 0,
        ]);

        $this->getJson('/api/v2/admin/logs/store-health')
            ->assertOk()
            ->assertJsonPath('message', 'Store health snapshots retrieved')
            ->assertJsonPath('data.total', 1);
    }

    public function test_list_store_health_filter_by_store_id(): void
    {
        $storeId1 = $this->createStore('Store A');
        $storeId2 = $this->createStore('Store B');

        StoreHealthSnapshot::forceCreate([
            'store_id'    => $storeId1,
            'date'        => '2025-06-01',
            'sync_status' => 'ok',
        ]);
        StoreHealthSnapshot::forceCreate([
            'store_id'    => $storeId2,
            'date'        => '2025-06-01',
            'sync_status' => 'error',
        ]);

        $this->getJson("/api/v2/admin/logs/store-health?store_id={$storeId1}")
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_list_store_health_filter_by_sync_status(): void
    {
        $storeId = $this->createStore();

        StoreHealthSnapshot::forceCreate([
            'store_id'    => $storeId,
            'date'        => '2025-06-01',
            'sync_status' => 'ok',
        ]);
        StoreHealthSnapshot::forceCreate([
            'store_id'    => $storeId,
            'date'        => '2025-06-02',
            'sync_status' => 'error',
        ]);

        $this->getJson('/api/v2/admin/logs/store-health?sync_status=error')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_list_store_health_filter_by_date(): void
    {
        $storeId = $this->createStore();

        StoreHealthSnapshot::forceCreate([
            'store_id'    => $storeId,
            'date'        => '2025-06-01',
            'sync_status' => 'ok',
        ]);
        StoreHealthSnapshot::forceCreate([
            'store_id'    => $storeId,
            'date'        => '2025-06-15',
            'sync_status' => 'ok',
        ]);

        $this->getJson('/api/v2/admin/logs/store-health?date=2025-06-01')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    // ═══════════════════════════════════════════════════════════
    //  EDGE CASES & PAGINATION
    // ═══════════════════════════════════════════════════════════

    public function test_activity_logs_custom_per_page(): void
    {
        for ($i = 0; $i < 5; $i++) {
            AdminActivityLog::forceCreate([
                'admin_user_id' => $this->admin->id,
                'action'        => "action_{$i}",
                'ip_address'    => '127.0.0.1',
                'created_at'    => now(),
            ]);
        }

        $this->getJson('/api/v2/admin/logs/activity?per_page=2')
            ->assertOk()
            ->assertJsonPath('data.per_page', 2)
            ->assertJsonPath('data.total', 5);
    }

    public function test_platform_events_default_level_is_info(): void
    {
        $this->postJson('/api/v2/admin/logs/events', [
            'event_type' => 'test_event',
            'message'    => 'Simple test',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.level', 'info');
    }

    public function test_health_dashboard_all_services_down(): void
    {
        SystemHealthCheck::forceCreate([
            'service'    => 'api',
            'status'     => 'down',
            'checked_at' => now(),
        ]);
        SystemHealthCheck::forceCreate([
            'service'    => 'database',
            'status'     => 'down',
            'checked_at' => now(),
        ]);

        $response = $this->getJson('/api/v2/admin/logs/health/dashboard');
        $response->assertOk();
        $this->assertEquals(0, $response->json('data.summary.health_score'));
        $this->assertEquals(2, $response->json('data.summary.down'));
    }

    public function test_security_alerts_filter_by_alert_type(): void
    {
        SecurityAlert::forceCreate([
            'alert_type'  => 'brute_force',
            'severity'    => 'high',
            'description' => 'BF attack',
            'status'      => 'new',
            'created_at'  => now(),
        ]);
        SecurityAlert::forceCreate([
            'alert_type'  => 'permission_escalation',
            'severity'    => 'critical',
            'description' => 'Escalation',
            'status'      => 'new',
            'created_at'  => now(),
        ]);

        $this->getJson('/api/v2/admin/logs/security-alerts?alert_type=brute_force')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_store_health_filter_zatca_compliance(): void
    {
        $storeId = $this->createStore();

        StoreHealthSnapshot::forceCreate([
            'store_id'         => $storeId,
            'date'             => '2025-06-01',
            'sync_status'      => 'ok',
            'zatca_compliance' => true,
        ]);
        StoreHealthSnapshot::forceCreate([
            'store_id'         => $storeId,
            'date'             => '2025-06-02',
            'sync_status'      => 'ok',
            'zatca_compliance' => false,
        ]);

        $this->getJson('/api/v2/admin/logs/store-health?zatca_compliance=true')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    // ─── Helpers ─────────────────────────────────────────────

    private function createStore(string $name = 'Test Store'): string
    {
        $orgId = \Illuminate\Support\Str::uuid()->toString();
        \Illuminate\Support\Facades\DB::table('organizations')->insert([
            'id'   => $orgId,
            'name' => $name . ' Org',
        ]);

        $storeId = \Illuminate\Support\Str::uuid()->toString();
        \Illuminate\Support\Facades\DB::table('stores')->insert([
            'id'              => $storeId,
            'organization_id' => $orgId,
            'name'            => $name,
        ]);

        return $storeId;
    }
}
