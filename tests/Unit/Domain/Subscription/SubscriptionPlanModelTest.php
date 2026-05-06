<?php

namespace Tests\Unit\Domain\Subscription;

use App\Domain\Subscription\Models\PlanFeatureToggle;
use App\Domain\Subscription\Models\PlanLimit;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for the SubscriptionPlan model.
 *
 * Covers: slug auto-generation, name_ar fallback, casts, relationships,
 * fillable columns, and SoftPOS eligibility attributes.
 */
class SubscriptionPlanModelTest extends TestCase
{
    use RefreshDatabase;

    // ─── Slug Auto-Generation ────────────────────────────────────

    public function test_slug_is_auto_generated_from_name_when_not_provided(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Growth Plan',
            'monthly_price' => 29.99,
        ]);

        $this->assertNotNull($plan->slug);
        $this->assertStringContainsString('growth', $plan->slug);
    }

    public function test_explicit_slug_is_preserved(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Growth Plan',
            'slug' => 'my-custom-slug',
            'monthly_price' => 29.99,
        ]);

        $this->assertSame('my-custom-slug', $plan->slug);
    }

    public function test_duplicate_slug_gets_unique_suffix(): void
    {
        SubscriptionPlan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'monthly_price' => 0,
        ]);

        $plan2 = SubscriptionPlan::create([
            'name' => 'Starter',
            'monthly_price' => 0,
        ]);

        $this->assertNotSame('starter', $plan2->slug);
        $this->assertStringStartsWith('starter', $plan2->slug);
    }

    // ─── name_ar Fallback ────────────────────────────────────────

    public function test_name_ar_defaults_to_name_when_not_provided(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Starter',
            'slug' => 'starter-test',
            'monthly_price' => 0,
        ]);

        $this->assertSame('Starter', $plan->name_ar);
    }

    public function test_explicit_name_ar_is_preserved(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Starter',
            'slug' => 'starter-ar-test',
            'name_ar' => 'ستارتر',
            'monthly_price' => 0,
        ]);

        $this->assertSame('ستارتر', $plan->name_ar);
    }

    // ─── Boolean Casts ───────────────────────────────────────────

    public function test_is_active_is_cast_to_boolean(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Test',
            'slug' => 'test-active-cast',
            'monthly_price' => 0,
            'is_active' => 1,
        ]);

        $this->assertTrue($plan->is_active);
        $this->assertIsBool($plan->is_active);
    }

    public function test_is_highlighted_is_cast_to_boolean(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Test',
            'slug' => 'test-highlighted',
            'monthly_price' => 0,
            'is_highlighted' => true,
        ]);

        $this->assertTrue($plan->is_highlighted);
        $this->assertIsBool($plan->is_highlighted);
    }

    public function test_softpos_free_eligible_is_cast_to_boolean(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'SoftPOS',
            'slug' => 'softpos-cast',
            'monthly_price' => 0,
            'softpos_free_eligible' => true,
        ]);

        $this->assertTrue($plan->softpos_free_eligible);
        $this->assertIsBool($plan->softpos_free_eligible);
    }

    // ─── Relationships ───────────────────────────────────────────

    public function test_has_many_plan_feature_toggles(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Growth',
            'slug' => 'growth-rel',
            'monthly_price' => 29.99,
        ]);

        PlanFeatureToggle::create([
            'subscription_plan_id' => $plan->id,
            'feature_key' => 'multi_branch',
            'is_enabled' => true,
        ]);
        PlanFeatureToggle::create([
            'subscription_plan_id' => $plan->id,
            'feature_key' => 'reports_advanced',
            'is_enabled' => false,
        ]);

        $this->assertCount(2, $plan->planFeatureToggles);
    }

    public function test_has_many_plan_limits(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Starter',
            'slug' => 'starter-limits',
            'monthly_price' => 0,
        ]);

        PlanLimit::create([
            'subscription_plan_id' => $plan->id,
            'limit_key' => 'products',
            'limit_value' => 50,
        ]);

        $this->assertCount(1, $plan->planLimits);
        $this->assertSame(50, (int) $plan->planLimits->first()->limit_value);
    }

    // ─── Pricing ─────────────────────────────────────────────────

    public function test_annual_price_can_be_null(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'No Annual',
            'slug' => 'no-annual',
            'monthly_price' => 19.99,
            'annual_price' => null,
        ]);

        $this->assertNull($plan->annual_price);
    }

    public function test_trial_days_defaults_to_zero(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'No Trial',
            'slug' => 'no-trial-plan',
            'monthly_price' => 19.99,
        ]);

        $this->assertSame(0, (int) $plan->trial_days);
    }

    public function test_grace_period_days_stored_correctly(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Grace Plan',
            'slug' => 'grace-plan',
            'monthly_price' => 9.99,
            'grace_period_days' => 7,
        ]);

        $this->assertSame(7, (int) $plan->grace_period_days);
    }

    // ─── SoftPOS Columns ─────────────────────────────────────────

    public function test_softpos_threshold_stored_correctly(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'SoftPOS Plan',
            'slug' => 'softpos-plan',
            'monthly_price' => 49.99,
            'softpos_free_eligible' => true,
            'softpos_free_threshold' => 500,
            'softpos_free_threshold_period' => 'monthly',
        ]);

        $this->assertSame(500, (int) $plan->softpos_free_threshold);
        $this->assertSame('monthly', $plan->softpos_free_threshold_period);
    }

    // ─── Sort Order ──────────────────────────────────────────────

    public function test_sort_order_is_stored_correctly(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Ordered',
            'slug' => 'ordered-plan',
            'monthly_price' => 0,
            'sort_order' => 3,
        ]);

        $this->assertSame(3, (int) $plan->sort_order);
    }

    public function test_plans_can_be_ordered_by_sort_order(): void
    {
        SubscriptionPlan::create(['name' => 'B', 'slug' => 'b-plan', 'monthly_price' => 0, 'sort_order' => 2]);
        SubscriptionPlan::create(['name' => 'A', 'slug' => 'a-plan', 'monthly_price' => 0, 'sort_order' => 1]);
        SubscriptionPlan::create(['name' => 'C', 'slug' => 'c-plan', 'monthly_price' => 0, 'sort_order' => 3]);

        $sorted = SubscriptionPlan::orderBy('sort_order')->pluck('name')->toArray();

        $this->assertSame(['A', 'B', 'C'], $sorted);
    }

    // ─── Active / Inactive ───────────────────────────────────────

    public function test_inactive_plan_is_stored_correctly(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Inactive Plan',
            'slug' => 'inactive-plan',
            'monthly_price' => 0,
            'is_active' => false,
        ]);

        $this->assertFalse($plan->is_active);
    }

    public function test_can_scope_active_plans(): void
    {
        SubscriptionPlan::create(['name' => 'Active 1', 'slug' => 'active-1', 'monthly_price' => 0, 'is_active' => true]);
        SubscriptionPlan::create(['name' => 'Active 2', 'slug' => 'active-2', 'monthly_price' => 0, 'is_active' => true]);
        SubscriptionPlan::create(['name' => 'Inactive', 'slug' => 'inactive-3', 'monthly_price' => 0, 'is_active' => false]);

        $activePlans = SubscriptionPlan::where('is_active', true)->count();

        $this->assertSame(2, $activePlans);
    }

    // ─── Description ─────────────────────────────────────────────

    public function test_description_and_description_ar_stored_correctly(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Full Plan',
            'slug' => 'full-plan',
            'monthly_price' => 0,
            'description' => 'Full description in English',
            'description_ar' => 'الوصف الكامل بالعربية',
        ]);

        $this->assertSame('Full description in English', $plan->description);
        $this->assertSame('الوصف الكامل بالعربية', $plan->description_ar);
    }
}
