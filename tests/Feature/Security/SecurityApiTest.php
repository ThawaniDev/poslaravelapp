<?php

namespace Tests\Feature\Security;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Security\Models\DeviceRegistration;
use App\Domain\Security\Models\LoginAttempt;
use App\Domain\Security\Models\SecurityAuditLog;
use App\Domain\Security\Models\SecurityPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SecurityApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private string $storeId;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure tables exist for SQLite
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
                $t->timestamps();
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
                $t->timestamp('attempted_at')->nullable();
            });
        }

        $org = Organization::create(['name' => 'Security Test Org']);
        $store = Store::create([
            'organization_id' => $org->id,
            'name' => 'Security Test Store',
            'name_ar' => 'متجر اختبار الأمان',
        ]);
        $this->storeId = $store->id;

        $user = User::create([
            'name' => 'Security Admin',
            'email' => 'security@test.com',
            'store_id' => $store->id,
            'password_hash' => bcrypt('password'),
        ]);
        $this->token = $user->createToken('test', ['*'])->plainTextToken;
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    // ─── Policy Tests ───────────────────────────────────────────

    public function test_get_policy_creates_default(): void
    {
        $res = $this->getJson("/api/v2/security/policy?store_id={$this->storeId}", $this->auth());
        $res->assertOk();

        $data = $res->json('data');
        $this->assertEquals($this->storeId, $data['store_id']);
        $this->assertEquals(4, $data['pin_min_length']);
        $this->assertEquals(6, $data['pin_max_length']);
        $this->assertEquals(300, $data['auto_lock_seconds']);
        $this->assertEquals(5, $data['max_failed_attempts']);
        $this->assertEquals(true, $data['require_pin_override_void']);
    }

    public function test_get_policy_requires_store_id(): void
    {
        $res = $this->getJson('/api/v2/security/policy', $this->auth());
        $res->assertStatus(422);
    }

    public function test_update_policy(): void
    {
        // Create default first
        $this->getJson("/api/v2/security/policy?store_id={$this->storeId}", $this->auth());

        $res = $this->putJson("/api/v2/security/policy?store_id={$this->storeId}", [
            'pin_min_length' => 6,
            'max_failed_attempts' => 3,
            'auto_lock_seconds' => 120,
            'require_pin_override_discount' => true,
            'discount_override_threshold' => 30.00,
        ], $this->auth());

        $res->assertOk();
        $data = $res->json('data');
        $this->assertEquals(6, $data['pin_min_length']);
        $this->assertEquals(3, $data['max_failed_attempts']);
        $this->assertEquals(120, $data['auto_lock_seconds']);
        $this->assertTrue($data['require_pin_override_discount']);
    }

    public function test_update_policy_validation(): void
    {
        $res = $this->putJson("/api/v2/security/policy?store_id={$this->storeId}", [
            'pin_min_length' => 2, // below min:4
        ], $this->auth());
        $res->assertStatus(422);
    }

    public function test_policy_unauthenticated(): void
    {
        $res = $this->getJson("/api/v2/security/policy?store_id={$this->storeId}");
        $res->assertUnauthorized();
    }

    // ─── Audit Log Tests ────────────────────────────────────────

    public function test_record_audit(): void
    {
        $res = $this->postJson('/api/v2/security/audit-logs', [
            'store_id' => $this->storeId,
            'user_type' => 'staff',
            'action' => 'login',
            'severity' => 'info',
            'ip_address' => '192.168.1.1',
        ], $this->auth());

        $res->assertStatus(201);
        $this->assertEquals('login', $res->json('data.action'));
        $this->assertEquals('info', $res->json('data.severity'));
    }

    public function test_record_audit_with_details(): void
    {
        $res = $this->postJson('/api/v2/security/audit-logs', [
            'store_id' => $this->storeId,
            'user_type' => 'owner',
            'action' => 'settings_change',
            'severity' => 'warning',
            'resource_type' => 'settings',
            'details' => ['field' => 'pin_min_length', 'old' => 4, 'new' => 6],
        ], $this->auth());

        $res->assertStatus(201);
        $this->assertEquals('settings', $res->json('data.resource_type'));
    }

    public function test_list_audit_logs(): void
    {
        SecurityAuditLog::create([
            'store_id' => $this->storeId,
            'user_type' => 'staff',
            'action' => 'login',
            'severity' => 'info',
            'created_at' => now(),
        ]);
        SecurityAuditLog::create([
            'store_id' => $this->storeId,
            'user_type' => 'staff',
            'action' => 'failed_login',
            'severity' => 'warning',
            'created_at' => now(),
        ]);

        $res = $this->getJson("/api/v2/security/audit-logs?store_id={$this->storeId}", $this->auth());
        $res->assertOk();
        $this->assertCount(2, $res->json('data.data'));
    }

    public function test_list_audit_logs_filter_by_action(): void
    {
        SecurityAuditLog::create([
            'store_id' => $this->storeId,
            'user_type' => 'staff',
            'action' => 'login',
            'severity' => 'info',
            'created_at' => now(),
        ]);
        SecurityAuditLog::create([
            'store_id' => $this->storeId,
            'user_type' => 'staff',
            'action' => 'failed_login',
            'severity' => 'warning',
            'created_at' => now(),
        ]);

        $res = $this->getJson("/api/v2/security/audit-logs?store_id={$this->storeId}&action=login", $this->auth());
        $res->assertOk();
        $this->assertCount(1, $res->json('data.data'));
    }

    public function test_list_audit_logs_filter_by_severity(): void
    {
        SecurityAuditLog::create([
            'store_id' => $this->storeId,
            'user_type' => 'staff',
            'action' => 'login',
            'severity' => 'info',
            'created_at' => now(),
        ]);
        SecurityAuditLog::create([
            'store_id' => $this->storeId,
            'user_type' => 'system',
            'action' => 'remote_wipe',
            'severity' => 'critical',
            'created_at' => now(),
        ]);

        $res = $this->getJson("/api/v2/security/audit-logs?store_id={$this->storeId}&severity=critical", $this->auth());
        $res->assertOk();
        $this->assertCount(1, $res->json('data.data'));
        $this->assertEquals('critical', $res->json('data.data.0.severity'));
    }

    public function test_record_audit_validation(): void
    {
        $res = $this->postJson('/api/v2/security/audit-logs', [
            'store_id' => $this->storeId,
            // missing required fields
        ], $this->auth());
        $res->assertStatus(422);
    }

    // ─── Device Tests ───────────────────────────────────────────

    public function test_register_device(): void
    {
        $res = $this->postJson('/api/v2/security/devices', [
            'store_id' => $this->storeId,
            'device_name' => 'POS Terminal 1',
            'hardware_id' => 'HW-ABC-123',
            'os_info' => 'Android 14',
            'app_version' => '2.1.0',
        ], $this->auth());

        $res->assertStatus(201);
        $this->assertEquals('POS Terminal 1', $res->json('data.device_name'));
        $this->assertEquals('HW-ABC-123', $res->json('data.hardware_id'));
        $this->assertTrue($res->json('data.is_active'));
    }

    public function test_register_device_updates_existing(): void
    {
        // First registration
        $this->postJson('/api/v2/security/devices', [
            'store_id' => $this->storeId,
            'device_name' => 'POS Terminal 1',
            'hardware_id' => 'HW-ABC-123',
            'os_info' => 'Android 13',
        ], $this->auth());

        // Update same hardware_id
        $res = $this->postJson('/api/v2/security/devices', [
            'store_id' => $this->storeId,
            'device_name' => 'POS Terminal 1 Updated',
            'hardware_id' => 'HW-ABC-123',
            'os_info' => 'Android 14',
            'app_version' => '2.2.0',
        ], $this->auth());

        $res->assertStatus(201);
        $this->assertEquals('POS Terminal 1 Updated', $res->json('data.device_name'));
        $this->assertEquals(1, DeviceRegistration::count());
    }

    public function test_list_devices(): void
    {
        DeviceRegistration::create([
            'store_id' => $this->storeId,
            'device_name' => 'Terminal A',
            'hardware_id' => 'HW-A',
            'is_active' => true,
            'last_active_at' => now(),
            'registered_at' => now(),
        ]);
        DeviceRegistration::create([
            'store_id' => $this->storeId,
            'device_name' => 'Terminal B',
            'hardware_id' => 'HW-B',
            'is_active' => false,
            'last_active_at' => now(),
            'registered_at' => now(),
        ]);

        $res = $this->getJson("/api/v2/security/devices?store_id={$this->storeId}", $this->auth());
        $res->assertOk();
        $this->assertCount(2, $res->json('data'));
    }

    public function test_list_devices_active_only(): void
    {
        DeviceRegistration::create([
            'store_id' => $this->storeId,
            'device_name' => 'Active',
            'hardware_id' => 'HW-1',
            'is_active' => true,
            'last_active_at' => now(),
            'registered_at' => now(),
        ]);
        DeviceRegistration::create([
            'store_id' => $this->storeId,
            'device_name' => 'Inactive',
            'hardware_id' => 'HW-2',
            'is_active' => false,
            'last_active_at' => now(),
            'registered_at' => now(),
        ]);

        $res = $this->getJson("/api/v2/security/devices?store_id={$this->storeId}&active_only=true", $this->auth());
        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals('Active', $res->json('data.0.device_name'));
    }

    public function test_deactivate_device(): void
    {
        $device = DeviceRegistration::create([
            'store_id' => $this->storeId,
            'device_name' => 'Terminal',
            'hardware_id' => 'HW-X',
            'is_active' => true,
            'last_active_at' => now(),
            'registered_at' => now(),
        ]);

        $res = $this->putJson("/api/v2/security/devices/{$device->id}/deactivate", [], $this->auth());
        $res->assertOk();
        $this->assertFalse($res->json('data.is_active'));
    }

    public function test_request_remote_wipe(): void
    {
        $device = DeviceRegistration::create([
            'store_id' => $this->storeId,
            'device_name' => 'Terminal',
            'hardware_id' => 'HW-WIPE',
            'is_active' => true,
            'last_active_at' => now(),
            'registered_at' => now(),
        ]);

        $res = $this->putJson("/api/v2/security/devices/{$device->id}/remote-wipe", [], $this->auth());
        $res->assertOk();
        $this->assertTrue($res->json('data.remote_wipe_requested'));
        $this->assertFalse($res->json('data.is_active'));
    }

    public function test_register_device_validation(): void
    {
        $res = $this->postJson('/api/v2/security/devices', [
            'store_id' => $this->storeId,
            // missing device_name and hardware_id
        ], $this->auth());
        $res->assertStatus(422);
    }

    // ─── Login Attempt Tests ────────────────────────────────────

    public function test_record_login_attempt(): void
    {
        $res = $this->postJson('/api/v2/security/login-attempts', [
            'store_id' => $this->storeId,
            'user_identifier' => 'cashier@test.com',
            'attempt_type' => 'pin',
            'is_successful' => true,
            'ip_address' => '10.0.0.1',
        ], $this->auth());

        $res->assertStatus(201);
        $this->assertEquals('cashier@test.com', $res->json('data.user_identifier'));
        $this->assertEquals('pin', $res->json('data.attempt_type'));
        $this->assertTrue($res->json('data.is_successful'));
    }

    public function test_record_failed_login_attempt(): void
    {
        $res = $this->postJson('/api/v2/security/login-attempts', [
            'store_id' => $this->storeId,
            'user_identifier' => 'cashier@test.com',
            'attempt_type' => 'password',
            'is_successful' => false,
        ], $this->auth());

        $res->assertStatus(201);
        $this->assertFalse($res->json('data.is_successful'));
    }

    public function test_list_login_attempts(): void
    {
        LoginAttempt::create([
            'store_id' => $this->storeId,
            'user_identifier' => 'user1',
            'attempt_type' => 'pin',
            'is_successful' => true,
            'attempted_at' => now(),
        ]);
        LoginAttempt::create([
            'store_id' => $this->storeId,
            'user_identifier' => 'user2',
            'attempt_type' => 'password',
            'is_successful' => false,
            'attempted_at' => now(),
        ]);

        $res = $this->getJson("/api/v2/security/login-attempts?store_id={$this->storeId}", $this->auth());
        $res->assertOk();
        $this->assertCount(2, $res->json('data.data'));
    }

    public function test_list_login_attempts_by_type(): void
    {
        LoginAttempt::create([
            'store_id' => $this->storeId,
            'user_identifier' => 'u1',
            'attempt_type' => 'pin',
            'is_successful' => true,
            'attempted_at' => now(),
        ]);
        LoginAttempt::create([
            'store_id' => $this->storeId,
            'user_identifier' => 'u2',
            'attempt_type' => 'biometric',
            'is_successful' => true,
            'attempted_at' => now(),
        ]);

        $res = $this->getJson("/api/v2/security/login-attempts?store_id={$this->storeId}&attempt_type=pin", $this->auth());
        $res->assertOk();
        $this->assertCount(1, $res->json('data.data'));
    }

    public function test_failed_attempt_count(): void
    {
        // Create multiple failed attempts
        for ($i = 0; $i < 3; $i++) {
            LoginAttempt::create([
                'store_id' => $this->storeId,
                'user_identifier' => 'brute@test.com',
                'attempt_type' => 'pin',
                'is_successful' => false,
                'attempted_at' => now(),
            ]);
        }

        $res = $this->getJson(
            "/api/v2/security/login-attempts/failed-count?store_id={$this->storeId}&user_identifier=brute@test.com",
            $this->auth(),
        );
        $res->assertOk();
        $this->assertEquals(3, $res->json('data.count'));
    }

    public function test_failed_attempt_count_window(): void
    {
        // Old failed attempt (outside window)
        LoginAttempt::create([
            'store_id' => $this->storeId,
            'user_identifier' => 'window@test.com',
            'attempt_type' => 'pin',
            'is_successful' => false,
            'attempted_at' => now()->subMinutes(60),
        ]);
        // Recent failed attempt
        LoginAttempt::create([
            'store_id' => $this->storeId,
            'user_identifier' => 'window@test.com',
            'attempt_type' => 'pin',
            'is_successful' => false,
            'attempted_at' => now(),
        ]);

        $res = $this->getJson(
            "/api/v2/security/login-attempts/failed-count?store_id={$this->storeId}&user_identifier=window@test.com&window_minutes=15",
            $this->auth(),
        );
        $res->assertOk();
        $this->assertEquals(1, $res->json('data.count'));
    }

    public function test_failed_attempt_count_requires_params(): void
    {
        $res = $this->getJson('/api/v2/security/login-attempts/failed-count', $this->auth());
        $res->assertStatus(422);
    }

    public function test_record_login_attempt_validation(): void
    {
        $res = $this->postJson('/api/v2/security/login-attempts', [
            'store_id' => $this->storeId,
            // missing user_identifier, attempt_type, is_successful
        ], $this->auth());
        $res->assertStatus(422);
    }

    // ─── Cross-cutting ──────────────────────────────────────────

    public function test_all_endpoints_require_auth(): void
    {
        $this->getJson('/api/v2/security/policy?store_id=x')->assertUnauthorized();
        $this->putJson('/api/v2/security/policy?store_id=x')->assertUnauthorized();
        $this->getJson('/api/v2/security/audit-logs?store_id=x')->assertUnauthorized();
        $this->postJson('/api/v2/security/audit-logs')->assertUnauthorized();
        $this->getJson('/api/v2/security/devices?store_id=x')->assertUnauthorized();
        $this->postJson('/api/v2/security/devices')->assertUnauthorized();
        $this->getJson('/api/v2/security/login-attempts?store_id=x')->assertUnauthorized();
        $this->postJson('/api/v2/security/login-attempts')->assertUnauthorized();
        $this->getJson('/api/v2/security/login-attempts/failed-count?store_id=x')->assertUnauthorized();
    }

    public function test_policy_idempotent_get(): void
    {
        $this->getJson("/api/v2/security/policy?store_id={$this->storeId}", $this->auth());
        $this->getJson("/api/v2/security/policy?store_id={$this->storeId}", $this->auth())->assertOk();

        $this->assertEquals(1, SecurityPolicy::where('store_id', $this->storeId)->count());
    }
}
