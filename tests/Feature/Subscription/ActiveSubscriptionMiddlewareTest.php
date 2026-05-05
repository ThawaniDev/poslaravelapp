<?php

namespace Tests\Feature\Subscription;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Enums\SubscriptionStatus;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Http\Middleware\CheckActiveSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ActiveSubscriptionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Store $store;
    private User $owner;
    private string $token;
    private SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        // Restore real middleware (TestCase aliases plan.active to bypass by default).
        app('router')->aliasMiddleware('plan.active', CheckActiveSubscription::class);

        Route::middleware(['auth:sanctum', 'plan.active'])
            ->get('/api/v2/_test/plan-active', fn () => response()->json(['ok' => true]));

        $this->org = Organization::create([
            'name' => 'Subscription Guard Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Subscription Guard Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Owner',
            'email' => 'owner-plan-active@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->owner->createToken('test')->plainTextToken;

        $this->plan = SubscriptionPlan::create([
            'name' => 'Growth',
            'name_ar' => 'النمو',
            'slug' => 'growth',
            'monthly_price' => 20,
            'annual_price' => 200,
            'is_active' => true,
        ]);
    }

    public function test_allows_active_trial_and_grace_statuses(): void
    {
        foreach ([SubscriptionStatus::Active, SubscriptionStatus::Trial, SubscriptionStatus::Grace] as $status) {
            StoreSubscription::query()->delete();

            StoreSubscription::create([
                'organization_id' => $this->org->id,
                'subscription_plan_id' => $this->plan->id,
                'status' => $status->value,
                'billing_cycle' => 'monthly',
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
            ]);

            $this->withToken($this->token)
                ->getJson('/api/v2/_test/plan-active')
                ->assertOk()
                ->assertJsonPath('ok', true);
        }
    }

    public function test_blocks_cancelled_and_expired_statuses(): void
    {
        foreach ([SubscriptionStatus::Cancelled, SubscriptionStatus::Expired] as $status) {
            StoreSubscription::query()->delete();

            StoreSubscription::create([
                'organization_id' => $this->org->id,
                'subscription_plan_id' => $this->plan->id,
                'status' => $status->value,
                'billing_cycle' => 'monthly',
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
            ]);

            $this->withToken($this->token)
                ->getJson('/api/v2/_test/plan-active')
                ->assertForbidden()
                ->assertJsonPath('error_code', 'no_subscription')
                ->assertJsonPath('subscription_required', true);
        }
    }

    public function test_blocks_when_subscription_is_missing(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v2/_test/plan-active')
            ->assertForbidden()
            ->assertJsonPath('error_code', 'no_subscription');
    }
}
