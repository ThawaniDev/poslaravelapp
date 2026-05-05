<?php

namespace Tests\Feature\StaffManagement;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Models\PlanLimit;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Http\Middleware\CheckPlanLimit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the plan.limit:staff_members middleware on POST /api/v2/staff/members.
 *
 * Verifies:
 * - Creating staff when at limit is blocked with 403 and upgrade_required flag
 * - Creating staff when below limit is allowed
 * - No subscription configured means unlimited
 * - Plan with -1 limit means unlimited
 * - Deleting a staff member reduces live count (next create would succeed)
 */
class SubscriptionLimitTest extends TestCase
{
    use RefreshDatabase;

    // Run migrate:fresh once per class to avoid PostgreSQL deadlocks on bulk table drops
    private static bool $classMigrated = false;

    private Organization $org;
    private Store $store;
    private User $owner;
    private string $token;

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

        // Restore real plan.limit middleware (TestCase bypasses it by default)
        app('router')->aliasMiddleware('plan.limit', CheckPlanLimit::class);

        $this->org = Organization::create([
            'name'          => 'Sub Limit Org',
            'business_type' => 'grocery',
            'country'       => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name'            => 'Sub Limit Store',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
            'is_active'       => true,
            'is_main_branch'  => true,
        ]);

        $this->owner = User::create([
            'name'            => 'Owner',
            'email'           => 'owner@sublimit.test',
            'password_hash'   => bcrypt('password'),
            'store_id'        => $this->store->id,
            'organization_id' => $this->org->id,
            'role'            => 'owner',
            'is_active'       => true,
        ]);
        $this->token = $this->owner->createToken('test')->plainTextToken;
    }

    // ═══════════════════════════════════════════════════════════
    // No subscription = unlimited
    // ═══════════════════════════════════════════════════════════

    public function test_create_staff_succeeds_when_no_subscription(): void
    {
        // No subscription configured → unlimited
        $this->withToken($this->token)
            ->postJson('/api/v2/staff/members', $this->validStaffPayload())
            ->assertCreated();
    }

    // ═══════════════════════════════════════════════════════════
    // Unlimited plan (-1)
    // ═══════════════════════════════════════════════════════════

    public function test_create_staff_succeeds_with_unlimited_plan(): void
    {
        $plan = $this->createPlan();
        $this->createSubscription($plan);
        PlanLimit::create([
            'subscription_plan_id' => $plan->id,
            'limit_key'            => 'staff_members',
            'limit_value'          => -1, // unlimited
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v2/staff/members', $this->validStaffPayload())
            ->assertCreated();
    }

    // ═══════════════════════════════════════════════════════════
    // Below limit
    // ═══════════════════════════════════════════════════════════

    public function test_create_staff_succeeds_when_below_limit(): void
    {
        $plan = $this->createPlan();
        $this->createSubscription($plan);
        PlanLimit::create([
            'subscription_plan_id' => $plan->id,
            'limit_key'            => 'staff_members',
            'limit_value'          => 5, // 5 allowed
        ]);

        // Currently 1 active user (the owner)
        $this->withToken($this->token)
            ->postJson('/api/v2/staff/members', $this->validStaffPayload())
            ->assertCreated();
    }

    // ═══════════════════════════════════════════════════════════
    // At limit (blocked)
    // ═══════════════════════════════════════════════════════════

    public function test_create_staff_blocked_when_at_limit(): void
    {
        $plan = $this->createPlan();
        $this->createSubscription($plan);
        PlanLimit::create([
            'subscription_plan_id' => $plan->id,
            'limit_key'            => 'staff_members',
            'limit_value'          => 1, // only 1 allowed
        ]);

        // Owner is already 1 active user → at limit
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/staff/members', $this->validStaffPayload());

        $response->assertForbidden()
            ->assertJsonFragment([
                'success'          => false,
                'error_code'       => 'limit_exceeded',
                'limit_key'        => 'staff_members',
                'upgrade_required' => true,
            ]);
    }

    public function test_blocked_response_includes_current_limit_and_remaining(): void
    {
        $plan = $this->createPlan();
        $this->createSubscription($plan);
        PlanLimit::create([
            'subscription_plan_id' => $plan->id,
            'limit_key'            => 'staff_members',
            'limit_value'          => 1,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/staff/members', $this->validStaffPayload());

        $response->assertForbidden();
        $data = $response->json();

        $this->assertArrayHasKey('current_limit', $data);
        $this->assertArrayHasKey('remaining', $data);
        $this->assertEquals(1, $data['current_limit']);
        $this->assertEquals(0, $data['remaining']);
    }

    // ═══════════════════════════════════════════════════════════
    // Deactivating staff reduces count – next create allowed
    // ═══════════════════════════════════════════════════════════

    public function test_deactivating_staff_allows_creating_new_within_limit(): void
    {
        $plan = $this->createPlan();
        $this->createSubscription($plan);
        PlanLimit::create([
            'subscription_plan_id' => $plan->id,
            'limit_key'            => 'staff_members',
            'limit_value'          => 2, // owner + 1 more
        ]);

        // Create first staff (ok – goes to 2)
        $created = $this->withToken($this->token)
            ->postJson('/api/v2/staff/members', $this->validStaffPayload())
            ->assertCreated()
            ->json();

        // Now at limit – next create should fail
        $this->withToken($this->token)
            ->postJson('/api/v2/staff/members', array_merge($this->validStaffPayload(), ['email' => 'new2@sub.test']))
            ->assertForbidden()
            ->assertJsonFragment(['error_code' => 'limit_exceeded']);

        // Deactivate the first staff member
        $staffId = $created['data']['id'] ?? $created['id'];
        $this->withToken($this->token)
            ->putJson("/api/v2/staff/members/{$staffId}", ['status' => 'inactive'])
            ->assertOk();

        // Now below limit again
        $this->withToken($this->token)
            ->postJson('/api/v2/staff/members', array_merge($this->validStaffPayload(), ['email' => 'new3@sub.test']))
            ->assertCreated();
    }

    // ═══════════════════════════════════════════════════════════
    // Exact limit boundary
    // ═══════════════════════════════════════════════════════════

    public function test_exactly_at_limit_blocks_next_create(): void
    {
        $plan = $this->createPlan();
        $this->createSubscription($plan);
        PlanLimit::create([
            'subscription_plan_id' => $plan->id,
            'limit_key'            => 'staff_members',
            'limit_value'          => 3,
        ]);

        // Owner is already 1 → can add 2 more
        $this->withToken($this->token)
            ->postJson('/api/v2/staff/members', array_merge($this->validStaffPayload(), ['email' => 'ex1@sub.test']))
            ->assertCreated();

        $this->withToken($this->token)
            ->postJson('/api/v2/staff/members', array_merge($this->validStaffPayload(), ['email' => 'ex2@sub.test']))
            ->assertCreated();

        // Now at limit (3 users): owner + ex1 + ex2
        $this->withToken($this->token)
            ->postJson('/api/v2/staff/members', array_merge($this->validStaffPayload(), ['email' => 'ex3@sub.test']))
            ->assertForbidden()
            ->assertJsonFragment(['error_code' => 'limit_exceeded']);
    }

    // ═══════════════════════════════════════════════════════════
    // Organization without a linked organization_id
    // ═══════════════════════════════════════════════════════════

    public function test_user_without_organization_id_gets_403_no_organization(): void
    {
        $orglessUser = User::create([
            'name'            => 'No Org',
            'email'           => 'noorg@sublimit.test',
            'password_hash'   => bcrypt('password'),
            'store_id'        => $this->store->id,
            'organization_id' => null, // intentionally null
            'role'            => 'owner',
            'is_active'       => true,
        ]);
        $orglessToken = $orglessUser->createToken('test')->plainTextToken;

        $this->withToken($orglessToken)
            ->postJson('/api/v2/staff/members', $this->validStaffPayload())
            ->assertForbidden()
            ->assertJsonFragment(['error_code' => 'no_organization']);
    }

    // ═══════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════

    private function validStaffPayload(array $overrides = []): array
    {
        return array_merge([
            'store_id'        => $this->store->id,
            'first_name'      => 'Test',
            'last_name'       => 'Staff',
            'email'           => 'newstaff@sub.test',
            'employment_type' => 'full_time',
            'salary_type'     => 'monthly',
            'hire_date'       => now()->toDateString(),
        ], $overrides);
    }

    private function createPlan(): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name'     => 'Test Plan',
            'slug'     => 'test-plan-' . uniqid(),
            'is_active' => true,
            'monthly_price' => 49.00,
            'annual_price'  => 490.00,
        ]);
    }

    private function createSubscription(SubscriptionPlan $plan): StoreSubscription
    {
        return StoreSubscription::create([
            'organization_id'     => $this->org->id,
            'subscription_plan_id' => $plan->id,
            'status'              => 'active',
            'billing_cycle'       => 'monthly',
            'current_period_start' => now(),
            'current_period_end'  => now()->addMonth(),
        ]);
    }
}
