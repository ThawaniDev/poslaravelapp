<?php

namespace Tests\Unit\Domain\Security;

use App\Domain\Security\Models\DeviceRegistration;
use App\Domain\Security\Models\LoginAttempt;
use App\Domain\Security\Models\SecurityAuditLog;
use App\Domain\Security\Models\SecurityIncident;
use App\Domain\Security\Models\SecurityPolicy;
use App\Domain\Security\Models\SecuritySession;
use App\Domain\Security\Services\SecurityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Unit tests for SecurityService — covers all service methods in isolation.
 */
class SecurityServiceTest extends TestCase
{
    use RefreshDatabase;

    private SecurityService $service;
    private string $storeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SecurityService();
        $this->storeId = (string) \Illuminate\Support\Str::uuid();

        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        if (!Schema::hasTable('security_policies')) {
            Schema::create('security_policies', function ($t) {
                $t->uuid('id')->primary();
                $t->uuid('store_id')->unique();
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
        if (!Schema::hasTable('security_audit_log')) {
            Schema::create('security_audit_log', function ($t) {
                $t->uuid('id')->primary();
                $t->uuid('store_id');
                $t->uuid('user_id')->nullable();
                $t->string('user_type')->nullable();
                $t->string('action');
                $t->string('resource_type')->nullable();
                $t->string('resource_id')->nullable();
                $t->json('details')->nullable();
                $t->string('severity')->default('info');
                $t->string('ip_address', 45)->nullable();
                $t->string('device_id')->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }
        if (!Schema::hasTable('device_registrations')) {
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
        if (!Schema::hasTable('login_attempts')) {
            Schema::create('login_attempts', function ($t) {
                $t->uuid('id')->primary();
                $t->uuid('store_id');
                $t->string('user_identifier');
                $t->string('attempt_type');
                $t->boolean('is_successful')->default(false);
                $t->string('ip_address', 45)->nullable();
                $t->string('device_id')->nullable();
                $t->timestamp('attempted_at')->nullable();
            });
        }
        if (!Schema::hasTable('security_sessions')) {
            Schema::create('security_sessions', function ($t) {
                $t->uuid('id')->primary();
                $t->uuid('store_id');
                $t->uuid('user_id')->nullable();
                $t->string('device_id')->nullable();
                $t->string('session_type')->default('pos');
                $t->string('status')->default('active');
                $t->string('ip_address', 45)->nullable();
                $t->string('user_agent')->nullable();
                $t->timestamp('started_at')->nullable();
                $t->timestamp('last_activity_at')->nullable();
                $t->timestamp('ended_at')->nullable();
                $t->string('end_reason')->nullable();
                $t->json('metadata')->nullable();
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('security_incidents')) {
            Schema::create('security_incidents', function ($t) {
                $t->uuid('id')->primary();
                $t->uuid('store_id');
                $t->string('incident_type');
                $t->string('severity');
                $t->string('title');
                $t->text('description')->nullable();
                $t->string('user_id')->nullable();
                $t->string('device_id')->nullable();
                $t->string('ip_address', 45)->nullable();
                $t->json('metadata')->nullable();
                $t->string('status')->default('open');
                $t->timestamp('resolved_at')->nullable();
                $t->string('resolved_by')->nullable();
                $t->text('resolution_notes')->nullable();
                $t->timestamps();
            });
        }
    }

    // ─── Policy Tests ────────────────────────────────────────────

    /** @test */
    public function getPolicy_creates_default_when_none_exists(): void
    {
        $policy = $this->service->getPolicy($this->storeId);

        $this->assertInstanceOf(SecurityPolicy::class, $policy);
        $this->assertEquals($this->storeId, $policy->store_id);
        $this->assertEquals(4, $policy->pin_min_length);
        $this->assertEquals(5, $policy->max_failed_attempts);
        $this->assertEquals(15, $policy->lockout_duration_minutes);
        $this->assertEquals(1, SecurityPolicy::where('store_id', $this->storeId)->count());
    }

    /** @test */
    public function getPolicy_is_idempotent_on_multiple_calls(): void
    {
        $first  = $this->service->getPolicy($this->storeId);
        $second = $this->service->getPolicy($this->storeId);

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, SecurityPolicy::where('store_id', $this->storeId)->count());
    }

    /** @test */
    public function updatePolicy_persists_changed_fields(): void
    {
        $this->service->getPolicy($this->storeId);

        $updated = $this->service->updatePolicy($this->storeId, [
            'pin_min_length'       => 6,
            'max_failed_attempts'  => 3,
            'require_2fa_owner'    => true,
            'session_max_hours'    => 8,
        ]);

        $this->assertEquals(6, $updated->pin_min_length);
        $this->assertEquals(3, $updated->max_failed_attempts);
        $this->assertTrue($updated->require_2fa_owner);
        $this->assertEquals(8, $updated->session_max_hours);
    }

    /** @test */
    public function updatePolicy_with_ip_restriction_and_ranges(): void
    {
        $this->service->getPolicy($this->storeId);

        $updated = $this->service->updatePolicy($this->storeId, [
            'ip_restriction_enabled' => true,
            'allowed_ip_ranges'      => ['192.168.1.0/24', '10.0.0.0/8'],
        ]);

        $this->assertTrue($updated->ip_restriction_enabled);
        $this->assertIsArray($updated->allowed_ip_ranges);
        $this->assertContains('192.168.1.0/24', $updated->allowed_ip_ranges);
    }

    // ─── Audit Log Tests ─────────────────────────────────────────

    /** @test */
    public function recordAudit_creates_audit_log_entry(): void
    {
        $userId = (string) \Illuminate\Support\Str::uuid();

        $log = $this->service->recordAudit([
            'store_id'      => $this->storeId,
            'user_id'       => $userId,
            'user_type'     => 'staff',
            'action'        => 'login',
            'severity'      => 'info',
            'ip_address'    => '127.0.0.1',
        ]);

        $this->assertInstanceOf(SecurityAuditLog::class, $log);
        $this->assertEquals('login', $log->action instanceof \BackedEnum ? $log->action->value : $log->action);
        $this->assertEquals('info', $log->severity instanceof \BackedEnum ? $log->severity->value : $log->severity);
        $this->assertEquals($userId, $log->user_id);
    }

    /** @test */
    public function recordAudit_stores_details_json(): void
    {
        $log = $this->service->recordAudit([
            'store_id' => $this->storeId,
            'user_type' => 'owner',
            'action'   => 'settings_change',
            'severity' => 'warning',
            'details'  => ['field' => 'pin_min_length', 'old' => 4, 'new' => 6],
        ]);

        $this->assertNotNull($log->id);
        $this->assertEquals('settings_change', $log->action instanceof \BackedEnum ? $log->action->value : $log->action);
    }

    /** @test */
    public function listAuditLogs_returns_paginated_for_store(): void
    {
        SecurityAuditLog::create(['store_id' => $this->storeId, 'user_type' => 'staff', 'action' => 'login', 'severity' => 'info', 'created_at' => now()]);
        SecurityAuditLog::create(['store_id' => $this->storeId, 'user_type' => 'staff', 'action' => 'logout', 'severity' => 'info', 'created_at' => now()]);
        // Different store — should NOT appear
        SecurityAuditLog::create(['store_id' => (string) \Illuminate\Support\Str::uuid(), 'user_type' => 'staff', 'action' => 'login', 'severity' => 'info', 'created_at' => now()]);

        $result = $this->service->listAuditLogs($this->storeId);

        $this->assertEquals(2, $result->total());
    }

    /** @test */
    public function listAuditLogs_filters_by_action(): void
    {
        SecurityAuditLog::create(['store_id' => $this->storeId, 'user_type' => 'staff', 'action' => 'login',  'severity' => 'info', 'created_at' => now()]);
        SecurityAuditLog::create(['store_id' => $this->storeId, 'user_type' => 'staff', 'action' => 'logout', 'severity' => 'info', 'created_at' => now()]);

        $result = $this->service->listAuditLogs($this->storeId, action: 'login');

        $this->assertEquals(1, $result->total());
        $item = $result->items()[0];
        $this->assertEquals('login', $item->action instanceof \BackedEnum ? $item->action->value : $item->action);
    }

    /** @test */
    public function listAuditLogs_filters_by_severity(): void
    {
        SecurityAuditLog::create(['store_id' => $this->storeId, 'user_type' => 'staff', 'action' => 'login',       'severity' => 'info',     'created_at' => now()]);
        SecurityAuditLog::create(['store_id' => $this->storeId, 'user_type' => 'staff', 'action' => 'failed_login', 'severity' => 'warning',  'created_at' => now()]);
        SecurityAuditLog::create(['store_id' => $this->storeId, 'user_type' => 'staff', 'action' => 'remote_wipe', 'severity' => 'critical',  'created_at' => now()]);

        $result = $this->service->listAuditLogs($this->storeId, severity: 'critical');

        $this->assertEquals(1, $result->total());
        $sev = $result->items()[0]->severity;
        $this->assertEquals('critical', $sev instanceof \BackedEnum ? $sev->value : $sev);
    }

    /** @test */
    public function listAuditLogs_respects_per_page_cap_of_200(): void
    {
        $result = $this->service->listAuditLogs($this->storeId, perPage: 9999);

        $this->assertEquals(200, $result->perPage());
    }

    /** @test */
    public function listAuditLogs_filters_by_user_id(): void
    {
        $userId = (string) \Illuminate\Support\Str::uuid();
        SecurityAuditLog::create(['store_id' => $this->storeId, 'user_id' => $userId, 'user_type' => 'staff', 'action' => 'login', 'severity' => 'info', 'created_at' => now()]);
        SecurityAuditLog::create(['store_id' => $this->storeId, 'user_id' => null, 'user_type' => 'system', 'action' => 'settings_change', 'severity' => 'info', 'created_at' => now()]);

        $result = $this->service->listAuditLogs($this->storeId, userId: $userId);

        $this->assertEquals(1, $result->total());
        $this->assertEquals($userId, $result->items()[0]->user_id);
    }

    /** @test */
    public function listAuditLogs_filters_by_since(): void
    {
        SecurityAuditLog::create(['store_id' => $this->storeId, 'user_type' => 'staff', 'action' => 'logout', 'severity' => 'info', 'created_at' => now()->subDays(10)]);
        SecurityAuditLog::create(['store_id' => $this->storeId, 'user_type' => 'staff', 'action' => 'login', 'severity' => 'info', 'created_at' => now()]);

        $result = $this->service->listAuditLogs($this->storeId, since: now()->subDay()->toDateTimeString());

        $this->assertEquals(1, $result->total());
        $act = $result->items()[0]->action;
        $this->assertEquals('login', $act instanceof \BackedEnum ? $act->value : $act);
    }

    /** @test */
    public function auditStats_returns_correct_structure(): void
    {
        SecurityAuditLog::create(['store_id' => $this->storeId, 'user_type' => 'staff', 'action' => 'login',       'severity' => 'info',    'created_at' => now()]);
        SecurityAuditLog::create(['store_id' => $this->storeId, 'user_type' => 'staff', 'action' => 'failed_login', 'severity' => 'warning', 'created_at' => now()]);
        SecurityAuditLog::create(['store_id' => $this->storeId, 'user_type' => 'staff', 'action' => 'remote_wipe', 'severity' => 'critical', 'created_at' => now()]);

        $stats = $this->service->auditStats($this->storeId);

        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('by_severity', $stats);
        $this->assertArrayHasKey('by_action', $stats);
        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(1, $stats['by_severity']['info'] ?? 0);
        $this->assertEquals(1, $stats['by_severity']['warning'] ?? 0);
        $this->assertEquals(1, $stats['by_severity']['critical'] ?? 0);
    }

    /** @test */
    public function auditStats_excludes_entries_outside_window(): void
    {
        SecurityAuditLog::create(['store_id' => $this->storeId, 'user_type' => 'staff', 'action' => 'login', 'severity' => 'info', 'created_at' => now()->subDays(30)]);
        SecurityAuditLog::create(['store_id' => $this->storeId, 'user_type' => 'staff', 'action' => 'login', 'severity' => 'info', 'created_at' => now()]);

        $stats = $this->service->auditStats($this->storeId, days: 7);

        $this->assertEquals(1, $stats['total']);
    }

    /** @test */
    public function exportAuditLogs_returns_csv_string_with_headers(): void
    {
        SecurityAuditLog::create([
            'store_id'   => $this->storeId,
            'user_id'    => (string) \Illuminate\Support\Str::uuid(),
            'user_type'  => 'staff',
            'action'     => 'login',
            'severity'   => 'info',
            'ip_address' => '10.0.0.1',
            'created_at' => now(),
        ]);

        $csv = $this->service->exportAuditLogs($this->storeId);

        $this->assertIsString($csv);
        $lines = explode("\n", trim($csv));
        // Header row
        $this->assertStringContainsString('timestamp', $lines[0]);
        $this->assertStringContainsString('action', $lines[0]);
        $this->assertStringContainsString('severity', $lines[0]);
        // Data row
        $this->assertGreaterThan(1, count($lines));
    }

    /** @test */
    public function exportAuditLogs_filters_by_action(): void
    {
        SecurityAuditLog::create(['store_id' => $this->storeId, 'user_type' => 'staff', 'action' => 'login',  'severity' => 'info', 'created_at' => now()]);
        SecurityAuditLog::create(['store_id' => $this->storeId, 'user_type' => 'staff', 'action' => 'logout', 'severity' => 'info', 'created_at' => now()]);

        $csv = $this->service->exportAuditLogs($this->storeId, action: 'login');

        $lines = array_filter(explode("\n", trim($csv)));
        // 1 header + 1 data row
        $this->assertCount(2, $lines);
    }

    /** @test */
    public function exportAuditLogs_limit_caps_rows(): void
    {
        for ($i = 0; $i < 10; $i++) {
            SecurityAuditLog::create([
                'store_id'  => $this->storeId,
                'user_type' => 'staff',
                'action'    => 'login',
                'severity'  => 'info',
                'created_at' => now(),
            ]);
        }

        $csv = $this->service->exportAuditLogs($this->storeId, limit: 3);
        $lines = array_filter(explode("\n", trim($csv)));
        // 1 header + 3 data rows
        $this->assertCount(4, $lines);
    }

    // ─── Device Tests ────────────────────────────────────────────

    /** @test */
    public function registerDevice_creates_new_device(): void
    {
        $device = $this->service->registerDevice([
            'store_id'    => $this->storeId,
            'device_name' => 'Counter 1',
            'hardware_id' => 'HW-001',
            'os_info'     => 'Windows 11',
            'app_version' => '2.0.0',
        ]);

        $this->assertInstanceOf(DeviceRegistration::class, $device);
        $this->assertEquals('Counter 1', $device->device_name);
        $this->assertTrue($device->is_active);
    }

    /** @test */
    public function registerDevice_updates_existing_by_hardware_id(): void
    {
        $this->service->registerDevice([
            'store_id'    => $this->storeId,
            'device_name' => 'Old Name',
            'hardware_id' => 'HW-DUP',
        ]);

        $updated = $this->service->registerDevice([
            'store_id'    => $this->storeId,
            'device_name' => 'New Name',
            'hardware_id' => 'HW-DUP',
            'app_version' => '2.1.0',
        ]);

        $this->assertEquals('New Name', $updated->device_name);
        $this->assertEquals(1, DeviceRegistration::where('hardware_id', 'HW-DUP')->count());
    }

    /** @test */
    public function listDevices_returns_store_devices(): void
    {
        $this->service->registerDevice(['store_id' => $this->storeId, 'device_name' => 'D1', 'hardware_id' => 'HW-A']);
        $this->service->registerDevice(['store_id' => $this->storeId, 'device_name' => 'D2', 'hardware_id' => 'HW-B']);
        // Different store — should NOT appear
        $otherId = (string) \Illuminate\Support\Str::uuid();
        $this->service->registerDevice(['store_id' => $otherId, 'device_name' => 'D3', 'hardware_id' => 'HW-C']);

        $devices = $this->service->listDevices($this->storeId);

        $this->assertCount(2, $devices);
    }

    /** @test */
    public function listDevices_activeOnly_filters_inactive(): void
    {
        $d1 = $this->service->registerDevice(['store_id' => $this->storeId, 'device_name' => 'Active', 'hardware_id' => 'HW-ACT']);
        $d2 = $this->service->registerDevice(['store_id' => $this->storeId, 'device_name' => 'Inactive', 'hardware_id' => 'HW-INACT']);
        $d2->update(['is_active' => false]);

        $active = $this->service->listDevices($this->storeId, activeOnly: true);
        $all    = $this->service->listDevices($this->storeId);

        $this->assertCount(1, $active);
        $this->assertCount(2, $all);
        $this->assertEquals('Active', $active->first()->device_name);
    }

    /** @test */
    public function deactivateDevice_sets_is_active_false(): void
    {
        $device = $this->service->registerDevice(['store_id' => $this->storeId, 'device_name' => 'D', 'hardware_id' => 'HW-DEACT']);

        $deactivated = $this->service->deactivateDevice($device->id);

        $this->assertFalse($deactivated->is_active);
    }

    /** @test */
    public function requestRemoteWipe_flags_device(): void
    {
        $device = $this->service->registerDevice(['store_id' => $this->storeId, 'device_name' => 'D', 'hardware_id' => 'HW-WIPE']);

        $wiped = $this->service->requestRemoteWipe($device->id);

        $this->assertTrue($wiped->remote_wipe_requested);
    }

    /** @test */
    public function touchDevice_updates_last_active_at(): void
    {
        $device = $this->service->registerDevice(['store_id' => $this->storeId, 'device_name' => 'D', 'hardware_id' => 'HW-TOUCH']);

        $touched = $this->service->touchDevice($device->id);

        $this->assertNotNull($touched->last_active_at);
    }

    // ─── Login Attempt Tests ─────────────────────────────────────

    /** @test */
    public function recordLoginAttempt_creates_successful_attempt(): void
    {
        $attempt = $this->service->recordLoginAttempt([
            'store_id'        => $this->storeId,
            'user_identifier' => 'staff@test.com',
            'attempt_type'    => 'pin',
            'is_successful'   => true,
            'ip_address'      => '192.168.1.1',
        ]);

        $this->assertInstanceOf(LoginAttempt::class, $attempt);
        $this->assertTrue($attempt->is_successful);
    }

    /** @test */
    public function recordLoginAttempt_creates_failed_attempt(): void
    {
        $attempt = $this->service->recordLoginAttempt([
            'store_id'        => $this->storeId,
            'user_identifier' => 'staff@test.com',
            'attempt_type'    => 'password',
            'is_successful'   => false,
        ]);

        $this->assertFalse($attempt->is_successful);
    }

    /** @test */
    public function recentFailedAttempts_counts_within_window(): void
    {
        // 3 failed attempts within window
        for ($i = 0; $i < 3; $i++) {
            LoginAttempt::create([
                'store_id'        => $this->storeId,
                'user_identifier' => 'user@test.com',
                'attempt_type'    => 'pin',
                'is_successful'   => false,
                'attempted_at'    => now()->subMinutes(5),
            ]);
        }
        // 1 old attempt outside window
        LoginAttempt::create([
            'store_id'        => $this->storeId,
            'user_identifier' => 'user@test.com',
            'attempt_type'    => 'pin',
            'is_successful'   => false,
            'attempted_at'    => now()->subHours(2),
        ]);

        $count = $this->service->recentFailedAttempts($this->storeId, 'user@test.com', 15);

        $this->assertEquals(3, $count);
    }

    /** @test */
    public function isLockedOut_returns_true_when_exceeds_threshold(): void
    {
        // Create policy with max_failed_attempts = 5
        $this->service->getPolicy($this->storeId);

        for ($i = 0; $i < 6; $i++) {
            LoginAttempt::create([
                'store_id'        => $this->storeId,
                'user_identifier' => 'locked@test.com',
                'attempt_type'    => 'pin',
                'is_successful'   => false,
                'attempted_at'    => now()->subMinutes(5),
            ]);
        }

        $this->assertTrue($this->service->isLockedOut($this->storeId, 'locked@test.com'));
    }

    /** @test */
    public function isLockedOut_returns_false_when_below_threshold(): void
    {
        $this->service->getPolicy($this->storeId);

        for ($i = 0; $i < 3; $i++) {
            LoginAttempt::create([
                'store_id'        => $this->storeId,
                'user_identifier' => 'safe@test.com',
                'attempt_type'    => 'pin',
                'is_successful'   => false,
                'attempted_at'    => now()->subMinutes(5),
            ]);
        }

        $this->assertFalse($this->service->isLockedOut($this->storeId, 'safe@test.com'));
    }

    /** @test */
    public function loginAttemptStats_returns_daily_totals(): void
    {
        LoginAttempt::create(['store_id' => $this->storeId, 'user_identifier' => 'u1', 'attempt_type' => 'pin', 'is_successful' => true,  'attempted_at' => now()]);
        LoginAttempt::create(['store_id' => $this->storeId, 'user_identifier' => 'u1', 'attempt_type' => 'pin', 'is_successful' => false, 'attempted_at' => now()]);

        $stats = $this->service->loginAttemptStats($this->storeId);

        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('successful', $stats);
        $this->assertArrayHasKey('failed', $stats);
        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(1, $stats['successful']);
        $this->assertEquals(1, $stats['failed']);
    }

    // ─── Session Tests ───────────────────────────────────────────

    /** @test */
    public function startSession_creates_active_session(): void
    {
        $userId = (string) \Illuminate\Support\Str::uuid();

        $session = $this->service->startSession([
            'store_id'   => $this->storeId,
            'user_id'    => $userId,
            'ip_address' => '10.0.0.1',
        ]);

        $this->assertInstanceOf(SecuritySession::class, $session);
        $this->assertEquals('active', $session->status);
        $this->assertNotNull($session->started_at);
    }

    /** @test */
    public function endSession_marks_session_ended(): void
    {
        $userId  = (string) \Illuminate\Support\Str::uuid();
        $session = $this->service->startSession(['store_id' => $this->storeId, 'user_id' => $userId]);

        $ended = $this->service->endSession($session->id, 'logout');

        $this->assertEquals('ended', $ended->status);
        $this->assertEquals('logout', $ended->end_reason);
        $this->assertNotNull($ended->ended_at);
    }

    /** @test */
    public function endAllSessions_terminates_all_active_for_user(): void
    {
        $userId = (string) \Illuminate\Support\Str::uuid();
        $this->service->startSession(['store_id' => $this->storeId, 'user_id' => $userId]);
        $this->service->startSession(['store_id' => $this->storeId, 'user_id' => $userId]);

        $count = $this->service->endAllSessions($this->storeId, $userId);

        $this->assertEquals(2, $count);
        $this->assertEquals(0, SecuritySession::where('store_id', $this->storeId)->where('status', 'active')->count());
    }

    /** @test */
    public function endAllSessions_does_not_affect_other_users(): void
    {
        $userId1 = (string) \Illuminate\Support\Str::uuid();
        $userId2 = (string) \Illuminate\Support\Str::uuid();
        $this->service->startSession(['store_id' => $this->storeId, 'user_id' => $userId1]);
        $this->service->startSession(['store_id' => $this->storeId, 'user_id' => $userId2]);

        $this->service->endAllSessions($this->storeId, $userId1);

        $this->assertEquals(1, SecuritySession::where('store_id', $this->storeId)->where('status', 'active')->count());
    }

    /** @test */
    public function sessionHeartbeat_updates_last_activity_at(): void
    {
        $userId  = (string) \Illuminate\Support\Str::uuid();
        $session = $this->service->startSession(['store_id' => $this->storeId, 'user_id' => $userId]);

        \Illuminate\Support\Facades\DB::table('security_sessions')
            ->where('id', $session->id)
            ->update(['last_activity_at' => now()->subHour()]);

        $refreshed = $this->service->sessionHeartbeat($session->id);

        $this->assertTrue($refreshed->last_activity_at > now()->subMinute());
    }

    /** @test */
    public function listSessions_returns_all_for_store(): void
    {
        $userId = (string) \Illuminate\Support\Str::uuid();
        $this->service->startSession(['store_id' => $this->storeId, 'user_id' => $userId]);
        $this->service->startSession(['store_id' => $this->storeId, 'user_id' => $userId]);
        // Different store
        $otherId = (string) \Illuminate\Support\Str::uuid();
        $this->service->startSession(['store_id' => $otherId, 'user_id' => $userId]);

        $sessions = $this->service->listSessions($this->storeId);

        $this->assertCount(2, $sessions);
    }

    /** @test */
    public function listSessions_activeOnly_filters_correctly(): void
    {
        $userId = (string) \Illuminate\Support\Str::uuid();
        $active  = $this->service->startSession(['store_id' => $this->storeId, 'user_id' => $userId]);
        $ended   = $this->service->startSession(['store_id' => $this->storeId, 'user_id' => $userId]);
        $this->service->endSession($ended->id, 'logout');

        $activeSessions = $this->service->listSessions($this->storeId, activeOnly: true);

        $this->assertCount(1, $activeSessions);
        $this->assertEquals($active->id, $activeSessions->first()->id);
    }

    // ─── Incident Tests ──────────────────────────────────────────

    /** @test */
    public function createIncident_creates_open_incident(): void
    {
        $incident = $this->service->createIncident([
            'store_id'      => $this->storeId,
            'incident_type' => 'brute_force',
            'severity'      => 'high',
            'title'         => 'Multiple failed logins',
        ]);

        $this->assertInstanceOf(SecurityIncident::class, $incident);
        $this->assertEquals('open', $incident->status);
        $this->assertEquals('brute_force', $incident->incident_type);
    }

    /** @test */
    public function resolveIncident_marks_resolved(): void
    {
        $incident = $this->service->createIncident([
            'store_id'      => $this->storeId,
            'incident_type' => 'unauthorized_access',
            'severity'      => 'critical',
            'title'         => 'Unauthorized entry',
        ]);

        $resolved = $this->service->resolveIncident($incident->id, (string) \Illuminate\Support\Str::uuid(), 'False positive');

        $this->assertEquals('resolved', $resolved->status);
        $this->assertNotNull($resolved->resolved_at);
        $this->assertEquals('False positive', $resolved->resolution_notes);
    }

    /** @test */
    public function listIncidents_filters_by_status(): void
    {
        $this->service->createIncident(['store_id' => $this->storeId, 'incident_type' => 'brute_force', 'severity' => 'high', 'title' => 'Open']);
        $closed = $this->service->createIncident(['store_id' => $this->storeId, 'incident_type' => 'device_theft', 'severity' => 'high', 'title' => 'Resolved']);
        $this->service->resolveIncident($closed->id, (string) \Illuminate\Support\Str::uuid());

        $open = $this->service->listIncidents($this->storeId, status: 'open');

        $this->assertEquals(1, $open->total());
        $this->assertEquals('Open', $open->items()[0]->title);
    }

    /** @test */
    public function listIncidents_filters_by_severity(): void
    {
        $this->service->createIncident(['store_id' => $this->storeId, 'incident_type' => 'brute_force', 'severity' => 'high',   'title' => 'High']);
        $this->service->createIncident(['store_id' => $this->storeId, 'incident_type' => 'brute_force', 'severity' => 'critical', 'title' => 'Critical']);

        $critical = $this->service->listIncidents($this->storeId, severity: 'critical');

        $this->assertEquals(1, $critical->total());
        $this->assertEquals('Critical', $critical->items()[0]->title);
    }

    // ─── Overview Tests ──────────────────────────────────────────

    /** @test */
    public function getOverview_returns_correct_structure(): void
    {
        $overview = $this->service->getOverview($this->storeId);

        $requiredKeys = [
            'active_devices', 'active_sessions', 'unresolved_incidents',
            'failed_logins_today', 'total_audit_logs', 'locked_out_users',
            'recent_activity', 'policy', 'login_stats', 'audit_stats', 'critical_audits_7d',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $overview, "Missing key: {$key}");
        }
    }

    /** @test */
    public function getOverview_counts_active_devices_correctly(): void
    {
        $this->service->registerDevice(['store_id' => $this->storeId, 'device_name' => 'D1', 'hardware_id' => 'HW-OV-1']);
        $d2 = $this->service->registerDevice(['store_id' => $this->storeId, 'device_name' => 'D2', 'hardware_id' => 'HW-OV-2']);
        $d2->update(['is_active' => false]);

        $overview = $this->service->getOverview($this->storeId);

        $this->assertEquals(1, $overview['active_devices']);
    }

    /** @test */
    public function getOverview_counts_active_sessions_correctly(): void
    {
        $userId = (string) \Illuminate\Support\Str::uuid();
        $s1 = $this->service->startSession(['store_id' => $this->storeId, 'user_id' => $userId]);
        $s2 = $this->service->startSession(['store_id' => $this->storeId, 'user_id' => $userId]);
        $this->service->endSession($s2->id, 'logout');

        $overview = $this->service->getOverview($this->storeId);

        $this->assertEquals(1, $overview['active_sessions']);
    }

    /** @test */
    public function getOverview_counts_failed_logins_today(): void
    {
        LoginAttempt::create(['store_id' => $this->storeId, 'user_identifier' => 'u', 'attempt_type' => 'pin', 'is_successful' => false, 'attempted_at' => now()]);
        LoginAttempt::create(['store_id' => $this->storeId, 'user_identifier' => 'u', 'attempt_type' => 'pin', 'is_successful' => false, 'attempted_at' => now()->subDays(2)]);

        $overview = $this->service->getOverview($this->storeId);

        $this->assertEquals(1, $overview['failed_logins_today']);
    }

    /** @test */
    public function getOverview_recent_activity_has_max_10_entries(): void
    {
        for ($i = 0; $i < 15; $i++) {
            SecurityAuditLog::create(['store_id' => $this->storeId, 'user_type' => 'staff', 'action' => 'login', 'severity' => 'info', 'created_at' => now()]);
        }

        $overview = $this->service->getOverview($this->storeId);

        $this->assertLessThanOrEqual(10, count($overview['recent_activity']));
    }
}
