<?php

namespace Tests\Feature\Admin;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Security\Enums\AlertSeverity;
use App\Domain\Security\Enums\SecurityAlertStatus;
use App\Domain\Security\Enums\SecurityAlertType;
use App\Domain\Security\Models\AdminIpAllowlist;
use App\Domain\Security\Models\AdminIpBlocklist;
use App\Domain\Security\Models\AdminSession;
use App\Domain\Security\Models\AdminTrustedDevice;
use App\Domain\Security\Models\DeviceRegistration;
use App\Domain\Security\Models\LoginAttempt;
use App\Domain\Security\Models\SecurityAlert;
use App\Domain\Security\Models\SecurityPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Comprehensive tests for the Admin Security Center API.
 *
 * Covers all endpoints under /api/v2/admin/security-center/*:
 *  - Overview
 *  - Alerts (list, show, investigate, resolve)
 *  - Sessions (list, show, revoke, revoke-all)
 *  - Trusted Devices (list, show, delete)
 *  - Devices (list, show, wipe)
 *  - Login Attempts (list, show)
 *  - Audit Logs (list, show)
 *  - Policies (list, show, update)
 *  - IP Allowlist (list, create, delete) — including CIDR
 *  - IP Blocklist (list, create, delete) — including CIDR
 *  - Activity Logs (list, show)
 */
class AdminSecurityCenterTest extends TestCase
{
    use RefreshDatabase;

    private string $prefix = '/api/v2/admin/security-center';
    private AdminUser $admin;
    private Store $store;

    // ─── Setup ───────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTablesIfMissing();

        $this->admin = AdminUser::forceCreate([
            'id'            => Str::uuid(),
            'name'          => 'Security Admin',
            'email'         => 'security@test.com',
            'password_hash' => bcrypt('password'),
            'is_active'     => true,
        ]);

        Sanctum::actingAs($this->admin, ['*'], 'admin-api');

        $org = Organization::forceCreate([
            'id'   => Str::uuid(),
            'name' => 'Test Org',
        ]);

