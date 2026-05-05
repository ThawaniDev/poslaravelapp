<?php

namespace Tests\Feature\StaffManagement;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Security\Models\PinOverride;
use App\Domain\Security\Services\PinOverrideService;
use App\Domain\StaffManagement\Models\Permission;
use App\Domain\StaffManagement\Models\StaffUser;
use App\Domain\StaffManagement\Services\PermissionService;
use App\Http\Middleware\CheckPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Comprehensive tests for the PIN Override system.
 *
 * Covers:
 * - Happy path: check endpoint, authorize endpoint, history endpoint
 * - Wrong PIN rejection
 * - Lockout after 5 failed attempts
 * - Cannot authorize own action
 * - Cross-store isolation
 * - Non-pin-protected permission rejection
 * - History endpoint with pagination and cross-store isolation
 * - Remaining attempts tracking
 */
class PinOverrideApiTest extends TestCase
{
    use RefreshDatabase;

    // Run migrate:fresh once per class to avoid PostgreSQL deadlocks
    private static bool $classMigrated = false;

    private Organization $org;
    private Store $store;

    // The user who requests authorization (e.g. cashier at POS)
    private User $requestingUser;
    private string $requestingToken;

    // The user who provides the PIN to authorize (supervisor/manager)
    private User $authorizingUser;
    // Supervisor's PIN (plain text for test setup)
    private string $supervisorPin = '4321';

    // Owner for history/audit access
    private User $owner;
    private string $ownerToken;

    // Permission used in PIN override tests
    private string $pinProtectedPermission = 'pos.void_transaction';

