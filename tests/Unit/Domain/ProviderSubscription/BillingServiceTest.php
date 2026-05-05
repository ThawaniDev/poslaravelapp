<?php

namespace Tests\Unit\Domain\ProviderSubscription;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderSubscription\Models\Invoice;
use App\Domain\ProviderSubscription\Models\InvoiceLineItem;
use App\Domain\ProviderSubscription\Models\StoreAddOn;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\ProviderSubscription\Models\SubscriptionCredit;
use App\Domain\ProviderSubscription\Services\BillingService;
use App\Domain\Subscription\Enums\BillingCycle;
use App\Domain\Subscription\Enums\SubscriptionStatus;
use App\Domain\Subscription\Models\PlanAddOn;
use App\Domain\Subscription\Models\SubscriptionDiscount;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for BillingService — invoice generation, lifecycle scheduled jobs.
 */
class BillingServiceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Store $store;
    private User $admin;
    private SubscriptionPlan $monthlyPlan;
    private SubscriptionPlan $freePlan;
    private SubscriptionPlan $softposEligiblePlan;
    private BillingService $billing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Billing Test Org',
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

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@billing.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->monthlyPlan = SubscriptionPlan::create([
            'name' => 'Growth',
            'slug' => 'growth',
            'monthly_price' => 29.99,
            'annual_price' => 299.99,
            'trial_days' => 0,
            'grace_period_days' => 7,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $this->freePlan = SubscriptionPlan::create([
            'name' => 'Free',
            'slug' => 'free-plan',
            'monthly_price' => 0,
            'annual_price' => 0,
            'trial_days' => 0,
            'grace_period_days' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->softposEligiblePlan = SubscriptionPlan::create([
            'name' => 'SoftPOS Pro',
            'slug' => 'softpos-pro',
            'monthly_price' => 49.99,
            'annual_price' => 499.99,
            'trial_days' => 0,
            'grace_period_days' => 7,
            'is_active' => true,
            'sort_order' => 3,
            'softpos_free_eligible' => true,
            'softpos_free_threshold' => 100,
            'softpos_free_threshold_period' => 'monthly',
        ]);

        $this->billing = app(BillingService::class);
    }

    // ─── Invoice Number Format ────────────────────────────────────

    public function test_first_invoice_number_is_padded(): void
    {
        $sub = $this->makeActiveSubscription($this->monthlyPlan);

        $invoice = $this->billing->generateInvoice($sub);

        $this->assertMatchesRegularExpression('/^INV-\d{8}$/', $invoice->invoice_number);
        $this->assertStringStartsWith('INV-', $invoice->invoice_number);
    }

    public function test_invoice_numbers_are_sequential(): void
    {
        $sub = $this->makeActiveSubscription($this->monthlyPlan);

        $inv1 = $this->billing->generateInvoice($sub, 'First');
        $inv2 = $this->billing->generateInvoice($sub, 'Second');

        [$seq1] = sscanf($inv1->invoice_number, 'INV-%d');
        [$seq2] = sscanf($inv2->invoice_number, 'INV-%d');

        $this->assertEquals($seq1 + 1, $seq2);
    }

    // ─── VAT Calculation ─────────────────────────────────────────

    public function test_invoice_includes_15_percent_vat(): void
    {
        $sub = $this->makeActiveSubscription($this->monthlyPlan);

        $invoice = $this->billing->generateInvoice($sub);

        $expectedTax = round(29.99 * 0.15, 2);
        $this->assertEquals($expectedTax, (float) $invoice->tax);
        $this->assertEquals(round(29.99 + $expectedTax, 2), (float) $invoice->total);
    }

    public function test_annual_plan_uses_annual_price(): void
    {
        $sub = $this->makeActiveSubscription($this->monthlyPlan, BillingCycle::Yearly);

        $invoice = $this->billing->generateInvoice($sub);

        $expectedTax = round(299.99 * 0.15, 2);
        $this->assertEquals($expectedTax, (float) $invoice->tax);
        $this->assertEquals(round(299.99 + $expectedTax, 2), (float) $invoice->total);
    }

    // ─── Zero Amount → Immediate Paid ────────────────────────────

    public function test_zero_amount_invoice_is_immediately_paid(): void
    {
        $sub = $this->makeActiveSubscription($this->freePlan);

        $invoice = $this->billing->generateInvoice($sub);

        $this->assertEquals('paid', $invoice->status->value);
        $this->assertEquals(0, (float) $invoice->total);
        $this->assertNotNull($invoice->paid_at);
    }

    // ─── Discount Code — Percentage ──────────────────────────────

    public function test_percentage_discount_is_applied_to_subtotal(): void
    {
        $discount = SubscriptionDiscount::create([
            'code' => 'SAVE20',
            'type' => 'percentage',
            'value' => 20.00,
            'max_uses' => null,
            'times_used' => 0,
        ]);

        $sub = $this->makeActiveSubscription($this->monthlyPlan);

        $invoice = $this->billing->generateInvoice($sub, null, $discount);

        // 29.99 * 0.20 = 5.998 → rounded = 6.00
        // subtotal after discount = 29.99 - 6.00 = 23.99
        $expectedSubtotal = round(29.99 - round(29.99 * 0.20, 2), 2);
        $this->assertEquals($expectedSubtotal, (float) $invoice->amount);

        // Verify there's a discount line item with negative total
        $discountLine = $invoice->invoiceLineItems->first(fn ($item) => (float) $item->total < 0 && str_contains($item->description, 'SAVE20'));
        $this->assertNotNull($discountLine, 'Expected a discount line item for code SAVE20');
        $this->assertEquals(-round(29.99 * 0.20, 2), (float) $discountLine->total);
    }

    public function test_fixed_discount_deducted_from_subtotal(): void
    {
        $discount = SubscriptionDiscount::create([
            'code' => 'FIXED10',
            'type' => 'fixed',
            'value' => 10.00,
            'max_uses' => null,
            'times_used' => 0,
        ]);

        $sub = $this->makeActiveSubscription($this->monthlyPlan);

        $invoice = $this->billing->generateInvoice($sub, null, $discount);

        $expectedSubtotal = round(29.99 - 10.00, 2);
        $this->assertEquals($expectedSubtotal, (float) $invoice->amount);
    }

    public function test_fixed_discount_cannot_exceed_subtotal(): void
    {
        $discount = SubscriptionDiscount::create([
            'code' => 'BIG100',
            'type' => 'fixed',
            'value' => 9999.00,
            'max_uses' => null,
            'times_used' => 0,
        ]);

        $sub = $this->makeActiveSubscription($this->monthlyPlan);

        $invoice = $this->billing->generateInvoice($sub, null, $discount);

        // amount cannot be negative — should be 0 → paid immediately
        $this->assertEquals(0, (float) $invoice->total);
        $this->assertEquals('paid', $invoice->status->value);
    }

    // ─── Credits ─────────────────────────────────────────────────

    public function test_credit_is_applied_to_invoice(): void
    {
        $sub = $this->makeActiveSubscription($this->monthlyPlan);

        SubscriptionCredit::create([
            'store_subscription_id' => $sub->id,
            'applied_by' => $this->admin->id,
            'amount' => 10.00,
            'reason' => 'Loyalty credit',
            'applied_at' => now(),
        ]);

        $invoice = $this->billing->generateInvoice($sub);

        $creditLine = $invoice->invoiceLineItems->first(fn ($item) => str_contains($item->description, 'Credit'));
        $this->assertNotNull($creditLine, 'Expected a credit line item');
        $this->assertEquals(-10.00, (float) $creditLine->total);

        // subtotal should be 29.99 - 10.00 = 19.99
        $this->assertEquals(19.99, (float) $invoice->amount);
    }

    public function test_credit_cannot_exceed_invoice_total(): void
    {
        $sub = $this->makeActiveSubscription($this->freePlan);

        SubscriptionCredit::create([
            'store_subscription_id' => $sub->id,
            'applied_by' => $this->admin->id,
            'amount' => 500.00,
            'reason' => 'Overpayment',
            'applied_at' => now(),
        ]);

        $invoice = $this->billing->generateInvoice($sub);

        // Free plan is already zero, credit won't make it negative
        $this->assertEquals(0, (float) $invoice->total);
        $this->assertEquals('paid', $invoice->status->value);
    }

    // ─── Add-Ons in Invoice ───────────────────────────────────────

    public function test_active_addon_is_included_in_invoice(): void
    {
        $addOn = PlanAddOn::create([
            'name' => 'Loyalty Module',
            'name_ar' => 'وحدة الولاء',
            'slug' => 'loyalty',
            'monthly_price' => 9.99,
            'is_active' => true,
        ]);

        StoreAddOn::create([
            'store_id' => $this->store->id,
            'plan_add_on_id' => $addOn->id,
            'is_active' => true,
            'activated_at' => now(),
        ]);

        $sub = $this->makeActiveSubscription($this->monthlyPlan);

        $invoice = $this->billing->generateInvoice($sub);

        $addOnLine = $invoice->invoiceLineItems->first(fn ($item) => str_contains($item->description, 'Loyalty'));
        $this->assertNotNull($addOnLine, 'Expected an add-on line item for Loyalty Module');
        $this->assertEquals(9.99, (float) $addOnLine->total);
    }

    public function test_annual_addon_price_is_scaled_by_12(): void
    {
        $addOn = PlanAddOn::create([
            'name' => 'Analytics',
            'name_ar' => 'تحليلات',
            'slug' => 'analytics',
            'monthly_price' => 5.00,
            'is_active' => true,
        ]);

        StoreAddOn::create([
            'store_id' => $this->store->id,
            'plan_add_on_id' => $addOn->id,
            'is_active' => true,
            'activated_at' => now(),
        ]);

        $sub = $this->makeActiveSubscription($this->monthlyPlan, BillingCycle::Yearly);

        $invoice = $this->billing->generateInvoice($sub);

        $addOnLine = $invoice->invoiceLineItems->first(fn ($item) => str_contains($item->description, 'Analytics'));
        $this->assertNotNull($addOnLine, 'Expected an annual add-on line item');
        $this->assertEquals(round(5.00 * 12, 2), (float) $addOnLine->total);
    }

    public function test_inactive_addon_excluded_from_invoice(): void
    {
        $addOn = PlanAddOn::create([
            'name' => 'Inactive Module',
            'name_ar' => 'وحدة غير نشطة',
            'slug' => 'inactive',
            'monthly_price' => 15.00,
            'is_active' => true,
        ]);

        StoreAddOn::create([
            'store_id' => $this->store->id,
            'plan_add_on_id' => $addOn->id,
            'is_active' => false,   // deactivated
            'activated_at' => now()->subMonth(),
            'deactivated_at' => now()->subDay(),
        ]);

        $sub = $this->makeActiveSubscription($this->monthlyPlan);

        $invoice = $this->billing->generateInvoice($sub);

        $addOnLine = $invoice->invoiceLineItems->first(fn ($item) => str_contains($item->description, 'Inactive'));
        $this->assertNull($addOnLine, 'Inactive add-on should NOT appear in invoice');
    }

    // ─── SoftPOS Free Tier ────────────────────────────────────────

    public function test_softpos_free_tier_adds_discount_line(): void
    {
        $sub = $this->makeActiveSubscription($this->softposEligiblePlan);

        $sub->update([
            'is_softpos_free' => true,
            'softpos_transaction_count' => 100,
            'original_amount' => 49.99,
            'discount_reason' => 'softpos_threshold_reached',
        ]);
        $sub->refresh();

        $invoice = $this->billing->generateInvoice($sub);

        // With SoftPOS free: plan line 49.99 + discount line -49.99 = 0 subtotal → paid immediately
        $this->assertEquals('paid', $invoice->status->value);
        $this->assertEquals(0, (float) $invoice->total);

        $softposLine = $invoice->invoiceLineItems->first(fn ($item) => str_contains($item->description, 'SoftPOS Free Tier'));
        $this->assertNotNull($softposLine, 'Expected a SoftPOS discount line item');
        $this->assertLessThan(0, (float) $softposLine->total);
    }

    public function test_softpos_free_not_applied_when_threshold_not_reached(): void
    {
        $sub = $this->makeActiveSubscription($this->softposEligiblePlan);

        $sub->update([
            'is_softpos_free' => true,
            'softpos_transaction_count' => 50, // below threshold of 100
        ]);
        $sub->refresh();

        $invoice = $this->billing->generateInvoice($sub);

        // Threshold not reached → no SoftPOS discount
        $softposLine = $invoice->invoiceLineItems->first(fn ($item) => str_contains($item->description, 'SoftPOS Free Tier'));
        $this->assertNull($softposLine, 'SoftPOS discount should NOT be applied below threshold');
    }

    // ─── Line Items Structure ─────────────────────────────────────

    public function test_invoice_has_plan_line_item(): void
    {
        $sub = $this->makeActiveSubscription($this->monthlyPlan);

        $invoice = $this->billing->generateInvoice($sub);

        $this->assertGreaterThan(0, $invoice->invoiceLineItems->count());

        $planLine = $invoice->invoiceLineItems->first(fn ($item) => str_contains($item->description, 'Growth'));
        $this->assertNotNull($planLine, 'Expected a plan line item for Growth');
        $this->assertEquals(29.99, (float) $planLine->total);
    }

    // ─── Renewal Invoice Generation ───────────────────────────────

    public function test_renewal_invoice_generated_for_expiring_subscription(): void
    {
        $sub = $this->makeActiveSubscription($this->monthlyPlan);

        // Move period end to within 3 days
        $sub->update([
            'current_period_end' => now()->addDays(2),
            'current_period_start' => now()->subMonth(),
        ]);

        $generated = $this->billing->generateRenewalInvoices(3);

        $this->assertCount(1, $generated);
        $this->assertStringContainsString('Renewal', $generated[0]->invoiceLineItems->first()->description ?? 'Renewal');
    }

    public function test_renewal_skipped_when_pending_invoice_exists(): void
    {
        $sub = $this->makeActiveSubscription($this->monthlyPlan);

        $sub->update([
            'current_period_end' => now()->addDays(2),
            'current_period_start' => now()->subMonth(),
        ]);

        // Create a pending invoice already
        Invoice::create([
            'store_subscription_id' => $sub->id,
            'invoice_number' => 'INV-00000001',
            'amount' => 29.99,
            'tax' => 4.50,
            'total' => 34.49,
            'status' => 'pending',
            'due_date' => now()->addDays(7),
        ]);

        $generated = $this->billing->generateRenewalInvoices(3);

        $this->assertCount(0, $generated);
    }

    public function test_renewal_not_generated_for_subscription_not_expiring_soon(): void
    {
        $sub = $this->makeActiveSubscription($this->monthlyPlan);

        $sub->update(['current_period_end' => now()->addDays(30)]);

        $generated = $this->billing->generateRenewalInvoices(3);

        $this->assertCount(0, $generated);
    }

    // ─── Expire Overdue Subscriptions ─────────────────────────────

    public function test_grace_subscription_is_expired_when_period_ends(): void
    {
        $sub = StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->monthlyPlan->id,
            'status' => SubscriptionStatus::Grace,
            'billing_cycle' => BillingCycle::Monthly,
            'current_period_start' => now()->subMonths(2),
            'current_period_end' => now()->subDay(), // expired
        ]);

        $count = $this->billing->expireOverdueSubscriptions();

        $this->assertEquals(1, $count);
        $this->assertEquals(SubscriptionStatus::Expired->value, $sub->fresh()->status->value);
    }

    public function test_trial_subscription_is_expired_when_trial_ends(): void
    {
        $sub = StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->monthlyPlan->id,
            'status' => SubscriptionStatus::Trial,
            'billing_cycle' => BillingCycle::Monthly,
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->addMonth(),
            'trial_ends_at' => now()->subDay(), // trial ended
        ]);

        $count = $this->billing->expireOverdueSubscriptions();

        $this->assertEquals(1, $count);
        $this->assertEquals(SubscriptionStatus::Expired->value, $sub->fresh()->status->value);
    }

    public function test_active_subscription_not_expired(): void
    {
        $this->makeActiveSubscription($this->monthlyPlan);

        $count = $this->billing->expireOverdueSubscriptions();

        $this->assertEquals(0, $count);
    }

    // ─── Renew Paid Subscriptions ─────────────────────────────────

    public function test_subscription_renewed_after_period_ends_with_paid_invoice(): void
    {
        $sub = $this->makeActiveSubscription($this->monthlyPlan);

        // Expire the period
        $sub->update([
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->subDay(),
        ]);

        // Create a paid invoice in the current period
        Invoice::create([
            'store_subscription_id' => $sub->id,
            'invoice_number' => 'INV-00000001',
            'amount' => 29.99,
            'tax' => 4.50,
            'total' => 34.49,
            'status' => 'paid',
            'due_date' => now()->subDay(),
            'paid_at' => now()->subDay(),
            'created_at' => now()->subMonth(), // within period
        ]);

        $count = $this->billing->renewPaidSubscriptions();

        $this->assertEquals(1, $count);
        $fresh = $sub->fresh();
        $this->assertTrue($fresh->current_period_end->isAfter(now()));
    }

    public function test_subscription_not_renewed_without_paid_invoice(): void
    {
        $sub = $this->makeActiveSubscription($this->monthlyPlan);

        $sub->update([
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->subDay(),
        ]);

        // No paid invoice

        $count = $this->billing->renewPaidSubscriptions();

        $this->assertEquals(0, $count);
    }

    // ─── Resume from Grace ────────────────────────────────────────

    public function test_resume_from_grace_period(): void
    {
        $sub = StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->monthlyPlan->id,
            'status' => SubscriptionStatus::Grace,
            'billing_cycle' => BillingCycle::Monthly,
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->addDays(5), // still within grace
            'cancelled_at' => now()->subDay(),
        ]);

        $resumed = $this->billing->resumeSubscription($this->org->id);

        $this->assertEquals(SubscriptionStatus::Active->value, $resumed->status->value);
        $this->assertNull($resumed->cancelled_at);
        $this->assertTrue($resumed->current_period_end->isAfter(now()));
    }

    public function test_resume_from_cancelled_status(): void
    {
        $sub = StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->monthlyPlan->id,
            'status' => SubscriptionStatus::Cancelled,
            'billing_cycle' => BillingCycle::Monthly,
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->subDay(),
            'cancelled_at' => now()->subDay(),
        ]);

        $resumed = $this->billing->resumeSubscription($this->org->id);

        $this->assertEquals(SubscriptionStatus::Active->value, $resumed->status->value);
        $this->assertNull($resumed->cancelled_at);
    }

    // ─── Credits — Available Credits Calculation ──────────────────

    public function test_available_credits_calculated_correctly(): void
    {
        $sub = $this->makeActiveSubscription($this->monthlyPlan);

        SubscriptionCredit::create([
            'store_subscription_id' => $sub->id,
            'applied_by' => $this->admin->id,
            'amount' => 50.00,
            'reason' => 'Initial credit',
            'applied_at' => now(),
        ]);

        $available = $this->billing->getAvailableCredits($sub->id);

        $this->assertEquals(50.00, $available);
    }

    public function test_used_credits_deducted_from_available(): void
    {
        $sub = $this->makeActiveSubscription($this->monthlyPlan);

        SubscriptionCredit::create([
            'store_subscription_id' => $sub->id,
            'applied_by' => $this->admin->id,
            'amount' => 50.00,
            'reason' => 'Credit',
            'applied_at' => now(),
        ]);

        // Generate an invoice which will consume credits
        $this->billing->generateInvoice($sub);

        $available = $this->billing->getAvailableCredits($sub->id);

        // 29.99 was consumed, remaining = 50 - 29.99 = 20.01
        $this->assertEqualsWithDelta(20.01, $available, 0.01);
    }

    // ─── Cancel → Grace Period ────────────────────────────────────

    public function test_cancel_with_grace_period_transitions_to_grace(): void
    {
        $sub = $this->makeActiveSubscription($this->monthlyPlan);

        $cancelled = $this->billing->cancelSubscription($this->org->id, 'Too expensive');

        $this->assertEquals(SubscriptionStatus::Grace->value, $cancelled->status->value);
        $this->assertNotNull($cancelled->cancelled_at);
    }

    public function test_cancel_without_grace_transitions_to_cancelled(): void
    {
        $sub = $this->makeActiveSubscription($this->freePlan);

        $cancelled = $this->billing->cancelSubscription($this->org->id);

        $this->assertEquals(SubscriptionStatus::Cancelled->value, $cancelled->status->value);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    private function makeActiveSubscription(SubscriptionPlan $plan, BillingCycle $cycle = BillingCycle::Monthly): StoreSubscription
    {
        return StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => $cycle,
            'current_period_start' => now(),
            'current_period_end' => $cycle === BillingCycle::Yearly ? now()->addYear() : now()->addMonth(),
        ]);
    }
}
