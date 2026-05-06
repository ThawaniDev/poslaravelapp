<?php

namespace Tests\Feature\Security;

use App\Domain\Auth\Enums\UserRole;
use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Security\Models\DeviceRegistration;
use App\Domain\Security\Models\LoginAttempt;
use App\Domain\Security\Models\SecurityAuditLog;
use App\Domain\Security\Models\SecurityIncident;
use App\Domain\Security\Models\SecurityPolicy;
use App\Domain\Security\Models\SecuritySession;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckActiveSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tests that:
 *  1. All security endpoints require authentication (401 when unauthenticated).
 *  2. Non-owner users without the required permissions receive 403.
 *  3. Owner users bypass permission checks and can access all endpoints.
 *  4. Users without an active subscription receive 403 from plan.active.
 *
 * Unlike most tests this class re-registers the REAL middleware so that
 * actual permission and subscription enforcement is tested.
 */
class SecurityPermissionSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private string $storeId;
    private string $orgId;

    // Full-access owner token
    private string $ownerToken;
    // No-permission cashier token
    private string $cashierToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureSchema();
        $this->seedData();
        $this->restoreRealMiddleware();
    }

    /**
     * Re-register the real permission and plan middlewares so enforcement is
     * actually tested (the base TestCase replaces them with BypassPermissionMiddleware).
     */
    private function restoreRealMiddleware(): void
    {
        $router = app('router');
        $router->aliasMiddleware('permission', CheckPermission::class);
        $router->aliasMiddleware('plan.active', CheckActiveSubscription::class);
    }

    private function seedData(): void
    {
        $org = Organization::create(['name' => 'Perm Test Org']);
        $this->orgId = $org->id;

        $store = Store::create([
            'organization_id' => $org->id,
            'name' => 'Perm Test Store',
            'name_ar' => 'متجر اختبار الصلاحيات',
        ]);
        $this->storeId = $store->id;

        // Owner user
        $owner = User::create([
            'name'          => 'Store Owner',
            'email'         => 'owner@perm.test',
            'store_id'      => $store->id,
            'organization_id' => $org->id,
            'role'          => UserRole::Owner,
            'password_hash' => bcrypt('password'),
        ]);
        $this->ownerToken = $owner->createToken('owner', ['*'])->plainTextToken;

        // Cashier — no permissions, no special role
        $cashier = User::create([
            'name'          => 'Cashier',
            'email'         => 'cashier@perm.test',
            'store_id'      => $store->id,
            'organization_id' => $org->id,
            'role'          => UserRole::Cashier,
            'password_hash' => bcrypt('password'),
        ]);
        $this->cashierToken = $cashier->createToken('cashier', ['*'])->plainTextToken;
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
        // Subscription tables
        if (!Schema::hasTable('subscription_plans')) {
            Schema::create('subscription_plans', function ($t) {
                $t->uuid('id')->primary();
                $t->string('name');
                $t->string('slug')->unique();
                $t->decimal('monthly_price', 10, 2)->default(0);
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('store_subscriptions')) {
            Schema::create('store_subscriptions', function ($t) {
                $t->uuid('id')->primary();
                $t->uuid('organization_id');
                $t->uuid('subscription_plan_id');
                $t->string('status', 30)->default('active');
                $t->string('billing_cycle', 20)->default('monthly');
                $t->timestamp('trial_ends_at')->nullable();
                $t->timestamp('current_period_start')->nullable();
                $t->timestamp('current_period_end')->nullable();
                $t->timestamp('cancelled_at')->nullable();
                $t->timestamps();
            });
        }
    }

    private function ownerAuth(): array
    {
        return ['Authorization' => "Bearer {$this->ownerToken}"];
    }

    private function cashierAuth(): array
    {
        return ['Authorization' => "Bearer {$this->cashierToken}"];
    }

    private function createActiveSubscription(): void
    {
        $plan = \App\Domain\Subscription\Models\SubscriptionPlan::create([
            'name'          => 'Test Plan',
            'slug'          => 'test-plan-' . uniqid(),
            'monthly_price' => 99.00,
        ]);

        \App\Domain\ProviderSubscription\Models\StoreSubscription::create([
            'organization_id'      => $this->orgId,
            'subscription_plan_id' => $plan->id,
            'status'               => 'active',
            'billing_cycle'        => 'monthly',
        ]);
    }

    // ─── Unauthenticated (401) Tests ─────────────────────────────

    /** @test */
    public function all_security_endpoints_return_401_when_unauthenticated(): void
    {
        $endpoints = [
            ['GET',  "/api/v2/security/overview?store_id={$this->storeId}"],
            ['GET',  "/api/v2/security/policy?store_id={$this->storeId}"],
            ['PUT',  "/api/v2/security/policy?store_id={$this->storeId}"],
            ['GET',  "/api/v2/security/audit-logs?store_id={$this->storeId}"],
            ['POST', '/api/v2/security/audit-logs'],
            ['GET',  "/api/v2/security/audit-logs/export?store_id={$this->storeId}"],
            ['GET',  "/api/v2/security/audit-stats?store_id={$this->storeId}"],
            ['GET',  "/api/v2/security/devices?store_id={$this->storeId}"],
            ['POST', '/api/v2/security/devices'],
            ['GET',  "/api/v2/security/login-attempts?store_id={$this->storeId}"],
            ['POST', '/api/v2/security/login-attempts'],
            ['GET',  "/api/v2/security/login-attempts/failed-count?store_id={$this->storeId}&user_identifier=x"],
            ['GET',  "/api/v2/security/login-attempts/is-locked-out?store_id={$this->storeId}&user_identifier=x"],
            ['GET',  "/api/v2/security/sessions?store_id={$this->storeId}"],
            ['POST', '/api/v2/security/sessions'],
            ['POST', '/api/v2/security/sessions/end-all'],
            ['GET',  "/api/v2/security/incidents?store_id={$this->storeId}"],
            ['POST', '/api/v2/security/incidents'],
        ];

        foreach ($endpoints as [$method, $uri]) {
            $method = strtolower($method) . 'Json';
            $res = $this->$method($uri);
            $this->assertEquals(401, $res->getStatusCode(), "Expected 401 for {$method} {$uri}");
        }
    }

    // ─── Subscription (403) Tests ────────────────────────────────

    /** @test */
    public function security_endpoints_return_403_without_active_subscription(): void
    {
        // Owner with NO subscription
        $res = $this->getJson("/api/v2/security/overview?store_id={$this->storeId}", $this->ownerAuth());

        $res->assertForbidden();
        $res->assertJsonPath('error_code', 'no_subscription');
        $this->assertTrue($res->json('subscription_required'));
    }

    /** @test */
    public function security_endpoints_accessible_with_active_subscription(): void
    {
        $this->createActiveSubscription();

        $res = $this->getJson("/api/v2/security/overview?store_id={$this->storeId}", $this->ownerAuth());

        $res->assertOk();
    }

    /** @test */
    public function trial_subscription_allows_access(): void
    {
        $plan = \App\Domain\Subscription\Models\SubscriptionPlan::create([
            'name'          => 'Trial Plan',
            'slug'          => 'trial-plan-' . uniqid(),
            'monthly_price' => 0,
        ]);

        \App\Domain\ProviderSubscription\Models\StoreSubscription::create([
            'organization_id'      => $this->orgId,
            'subscription_plan_id' => $plan->id,
            'status'               => 'trial',
            'billing_cycle'        => 'monthly',
            'trial_ends_at'        => now()->addDays(14),
        ]);

        $res = $this->getJson("/api/v2/security/overview?store_id={$this->storeId}", $this->ownerAuth());

        $res->assertOk();
    }

    /** @test */
    public function expired_subscription_blocks_access(): void
    {
        $plan = \App\Domain\Subscription\Models\SubscriptionPlan::create([
            'name'          => 'Expired Plan',
            'slug'          => 'expired-plan-' . uniqid(),
            'monthly_price' => 99,
        ]);

        \App\Domain\ProviderSubscription\Models\StoreSubscription::create([
            'organization_id'      => $this->orgId,
            'subscription_plan_id' => $plan->id,
            'status'               => 'expired',
            'billing_cycle'        => 'monthly',
        ]);

        $res = $this->getJson("/api/v2/security/overview?store_id={$this->storeId}", $this->ownerAuth());

        $res->assertForbidden();
        $res->assertJsonPath('error_code', 'no_subscription');
    }

    // ─── Permission Enforcement (403) Tests ──────────────────────

    /** @test */
    public function cashier_cannot_access_security_dashboard(): void
    {
        $this->createActiveSubscription();

        $res = $this->getJson("/api/v2/security/overview?store_id={$this->storeId}", $this->cashierAuth());

        // Cashiers don't have security.view_dashboard
        $res->assertForbidden();
    }

    /** @test */
    public function cashier_cannot_manage_security_policy(): void
    {
        $this->createActiveSubscription();

        $res = $this->getJson("/api/v2/security/policy?store_id={$this->storeId}", $this->cashierAuth());

        $res->assertForbidden();
    }

    /** @test */
    public function cashier_cannot_view_audit_logs(): void
    {
        $this->createActiveSubscription();

        $res = $this->getJson("/api/v2/security/audit-logs?store_id={$this->storeId}", $this->cashierAuth());

        $res->assertForbidden();
    }

    /** @test */
    public function cashier_cannot_export_audit_logs(): void
    {
        $this->createActiveSubscription();

        $res = $this->getJson("/api/v2/security/audit-logs/export?store_id={$this->storeId}", $this->cashierAuth());

        $res->assertForbidden();
    }

    /** @test */
    public function cashier_cannot_register_devices(): void
    {
        $this->createActiveSubscription();

        $res = $this->postJson('/api/v2/security/devices', [
            'store_id'    => $this->storeId,
            'device_name' => 'Test Device',
            'hardware_id' => 'HW-001',
        ], $this->cashierAuth());

        $res->assertForbidden();
    }

    /** @test */
    public function cashier_cannot_create_incidents(): void
    {
        $this->createActiveSubscription();

        $res = $this->postJson('/api/v2/security/incidents', [
            'store_id'      => $this->storeId,
            'incident_type' => 'brute_force',
            'severity'      => 'high',
            'title'         => 'Test Incident',
        ], $this->cashierAuth());

        $res->assertForbidden();
    }

    // ─── Owner Access Tests ───────────────────────────────────────

    /** @test */
    public function owner_can_access_all_security_endpoints(): void
    {
        $this->createActiveSubscription();

        $this->getJson("/api/v2/security/overview?store_id={$this->storeId}", $this->ownerAuth())->assertOk();
        $this->getJson("/api/v2/security/policy?store_id={$this->storeId}", $this->ownerAuth())->assertOk();
        $this->getJson("/api/v2/security/audit-logs?store_id={$this->storeId}", $this->ownerAuth())->assertOk();
        $this->getJson("/api/v2/security/devices?store_id={$this->storeId}", $this->ownerAuth())->assertOk();
        $this->getJson("/api/v2/security/login-attempts?store_id={$this->storeId}", $this->ownerAuth())->assertOk();
        $this->getJson("/api/v2/security/sessions?store_id={$this->storeId}", $this->ownerAuth())->assertOk();
        $this->getJson("/api/v2/security/incidents?store_id={$this->storeId}", $this->ownerAuth())->assertOk();
    }

    /** @test */
    public function owner_can_export_audit_logs(): void
    {
        $this->createActiveSubscription();

        SecurityAuditLog::create([
            'store_id'  => $this->storeId,
            'user_type' => 'owner',
            'action'    => 'login',
            'severity'  => 'info',
            'created_at' => now(),
        ]);

        $res = $this->get("/api/v2/security/audit-logs/export?store_id={$this->storeId}", $this->ownerAuth());

        $res->assertOk();
        // CSV export returns a streamed response — capture via output buffering
        ob_start();
        $res->baseResponse->sendContent();
        $content = ob_get_clean();
        $this->assertStringContainsString('timestamp', $content);
    }

    /** @test */
    public function owner_can_register_and_deactivate_device(): void
    {
        $this->createActiveSubscription();

        $register = $this->postJson('/api/v2/security/devices', [
            'store_id'    => $this->storeId,
            'device_name' => 'Test Terminal',
            'hardware_id' => 'HW-PERM-001',
        ], $this->ownerAuth());
        $register->assertStatus(201);

        $deviceId = $register->json('data.id');
        $this->putJson("/api/v2/security/devices/{$deviceId}/deactivate", [], $this->ownerAuth())->assertOk();
    }

    /** @test */
    public function owner_can_request_remote_wipe(): void
    {
        $this->createActiveSubscription();

        $device = DeviceRegistration::create([
            'store_id'    => $this->storeId,
            'device_name' => 'Compromised Device',
            'hardware_id' => 'HW-WIPE-PERM',
            'is_active'   => true,
        ]);

        $res = $this->putJson("/api/v2/security/devices/{$device->id}/remote-wipe", [], $this->ownerAuth());

        $res->assertOk();
        $this->assertTrue($res->json('data.remote_wipe_requested'));
    }

    /** @test */
    public function permission_error_response_contains_required_permissions(): void
    {
        $this->createActiveSubscription();

        $res = $this->getJson("/api/v2/security/overview?store_id={$this->storeId}", $this->cashierAuth());

        $res->assertForbidden();
        $data = $res->json();
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('message', $data);
    }
}
