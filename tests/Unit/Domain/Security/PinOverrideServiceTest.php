<?php

namespace Tests\Unit\Domain\Security;

use App\Domain\Auth\Models\User;
use App\Domain\Security\Models\PinOverride;
use App\Domain\Security\Services\PinOverrideService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Unit tests for PinOverrideService.
 *
 * Tests the cache-based lockout mechanism, PIN validation, and permission checks.
 */
class PinOverrideServiceTest extends TestCase
{
    use RefreshDatabase;

    private PinOverrideService $service;
    private string $storeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PinOverrideService();
        $this->storeId = (string) \Illuminate\Support\Str::uuid();

        $this->ensureSchema();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    private function ensureSchema(): void
    {
        if (!Schema::hasTable('pin_overrides')) {
            Schema::create('pin_overrides', function ($t) {
                $t->uuid('id')->primary();
                $t->uuid('store_id');
                $t->uuid('requesting_user_id');
                $t->uuid('authorizing_user_id');
                $t->string('permission_code');
                $t->json('action_context')->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }

        // Permissions table needed for requiresPin checks
        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', function ($t) {
                $t->id();
                $t->string('name')->unique();
                $t->string('display_name')->nullable();
                $t->string('guard_name')->default('web');
                $t->boolean('requires_pin')->default(false);
                $t->string('category')->nullable();
                $t->timestamps();
            });
        }
    }

    private function createUser(string $pin = '1234', string $role = 'cashier'): User
    {
        $user = new User();
        $user->id = (string) \Illuminate\Support\Str::uuid();
        $user->name = 'Test User';
        $user->email = 'user_' . uniqid() . '@test.com';
        $user->password = Hash::make('password');
        $user->pin_hash = Hash::make($pin);
        $user->store_id = $this->storeId;
        $user->is_active = true;
        $user->role = $role;
        $user->save();
        return $user;
    }

    private function createPermission(string $name, bool $requiresPin = true): void
    {
        DB::table('permissions')->insert([
            'name'         => $name,
            'display_name' => $name,
            'guard_name'   => 'web',
            'requires_pin' => $requiresPin,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    private function lockoutCacheKey(string $userId): string
    {
        return "pin_override_lockout:{$this->storeId}:{$userId}";
    }

    // ─── requiresPin Tests ───────────────────────────────────────

    /** @test */
    public function requiresPin_returns_true_for_pin_required_permission(): void
    {
        $this->createPermission('void_transaction', true);

        $this->assertTrue($this->service->requiresPin('void_transaction'));
    }

    /** @test */
    public function requiresPin_returns_false_for_non_pin_permission(): void
    {
        $this->createPermission('view_products', false);

        $this->assertFalse($this->service->requiresPin('view_products'));
    }

    /** @test */
    public function requiresPin_returns_false_for_unknown_permission(): void
    {
        $this->assertFalse($this->service->requiresPin('nonexistent_permission'));
    }

    // ─── Lockout Tests ───────────────────────────────────────────

    /** @test */
    public function authorize_throws_when_already_locked_out(): void
    {
        $requestingUser = $this->createUser('0000', 'cashier');

        // Simulate lockout
        $key = $this->lockoutCacheKey($requestingUser->id);
        Cache::put($key, true, now()->addMinutes(15));
        Cache::put($key . ':remaining', 14, now()->addMinutes(14));

        $this->createPermission('void_transaction', true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/locked out/');

        $this->service->authorize($this->storeId, $requestingUser, '9999', 'void_transaction');
    }

    /** @test */
    public function authorize_increments_attempt_counter_on_bad_pin(): void
    {
        $requestingUser = $this->createUser('0000', 'cashier');
        $this->createPermission('void_transaction', true);

        // Attempt with wrong PIN (no authorizing user registered)
        try {
            $this->service->authorize($this->storeId, $requestingUser, '9999', 'void_transaction');
        } catch (\InvalidArgumentException $e) {
        }

        $attemptsKey = $this->lockoutCacheKey($requestingUser->id) . ':attempts';
        $this->assertEquals(1, Cache::get($attemptsKey));
    }

    /** @test */
    public function authorize_triggers_lockout_after_max_attempts(): void
    {
        $requestingUser = $this->createUser('0000', 'cashier');
        $this->createPermission('void_transaction', true);

        for ($i = 0; $i < PinOverrideService::MAX_ATTEMPTS; $i++) {
            try {
                $this->service->authorize($this->storeId, $requestingUser, 'wrong', 'void_transaction');
            } catch (\InvalidArgumentException) {
                // expected
            }
        }

        $this->assertTrue(Cache::has($this->lockoutCacheKey($requestingUser->id)));
    }

    /** @test */
    public function authorize_clears_attempt_counter_on_success(): void
    {
        $this->createPermission('void_transaction', true);

        $requestingUser = $this->createUser('1111', 'cashier');
        $authorizingUser = $this->createUser('9999', 'owner');

        // Pre-warm attempt counter
        $attemptsKey = $this->lockoutCacheKey($requestingUser->id) . ':attempts';
        Cache::put($attemptsKey, 3, now()->addMinutes(15));

        // Successful authorize
        $override = $this->service->authorize($this->storeId, $requestingUser, '9999', 'void_transaction');

        $this->assertInstanceOf(PinOverride::class, $override);
        $this->assertNull(Cache::get($attemptsKey));
    }

    // ─── Permission Validation Tests ─────────────────────────────

    /** @test */
    public function authorize_throws_when_permission_does_not_exist(): void
    {
        $requestingUser = $this->createUser('1111', 'cashier');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/does not require PIN override/');

        $this->service->authorize($this->storeId, $requestingUser, '9999', 'nonexistent_perm');
    }

    /** @test */
    public function authorize_throws_when_permission_requires_pin_is_false(): void
    {
        $this->createPermission('view_reports', false);
        $requestingUser = $this->createUser('1111', 'cashier');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/does not require PIN override/');

        $this->service->authorize($this->storeId, $requestingUser, '9999', 'view_reports');
    }

    // ─── history Tests ───────────────────────────────────────────

    /** @test */
    public function history_returns_recent_overrides_for_store(): void
    {
        $user1 = $this->createUser('1111', 'cashier');
        $user2 = $this->createUser('2222', 'owner');

        PinOverride::create([
            'store_id'            => $this->storeId,
            'requesting_user_id'  => $user1->id,
            'authorizing_user_id' => $user2->id,
            'permission_code'     => 'void_transaction',
            'action_context'      => [],
            'created_at'          => now(),
        ]);

        $history = $this->service->history($this->storeId);

        $this->assertCount(1, $history);
        $this->assertEquals('void_transaction', $history->first()->permission_code);
    }

    /** @test */
    public function history_does_not_return_other_store_overrides(): void
    {
        $user1 = $this->createUser('1111', 'cashier');
        $user2 = $this->createUser('2222', 'owner');
        $otherId = (string) \Illuminate\Support\Str::uuid();

        PinOverride::create([
            'store_id'            => $otherId,
            'requesting_user_id'  => $user1->id,
            'authorizing_user_id' => $user2->id,
            'permission_code'     => 'void_transaction',
            'action_context'      => [],
            'created_at'          => now(),
        ]);

        $history = $this->service->history($this->storeId);

        $this->assertCount(0, $history);
    }

    /** @test */
    public function history_respects_limit_parameter(): void
    {
        $user1 = $this->createUser('1111', 'cashier');
        $user2 = $this->createUser('2222', 'owner');

        for ($i = 0; $i < 10; $i++) {
            PinOverride::create([
                'store_id'            => $this->storeId,
                'requesting_user_id'  => $user1->id,
                'authorizing_user_id' => $user2->id,
                'permission_code'     => 'void_transaction',
                'action_context'      => [],
                'created_at'          => now(),
            ]);
        }

        $history = $this->service->history($this->storeId, limit: 3);

        $this->assertCount(3, $history);
    }

    // ─── Lockout Constants Tests ──────────────────────────────────

    /** @test */
    public function max_attempts_constant_is_five(): void
    {
        $this->assertEquals(5, PinOverrideService::MAX_ATTEMPTS);
    }

    /** @test */
    public function lockout_minutes_constant_is_fifteen(): void
    {
        $this->assertEquals(15, PinOverrideService::LOCKOUT_MINUTES);
    }
}