    protected function refreshTestDatabase(): void
    {
        if (!static::$classMigrated) {
            $this->migrateDatabases();
            $this->app[\Illuminate\Contracts\Console\Kernel::class]->setArtisan(null);
            static::$classMigrated = true;
        }
        $this->beginDatabaseTransaction();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Re-register real permission middleware (bypassed in TestCase by default)
        app('router')->aliasMiddleware('permission', CheckPermission::class);

        app(PermissionService::class)->seedAll();

        $this->org = Organization::create([
            'name'          => 'PIN Test Org',
            'business_type' => 'grocery',
            'country'       => 'OM',
        ]);
        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name'            => 'PIN Test Store',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
            'is_active'       => true,
            'is_main_branch'  => true,
        ]);

        // Owner (pin_hash '1234' for owner-PIN authorize tests)
        $this->owner = User::create([
            'name'            => 'Owner',
            'email'           => 'owner@pintest.com',
            'password_hash'   => bcrypt('password'),
            'store_id'        => $this->store->id,
            'organization_id' => $this->org->id,
            'role'            => 'owner',
            'is_active'       => true,
            'pin_hash'        => bcrypt('1234'),
        ]);
        $this->ownerToken = $this->owner->createToken('test')->plainTextToken;

        // Requesting user (cashier – initiates PIN override request)
        $this->requestingUser = User::create([
            'name'            => 'Requesting Cashier',
            'email'           => 'cashier@pintest.com',
            'password_hash'   => bcrypt('password'),
            'store_id'        => $this->store->id,
            'organization_id' => $this->org->id,
            'role'            => 'cashier',
            'is_active'       => true,
        ]);
        $this->requestingToken = $this->requestingUser->createToken('test')->plainTextToken;

        // Authorizing user (supervisor with PIN set and the required permission)
        $this->authorizingUser = User::create([
            'name'            => 'Supervisor',
            'email'           => 'supervisor@pintest.com',
            'password_hash'   => bcrypt('password'),
            'store_id'        => $this->store->id,
            'organization_id' => $this->org->id,
            'role'            => 'cashier',
            'is_active'       => true,
            'pin_hash'        => bcrypt($this->supervisorPin),
        ]);

        // Grant supervisor the void permission
        $this->grantPermission($this->authorizingUser, $this->pinProtectedPermission);

        // Make sure void_transaction requires PIN
        Permission::where('name', $this->pinProtectedPermission)
            ->update(['requires_pin' => true]);
    }

    // ═══════════════════════════════════════════════════════════
    // Check endpoint – /api/v2/staff/pin-override/check
    // ═══════════════════════════════════════════════════════════

    public function test_check_returns_true_for_pin_protected_permission(): void
    {
        $this->withToken($this->requestingToken)
            ->getJson("/api/v2/staff/pin-override/check?permission_code={$this->pinProtectedPermission}")
            ->assertOk()
            ->assertJsonFragment(['requires_pin' => true]);
    }

    public function test_check_returns_false_for_non_pin_permission(): void
    {
        // staff.view typically does not require PIN
        Permission::where('name', 'staff.view')->update(['requires_pin' => false]);

        $this->withToken($this->requestingToken)
            ->getJson('/api/v2/staff/pin-override/check?permission_code=staff.view')
            ->assertOk()
            ->assertJsonFragment(['requires_pin' => false]);
    }

    public function test_check_requires_auth(): void
    {
        $this->getJson("/api/v2/staff/pin-override/check?permission_code={$this->pinProtectedPermission}")
            ->assertUnauthorized();
    }

    // ═══════════════════════════════════════════════════════════
    // Authorize endpoint – POST /api/v2/staff/pin-override
    // ═══════════════════════════════════════════════════════════

    public function test_happy_path_authorize_with_correct_pin(): void
    {
        $response = $this->withToken($this->requestingToken)
            ->postJson('/api/v2/staff/pin-override', [
                'store_id'        => $this->store->id,
                'pin'             => $this->supervisorPin,
                'permission_code' => $this->pinProtectedPermission,
                'context'         => ['action' => 'void transaction #123'],
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'authorized_by',
                'authorized_by_name',
                'permission_code',
            ])
            ->assertJsonFragment([
                'success'         => true,
                'authorized_by'   => $this->authorizingUser->id,
                'permission_code' => $this->pinProtectedPermission,
            ]);
    }

    public function test_authorize_records_pin_override_in_db(): void
    {
        $this->withToken($this->requestingToken)
            ->postJson('/api/v2/staff/pin-override', [
                'store_id'        => $this->store->id,
                'pin'             => $this->supervisorPin,
                'permission_code' => $this->pinProtectedPermission,
            ])
            ->assertOk();

        $this->assertDatabaseHas('pin_overrides', [
            'store_id'           => $this->store->id,
            'requesting_user_id' => $this->requestingUser->id,
            'authorizing_user_id'=> $this->authorizingUser->id,
            'permission_code'    => $this->pinProtectedPermission,
        ]);
    }

    public function test_authorize_requires_auth(): void
    {
        $this->postJson('/api/v2/staff/pin-override', [
            'store_id'        => $this->store->id,
            'pin'             => $this->supervisorPin,
            'permission_code' => $this->pinProtectedPermission,
        ])->assertUnauthorized();
    }

    public function test_authorize_with_wrong_pin_returns_error(): void
    {
        $response = $this->withToken($this->requestingToken)
            ->postJson('/api/v2/staff/pin-override', [
                'store_id'        => $this->store->id,
                'pin'             => '0000',
                'permission_code' => $this->pinProtectedPermission,
            ]);

        $response->assertStatus(401)
            ->assertJsonFragment(['success' => false]);
    }

    public function test_authorize_wrong_pin_increments_attempt_counter(): void
    {
        Cache::flush();

        $this->withToken($this->requestingToken)
            ->postJson('/api/v2/staff/pin-override', [
                'store_id'        => $this->store->id,
                'pin'             => '0000',
                'permission_code' => $this->pinProtectedPermission,
            ])
            ->assertStatus(401);

        $attemptsKey = "pin_override_lockout:{$this->store->id}:{$this->requestingUser->id}:attempts";
        $this->assertEquals(1, Cache::get($attemptsKey, 0));
    }

    public function test_lockout_after_5_wrong_pins(): void
    {
        Cache::flush();

        // Submit 5 wrong PINs
        for ($i = 0; $i < 5; $i++) {
            $this->withToken($this->requestingToken)
                ->postJson('/api/v2/staff/pin-override', [
                    'store_id'        => $this->store->id,
                    'pin'             => '9999',
                    'permission_code' => $this->pinProtectedPermission,
                ]);
        }

        // 6th attempt – should be locked out
        $response = $this->withToken($this->requestingToken)
            ->postJson('/api/v2/staff/pin-override', [
                'store_id'        => $this->store->id,
                'pin'             => $this->supervisorPin,
                'permission_code' => $this->pinProtectedPermission,
            ]);

        $response->assertStatus(429)
            ->assertJsonFragment(['success' => false])
            ->assertJsonStructure(['message', 'minutes_remaining']);
    }

    public function test_lockout_response_contains_minutes_remaining(): void
    {
        Cache::flush();

        // Trigger lockout
        for ($i = 0; $i < 5; $i++) {
            $this->withToken($this->requestingToken)
                ->postJson('/api/v2/staff/pin-override', [
                    'store_id'        => $this->store->id,
                    'pin'             => '9999',
                    'permission_code' => $this->pinProtectedPermission,
                ]);
        }

        $response = $this->withToken($this->requestingToken)
            ->postJson('/api/v2/staff/pin-override', [
                'store_id'        => $this->store->id,
                'pin'             => '9999',
                'permission_code' => $this->pinProtectedPermission,
            ]);

        $response->assertStatus(429);
        $data = $response->json();
        $this->assertArrayHasKey('minutes_remaining', $data);
        $this->assertGreaterThan(0, $data['minutes_remaining']);
    }

    public function test_successful_pin_clears_attempt_counter(): void
    {
        Cache::flush();

        // Two wrong attempts
        $this->withToken($this->requestingToken)
            ->postJson('/api/v2/staff/pin-override', [
                'store_id'        => $this->store->id,
                'pin'             => '0000',
                'permission_code' => $this->pinProtectedPermission,
            ]);
        $this->withToken($this->requestingToken)
            ->postJson('/api/v2/staff/pin-override', [
                'store_id'        => $this->store->id,
                'pin'             => '1111',
                'permission_code' => $this->pinProtectedPermission,
            ]);

        // Correct PIN
        $this->withToken($this->requestingToken)
            ->postJson('/api/v2/staff/pin-override', [
                'store_id'        => $this->store->id,
                'pin'             => $this->supervisorPin,
                'permission_code' => $this->pinProtectedPermission,
            ])
            ->assertOk();

        $lockoutBase = "pin_override_lockout:{$this->store->id}:{$this->requestingUser->id}";
        $this->assertEquals(0, Cache::get($lockoutBase . ':attempts', 0));
    }

    // ═══════════════════════════════════════════════════════════
    // Cannot authorize own action
    // ═══════════════════════════════════════════════════════════

    public function test_cannot_authorize_own_action(): void
    {
        // Give the requesting user a PIN and the permission
        $this->requestingUser->update(['pin_hash' => bcrypt('5678')]);
        $this->grantPermission($this->requestingUser, $this->pinProtectedPermission);

        $response = $this->withToken($this->requestingToken)
            ->postJson('/api/v2/staff/pin-override', [
                'store_id'        => $this->store->id,
                'pin'             => '5678',
                'permission_code' => $this->pinProtectedPermission,
            ]);

        // Should fail because a user cannot authorize their own action
        $response->assertStatus(401)
            ->assertJsonFragment(['success' => false]);
    }

    // ═══════════════════════════════════════════════════════════
    // Cross-store isolation
    // ═══════════════════════════════════════════════════════════

    public function test_supervisor_from_different_store_cannot_authorize(): void
    {
        $otherOrg = Organization::create([
            'name' => 'Other Org', 'business_type' => 'grocery', 'country' => 'OM',
        ]);
        $otherStore = Store::create([
            'organization_id' => $otherOrg->id,
            'name'            => 'Other Store',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
            'is_active'       => true,
        ]);

        // Supervisor in OTHER store with numeric PIN
        User::create([
            'name'            => 'Other Super',
            'email'           => 'othersup@pintest.com',
            'password_hash'   => bcrypt('password'),
            'store_id'        => $otherStore->id,
            'organization_id' => $otherOrg->id,
            'role'            => 'cashier',
            'is_active'       => true,
            'pin_hash'        => bcrypt('9876'),
        ]);

        // This other supervisor's PIN won't match any authorized user in THIS store
        $response = $this->withToken($this->requestingToken)
            ->postJson('/api/v2/staff/pin-override', [
                'store_id'        => $this->store->id,
                'pin'             => '9876',
                'permission_code' => $this->pinProtectedPermission,
            ]);

        $response->assertStatus(401)
            ->assertJsonFragment(['success' => false]);
    }

    // ═══════════════════════════════════════════════════════════
    // Non-pin-protected permission
    // ═══════════════════════════════════════════════════════════

    public function test_authorize_fails_for_non_pin_protected_permission(): void
    {
        Permission::where('name', 'staff.view')->update(['requires_pin' => false]);

        $response = $this->withToken($this->requestingToken)
            ->postJson('/api/v2/staff/pin-override', [
                'store_id'        => $this->store->id,
                'pin'             => $this->supervisorPin,
                'permission_code' => 'staff.view',
            ]);

        // Should fail – this permission doesn't need PIN
        $response->assertStatus(422)
            ->assertJsonFragment(['success' => false]);
    }

    // ═══════════════════════════════════════════════════════════
    // Authorizing user has no PIN set
    // ═══════════════════════════════════════════════════════════

    public function test_authorize_fails_if_no_supervisor_has_pin(): void
    {
        // Remove supervisor's PIN
        $this->authorizingUser->update(['pin_hash' => null]);

        $response = $this->withToken($this->requestingToken)
            ->postJson('/api/v2/staff/pin-override', [
                'store_id'        => $this->store->id,
                'pin'             => $this->supervisorPin,
                'permission_code' => $this->pinProtectedPermission,
            ]);

        $response->assertStatus(401);
    }

    // ═══════════════════════════════════════════════════════════
    // Authorizing user not active
    // ═══════════════════════════════════════════════════════════

    public function test_authorize_fails_if_supervisor_inactive(): void
    {
        $this->authorizingUser->update(['is_active' => false]);

        $response = $this->withToken($this->requestingToken)
            ->postJson('/api/v2/staff/pin-override', [
                'store_id'        => $this->store->id,
                'pin'             => $this->supervisorPin,
                'permission_code' => $this->pinProtectedPermission,
            ]);

        $response->assertStatus(401);
    }

    // ═══════════════════════════════════════════════════════════
    // History endpoint – GET /api/v2/staff/pin-override/history
    // ═══════════════════════════════════════════════════════════

    public function test_pin_override_history_returns_records(): void
    {
        // Create 3 pin override records
        $this->createOverride();
        $this->createOverride();
        $this->createOverride();

        $this->withToken($this->ownerToken)
            ->getJson('/api/v2/staff/pin-override/history')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'requesting_user',
                        'authorizing_user',
                        'permission_code',
                    ],
                ],
            ]);
    }

    public function test_pin_override_history_is_paginated(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createOverride();
        }

        $this->withToken($this->ownerToken)
            ->getJson('/api/v2/staff/pin-override/history?per_page=2')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonPath('meta.total', 5);
    }

    public function test_history_includes_requesting_and_authorizing_user_names(): void
    {
        $this->createOverride();

        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/staff/pin-override/history')
            ->assertOk();

        $record = $response->json('data.0');
        $this->assertNotEmpty($record['requesting_user']['name']);
        $this->assertNotEmpty($record['authorizing_user']['name']);
    }

    public function test_history_only_returns_own_store_records(): void
    {
        // Create override in THIS store
        $this->createOverride();

        // Create override in OTHER store
        $otherOrg = Organization::create([
            'name' => 'Other Org2', 'business_type' => 'grocery', 'country' => 'OM',
        ]);
        $otherStore = Store::create([
            'organization_id' => $otherOrg->id,
            'name'            => 'Other Store2',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
            'is_active'       => true,
        ]);
        $otherOwner = User::create([
            'name'            => 'Other Owner',
            'email'           => 'otherowner2@pintest.com',
            'password_hash'   => bcrypt('password'),
            'store_id'        => $otherStore->id,
            'organization_id' => $otherOrg->id,
            'role'            => 'owner',
            'is_active'       => true,
        ]);
        $otherUser = User::create([
            'name'            => 'Other Req',
            'email'           => 'otherreq@pintest.com',
            'password_hash'   => bcrypt('password'),
            'store_id'        => $otherStore->id,
            'organization_id' => $otherOrg->id,
            'role'            => 'cashier',
            'is_active'       => true,
        ]);

        PinOverride::create([
            'store_id'            => $otherStore->id,
            'requesting_user_id'  => $otherUser->id,
            'authorizing_user_id' => $otherOwner->id,
            'permission_code'     => $this->pinProtectedPermission,
        ]);

        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/staff/pin-override/history')
            ->assertOk();

        $records = $response->json('data');
        foreach ($records as $record) {
            $this->assertEquals($this->store->id, $record['store_id']);
        }
    }

    public function test_history_requires_security_view_audit_permission(): void
    {
        $this->withToken($this->requestingToken)
            ->getJson('/api/v2/staff/pin-override/history')
            ->assertForbidden();
    }

    // ═══════════════════════════════════════════════════════════
    // Owner can also authorize PIN
    // ═══════════════════════════════════════════════════════════

    public function test_owner_can_authorize_pin_override(): void
    {
        // owner has pin_hash = bcrypt('1234') set in setUp
        $response = $this->withToken($this->requestingToken)
            ->postJson('/api/v2/staff/pin-override', [
                'store_id'        => $this->store->id,
                'pin'             => '1234',
                'permission_code' => $this->pinProtectedPermission,
            ]);

        $response->assertOk()
            ->assertJsonFragment([
                'success'       => true,
                'authorized_by' => $this->owner->id,
            ]);
    }

    // ═══════════════════════════════════════════════════════════
    // Validation
    // ═══════════════════════════════════════════════════════════

    public function test_authorize_requires_pin_field(): void
    {
        $this->withToken($this->requestingToken)
            ->postJson('/api/v2/staff/pin-override', [
                'permission_code' => $this->pinProtectedPermission,
            ])
            ->assertUnprocessable();
    }

    public function test_authorize_requires_permission_code_field(): void
    {
        $this->withToken($this->requestingToken)
            ->postJson('/api/v2/staff/pin-override', [
                'pin' => '1234',
            ])
            ->assertUnprocessable();
    }

    // ═══════════════════════════════════════════════════════════
    // Remaining attempts
    // ═══════════════════════════════════════════════════════════

    public function test_remaining_attempts_decreases_on_wrong_pin(): void
    {
        Cache::flush();

        $first = $this->withToken($this->requestingToken)
            ->postJson('/api/v2/staff/pin-override', [
                'store_id'        => $this->store->id,
                'pin'             => '0000',
                'permission_code' => $this->pinProtectedPermission,
            ])
            ->assertStatus(401)
            ->json();

        $second = $this->withToken($this->requestingToken)
            ->postJson('/api/v2/staff/pin-override', [
                'store_id'        => $this->store->id,
                'pin'             => '1111',
                'permission_code' => $this->pinProtectedPermission,
            ])
            ->assertStatus(401)
            ->json();

        $this->assertArrayHasKey('remaining_attempts', $first);
        $this->assertArrayHasKey('remaining_attempts', $second);
        $this->assertLessThan($first['remaining_attempts'], $second['remaining_attempts']);
    }

    // ═══════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════

    private function createOverride(): PinOverride
    {
        return PinOverride::create([
            'store_id'            => $this->store->id,
            'requesting_user_id'  => $this->requestingUser->id,
            'authorizing_user_id' => $this->authorizingUser->id,
            'permission_code'     => $this->pinProtectedPermission,
            'action_context'      => ['action' => 'void'],
        ]);
    }

    private function grantPermission(User $user, string $permissionName): void
    {
        $permission = Permission::where('name', $permissionName)->first();

        if (!$permission) {
            return;
        }

        $roleName = 'auto_' . substr(md5($permissionName . $user->id), 0, 8);
        $role = \App\Domain\StaffManagement\Models\Role::firstOrCreate(
            ['name' => $roleName, 'store_id' => $this->store->id],
            ['display_name' => 'Auto', 'guard_name' => 'staff', 'is_predefined' => false],
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);

        \Illuminate\Support\Facades\DB::table('model_has_roles')->updateOrInsert([
            'role_id'    => $role->id,
            'model_id'   => $user->id,
            'model_type' => get_class($user),
        ]);
    }
}
