<?php

namespace Tests\Feature\Api\Admin;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Security\Models\AdminIpAllowlist;
use App\Domain\Security\Models\AdminIpBlocklist;
use App\Domain\Security\Models\AdminSession;
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
              'login_attempts', 'ip_management',
          ]]);
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
        // Could be revoked_at or ended_at or status change
        $this->assertTrue(
            $fresh->ended_at !== null || $fresh->revoked_at !== null || $fresh->status === 'closed'
        );
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

    public function test_create_allowlist_validation(): void
    {
        $this->postJson("{$this->base}/ip-allowlist", [])->assertUnprocessable();
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
}
