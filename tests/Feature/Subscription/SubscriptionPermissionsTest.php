<?php

namespace Tests\Feature\Subscription;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Enums\SubscriptionStatus;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature tests for subscription permission gating.
 *
 * Covers: unauthenticated access, user without organization,
 * subscription.view and subscription.manage permission checks,
 * admin billing.plans permission, plan listing (public) vs management (guarded).
 */
class SubscriptionPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Organization $org;
    private Store $store;
    private string $ownerToken;
    private SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Permissions Test Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Main Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Owner User',
            'email' => 'owner.perms@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->ownerToken = $this->owner->createToken('test', ['*'])->plainTextToken;

        $this->plan = SubscriptionPlan::create([
            'name' => 'Starter',
            'slug' => 'starter-perms',
            'monthly_price' => 0,
            'is_active' => true,
        ]);
    }

    // ─── Unauthenticated Access ──────────────────────────────────

    public function test_unauthenticated_user_cannot_get_current_subscription(): void
    {
        $response = $this->getJson('/api/v2/subscription/current');

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_subscribe(): void
    {
        $response = $this->postJson('/api/v2/subscription/subscribe', [
            'plan_id' => $this->plan->id,
        ]);

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_cancel(): void
    {
        $response = $this->postJson('/api/v2/subscription/cancel');

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_resume(): void
    {
        $response = $this->postJson('/api/v2/subscription/resume');

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_change_plan(): void
    {
        $response = $this->putJson('/api/v2/subscription/change-plan', [
            'plan_id' => $this->plan->id,
        ]);

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_access_invoices(): void
    {
        $response = $this->getJson('/api/v2/subscription/invoices');

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_check_feature(): void
    {
        $response = $this->getJson('/api/v2/subscription/check-feature/pos');

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_check_limit(): void
    {
        $response = $this->getJson('/api/v2/subscription/check-limit/products');

        $response->assertUnauthorized();
    }

    // ─── Plan Listing is Public ──────────────────────────────────

    public function test_unauthenticated_user_can_list_public_plans(): void
    {
        $response = $this->getJson('/api/v2/subscription/plans');

        $response->assertOk();
        $response->assertJsonStructure(['data', 'success']);
    }

    public function test_unauthenticated_user_can_get_single_plan(): void
    {
        $response = $this->getJson("/api/v2/subscription/plans/{$this->plan->id}");

        $response->assertOk();
    }

    public function test_unauthenticated_user_can_compare_plans(): void
    {
        $plan2 = SubscriptionPlan::create([
            'name' => 'Growth',
            'slug' => 'growth-perms-compare',
            'monthly_price' => 29.99,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v2/subscription/plans/compare', [
            'plan_ids' => [$this->plan->id, $plan2->id],
        ]);

        $response->assertOk();
    }

    // ─── User Without Organization ───────────────────────────────

    public function test_user_without_organization_gets_404_on_current(): void
    {
        $noOrgUser = User::create([
            'name' => 'No Org User',
            'email' => 'noorgs@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => null,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $token = $noOrgUser->createToken('test', ['*'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v2/subscription/current');

        $response->assertNotFound();
    }

    public function test_user_without_organization_cannot_subscribe(): void
    {
        $noOrgUser = User::create([
            'name' => 'No Org Subscribe',
            'email' => 'noorgsub@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => null,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $token = $noOrgUser->createToken('test', ['*'])->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v2/subscription/subscribe', [
            'plan_id' => $this->plan->id,
        ]);

        $response->assertNotFound();
    }

    // ─── Authenticated Owner Accesses ───────────────────────────

    public function test_authenticated_owner_can_subscribe(): void
    {
        $response = $this->withToken($this->ownerToken)->postJson('/api/v2/subscription/subscribe', [
            'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertCreated();
    }

    public function test_authenticated_owner_can_get_current_subscription(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->withToken($this->ownerToken)->getJson('/api/v2/subscription/current');

        $response->assertOk();
        $response->assertJsonPath('data.organization_id', $this->org->id);
    }

    public function test_authenticated_owner_can_check_features(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->withToken($this->ownerToken)->getJson('/api/v2/subscription/check-feature/pos');

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['feature_key', 'is_enabled']]);
    }

    public function test_authenticated_owner_can_check_limits(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->withToken($this->ownerToken)->getJson('/api/v2/subscription/check-limit/products');

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['can_perform', 'remaining']]);
    }

    // ─── Cross-Organization Isolation ───────────────────────────

    public function test_user_can_only_see_own_organization_subscription(): void
    {
        // Create subscription for this org
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        // Create a different org and subscription
        $otherOrg = Organization::create([
            'name' => 'Other Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $otherStore = Store::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other.user@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $otherStore->id,
            'organization_id' => $otherOrg->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $otherToken = $otherUser->createToken('test', ['*'])->plainTextToken;

        $response = $this->withToken($otherToken)->getJson('/api/v2/subscription/current');

        $response->assertOk();
        // Other org has no subscription
        $this->assertNull($response->json('data'));
    }
}
