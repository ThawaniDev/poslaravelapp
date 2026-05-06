<?php

namespace Tests\Feature\Subscription;

use App\Domain\Auth\Models\User;
use App\Domain\Billing\Models\PaymentRetryRule;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderSubscription\Jobs\ExpireSubscriptionsJob;
use App\Domain\ProviderSubscription\Jobs\GenerateRenewalInvoicesJob;
use App\Domain\ProviderSubscription\Jobs\RenewPaidSubscriptionsJob;
use App\Domain\ProviderSubscription\Jobs\RetryFailedPaymentsJob;
use App\Domain\ProviderSubscription\Models\Invoice;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Enums\SubscriptionStatus;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Feature tests for subscription scheduled jobs.
 *
 * Tests the actual DB side-effects of each job by dispatching it synchronously
 * (via dispatch()) and asserting the resulting DB state.
 *
 * Covered jobs:
 *   - ExpireSubscriptionsJob: grace→expired, trial→expired past date
 *   - GenerateRenewalInvoicesJob: creates pending invoice for subs expiring soon
 *   - RenewPaidSubscriptionsJob: advances period for active+paid subs
 *   - RetryFailedPaymentsJob: retries failed invoices → pending, grace after max retries
 */
class SubscriptionJobsTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = SubscriptionPlan::create([
            'name' => 'Growth',
            'slug' => 'growth-jobs',
            'monthly_price' => 29.99,
            'grace_period_days' => 7,
            'trial_days' => 14,
            'is_active' => true,
        ]);
    }

    private function makeOrg(string $name): Organization
    {
        $org = Organization::create([
            'name' => $name,
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);
        Store::create([
            'organization_id' => $org->id,
            'name' => $name . ' Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        return $org;
    }

    // ─── ExpireSubscriptionsJob ──────────────────────────────────

    public function test_expire_job_transitions_overdue_grace_subscription_to_expired(): void
    {
        $org = $this->makeOrg('Grace Expire Org');
        $sub = StoreSubscription::create([
            'organization_id' => $org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::Grace,
            'billing_cycle' => 'monthly',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->subDay(), // past grace period end
        ]);

        ExpireSubscriptionsJob::dispatchSync();

        $this->assertSame(SubscriptionStatus::Expired->value, $sub->fresh()->status->value);
    }

    public function test_expire_job_transitions_overdue_trial_to_expired(): void
    {
        $org = $this->makeOrg('Trial Expire Org');
        $sub = StoreSubscription::create([
            'organization_id' => $org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::Trial,
            'billing_cycle' => 'monthly',
            'trial_ends_at' => now()->subDay(),
            'current_period_start' => now()->subDays(15),
            'current_period_end' => now()->subDay(),
        ]);

        ExpireSubscriptionsJob::dispatchSync();

        $this->assertSame(SubscriptionStatus::Expired->value, $sub->fresh()->status->value);
    }

    public function test_expire_job_does_not_touch_active_subscriptions(): void
    {
        $org = $this->makeOrg('Active Org');
        $sub = StoreSubscription::create([
            'organization_id' => $org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        ExpireSubscriptionsJob::dispatchSync();

        $this->assertSame(SubscriptionStatus::Active->value, $sub->fresh()->status->value);
    }

    public function test_expire_job_does_not_touch_grace_subscription_still_in_grace_period(): void
    {
        $org = $this->makeOrg('In Grace Org');
        $sub = StoreSubscription::create([
            'organization_id' => $org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::Grace,
            'billing_cycle' => 'monthly',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->addDays(3), // still in grace
        ]);

        ExpireSubscriptionsJob::dispatchSync();

        $this->assertSame(SubscriptionStatus::Grace->value, $sub->fresh()->status->value);
    }

    public function test_expire_job_does_not_touch_trial_that_hasnt_ended(): void
    {
        $org = $this->makeOrg('Active Trial Org');
        $sub = StoreSubscription::create([
            'organization_id' => $org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::Trial,
            'billing_cycle' => 'monthly',
            'trial_ends_at' => now()->addDays(7),
            'current_period_start' => now(),
            'current_period_end' => now()->addDays(14),
        ]);

        ExpireSubscriptionsJob::dispatchSync();

        $this->assertSame(SubscriptionStatus::Trial->value, $sub->fresh()->status->value);
    }

    public function test_expire_job_expires_multiple_overdue_subscriptions(): void
    {
        $org1 = $this->makeOrg('Batch Org 1');
        $org2 = $this->makeOrg('Batch Org 2');

        $sub1 = StoreSubscription::create([
            'organization_id' => $org1->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::Grace,
            'billing_cycle' => 'monthly',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->subDay(),
        ]);
        $sub2 = StoreSubscription::create([
            'organization_id' => $org2->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::Trial,
            'billing_cycle' => 'monthly',
            'trial_ends_at' => now()->subDay(),
            'current_period_start' => now()->subDays(15),
            'current_period_end' => now()->subDay(),
        ]);

        ExpireSubscriptionsJob::dispatchSync();

        $this->assertSame(SubscriptionStatus::Expired->value, $sub1->fresh()->status->value);
        $this->assertSame(SubscriptionStatus::Expired->value, $sub2->fresh()->status->value);
    }

    // ─── GenerateRenewalInvoicesJob ──────────────────────────────

    public function test_renewal_job_creates_pending_invoice_for_soon_expiring_subscription(): void
    {
        $org = $this->makeOrg('Renewal Org');
        $sub = StoreSubscription::create([
            'organization_id' => $org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now()->subMonth()->addDays(27),
            'current_period_end' => now()->addDays(2), // expires in 2 days (within 3-day window)
        ]);

        GenerateRenewalInvoicesJob::dispatchSync();

        $this->assertDatabaseHas('invoices', [
            'store_subscription_id' => $sub->id,
            'status' => 'pending',
        ]);
    }

    public function test_renewal_job_skips_subscription_expiring_too_far_in_future(): void
    {
        $org = $this->makeOrg('Far Future Org');
        $sub = StoreSubscription::create([
            'organization_id' => $org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addDays(20), // far future, skip
        ]);

        GenerateRenewalInvoicesJob::dispatchSync();

        $this->assertDatabaseMissing('invoices', [
            'store_subscription_id' => $sub->id,
        ]);
    }

    public function test_renewal_job_skips_already_invoiced_subscription(): void
    {
        $org = $this->makeOrg('Already Invoiced Org');
        $sub = StoreSubscription::create([
            'organization_id' => $org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now()->subDays(27),
            'current_period_end' => now()->addDays(2),
        ]);

        // Already has a pending invoice from the current period
        Invoice::create([
            'store_subscription_id' => $sub->id,
            'invoice_number' => 'INV-SKIP',
            'amount' => 29.99,
            'tax' => 4.50,
            'total' => 34.49,
            'status' => 'pending',
            'created_at' => now()->subDays(1), // created in current period
        ]);

        $initialCount = Invoice::where('store_subscription_id', $sub->id)->count();

        GenerateRenewalInvoicesJob::dispatchSync();

        $this->assertSame($initialCount, Invoice::where('store_subscription_id', $sub->id)->count());
    }

    public function test_renewal_job_uses_custom_days_before_expiry(): void
    {
        $org = $this->makeOrg('Custom Days Org');
        $sub = StoreSubscription::create([
            'organization_id' => $org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now()->subDays(25),
            'current_period_end' => now()->addDays(4), // expiring in 4 days
        ]);

        // Default 3-day window would skip this; use 5-day window
        GenerateRenewalInvoicesJob::dispatch(5);
        (new GenerateRenewalInvoicesJob(5))->handle(app(\App\Domain\ProviderSubscription\Services\BillingService::class));

        $this->assertDatabaseHas('invoices', [
            'store_subscription_id' => $sub->id,
            'status' => 'pending',
        ]);
    }

    // ─── RenewPaidSubscriptionsJob ───────────────────────────────

    public function test_renew_job_advances_period_for_active_subscription_with_paid_invoice(): void
    {
        $org = $this->makeOrg('Renew Paid Org');
        $periodEnd = now()->subHour(); // period has ended
        $sub = StoreSubscription::create([
            'organization_id' => $org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => $periodEnd,
        ]);

        // Paid invoice in the period
        Invoice::create([
            'store_subscription_id' => $sub->id,
            'invoice_number' => 'INV-PAID',
            'amount' => 29.99,
            'tax' => 4.50,
            'total' => 34.49,
            'status' => 'paid',
            'paid_at' => now()->subDays(2),
            'created_at' => now()->subMonth()->addDays(1),
        ]);

        RenewPaidSubscriptionsJob::dispatchSync();

        $fresh = $sub->fresh();
        $this->assertTrue($fresh->current_period_end->gt($periodEnd));
        $this->assertSame(SubscriptionStatus::Active->value, $fresh->status->value);
    }

    public function test_renew_job_skips_subscription_without_paid_invoice(): void
    {
        $org = $this->makeOrg('No Paid Invoice Org');
        $periodEnd = now()->subHour();
        $sub = StoreSubscription::create([
            'organization_id' => $org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => $periodEnd,
        ]);

        // Only a pending invoice — no paid
        Invoice::create([
            'store_subscription_id' => $sub->id,
            'invoice_number' => 'INV-PENDING',
            'amount' => 29.99,
            'tax' => 4.50,
            'total' => 34.49,
            'status' => 'pending',
            'created_at' => now()->subMonth()->addDays(1),
        ]);

        RenewPaidSubscriptionsJob::dispatchSync();

        // Period should NOT have advanced
        $this->assertEqualsWithDelta(
            $periodEnd->timestamp,
            $sub->fresh()->current_period_end->timestamp,
            5
        );
    }

    public function test_renew_job_skips_future_period_subscriptions(): void
    {
        $org = $this->makeOrg('Future Period Org');
        $sub = StoreSubscription::create([
            'organization_id' => $org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(), // still in period
        ]);

        $originalEnd = $sub->current_period_end;

        RenewPaidSubscriptionsJob::dispatchSync();

        $this->assertEqualsWithDelta(
            $originalEnd->timestamp,
            $sub->fresh()->current_period_end->timestamp,
            5
        );
    }

    // ─── RetryFailedPaymentsJob ──────────────────────────────────

    public function test_retry_job_changes_failed_invoice_to_pending(): void
    {
        $org = $this->makeOrg('Failed Payment Org');
        $sub = StoreSubscription::create([
            'organization_id' => $org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->addDays(7),
        ]);

        $invoice = Invoice::create([
            'store_subscription_id' => $sub->id,
            'invoice_number' => 'INV-FAIL',
            'amount' => 29.99,
            'tax' => 4.50,
            'total' => 34.49,
            'status' => 'failed',
            'due_date' => now()->addDays(1),
        ]);
        DB::table('invoices')->where('id', $invoice->id)->update(['updated_at' => now()->subHours(30)]);

        RetryFailedPaymentsJob::dispatchSync();

        $this->assertSame('pending', $invoice->fresh()->status->value);
    }

    public function test_retry_job_does_not_retry_recently_failed_invoice(): void
    {
        $org = $this->makeOrg('Recent Fail Org');
        $sub = StoreSubscription::create([
            'organization_id' => $org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->addDays(7),
        ]);

        $invoice = Invoice::create([
            'store_subscription_id' => $sub->id,
            'invoice_number' => 'INV-RECENT-FAIL',
            'amount' => 29.99,
            'tax' => 4.50,
            'total' => 34.49,
            'status' => 'failed',
            'due_date' => now()->addDays(1),
        ]);
        DB::table('invoices')->where('id', $invoice->id)->update(['updated_at' => now()->subMinutes(30)]);

        RetryFailedPaymentsJob::dispatchSync();

        // Should still be failed (retry interval not reached)
        $this->assertSame('failed', $invoice->fresh()->status->value);
    }

    public function test_retry_job_moves_subscription_to_grace_after_max_retries(): void
    {
        // Set max_retries = 1 so a single failed invoice triggers the grace move.
        // The retry-count logic counts invoices with the same invoice_number and
        // status='failed'; with a unique constraint on invoice_number there is
        // always exactly 1 such record, which meets the threshold when max_retries=1.
        PaymentRetryRule::create([
            'max_retries' => 1,
            'retry_interval_hours' => 24,
            'grace_period_after_failure_days' => 7,
        ]);

        $org = $this->makeOrg('Max Retries Org');
        $sub = StoreSubscription::create([
            'organization_id' => $org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->addDays(7),
        ]);

        $inv = Invoice::create([
            'store_subscription_id' => $sub->id,
            'invoice_number' => 'INV-MAX-RETRY-SINGLE',
            'amount' => 29.99,
            'tax' => 4.50,
            'total' => 34.49,
            'status' => 'failed',
            'due_date' => now()->subDays(2),
        ]);
        // old enough to pass retry interval check
        DB::table('invoices')->where('id', $inv->id)->update(['updated_at' => now()->subHours(50)]);

        RetryFailedPaymentsJob::dispatchSync();

        $this->assertSame(SubscriptionStatus::Grace->value, $sub->fresh()->status->value);
    }
}