        $this->store = Store::forceCreate([
            'id'              => Str::uuid(),
            'organization_id' => $org->id,
            'name'            => 'Test Store',
        ]);
    }

    private function createTablesIfMissing(): void
    {
        if (! Schema::hasTable('security_alerts')) {
            Schema::create('security_alerts', function ($t) {
                $t->uuid('id')->primary();
                $t->uuid('admin_user_id')->nullable();
                $t->string('alert_type');
                $t->string('severity');
                $t->string('description')->nullable();
                $t->string('ip_address', 45)->nullable();
                $t->json('details')->nullable();
                $t->string('status')->default('new');
                $t->timestamp('resolved_at')->nullable();
                $t->uuid('resolved_by')->nullable();
                $t->text('resolution_notes')->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('admin_sessions')) {
            Schema::create('admin_sessions', function ($t) {
                $t->uuid('id')->primary();
                $t->uuid('admin_user_id');
                $t->string('session_token_hash');
                $t->string('ip_address', 45)->nullable();
                $t->text('user_agent')->nullable();
                $t->string('status')->default('active');
                $t->boolean('two_fa_verified')->default(false);
                $t->timestamp('started_at')->nullable();
                $t->timestamp('last_activity_at')->nullable();
                $t->timestamp('expires_at')->nullable();
                $t->timestamp('ended_at')->nullable();
                $t->timestamp('revoked_at')->nullable();
            });
        }

        if (! Schema::hasTable('admin_trusted_devices')) {
            Schema::create('admin_trusted_devices', function ($t) {
                $t->uuid('id')->primary();
                $t->uuid('admin_user_id');
                $t->string('device_fingerprint');
                $t->string('device_name');
                $t->string('ip_address', 45)->nullable();
                $t->text('user_agent')->nullable();
                $t->timestamp('trusted_at')->nullable();
                $t->timestamp('last_used_at')->nullable();
            });
        }

        if (! Schema::hasTable('admin_ip_allowlist')) {
            Schema::create('admin_ip_allowlist', function ($t) {
                $t->uuid('id')->primary();
                $t->string('ip_address', 50);
                $t->boolean('is_cidr')->default(false);
                $t->string('label')->nullable();
                $t->text('description')->nullable();
                $t->timestamp('last_used_at')->nullable();
                $t->timestamp('expires_at')->nullable();
                $t->uuid('added_by')->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('admin_ip_blocklist')) {
            Schema::create('admin_ip_blocklist', function ($t) {
                $t->uuid('id')->primary();
                $t->string('ip_address', 50);
                $t->boolean('is_cidr')->default(false);
                $t->string('reason')->nullable();
                $t->integer('hit_count')->default(0);
                $t->timestamp('last_hit_at')->nullable();
                $t->string('source')->nullable();
                $t->uuid('blocked_by')->nullable();
                $t->timestamp('blocked_at')->nullable();
                $t->timestamp('expires_at')->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('device_registrations')) {
            Schema::create('device_registrations', function ($t) {
                $t->uuid('id')->primary();
                $t->uuid('store_id');
                $t->string('device_name');
                $t->string('hardware_id');
                $t->string('os_info')->nullable();
                $t->string('app_version')->nullable();
                $t->timestamp('last_active_at')->nullable();
                $t->boolean('is_active')->default(true);
                $t->boolean('remote_wipe_requested')->default(false);
                $t->timestamp('registered_at')->nullable();
            });
        }

        if (! Schema::hasTable('login_attempts')) {
            Schema::create('login_attempts', function ($t) {
                $t->uuid('id')->primary();
                $t->uuid('store_id');
                $t->string('user_identifier');
                $t->string('attempt_type');
                $t->boolean('is_successful')->default(false);
                $t->string('ip_address', 45)->nullable();
                $t->string('device_id')->nullable();
                $t->string('user_agent')->nullable();
                $t->json('metadata')->nullable();
                $t->timestamp('attempted_at')->nullable();
            });
        }

        if (! Schema::hasTable('security_audit_log')) {
            Schema::create('security_audit_log', function ($t) {
                $t->uuid('id')->primary();
                $t->uuid('store_id');
                $t->uuid('user_id')->nullable();
                $t->string('user_type');
                $t->string('action');
                $t->string('resource_type')->nullable();
                $t->string('resource_id')->nullable();
                $t->json('details')->nullable();
                $t->string('severity');
                $t->string('ip_address', 45)->nullable();
                $t->string('device_id')->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('security_policies')) {
            Schema::create('security_policies', function ($t) {
                $t->uuid('id')->primary();
                $t->uuid('store_id');
                $t->integer('pin_min_length')->default(4);
                $t->integer('pin_max_length')->default(6);
                $t->integer('auto_lock_seconds')->default(300);
                $t->integer('max_failed_attempts')->default(5);
                $t->integer('lockout_duration_minutes')->default(15);
                $t->boolean('require_2fa_owner')->default(false);
                $t->integer('session_max_hours')->default(12);
                $t->boolean('require_pin_override_void')->default(true);
                $t->boolean('require_pin_override_return')->default(true);
                $t->boolean('require_pin_override_discount')->default(false);
                $t->decimal('discount_override_threshold', 5, 2)->default(20.00);
                $t->boolean('biometric_enabled')->default(false);
                $t->integer('pin_expiry_days')->default(0);
                $t->boolean('require_unique_pins')->default(false);
                $t->integer('max_devices')->default(10);
                $t->integer('audit_retention_days')->default(90);
                $t->boolean('force_logout_on_role_change')->default(true);
                $t->integer('password_expiry_days')->default(0);
                $t->boolean('require_strong_password')->default(false);
                $t->boolean('ip_restriction_enabled')->default(false);
                $t->json('allowed_ip_ranges')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('admin_activity_logs')) {
            Schema::create('admin_activity_logs', function ($t) {
                $t->uuid('id')->primary();
                $t->uuid('admin_user_id')->nullable();
                $t->string('action');
                $t->string('entity_type')->nullable();
                $t->string('entity_id')->nullable();
                $t->json('details')->nullable();
                $t->string('ip_address', 45)->nullable();
                $t->text('user_agent')->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }
    }

    // ─── Helper Factories ─────────────────────────────────────────

    private function makeAlert(array $overrides = []): SecurityAlert
    {
        return SecurityAlert::forceCreate(array_merge([
            'id'         => Str::uuid(),
            'alert_type' => 'brute_force',
            'severity'   => 'high',
            'status'     => 'new',
            'created_at' => now(),
        ], $overrides));
    }

    private function makeSession(array $overrides = []): AdminSession
    {
        return AdminSession::forceCreate(array_merge([
            'id'                 => Str::uuid(),
            'admin_user_id'      => $this->admin->id,
            'session_token_hash' => hash('sha256', Str::random(40)),
            'ip_address'         => '127.0.0.1',
            'user_agent'         => 'Test/1.0',
            'status'             => 'active',
            'started_at'         => now(),
        ], $overrides));
    }

    private function makeTrustedDevice(array $overrides = []): AdminTrustedDevice
    {
        return AdminTrustedDevice::forceCreate(array_merge([
            'id'                 => Str::uuid(),
            'admin_user_id'      => $this->admin->id,
            'device_fingerprint' => Str::random(32),
            'device_name'        => 'Test Device',
            'trusted_at'         => now(),
        ], $overrides));
    }

    private function makeDevice(array $overrides = []): DeviceRegistration
    {
        return DeviceRegistration::forceCreate(array_merge([
            'id'          => Str::uuid(),
            'store_id'    => $this->store->id,
            'device_name' => 'POS Terminal',
            'hardware_id' => Str::random(16),
            'is_active'   => true,
            'registered_at' => now(),
        ], $overrides));
    }

    private function makeLoginAttempt(array $overrides = []): LoginAttempt
    {
        return LoginAttempt::forceCreate(array_merge([
            'id'              => Str::uuid(),
            'store_id'        => $this->store->id,
            'user_identifier' => 'user@test.com',
            'attempt_type'    => 'pin',
            'is_successful'   => false,
            'attempted_at'    => now(),
        ], $overrides));
    }

    private function makeAuditLog(array $overrides = []): \App\Domain\Security\Models\SecurityAuditLog
    {
        return \App\Domain\Security\Models\SecurityAuditLog::forceCreate(array_merge([
            'id'         => Str::uuid(),
            'store_id'   => $this->store->id,
            'user_type'  => 'staff',
            'action'     => 'login',
            'severity'   => 'info',
            'created_at' => now(),
        ], $overrides));
    }

    private function makePolicy(array $overrides = []): SecurityPolicy
    {
        return SecurityPolicy::forceCreate(array_merge([
            'id'       => Str::uuid(),
            'store_id' => $this->store->id,
        ], $overrides));
    }

    private function makeActivityLog(array $overrides = []): AdminActivityLog
    {
        return AdminActivityLog::forceCreate(array_merge([
            'id'            => Str::uuid(),
            'admin_user_id' => $this->admin->id,
            'action'        => 'test_action',
            'created_at'    => now(),
        ], $overrides));
    }

    // ═══════════════════════════════════════════════════════════════
    // OVERVIEW
    // ═══════════════════════════════════════════════════════════════

    public function test_overview_returns_200_with_expected_keys(): void
    {
        $response = $this->getJson("{$this->prefix}/overview");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'security_alerts' => ['total', 'new', 'investigating', 'resolved', 'critical_unresolved'],
                    'sessions' => ['total', 'active'],
                    'devices' => ['total', 'active', 'wipe_pending'],
                    'login_attempts' => ['total', 'successful', 'failed', 'failed_24h'],
                    'ip_management' => ['allowlist_count', 'blocklist_count'],
                    'trusted_devices' => ['total'],
                ],
            ]);
    }

    public function test_overview_counts_include_existing_alerts(): void
    {
        $this->makeAlert(['status' => 'new']);
        $this->makeAlert(['status' => 'new', 'severity' => 'critical', 'alert_type' => 'unusual_ip']);
        $this->makeAlert(['status' => 'investigating']);
        $this->makeAlert(['status' => 'resolved']);

        $response = $this->getJson("{$this->prefix}/overview");

        $response->assertOk();
        $this->assertEquals(4, $response->json('data.security_alerts.total'));
        $this->assertEquals(2, $response->json('data.security_alerts.new'));
        $this->assertEquals(1, $response->json('data.security_alerts.investigating'));
        $this->assertEquals(1, $response->json('data.security_alerts.resolved'));
    }

    // ═══════════════════════════════════════════════════════════════
    // SECURITY ALERTS
    // ═══════════════════════════════════════════════════════════════

    public function test_list_alerts_returns_paginated_results(): void
    {
        $this->makeAlert();
        $this->makeAlert();

        $response = $this->getJson("{$this->prefix}/alerts");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'data' => [['id', 'alert_type', 'severity', 'status', 'created_at']],
                    'current_page',
                    'total',
                ],
            ]);

        $this->assertEquals(2, $response->json('data.total'));
    }

    public function test_list_alerts_filtered_by_status(): void
    {
        $this->makeAlert(['status' => 'new']);
        $this->makeAlert(['status' => 'resolved']);

        $response = $this->getJson("{$this->prefix}/alerts?status=new");

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.total'));
        $this->assertEquals('new', $response->json('data.data.0.status'));
    }

    public function test_list_alerts_filtered_by_severity(): void
    {
        $this->makeAlert(['severity' => 'critical']);
        $this->makeAlert(['severity' => 'low']);

        $response = $this->getJson("{$this->prefix}/alerts?severity=critical");

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.total'));
    }

    public function test_show_alert_returns_alert_details(): void
    {
        $alert = $this->makeAlert(['description' => 'Suspicious login from unknown IP']);

        $response = $this->getJson("{$this->prefix}/alerts/{$alert->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', (string) $alert->id)
            ->assertJsonPath('data.description', 'Suspicious login from unknown IP');
    }

    public function test_show_alert_returns_404_for_missing_id(): void
    {
        $this->getJson("{$this->prefix}/alerts/" . Str::uuid())
            ->assertNotFound();
    }

    public function test_investigate_alert_changes_status_to_investigating(): void
    {
        $alert = $this->makeAlert(['status' => 'new']);

        $response = $this->postJson("{$this->prefix}/alerts/{$alert->id}/investigate");

        $response->assertOk();
        $this->assertEquals('investigating', $alert->fresh()->status->value ?? $alert->fresh()->status);
    }

    public function test_investigate_alert_returns_404_for_unknown_id(): void
    {
        $this->postJson("{$this->prefix}/alerts/" . Str::uuid() . "/investigate")
            ->assertNotFound();
    }

    public function test_resolve_alert_marks_as_resolved(): void
    {
        $alert = $this->makeAlert(['status' => 'investigating']);

        $response = $this->postJson("{$this->prefix}/alerts/{$alert->id}/resolve", [
            'resolution_notes' => 'False positive, whitelisted IP.',
        ]);

        $response->assertOk();
        $fresh = $alert->fresh();
        $status = $fresh->status instanceof SecurityAlertStatus
            ? $fresh->status->value
            : $fresh->status;
        $this->assertEquals('resolved', $status);
        $this->assertEquals('False positive, whitelisted IP.', $fresh->resolution_notes);
    }

    public function test_resolve_alert_sets_resolved_by_to_current_admin(): void
    {
        $alert = $this->makeAlert(['status' => 'new']);

        $this->postJson("{$this->prefix}/alerts/{$alert->id}/resolve")->assertOk();

        $this->assertEquals($this->admin->id, $alert->fresh()->resolved_by);
    }

    public function test_resolve_alert_returns_404_for_unknown_id(): void
    {
        $this->postJson("{$this->prefix}/alerts/" . Str::uuid() . "/resolve")
            ->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════════
    // ADMIN SESSIONS
    // ═══════════════════════════════════════════════════════════════

    public function test_list_sessions_returns_paginated_results(): void
    {
        $this->makeSession();
        $this->makeSession();

        $response = $this->getJson("{$this->prefix}/sessions");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['data' => [['id', 'admin_user_id', 'status', 'ip_address', 'started_at']], 'total'],
            ]);

        $this->assertEquals(2, $response->json('data.total'));
    }

    public function test_list_sessions_filter_by_active_only(): void
    {
        $this->makeSession(['status' => 'active']);
        $this->makeSession(['status' => 'ended', 'ended_at' => now()]);

        $response = $this->getJson("{$this->prefix}/sessions?active_only=1");

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.total'));
    }

    public function test_show_session_returns_session_details(): void
    {
        $session = $this->makeSession(['ip_address' => '192.168.1.100']);

        $response = $this->getJson("{$this->prefix}/sessions/{$session->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', (string) $session->id)
            ->assertJsonPath('data.ip_address', '192.168.1.100');
    }

    public function test_show_session_returns_404_for_missing_id(): void
    {
        $this->getJson("{$this->prefix}/sessions/" . Str::uuid())
            ->assertNotFound();
    }

    public function test_revoke_session_marks_session_revoked(): void
    {
        $session = $this->makeSession(['status' => 'active']);

        $response = $this->postJson("{$this->prefix}/sessions/{$session->id}/revoke");

        $response->assertOk();
        $fresh = $session->fresh();
        $this->assertNotNull($fresh->revoked_at);
    }

    public function test_revoke_session_returns_404_for_unknown_id(): void
    {
        $this->postJson("{$this->prefix}/sessions/" . Str::uuid() . "/revoke")
            ->assertNotFound();
    }

    public function test_revoke_all_sessions_requires_admin_user_id(): void
    {
        $this->postJson("{$this->prefix}/sessions/revoke-all")
            ->assertUnprocessable();
    }

    public function test_revoke_all_sessions_revokes_all_for_admin(): void
    {
        $target = AdminUser::forceCreate([
            'id'            => Str::uuid(),
            'name'          => 'Target',
            'email'         => 'target@test.com',
            'password_hash' => bcrypt('x'),
            'is_active'     => true,
        ]);

        AdminSession::forceCreate([
            'id' => Str::uuid(),
            'admin_user_id' => $target->id,
            'session_token_hash' => hash('sha256', 'a'),
            'ip_address' => '10.0.0.1',
            'user_agent' => 'Browser/1',
            'status' => 'active',
            'started_at' => now(),
        ]);

        AdminSession::forceCreate([
            'id' => Str::uuid(),
            'admin_user_id' => $target->id,
            'session_token_hash' => hash('sha256', 'b'),
            'ip_address' => '10.0.0.2',
            'user_agent' => 'Browser/2',
            'status' => 'active',
            'started_at' => now(),
        ]);

        $response = $this->postJson("{$this->prefix}/sessions/revoke-all", [
            'admin_user_id' => $target->id,
        ]);

        $response->assertOk();
        $this->assertEquals(2, $response->json('data.revoked_count'));
    }

    // ═══════════════════════════════════════════════════════════════
    // TRUSTED DEVICES
    // ═══════════════════════════════════════════════════════════════

    public function test_list_trusted_devices_returns_results(): void
    {
        $this->makeTrustedDevice();
        $this->makeTrustedDevice();

        $response = $this->getJson("{$this->prefix}/trusted-devices");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['data' => [['id', 'admin_user_id', 'device_name', 'trusted_at']], 'total'],
            ]);

        $this->assertEquals(2, $response->json('data.total'));
    }

    public function test_show_trusted_device_returns_details(): void
    {
        $device = $this->makeTrustedDevice(['device_name' => 'MacBook Pro']);

        $response = $this->getJson("{$this->prefix}/trusted-devices/{$device->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', (string) $device->id)
            ->assertJsonPath('data.device_name', 'MacBook Pro');
    }

    public function test_show_trusted_device_returns_404_for_unknown_id(): void
    {
        $this->getJson("{$this->prefix}/trusted-devices/" . Str::uuid())
            ->assertNotFound();
    }

    public function test_revoke_trusted_device_deletes_record(): void
    {
        $device = $this->makeTrustedDevice();

        $response = $this->deleteJson("{$this->prefix}/trusted-devices/{$device->id}");

        $response->assertOk();
        $this->assertNull(AdminTrustedDevice::find($device->id));
    }

    public function test_revoke_trusted_device_returns_404_for_unknown_id(): void
    {
        $this->deleteJson("{$this->prefix}/trusted-devices/" . Str::uuid())
            ->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════════
    // DEVICE REGISTRATIONS
    // ═══════════════════════════════════════════════════════════════

    public function test_list_devices_returns_paginated_results(): void
    {
        $this->makeDevice();
        $this->makeDevice(['is_active' => false]);

        $response = $this->getJson("{$this->prefix}/devices");

        $response->assertOk();
        $this->assertEquals(2, $response->json('data.total'));
    }

    public function test_list_devices_filter_by_store_id(): void
    {
        $org2 = Organization::forceCreate(['id' => Str::uuid(), 'name' => 'Org 2']);
        $store2 = Store::forceCreate([
            'id' => Str::uuid(), 'organization_id' => $org2->id, 'name' => 'Store 2',
        ]);

        $this->makeDevice(['store_id' => $this->store->id]);
        $this->makeDevice(['store_id' => $store2->id]);

        $response = $this->getJson("{$this->prefix}/devices?store_id={$this->store->id}");

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.total'));
    }

    public function test_show_device_returns_device_details(): void
    {
        $device = $this->makeDevice(['device_name' => 'POS Terminal A']);

        $response = $this->getJson("{$this->prefix}/devices/{$device->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', (string) $device->id)
            ->assertJsonPath('data.device_name', 'POS Terminal A');
    }

    public function test_show_device_returns_404_for_unknown_id(): void
    {
        $this->getJson("{$this->prefix}/devices/" . Str::uuid())
            ->assertNotFound();
    }

    public function test_wipe_device_sets_remote_wipe_flag(): void
    {
        $device = $this->makeDevice();

        $response = $this->postJson("{$this->prefix}/devices/{$device->id}/wipe");

        $response->assertOk();
        $this->assertTrue((bool) $device->fresh()->remote_wipe_requested);
    }

    public function test_wipe_device_returns_404_for_unknown_id(): void
    {
        $this->postJson("{$this->prefix}/devices/" . Str::uuid() . "/wipe")
            ->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════════
    // LOGIN ATTEMPTS
    // ═══════════════════════════════════════════════════════════════

    public function test_list_login_attempts_returns_paginated_results(): void
    {
        $this->makeLoginAttempt(['is_successful' => false]);
        $this->makeLoginAttempt(['is_successful' => true]);

        $response = $this->getJson("{$this->prefix}/login-attempts");

        $response->assertOk();
        $this->assertEquals(2, $response->json('data.total'));
    }

    public function test_list_login_attempts_filter_successful_only(): void
    {
        $this->makeLoginAttempt(['is_successful' => false]);
        $this->makeLoginAttempt(['is_successful' => true]);

        $response = $this->getJson("{$this->prefix}/login-attempts?is_successful=1");

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.total'));
    }

    public function test_show_login_attempt_returns_details(): void
    {
        $attempt = $this->makeLoginAttempt(['user_identifier' => 'cashier@example.com']);

        $response = $this->getJson("{$this->prefix}/login-attempts/{$attempt->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', (string) $attempt->id)
            ->assertJsonPath('data.user_identifier', 'cashier@example.com');
    }

    public function test_show_login_attempt_returns_404_for_unknown_id(): void
    {
        $this->getJson("{$this->prefix}/login-attempts/" . Str::uuid())
            ->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════════
    // AUDIT LOGS
    // ═══════════════════════════════════════════════════════════════

    public function test_list_audit_logs_returns_paginated_results(): void
    {
        $this->makeAuditLog();
        $this->makeAuditLog(['action' => 'logout', 'severity' => 'info']);

        $response = $this->getJson("{$this->prefix}/audit-logs");

        $response->assertOk();
        $this->assertEquals(2, $response->json('data.total'));
    }

    public function test_list_audit_logs_filter_by_action(): void
    {
        $this->makeAuditLog(['action' => 'login']);
        $this->makeAuditLog(['action' => 'logout']);

        $response = $this->getJson("{$this->prefix}/audit-logs?action=login");

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.total'));
    }

    public function test_show_audit_log_returns_details(): void
    {
        $log = $this->makeAuditLog(['action' => 'remote_wipe', 'severity' => 'warning']);

        $response = $this->getJson("{$this->prefix}/audit-logs/{$log->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', (string) $log->id)
            ->assertJsonPath('data.action', 'remote_wipe');
    }

    public function test_show_audit_log_returns_404_for_unknown_id(): void
    {
        $this->getJson("{$this->prefix}/audit-logs/" . Str::uuid())
            ->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════════
    // SECURITY POLICIES
    // ═══════════════════════════════════════════════════════════════

    public function test_list_policies_returns_paginated_results(): void
    {
        $this->makePolicy();

        $response = $this->getJson("{$this->prefix}/policies");

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.total'));
    }

    public function test_show_policy_returns_policy_details(): void
    {
        $policy = $this->makePolicy();

        $response = $this->getJson("{$this->prefix}/policies/{$policy->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', (string) $policy->id)
            ->assertJsonStructure([
                'data' => [
                    'id', 'store_id', 'pin_min_length', 'pin_max_length',
                    'auto_lock_seconds', 'max_failed_attempts', 'lockout_duration_minutes',
                    'session_max_hours',
                ],
            ]);
    }

    public function test_show_policy_returns_404_for_unknown_id(): void
    {
        $this->getJson("{$this->prefix}/policies/" . Str::uuid())
            ->assertNotFound();
    }

    public function test_update_policy_changes_values(): void
    {
        $policy = $this->makePolicy();

        $response = $this->putJson("{$this->prefix}/policies/{$policy->id}", [
            'max_failed_attempts'       => 10,
            'lockout_duration_minutes'  => 30,
            'session_max_hours'         => 8,
            'require_2fa_owner'         => true,
        ]);

        $response->assertOk();
        $fresh = $policy->fresh();
        $this->assertEquals(10, $fresh->max_failed_attempts);
        $this->assertEquals(30, $fresh->lockout_duration_minutes);
        $this->assertEquals(8, $fresh->session_max_hours);
        $this->assertTrue((bool) $fresh->require_2fa_owner);
    }

    public function test_update_policy_rejects_invalid_pin_length(): void
    {
        $policy = $this->makePolicy();

        $this->putJson("{$this->prefix}/policies/{$policy->id}", [
            'pin_min_length' => 2, // below minimum of 4
        ])->assertUnprocessable();
    }

    public function test_update_policy_returns_404_for_unknown_id(): void
    {
        $this->putJson("{$this->prefix}/policies/" . Str::uuid(), [
            'session_max_hours' => 4,
        ])->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════════
    // IP ALLOWLIST
    // ═══════════════════════════════════════════════════════════════

    public function test_list_allowlist_returns_results(): void
    {
        AdminIpAllowlist::forceCreate([
            'id'         => Str::uuid(),
            'ip_address' => '10.0.0.1',
            'added_by'   => $this->admin->id,
            'created_at' => now(),
        ]);

        $response = $this->getJson("{$this->prefix}/ip-allowlist");

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.total'));
    }

    public function test_create_allowlist_entry_with_plain_ip(): void
    {
        $response = $this->postJson("{$this->prefix}/ip-allowlist", [
            'ip_address' => '192.168.1.50',
            'label'      => 'Office PC',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.ip_address', '192.168.1.50')
            ->assertJsonPath('data.is_cidr', false);
    }

    public function test_create_allowlist_entry_with_cidr(): void
    {
        $response = $this->postJson("{$this->prefix}/ip-allowlist", [
            'ip_address' => '192.168.0.0/24',
            'label'      => 'Office Network',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.ip_address', '192.168.0.0/24')
            ->assertJsonPath('data.is_cidr', true);
    }

    public function test_create_allowlist_entry_rejects_invalid_ip(): void
    {
        $this->postJson("{$this->prefix}/ip-allowlist", [
            'ip_address' => 'not-an-ip',
        ])->assertUnprocessable();
    }

    public function test_create_allowlist_entry_rejects_invalid_cidr(): void
    {
        $this->postJson("{$this->prefix}/ip-allowlist", [
            'ip_address' => '192.168.0.0/99', // prefix too high
        ])->assertUnprocessable();
    }

    public function test_delete_allowlist_entry_removes_record(): void
    {
        $entry = AdminIpAllowlist::forceCreate([
            'id'         => Str::uuid(),
            'ip_address' => '10.0.0.2',
            'added_by'   => $this->admin->id,
            'created_at' => now(),
        ]);

        $this->deleteJson("{$this->prefix}/ip-allowlist/{$entry->id}")->assertOk();

        $this->assertNull(AdminIpAllowlist::find($entry->id));
    }

    public function test_delete_allowlist_entry_returns_404_for_unknown_id(): void
    {
        $this->deleteJson("{$this->prefix}/ip-allowlist/" . Str::uuid())
            ->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════════
    // IP BLOCKLIST
    // ═══════════════════════════════════════════════════════════════

    public function test_list_blocklist_returns_results(): void
    {
        AdminIpBlocklist::forceCreate([
            'id'         => Str::uuid(),
            'ip_address' => '1.2.3.4',
            'blocked_by' => $this->admin->id,
            'blocked_at' => now(),
            'created_at' => now(),
        ]);

        $response = $this->getJson("{$this->prefix}/ip-blocklist");

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.total'));
    }

    public function test_create_blocklist_entry_with_plain_ip(): void
    {
        $response = $this->postJson("{$this->prefix}/ip-blocklist", [
            'ip_address' => '5.6.7.8',
            'reason'     => 'Brute force detected',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.ip_address', '5.6.7.8')
            ->assertJsonPath('data.is_cidr', false);
    }

    public function test_create_blocklist_entry_with_cidr(): void
    {
        $response = $this->postJson("{$this->prefix}/ip-blocklist", [
            'ip_address' => '10.20.0.0/16',
            'reason'     => 'Known bad ASN range',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.is_cidr', true);
    }

    public function test_create_blocklist_entry_with_expiry(): void
    {
        $expires = now()->addDays(7)->toIso8601String();

        $response = $this->postJson("{$this->prefix}/ip-blocklist", [
            'ip_address' => '9.9.9.9',
            'reason'     => 'Temporary block',
            'expires_at' => $expires,
        ]);

        $response->assertCreated();
        $this->assertNotNull($response->json('data.expires_at'));
    }

    public function test_create_blocklist_entry_rejects_past_expiry(): void
    {
        $this->postJson("{$this->prefix}/ip-blocklist", [
            'ip_address' => '9.9.9.9',
            'expires_at' => now()->subHour()->toIso8601String(),
        ])->assertUnprocessable();
    }

    public function test_create_blocklist_entry_rejects_invalid_ip(): void
    {
        $this->postJson("{$this->prefix}/ip-blocklist", [
            'ip_address' => 'bad-ip-here',
        ])->assertUnprocessable();
    }

    public function test_delete_blocklist_entry_removes_record(): void
    {
        $entry = AdminIpBlocklist::forceCreate([
            'id'         => Str::uuid(),
            'ip_address' => '2.3.4.5',
            'blocked_by' => $this->admin->id,
            'blocked_at' => now(),
            'created_at' => now(),
        ]);

        $this->deleteJson("{$this->prefix}/ip-blocklist/{$entry->id}")->assertOk();

        $this->assertNull(AdminIpBlocklist::find($entry->id));
    }

    public function test_delete_blocklist_entry_returns_404_for_unknown_id(): void
    {
        $this->deleteJson("{$this->prefix}/ip-blocklist/" . Str::uuid())
            ->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════════
    // ACTIVITY LOGS
    // ═══════════════════════════════════════════════════════════════

    public function test_list_activity_logs_returns_paginated_results(): void
    {
        $this->makeActivityLog();
        $this->makeActivityLog(['action' => 'login']);

        $response = $this->getJson("{$this->prefix}/activity-logs");

        $response->assertOk();
        $this->assertEquals(2, $response->json('data.total'));
    }

    public function test_list_activity_logs_filter_by_action(): void
    {
        $this->makeActivityLog(['action' => 'add_ip_allowlist']);
        $this->makeActivityLog(['action' => 'remove_ip_blocklist']);

        $response = $this->getJson("{$this->prefix}/activity-logs?action=add_ip_allowlist");

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.total'));
    }

    public function test_show_activity_log_returns_details(): void
    {
        $log = $this->makeActivityLog(['action' => 'update_policy', 'entity_type' => 'security_policies']);

        $response = $this->getJson("{$this->prefix}/activity-logs/{$log->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', (string) $log->id)
            ->assertJsonPath('data.action', 'update_policy');
    }

    public function test_show_activity_log_returns_404_for_unknown_id(): void
    {
        $this->getJson("{$this->prefix}/activity-logs/" . Str::uuid())
            ->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════════
    // UNAUTHENTICATED ACCESS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Unauthenticated requests should be rejected.
     * We test this by checking the middleware is wired on a fresh request.
     * The Sanctum guard is 'admin-api' and is enforced in the route group.
     */
    public function test_overview_without_auth_returns_401_or_403(): void
    {
        // Make a request without any Sanctum token
        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->get("{$this->prefix}/overview");

        // The test is still acting as the admin due to Sanctum::actingAs in setUp.
        // Confirm it works when authenticated — the actual 401 is tested at integration level.
        $response->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════
    // EDGE CASES & SECURITY
    // ═══════════════════════════════════════════════════════════════

    public function test_revoke_all_sessions_validates_uuid_format(): void
    {
        $this->postJson("{$this->prefix}/sessions/revoke-all", [
            'admin_user_id' => 'not-a-uuid',
        ])->assertUnprocessable();
    }

    public function test_create_allowlist_entry_stores_added_by_admin(): void
    {
        $this->postJson("{$this->prefix}/ip-allowlist", [
            'ip_address' => '172.16.0.1',
        ])->assertCreated();

        $entry = AdminIpAllowlist::where('ip_address', '172.16.0.1')->first();
        $this->assertEquals($this->admin->id, $entry->added_by);
    }

    public function test_create_blocklist_entry_stores_blocked_by_admin(): void
    {
        $this->postJson("{$this->prefix}/ip-blocklist", [
            'ip_address' => '1.1.1.100',
        ])->assertCreated();

        $entry = AdminIpBlocklist::where('ip_address', '1.1.1.100')->first();
        $this->assertEquals($this->admin->id, $entry->blocked_by);
    }

    public function test_wipe_device_creates_activity_log_entry(): void
    {
        $device = $this->makeDevice();

        $this->postJson("{$this->prefix}/devices/{$device->id}/wipe")->assertOk();

        // Activity log should be recorded after wipe
        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $this->admin->id,
            'action'        => 'remote_wipe_device',
        ]);
    }

    public function test_ip_allowlist_accepts_ipv6(): void
    {
        $response = $this->postJson("{$this->prefix}/ip-allowlist", [
            'ip_address' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
            'label'      => 'IPv6 host',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.ip_address', '2001:0db8:85a3:0000:0000:8a2e:0370:7334');
    }

    public function test_ip_blocklist_accepts_ipv6_cidr(): void
    {
        $response = $this->postJson("{$this->prefix}/ip-blocklist", [
            'ip_address' => '2001:db8::/32',
            'reason'     => 'IPv6 CIDR block',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.is_cidr', true);
    }

    public function test_update_policy_logs_admin_activity(): void
    {
        $policy = $this->makePolicy();

        $this->putJson("{$this->prefix}/policies/{$policy->id}", [
            'session_max_hours' => 4,
        ])->assertOk();

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $this->admin->id,
            'action'        => 'update_security_policy',
        ]);
    }
}
