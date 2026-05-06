<?php

namespace Tests\Feature\Subscription;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Enums\SubscriptionStatus;
use App\Domain\Subscription\Models\PlanFeatureToggle;
use App\Domain\Subscription\Models\PlanLimit;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\Subscription\Services\PlanEnforcementService;
use App\Http\Middleware\CheckActiveSubscription;
use App\Http\Middleware\CheckPlanFeature;
use App\Http\Middleware\CheckPlanLimit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Feature tests for plan middleware:
 *  - plan.active  → CheckActiveSubscription
 *  - plan.feature → CheckPlanFeature
 *  - plan.limit   → CheckPlanLimit
 *
 * Middleware classes are invoked directly so that database state drives
 * their behaviour without relying on route-alias resolution quirks.
 */
class FeatureGateMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Organization $org;
    private Store $store;
    private SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Middleware Test Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Middleware Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Middleware Owner',
            'email' => 'middleware@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->plan = SubscriptionPlan::create([
            'name' => 'Growth',
            'slug' => 'growth-mw',
            'monthly_price' => 29.99,
            'grace_period_days' => 7,
            'is_active' => true,
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    /** Build a request whose authenticated user is $this->owner. */
    private function makeRequest(): Request
    {
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $this->owner);
        return $request;
    }

    private function createActiveSubscription(?string $status = null): StoreSubscription
    {
        return StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => $status ?? SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);
    }

    private function runCheckActive(Request $request): Response
    {
        return (new CheckActiveSubscription())->handle(
            $request,
            fn ($r) => response()->json(['ok' => true])
        );
    }

    private function runCheckFeature(Request $request, string $featureKey): Response
    {
        return (new CheckPlanFeature(app(PlanEnforcementService::class)))->handle(
            $request,
            fn ($r) => response()->json(['ok' => true]),
            $featureKey
        );
    }

    private function runCheckLimit(Request $request, string $limitKey): Response
    {
        return (new CheckPlanLimit(app(PlanEnforcementService::class)))->handle(
            $request,
            fn ($r) => response()->json(['ok' => true]),
            $limitKey
        );
    }

    private function decode(Response $response): array
    {
        return json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    // ─── CheckActiveSubscription ─────────────────────────────────

    public function test_plan_active_allows_active_subscription(): void
    {
        $this->createActiveSubscription(SubscriptionStatus::Active->value);

        $response = $this->runCheckActive($this->makeRequest());

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($this->decode($response)['ok']);
    }

    public function test_plan_active_allows_trial_subscription(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::Trial,
            'billing_cycle' => 'monthly',
            'trial_ends_at' => now()->addDays(14),
            'current_period_start' => now(),
            'current_period_end' => now()->addDays(14),
        ]);

        $response = $this->runCheckActive($this->makeRequest());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_plan_active_allows_grace_period_subscription(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::Grace,
            'billing_cycle' => 'monthly',
            'cancelled_at' => now(),
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->addDays(3),
        ]);

        $response = $this->runCheckActive($this->makeRequest());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_plan_active_blocks_when_no_subscription(): void
    {
        $response = $this->runCheckActive($this->makeRequest());

        $this->assertEquals(403, $response->getStatusCode());
        $data = $this->decode($response);
        $this->assertEquals('no_subscription', $data['error_code']);
        $this->assertTrue($data['subscription_required']);
    }

    public function test_plan_active_blocks_cancelled_subscription(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::Cancelled,
            'billing_cycle' => 'monthly',
            'cancelled_at' => now(),
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->subDay(),
        ]);

        $response = $this->runCheckActive($this->makeRequest());

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('no_subscription', $this->decode($response)['error_code']);
    }

    public function test_plan_active_blocks_expired_subscription(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::Expired,
            'billing_cycle' => 'monthly',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->subDay(),
        ]);

        $response = $this->runCheckActive($this->makeRequest());

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_plan_active_blocks_when_user_has_no_organization(): void
    {
        $userWithoutOrg = new User(['organization_id' => null]);
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $userWithoutOrg);

        $response = $this->runCheckActive($request);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('no_organization', $this->decode($response)['error_code']);
    }

    public function test_plan_active_injects_subscription_into_request(): void
    {
        $sub = $this->createActiveSubscription();

        $capturedRequest = null;
        $request = $this->makeRequest();

        (new CheckActiveSubscription())->handle($request, function ($r) use (&$capturedRequest) {
            $capturedRequest = $r;
            return response()->json(['ok' => true]);
        });

        $this->assertNotNull($capturedRequest->attributes->get('subscription'));
        $this->assertEquals($sub->id, $capturedRequest->attributes->get('subscription')->id);
    }

    // ─── CheckPlanFeature ────────────────────────────────────────

    public function test_plan_feature_allows_enabled_feature(): void
    {
        $this->createActiveSubscription();

        PlanFeatureToggle::create([
            'subscription_plan_id' => $this->plan->id,
            'feature_key' => 'multi_branch',
            'is_enabled' => true,
        ]);

        $response = $this->runCheckFeature($this->makeRequest(), 'multi_branch');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_plan_feature_blocks_disabled_feature(): void
    {
        $this->createActiveSubscription();

        PlanFeatureToggle::create([
            'subscription_plan_id' => $this->plan->id,
            'feature_key' => 'reports_advanced',
            'is_enabled' => false,
        ]);

        $response = $this->runCheckFeature($this->makeRequest(), 'reports_advanced');

        $this->assertEquals(403, $response->getStatusCode());
        $data = $this->decode($response);
        $this->assertEquals('feature_not_available', $data['error_code']);
        $this->assertEquals('reports_advanced', $data['feature_key']);
        $this->assertTrue($data['upgrade_required']);
    }

    public function test_plan_feature_blocks_when_feature_not_configured(): void
    {
        $this->createActiveSubscription();
        // No PlanFeatureToggle for 'delivery_integration'

        $response = $this->runCheckFeature($this->makeRequest(), 'delivery_integration');

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('feature_not_available', $this->decode($response)['error_code']);
    }

    public function test_plan_feature_blocks_without_subscription(): void
    {
        $response = $this->runCheckFeature($this->makeRequest(), 'pos');

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_plan_feature_blocked_response_includes_arabic_message(): void
    {
        $this->createActiveSubscription();

        PlanFeatureToggle::create([
            'subscription_plan_id' => $this->plan->id,
            'feature_key' => 'white_label',
            'is_enabled' => false,
        ]);

        $response = $this->runCheckFeature($this->makeRequest(), 'white_label');
        $data = $this->decode($response);

        $this->assertArrayHasKey('message_ar', $data);
        $this->assertNotEmpty($data['message_ar']);
    }

    public function test_plan_feature_blocks_when_user_has_no_organization(): void
    {
        $userWithoutOrg = new User(['organization_id' => null]);
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $userWithoutOrg);

        $response = $this->runCheckFeature($request, 'multi_branch');

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('no_organization', $this->decode($response)['error_code']);
    }

    // ─── CheckPlanLimit ──────────────────────────────────────────

    public function test_plan_limit_allows_action_within_limit(): void
    {
        $this->createActiveSubscription();

        PlanLimit::create([
            'subscription_plan_id' => $this->plan->id,
            'limit_key' => 'branches',
            'limit_value' => 5,
        ]);

        // setUp creates 1 store (branch), limit is 5 → within limit
        $response = $this->runCheckLimit($this->makeRequest(), 'branches');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_plan_limit_blocks_when_limit_exceeded(): void
    {
        $this->createActiveSubscription();

        PlanLimit::create([
            'subscription_plan_id' => $this->plan->id,
            'limit_key' => 'branches',
            'limit_value' => 1,
        ]);

        // Already have 1 branch; add a second to exceed the limit of 1
        Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Extra Branch',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => false,
        ]);

        $response = $this->runCheckLimit($this->makeRequest(), 'branches');

        $this->assertEquals(403, $response->getStatusCode());
        $data = $this->decode($response);
        $this->assertEquals('limit_exceeded', $data['error_code']);
        $this->assertEquals('branches', $data['limit_key']);
        $this->assertTrue($data['upgrade_required']);
    }

    public function test_plan_limit_allows_when_no_limit_configured(): void
    {
        $this->createActiveSubscription();
        // No PlanLimit row for 'products' → treated as unlimited

        $response = $this->runCheckLimit($this->makeRequest(), 'products');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_plan_limit_allows_unlimited_value(): void
    {
        $this->createActiveSubscription();

        PlanLimit::create([
            'subscription_plan_id' => $this->plan->id,
            'limit_key' => 'staff_members',
            'limit_value' => -1, // -1 signals unlimited
        ]);

        $response = $this->runCheckLimit($this->makeRequest(), 'staff_members');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_plan_limit_blocked_response_includes_current_limit_and_remaining(): void
    {
        $this->createActiveSubscription();

        PlanLimit::create([
            'subscription_plan_id' => $this->plan->id,
            'limit_key' => 'branches',
            'limit_value' => 1,
        ]);

        Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Overflow Branch',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => false,
        ]);

        $response = $this->runCheckLimit($this->makeRequest(), 'branches');
        $data = $this->decode($response);

        $this->assertEquals(1, $data['current_limit']);
        $this->assertSame(0, $data['remaining']);
    }

    public function test_plan_limit_blocks_when_user_has_no_organization(): void
    {
        $userWithoutOrg = new User(['organization_id' => null]);
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $userWithoutOrg);

        $response = $this->runCheckLimit($request, 'branches');

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('no_organization', $this->decode($response)['error_code']);
    }
}
