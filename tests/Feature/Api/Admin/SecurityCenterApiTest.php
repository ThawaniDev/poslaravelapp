<?php

namespace Tests\Feature\Api\Admin;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Security\Models\AdminIpAllowlist;
use App\Domain\Security\Models\AdminIpBlocklist;
use App\Domain\Security\Models\AdminSession;
use App\Domain\Security\Models\AdminTrustedDevice;
use App\Domain\Security\Models\DeviceRegistration;
use App\Domain\Security\Models\LoginAttempt;
use App\Domain\Security\Models\SecurityAlert;
use App\Domain\Security\Models\SecurityAuditLog;
use App\Domain\Security\Models\SecurityPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityCenterApiTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;
    private string $base = '/api/v2/admin/security-center';

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = AdminUser::forceCreate([
            'name'          => 'Admin',
            'email'         => 'admin@test.com',
            'password_hash' => bcrypt('password'),
            'is_active'     => true,
        ]);

        Sanctum::actingAs($this->admin, ['*'], 'admin-api');
    }

    // ── Overview ─────────────────────────────────────────────
    public function test_overview_returns_all_sections(): void
    {
        $r = $this->getJson("{$this->base}/overview");
        $r->assertOk()
          ->assertJsonStructure(['data' => [
              'security_alerts', 'sessions', 'devices',
              'login_attempts', 'ip_management', 'trusted_devices',
          ]]);
    }

    public function test_overview_counts_are_accurate(): void
    {
        SecurityAlert::forceCreate([
            'alert_type' => 'brute_force', 'severity' => 'high',
            'description' => 'A', 'status' => 'new',
        ]);
        SecurityAlert::forceCreate([
            'alert_type' => 'unusual_ip', 'severity' => 'low',
            'description' => 'B', 'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        $r = $this->getJson("{$this->base}/overview");
        $r->assertOk();
        $this->assertEquals(2, $r->json('data.security_alerts.total'));
        $this->assertEquals(1, $r->json('data.security_alerts.new'));
    }

    // ── Security Alerts ──────────────────────────────────────
    public function test_list_alerts_returns_paginated(): void
    {
        SecurityAlert::forceCreate([
            'alert_type' => 'brute_force', 'severity' => 'high',
            'description' => 'Test alert', 'status' => 'new',
        ]);
        $r = $this->getJson("{$this->base}/alerts");
        $r->assertOk();
        $this->assertGreaterThanOrEqual(1, count($r->json('data.data') ?? $r->json('data')));
    }

    public function test_list_alerts_filter_status(): void
    {
        SecurityAlert::forceCreate([
            'alert_type' => 'brute_force', 'severity' => 'high',
            'description' => 'A', 'status' => 'new',
        ]);
        SecurityAlert::forceCreate([
            'alert_type' => 'unusual_ip', 'severity' => 'low',
            'description' => 'B', 'status' => 'resolved',
            'resolved_at' => now(),
        ]);
        $r = $this->getJson("{$this->base}/alerts?status=new");
        $r->assertOk();
        $items = $r->json('data.data') ?? $r->json('data');
        foreach ($items as $item) {
            $this->assertEquals('new', $item['status']);
        }
    }

    public function test_list_alerts_filter_by_severity(): void
    {
        SecurityAlert::forceCreate([
            'alert_type' => 'brute_force', 'severity' => 'high',
            'description' => 'High', 'status' => 'new',
        ]);
        SecurityAlert::forceCreate([
            'alert_type' => 'unusual_ip', 'severity' => 'low',
            'description' => 'Low', 'status' => 'new',
        ]);
        $r = $this->getJson("{$this->base}/alerts?severity=high");
        $r->assertOk();
        $items = $r->json('data.data') ?? $r->json('data');
        $this->assertCount(1, $items);
    }

    public function test_list_alerts_filter_by_alert_type(): void
    {
        SecurityAlert::forceCreate([
            'alert_type' => 'brute_force', 'severity' => 'high',
            'description' => 'BF', 'status' => 'new',
        ]);
        SecurityAlert::forceCreate([
            'alert_type' => 'unusual_ip', 'severity' => 'low',
            'description' => 'IP', 'status' => 'new',
        ]);
        $r = $this->getJson("{$this->base}/alerts?alert_type=brute_force");
        $r->assertOk();
        $items = $r->json('data.data') ?? $r->json('data');
        $this->assertCount(1, $items);
    }

    public function test_list_alerts_search(): void
    {
        SecurityAlert::forceCreate([
            'alert_type' => 'brute_force', 'severity' => 'high',
            'description' => 'Suspicious login from office', 'status' => 'new',
            'ip_address' => '10.20.30.40',
        ]);
        $r = $this->getJson("{$this->base}/alerts?search=office");
        $r->assertOk();
        $items = $r->json('data.data') ?? $r->json('data');
        $this->assertCount(1, $items);
    }

    public function test_show_alert(): void
    {
        $a = SecurityAlert::forceCreate([
            'alert_type' => 'brute_force', 'severity' => 'high',
            'description' => 'Show-me', 'status' => 'new',
        ]);
        $r = $this->getJson("{$this->base}/alerts/{$a->id}");
        $r->assertOk();
    }

    public function test_show_alert_not_found(): void
    {
        $this->getJson("{$this->base}/alerts/nonexistent")->assertNotFound();
    }

    public function test_resolve_alert(): void
    {
        $a = SecurityAlert::forceCreate([
            'alert_type' => 'brute_force', 'severity' => 'high',
            'description' => 'Resolve-me', 'status' => 'new',
        ]);
        $r = $this->postJson("{$this->base}/alerts/{$a->id}/resolve", [
            'resolution_notes' => 'Fixed',
        ]);
        $r->assertOk();
        $this->assertEquals('resolved', $a->fresh()->status->value ?? $a->fresh()->status);
    }

    public function test_resolve_alert_creates_audit_log(): void
    {
        $a = SecurityAlert::forceCreate([
            'alert_type' => 'brute_force', 'severity' => 'high',
            'description' => 'Audit-me', 'status' => 'new',
        ]);
        $this->postJson("{$this->base}/alerts/{$a->id}/resolve", [
            'resolution_notes' => 'All clear',
        ])->assertOk();

        $this->assertDatabaseHas('admin_activity_logs', [
            'action'      => 'resolve_alert',
            'entity_type' => 'security_alert',
            'entity_id'   => $a->id,
        ]);
    }

    public function test_resolve_alert_not_found(): void
    {
        $this->postJson("{$this->base}/alerts/nonexistent/resolve")->assertNotFound();
    }

    public function test_resolve_alert_sets_resolved_by(): void
    {
        $a = SecurityAlert::forceCreate([
            'alert_type' => 'brute_force', 'severity' => 'high',
            'description' => 'Resolver check', 'status' => 'new',
        ]);
        $this->postJson("{$this->base}/alerts/{$a->id}/resolve")->assertOk();
        $this->assertEquals($this->admin->id, $a->fresh()->resolved_by);
        $this->assertNotNull($a->fresh()->resolved_at);
    }

    // ── Admin Sessions ───────────────────────────────────────
    public function test_list_sessions(): void
    {
        AdminSession::forceCreate([
            'admin_user_id'    => $this->admin->id,
            'ip_address'       => '1.2.3.4',
            'user_agent'       => 'Test',
            'status'           => 'active',
            'started_at'       => now(),
            'last_activity_at' => now(),
        ]);
        $r = $this->getJson("{$this->base}/sessions");
        $r->assertOk();
        $this->assertGreaterThanOrEqual(1, count($r->json('data.data') ?? $r->json('data')));
    }

    public function test_list_sessions_filter_active_only(): void
    {
        AdminSession::forceCreate([
            'admin_user_id'    => $this->admin->id,
            'ip_address'       => '1.1.1.1',
            'user_agent'       => 'Active',
            'status'           => 'active',
            'started_at'       => now(),
            'last_activity_at' => now(),
        ]);
        AdminSession::forceCreate([
            'admin_user_id'    => $this->admin->id,
            'ip_address'       => '2.2.2.2',
            'user_agent'       => 'Closed',
            'status'           => 'closed',
            'started_at'       => now()->subHour(),
            'last_activity_at' => now()->subHour(),
            'ended_at'         => now(),
        ]);
        $r = $this->getJson("{$this->base}/sessions?active_only=1");
        $r->assertOk();
        $items = $r->json('data.data') ?? $r->json('data');
        foreach ($items as $item) {
            $this->assertEquals('active', $item['status']);
        }
    }

    public function test_show_session(): void
    {
        $s = AdminSession::forceCreate([
            'admin_user_id'    => $this->admin->id,
            'ip_address'       => '5.6.7.8',
            'user_agent'       => 'T2',
            'status'           => 'active',
            'started_at'       => now(),
            'last_activity_at' => now(),
        ]);
        $this->getJson("{$this->base}/sessions/{$s->id}")->assertOk();
    }

    public function test_show_session_not_found(): void
    {
        $this->getJson("{$this->base}/sessions/nonexistent")->assertNotFound();
    }

    public function test_revoke_session(): void
    {
        $s = AdminSession::forceCreate([
            'admin_user_id'    => $this->admin->id,
            'ip_address'       => '1.1.1.1',
            'user_agent'       => 'T3',
            'status'           => 'active',
            'started_at'       => now(),
            'last_activity_at' => now(),
        ]);
        $r = $this->postJson("{$this->base}/sessions/{$s->id}/revoke");
        $r->assertOk();
        $fresh = $s->fresh();
        $this->assertNotNull($fresh->revoked_at);
        $this->assertEquals('revoked', $fresh->status->value ?? $fresh->status);
    }

    public function test_revoke_session_creates_audit_log(): void
    {
        $s = AdminSession::forceCreate([
            'admin_user_id'    => $this->admin->id,
            'ip_address'       => '3.3.3.3',
            'user_agent'       => 'Audit',
            'status'           => 'active',
            'started_at'       => now(),
            'last_activity_at' => now(),
        ]);
        $this->postJson("{$this->base}/sessions/{$s->id}/revoke")->assertOk();

        $this->assertDatabaseHas('admin_activity_logs', [
            'action'      => 'revoke_session',
            'entity_type' => 'admin_session',
            'entity_id'   => $s->id,
        ]);
    }

    public function test_revoke_session_not_found(): void
    {
        $this->postJson("{$this->base}/sessions/nonexistent/revoke")->assertNotFound();
    }

    // ── Device Registrations ─────────────────────────────────
    public function test_list_devices(): void
    {
        DeviceRegistration::forceCreate([
            'store_id'    => 'store-1',
            'device_name' => 'iPad #1',
            'hardware_id' => 'hw-123',
            'is_active'   => true,
            'remote_wipe_requested' => false,
            'registered_at' => now(),
        ]);
        $r = $this->getJson("{$this->base}/devices");
        $r->assertOk();
        $this->assertGreaterThanOrEqual(1, count($r->json('data.data') ?? $r->json('data')));
    }

    public function test_show_device(): void
    {
        $d = DeviceRegistration::forceCreate([
            'store_id' => 'store-2', 'device_name' => 'Tablet',
            'hardware_id' => 'hw-456', 'is_active' => true,
            'remote_wipe_requested' => false, 'registered_at' => now(),
        ]);
        $this->getJson("{$this->base}/devices/{$d->id}")->assertOk();
    }

    public function test_show_device_not_found(): void
    {
        $this->getJson("{$this->base}/devices/nonexistent")->assertNotFound();
    }

    public function test_wipe_device(): void
    {
        $d = DeviceRegistration::forceCreate([
            'store_id' => 'store-3', 'device_name' => 'Wipe-me',
            'hardware_id' => 'hw-789', 'is_active' => true,
            'remote_wipe_requested' => false, 'registered_at' => now(),
        ]);
        $r = $this->postJson("{$this->base}/devices/{$d->id}/wipe");
        $r->assertOk();
        $this->assertTrue((bool)$d->fresh()->remote_wipe_requested);
    }

    public function test_wipe_device_creates_audit_log(): void
    {
        $d = DeviceRegistration::forceCreate([
            'store_id' => 'store-4', 'device_name' => 'Audit-wipe',
            'hardware_id' => 'hw-audit', 'is_active' => true,
            'remote_wipe_requested' => false, 'registered_at' => now(),
        ]);
        $this->postJson("{$this->base}/devices/{$d->id}/wipe")->assertOk();

        $this->assertDatabaseHas('admin_activity_logs', [
            'action'      => 'remote_wipe_device',
            'entity_type' => 'device_registration',
            'entity_id'   => $d->id,
        ]);
    }

    public function test_wipe_device_not_found(): void
    {
        $this->postJson("{$this->base}/devices/nonexistent/wipe")->assertNotFound();
    }

    public function test_filter_devices_active(): void
    {
        DeviceRegistration::forceCreate([
            'store_id' => 's1', 'device_name' => 'Active',
            'hardware_id' => 'h1', 'is_active' => true,
            'remote_wipe_requested' => false, 'registered_at' => now(),
        ]);
        DeviceRegistration::forceCreate([
            'store_id' => 's2', 'device_name' => 'Inactive',
            'hardware_id' => 'h2', 'is_active' => false,
            'remote_wipe_requested' => false, 'registered_at' => now(),
        ]);
        $r = $this->getJson("{$this->base}/devices?is_active=1");
        $r->assertOk();
        $items = $r->json('data.data') ?? $r->json('data');
        foreach ($items as $item) {
            $this->assertTrue((bool)$item['is_active']);
        }
    }

    public function test_filter_devices_by_store(): void
    {
        DeviceRegistration::forceCreate([
            'store_id' => 'store-target', 'device_name' => 'Target',
            'hardware_id' => 'h-t', 'is_active' => true,
            'remote_wipe_requested' => false, 'registered_at' => now(),
        ]);
        DeviceRegistration::forceCreate([
            'store_id' => 'store-other', 'device_name' => 'Other',
            'hardware_id' => 'h-o', 'is_active' => true,
            'remote_wipe_requested' => false, 'registered_at' => now(),
        ]);
        $r = $this->getJson("{$this->base}/devices?store_id=store-target");
        $r->assertOk();
        $items = $r->json('data.data') ?? $r->json('data');
        $this->assertCount(1, $items);
        $this->assertEquals('Target', $items[0]['device_name']);
    }

    // ── Login Attempts ───────────────────────────────────────
    public function test_list_login_attempts(): void
    {
        LoginAttempt::forceCreate([
            'user_identifier' => 'user@test.com',
            'attempt_type'    => 'pin',
            'is_successful'   => true,
            'ip_address'      => '10.0.0.1',
            'attempted_at'    => now(),
        ]);
        $r = $this->getJson("{$this->base}/login-attempts");
        $r->assertOk();
        $this->assertGreaterThanOrEqual(1, count($r->json('data.data') ?? $r->json('data')));
    }

    public function test_show_login_attempt(): void
    {
        $a = LoginAttempt::forceCreate([
            'user_identifier' => 'u2@test.com',
            'attempt_type'    => 'password',
            'is_successful'   => false,
            'attempted_at'    => now(),
        ]);
        $this->getJson("{$this->base}/login-attempts/{$a->id}")->assertOk();
    }

    public function test_show_login_attempt_not_found(): void
    {
        $this->getJson("{$this->base}/login-attempts/nonexistent")->assertNotFound();
    }

    public function test_filter_login_attempts_by_type(): void
    {
        LoginAttempt::forceCreate([
            'user_identifier' => 'a', 'attempt_type' => 'pin',
            'is_successful' => true, 'attempted_at' => now(),
        ]);
        LoginAttempt::forceCreate([
            'user_identifier' => 'b', 'attempt_type' => 'biometric',
            'is_successful' => false, 'attempted_at' => now(),
        ]);
        $r = $this->getJson("{$this->base}/login-attempts?attempt_type=pin");
        $r->assertOk();
        $items = $r->json('data.data') ?? $r->json('data');
        foreach ($items as $item) {
            $this->assertContains($item['attempt_type'], ['pin']);
        }
    }

    public function test_filter_login_attempts_by_success(): void
    {
        LoginAttempt::forceCreate([
            'user_identifier' => 'a',
            'attempt_type'    => 'pin',
            'is_successful'   => true,
            'attempted_at'    => now(),
        ]);
        LoginAttempt::forceCreate([
            'user_identifier' => 'b',
            'attempt_type'    => 'pin',
            'is_successful'   => false,
            'attempted_at'    => now(),
        ]);
        $r = $this->getJson("{$this->base}/login-attempts?is_successful=0");
        $r->assertOk();
        $items = $r->json('data.data') ?? $r->json('data');
        foreach ($items as $item) {
            $this->assertFalse((bool) $item['is_successful']);
        }
    }

    // ── Security Audit Log ───────────────────────────────────
    public function test_list_audit_logs(): void
    {
        SecurityAuditLog::forceCreate([
            'action'   => 'login',
            'severity' => 'info',
        ]);
        $r = $this->getJson("{$this->base}/audit-logs");
        $r->assertOk();
        $this->assertGreaterThanOrEqual(1, count($r->json('data.data') ?? $r->json('data')));
    }

    public function test_show_audit_log(): void
    {
        $l = SecurityAuditLog::forceCreate([
            'action'   => 'logout',
            'severity' => 'info',
        ]);
        $this->getJson("{$this->base}/audit-logs/{$l->id}")->assertOk();
    }

    public function test_show_audit_log_not_found(): void
    {
        $this->getJson("{$this->base}/audit-logs/nonexistent")->assertNotFound();
    }

    public function test_filter_audit_logs_by_severity(): void
    {
        SecurityAuditLog::forceCreate(['action' => 'login', 'severity' => 'info']);
        SecurityAuditLog::forceCreate(['action' => 'remote_wipe', 'severity' => 'critical']);
        $r = $this->getJson("{$this->base}/audit-logs?severity=critical");
        $r->assertOk();
        $items = $r->json('data.data') ?? $r->json('data');
        foreach ($items as $item) {
            $this->assertEquals('critical', $item['severity']);
        }
    }

    public function test_filter_audit_logs_by_action(): void
    {
        SecurityAuditLog::forceCreate(['action' => 'login', 'severity' => 'info']);
        SecurityAuditLog::forceCreate(['action' => 'remote_wipe', 'severity' => 'critical']);
        $r = $this->getJson("{$this->base}/audit-logs?action=login");
        $r->assertOk();
        $items = $r->json('data.data') ?? $r->json('data');
        $this->assertCount(1, $items);
    }

    // ── Security Policies ────────────────────────────────────
    public function test_list_policies(): void
    {
        SecurityPolicy::forceCreate([
            'store_id'       => 'store-pol-1',
            'pin_min_length' => 4,
            'pin_max_length' => 8,
            'auto_lock_seconds' => 300,
            'max_failed_attempts' => 5,
            'lockout_duration_minutes' => 15,
        ]);
        $this->getJson("{$this->base}/policies")->assertOk();
    }

    public function test_show_policy(): void
    {
        $p = SecurityPolicy::forceCreate([
            'store_id'       => 'store-pol-2',
            'pin_min_length' => 6,
            'pin_max_length' => 10,
            'auto_lock_seconds' => 600,
            'max_failed_attempts' => 3,
            'lockout_duration_minutes' => 30,
        ]);
        $this->getJson("{$this->base}/policies/{$p->id}")->assertOk();
    }

    public function test_show_policy_not_found(): void
    {
        $this->getJson("{$this->base}/policies/nonexistent")->assertNotFound();
    }

    public function test_update_policy(): void
    {
        $p = SecurityPolicy::forceCreate([
            'store_id'       => 'store-pol-3',
            'pin_min_length' => 4,
            'pin_max_length' => 8,
            'auto_lock_seconds' => 300,
            'max_failed_attempts' => 5,
            'lockout_duration_minutes' => 15,
        ]);
        $r = $this->putJson("{$this->base}/policies/{$p->id}", [
            'pin_min_length'      => 6,
            'max_failed_attempts' => 10,
        ]);
        $r->assertOk();
        $this->assertEquals(6, $p->fresh()->pin_min_length);
        $this->assertEquals(10, $p->fresh()->max_failed_attempts);
    }

    public function test_update_policy_creates_audit_log(): void
    {
        $p = SecurityPolicy::forceCreate([
            'store_id'       => 'store-pol-audit',
            'pin_min_length' => 4,
            'pin_max_length' => 8,
            'auto_lock_seconds' => 300,
            'max_failed_attempts' => 5,
            'lockout_duration_minutes' => 15,
        ]);
        $this->putJson("{$this->base}/policies/{$p->id}", [
            'pin_min_length' => 8,
        ])->assertOk();

        $this->assertDatabaseHas('admin_activity_logs', [
            'action'      => 'update_security_policy',
            'entity_type' => 'security_policy',
            'entity_id'   => $p->id,
        ]);
    }

    public function test_update_policy_not_found(): void
    {
        $this->putJson("{$this->base}/policies/nonexistent", [
            'pin_min_length' => 6,
        ])->assertNotFound();
    }

    public function test_update_policy_validation(): void
    {
        $p = SecurityPolicy::forceCreate([
            'store_id' => 'store-pol-4',
            'pin_min_length' => 4, 'pin_max_length' => 8,
            'auto_lock_seconds' => 300,
            'max_failed_attempts' => 5, 'lockout_duration_minutes' => 15,
        ]);
        $this->putJson("{$this->base}/policies/{$p->id}", [
            'pin_min_length' => 2,
        ])->assertUnprocessable();
    }

    // ── IP Allowlist ─────────────────────────────────────────
    public function test_list_allowlist(): void
    {
        AdminIpAllowlist::forceCreate([
            'ip_address' => '10.0.0.1',
            'label'      => 'Office',
            'added_by'   => $this->admin->id,
        ]);
        $this->getJson("{$this->base}/ip-allowlist")->assertOk();
    }

    public function test_create_allowlist_entry(): void
    {
        $r = $this->postJson("{$this->base}/ip-allowlist", [
            'ip_address' => '192.168.1.1',
            'label'      => 'Home',
        ]);
        $r->assertCreated();
        $this->assertDatabaseHas('admin_ip_allowlist', ['ip_address' => '192.168.1.1']);
    }

    public function test_create_allowlist_creates_audit_log(): void
    {
        $this->postJson("{$this->base}/ip-allowlist", [
            'ip_address' => '192.168.2.2',
            'label'      => 'Audit test',
        ])->assertCreated();

        $this->assertDatabaseHas('admin_activity_logs', [
            'action'      => 'add_ip_allowlist',
            'entity_type' => 'admin_ip_allowlist',
        ]);
    }

    public function test_create_allowlist_validation(): void
    {
        $this->postJson("{$this->base}/ip-allowlist", [])->assertUnprocessable();
    }

    public function test_create_allowlist_invalid_ip(): void
    {
        $this->postJson("{$this->base}/ip-allowlist", [
            'ip_address' => 'not-an-ip',
        ])->assertUnprocessable();
    }

    public function test_delete_allowlist_entry(): void
    {
        $e = AdminIpAllowlist::forceCreate([
            'ip_address' => '172.16.0.1',
            'label'      => 'Remove-me',
            'added_by'   => $this->admin->id,
        ]);
        $this->deleteJson("{$this->base}/ip-allowlist/{$e->id}")->assertOk();
        $this->assertDatabaseMissing('admin_ip_allowlist', ['id' => $e->id]);
    }

    public function test_delete_allowlist_creates_audit_log(): void
    {
        $e = AdminIpAllowlist::forceCreate([
            'ip_address' => '172.16.0.2',
            'label'      => 'Audit-delete',
            'added_by'   => $this->admin->id,
        ]);
        $this->deleteJson("{$this->base}/ip-allowlist/{$e->id}")->assertOk();

        $this->assertDatabaseHas('admin_activity_logs', [
            'action'      => 'remove_ip_allowlist',
            'entity_type' => 'admin_ip_allowlist',
            'entity_id'   => $e->id,
        ]);
    }

    public function test_delete_allowlist_not_found(): void
    {
        $this->deleteJson("{$this->base}/ip-allowlist/nonexistent")->assertNotFound();
    }

    // ── IP Blocklist ─────────────────────────────────────────
    public function test_list_blocklist(): void
    {
        AdminIpBlocklist::forceCreate([
            'ip_address' => '10.10.10.10',
            'reason'     => 'Spam',
            'blocked_by' => $this->admin->id,
        ]);
        $this->getJson("{$this->base}/ip-blocklist")->assertOk();
    }

    public function test_create_blocklist_entry(): void
    {
        $r = $this->postJson("{$this->base}/ip-blocklist", [
            'ip_address' => '8.8.8.8',
            'reason'     => 'Suspicious',
        ]);
        $r->assertCreated();
        $this->assertDatabaseHas('admin_ip_blocklist', ['ip_address' => '8.8.8.8']);
    }

    public function test_create_blocklist_with_expiry(): void
    {
        $expiresAt = now()->addDays(7)->toISOString();
        $r = $this->postJson("{$this->base}/ip-blocklist", [
            'ip_address' => '9.9.9.9',
            'reason'     => 'Temporary block',
            'expires_at' => $expiresAt,
        ]);
        $r->assertCreated();
        $entry = AdminIpBlocklist::where('ip_address', '9.9.9.9')->first();
        $this->assertNotNull($entry->expires_at);
    }

    public function test_create_blocklist_creates_audit_log(): void
    {
        $this->postJson("{$this->base}/ip-blocklist", [
            'ip_address' => '7.7.7.7',
            'reason'     => 'Audit',
        ])->assertCreated();

        $this->assertDatabaseHas('admin_activity_logs', [
            'action'      => 'add_ip_blocklist',
            'entity_type' => 'admin_ip_blocklist',
        ]);
    }

    public function test_create_blocklist_invalid_ip(): void
    {
        $this->postJson("{$this->base}/ip-blocklist", [
            'ip_address' => 'bad-ip',
        ])->assertUnprocessable();
    }

    public function test_delete_blocklist_entry(): void
    {
        $e = AdminIpBlocklist::forceCreate([
            'ip_address' => '11.11.11.11',
            'reason'     => 'Remove-me',
            'blocked_by' => $this->admin->id,
        ]);
        $this->deleteJson("{$this->base}/ip-blocklist/{$e->id}")->assertOk();
        $this->assertDatabaseMissing('admin_ip_blocklist', ['id' => $e->id]);
    }

    public function test_delete_blocklist_creates_audit_log(): void
    {
        $e = AdminIpBlocklist::forceCreate([
            'ip_address' => '12.12.12.12',
            'reason'     => 'Audit-delete',
            'blocked_by' => $this->admin->id,
        ]);
        $this->deleteJson("{$this->base}/ip-blocklist/{$e->id}")->assertOk();

        $this->assertDatabaseHas('admin_activity_logs', [
            'action'      => 'remove_ip_blocklist',
            'entity_type' => 'admin_ip_blocklist',
            'entity_id'   => $e->id,
        ]);
    }

    public function test_delete_blocklist_not_found(): void
    {
        $this->deleteJson("{$this->base}/ip-blocklist/nonexistent")->assertNotFound();
    }

    // ── Pagination ───────────────────────────────────────────
    public function test_alerts_pagination(): void
    {
        for ($i = 0; $i < 20; $i++) {
            SecurityAlert::forceCreate([
                'alert_type' => 'brute_force', 'severity' => 'low',
                'description' => "Alert $i", 'status' => 'new',
            ]);
        }
        $r = $this->getJson("{$this->base}/alerts?per_page=5");
        $r->assertOk()->assertJsonPath('data.per_page', 5);
    }

    public function test_sessions_pagination(): void
    {
        for ($i = 0; $i < 20; $i++) {
            AdminSession::forceCreate([
                'admin_user_id'    => $this->admin->id,
                'ip_address'       => "10.0.0.{$i}",
                'user_agent'       => "Agent-{$i}",
                'status'           => 'active',
                'started_at'       => now(),
                'last_activity_at' => now(),
            ]);
        }
        $r = $this->getJson("{$this->base}/sessions?per_page=5");
        $r->assertOk()->assertJsonPath('data.per_page', 5);
    }

    // ── Response structure ───────────────────────────────────
    public function test_list_endpoints_return_success_wrapper(): void
    {
        $this->getJson("{$this->base}/alerts")
             ->assertOk()
             ->assertJsonStructure(['success', 'message', 'data']);
    }

    public function test_show_endpoints_return_success_wrapper(): void
    {
        $a = SecurityAlert::forceCreate([
            'alert_type' => 'brute_force', 'severity' => 'high',
            'description' => 'Wrapper test', 'status' => 'new',
        ]);
        $this->getJson("{$this->base}/alerts/{$a->id}")
             ->assertOk()
             ->assertJsonStructure(['success', 'message', 'data']);
    }
}
