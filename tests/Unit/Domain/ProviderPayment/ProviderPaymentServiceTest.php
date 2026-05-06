<?php

namespace Tests\Unit\Domain\ProviderPayment;

use App\Domain\ProviderPayment\Enums\PaymentPurpose;
use App\Domain\ProviderPayment\Enums\ProviderPaymentStatus;
use App\Domain\ProviderPayment\Models\ProviderPayment;
use App\Domain\ProviderPayment\Services\PaymentEmailService;
use App\Domain\ProviderPayment\Services\PayTabsService;
use App\Domain\ProviderPayment\Services\ProviderPaymentService;
use App\Domain\ProviderSubscription\Models\StoreAddOn;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\ProviderSubscription\Services\BillingService;
use App\Domain\Subscription\Enums\BillingCycle;
use App\Domain\Subscription\Enums\SubscriptionStatus;
use App\Domain\Subscription\Models\PlanAddOn;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class ProviderPaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PayTabsService $payTabsMock;
    private PaymentEmailService $emailMock;
    private BillingService $billingMock;
    private ProviderPaymentService $service;
    private Organization $org;
    private Store $store;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->payTabsMock = Mockery::mock(PayTabsService::class);
        $this->emailMock = Mockery::mock(PaymentEmailService::class);
        $this->billingMock = Mockery::mock(BillingService::class);

        $this->service = new ProviderPaymentService(
            $this->payTabsMock,
            $this->emailMock,
            $this->billingMock,
        );

        $this->org = Organization::create([
            'name' => 'Test Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Owner',
            'email' => 'owner@unit.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
    }

    // ─── initiatePayment ──────────────────────────────────────────

    public function test_initiate_payment_persists_payment_and_returns_redirect(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'monthly_price' => 49.99,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->payTabsMock->shouldReceive('createPaymentPage')->once()->andReturn([
            'success' => true,
            'redirect_url' => 'https://pay.example.com/checkout',
            'tran_ref' => 'TEST-TRAN-001',
            'error' => null,
        ]);

        $result = $this->service->initiatePayment(
            organizationId: $this->org->id,
            purpose: PaymentPurpose::Subscription,
            purposeLabel: 'Pro (monthly)',
            amount: 49.99,
            customerDetails: ['name' => 'Owner', 'email' => 'owner@unit.com', 'phone' => '0500000000'],
            returnUrl: 'https://app.example.com/return',
            purposeReferenceId: $plan->id,
            initiatedBy: $this->user->id,
            paymentContext: ['billing_cycle' => 'monthly'],
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('https://pay.example.com/checkout', $result['redirect_url']);

        $this->assertDatabaseHas('provider_payments', [
            'organization_id' => $this->org->id,
            'purpose' => 'subscription',
            'status' => 'pending',
        ]);
    }

    public function test_initiate_payment_stores_billing_cycle_and_discount_in_context(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'monthly_price' => 49.99,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->payTabsMock->shouldReceive('createPaymentPage')->once()->andReturn([
            'success' => true,
            'redirect_url' => 'https://pay.example.com/checkout',
            'tran_ref' => 'TEST-TRAN-002',
            'error' => null,
        ]);

        $result = $this->service->initiatePayment(
            organizationId: $this->org->id,
            purpose: PaymentPurpose::Subscription,
            purposeLabel: 'Pro (yearly)',
            amount: 499.99,
            customerDetails: ['name' => 'Owner', 'email' => 'owner@unit.com'],
            returnUrl: 'https://app.example.com/return',
            purposeReferenceId: $plan->id,
            initiatedBy: $this->user->id,
            paymentContext: ['billing_cycle' => 'yearly', 'discount_code' => 'ANNUAL20'],
        );

        $payment = ProviderPayment::where('cart_id', $result['cart_id'])->first();
        $this->assertNotNull($payment);
        $this->assertEquals('yearly', $payment->payment_context['billing_cycle']);
        $this->assertEquals('ANNUAL20', $payment->payment_context['discount_code']);
    }

    public function test_initiate_throws_for_inactive_subscription_plan(): void
    {
        $inactivePlan = SubscriptionPlan::create([
            'name' => 'Old',
            'slug' => 'old',
            'monthly_price' => 9.99,
            'is_active' => false,
            'sort_order' => 99,
        ]);

        $this->payTabsMock->shouldNotReceive('createPaymentPage');

        $this->expectException(\RuntimeException::class);

        $this->service->initiatePayment(
            organizationId: $this->org->id,
            purpose: PaymentPurpose::Subscription,
            purposeLabel: 'Old',
            amount: 9.99,
            customerDetails: [],
            returnUrl: 'https://return',
            purposeReferenceId: $inactivePlan->id,
        );
    }

    public function test_initiate_throws_for_inactive_plan_addon(): void
    {
        $addon = PlanAddOn::create([
            'name' => 'Old Addon',
            'slug' => 'old-addon',
            'monthly_price' => 5.00,
            'is_active' => false,
        ]);

        $this->payTabsMock->shouldNotReceive('createPaymentPage');

        $this->expectException(\RuntimeException::class);

        $this->service->initiatePayment(
            organizationId: $this->org->id,
            purpose: PaymentPurpose::PlanAddon,
            purposeLabel: 'Old Addon',
            amount: 5.00,
            customerDetails: [],
            returnUrl: 'https://return',
            purposeReferenceId: $addon->id,
        );
    }

    public function test_initiate_converts_usd_to_sar(): void
    {
        // Set exchange rate via system setting or rely on fallback (3.75)
        $this->payTabsMock->shouldReceive('createPaymentPage')->once()->andReturn([
            'success' => true,
            'redirect_url' => 'https://pay.example.com/checkout',
            'tran_ref' => 'TEST-TRAN-USD-001',
            'error' => null,
        ]);

        $result = $this->service->initiatePayment(
            organizationId: $this->org->id,
            purpose: PaymentPurpose::Other,
            purposeLabel: 'USD Test',
            amount: 10.00,
            customerDetails: [],
            returnUrl: 'https://return',
            currency: 'USD',
        );

        $this->assertTrue($result['success']);
        $payment = ProviderPayment::where('cart_id', $result['cart_id'])->first();

        $this->assertEquals('SAR', $payment->currency);
        $this->assertEquals('USD', $payment->original_currency);
        $this->assertEquals(10.00, (float) $payment->original_amount);
        // 10 USD * 3.75 = 37.50 SAR (fallback rate)
        $this->assertEquals(37.50, (float) $payment->amount);
    }

    // ─── activateSubscription ─────────────────────────────────────

    public function test_activate_subscription_calls_billing_subscribe_for_new_org(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'monthly_price' => 49.99,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $subscription = new StoreSubscription([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => BillingCycle::Monthly,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);
        $subscription->id = \Illuminate\Support\Str::uuid()->toString();

        $payment = ProviderPayment::create([
            'organization_id' => $this->org->id,
            'purpose' => PaymentPurpose::Subscription,
            'purpose_label' => 'Pro (monthly)',
            'purpose_reference_id' => $plan->id,
            'amount' => 49.99,
            'tax_amount' => 7.50,
            'total_amount' => 57.49,
            'currency' => 'SAR',
            'gateway' => 'paytabs',
            'cart_id' => 'WP-ACT-001',
            'status' => ProviderPaymentStatus::Completed,
            'payment_context' => ['billing_cycle' => 'monthly'],
            'initiated_by' => $this->user->id,
        ]);

        $this->billingMock
            ->shouldReceive('subscribe')
            ->with($this->org->id, $plan->id, BillingCycle::Monthly, Mockery::any(), null)
            ->once()
            ->andReturn($subscription);

        $this->emailMock->shouldReceive('sendPaymentConfirmation')->andReturn(true)->byDefault();

        // Call the protected method via reflection or expose via public wrapper for test
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('activateSubscription');
        $method->setAccessible(true);
        $method->invoke($this->service, $payment);
    }

    public function test_activate_subscription_calls_billing_change_plan_for_existing_subscription(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'monthly_price' => 149.99,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        // Create existing subscription so activateSubscription uses changePlan
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => BillingCycle::Monthly,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $newPlan = SubscriptionPlan::create([
            'name' => 'Enterprise Plus',
            'slug' => 'enterprise-plus',
            'monthly_price' => 249.99,
            'is_active' => true,
            'sort_order' => 3,
        ]);

        $updatedSub = new StoreSubscription([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $newPlan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => BillingCycle::Yearly,
            'current_period_start' => now(),
            'current_period_end' => now()->addYear(),
        ]);
        $updatedSub->id = \Illuminate\Support\Str::uuid()->toString();

        $payment = ProviderPayment::create([
            'organization_id' => $this->org->id,
            'purpose' => PaymentPurpose::Subscription,
            'purpose_label' => 'Enterprise Plus (yearly)',
            'purpose_reference_id' => $newPlan->id,
            'amount' => 2499.99,
            'tax_amount' => 375.00,
            'total_amount' => 2874.99,
            'currency' => 'SAR',
            'gateway' => 'paytabs',
            'cart_id' => 'WP-CHG-001',
            'status' => ProviderPaymentStatus::Completed,
            'payment_context' => ['billing_cycle' => 'yearly'],
            'initiated_by' => $this->user->id,
        ]);

        $this->billingMock
            ->shouldReceive('changePlan')
            ->with($this->org->id, $newPlan->id, BillingCycle::Yearly)
            ->once()
            ->andReturn($updatedSub);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('activateSubscription');
        $method->setAccessible(true);
        $method->invoke($this->service, $payment);
    }

    // ─── activateAddon ─────────────────────────────────────────────

    public function test_activate_addon_upserts_store_add_on_by_composite_key(): void
    {
        $addon = PlanAddOn::create([
            'name' => 'Kitchen Display',
            'slug' => 'kds',
            'monthly_price' => 10.00,
            'is_active' => true,
        ]);

        $payment = ProviderPayment::create([
            'organization_id' => $this->org->id,
            'purpose' => PaymentPurpose::PlanAddon,
            'purpose_label' => 'Kitchen Display',
            'purpose_reference_id' => $addon->id,
            'amount' => 10.00,
            'tax_amount' => 1.50,
            'total_amount' => 11.50,
            'currency' => 'SAR',
            'gateway' => 'paytabs',
            'cart_id' => 'WP-ADDON-001',
            'status' => ProviderPaymentStatus::Completed,
            'initiated_by' => $this->user->id,
        ]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('activateAddon');
        $method->setAccessible(true);
        $method->invoke($this->service, $payment);

        $this->assertDatabaseHas('store_add_ons', [
            'store_id' => $this->store->id,
            'plan_add_on_id' => $addon->id,
            'is_active' => true,
        ]);
    }

    public function test_activate_addon_is_idempotent_on_duplicate_call(): void
    {
        $addon = PlanAddOn::create([
            'name' => 'Loyalty',
            'slug' => 'loyalty',
            'monthly_price' => 8.00,
            'is_active' => true,
        ]);

        // Pre-insert the row to simulate already active
        DB::table('store_add_ons')->insert([
            'store_id' => $this->store->id,
            'plan_add_on_id' => $addon->id,
            'is_active' => true,
            'activated_at' => now(),
        ]);

        $payment = ProviderPayment::create([
            'organization_id' => $this->org->id,
            'purpose' => PaymentPurpose::PlanAddon,
            'purpose_label' => 'Loyalty',
            'purpose_reference_id' => $addon->id,
            'amount' => 8.00,
            'tax_amount' => 1.20,
            'total_amount' => 9.20,
            'currency' => 'SAR',
            'gateway' => 'paytabs',
            'cart_id' => 'WP-ADDON-IDEM-001',
            'status' => ProviderPaymentStatus::Completed,
            'initiated_by' => $this->user->id,
        ]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('activateAddon');
        $method->setAccessible(true);
        $method->invoke($this->service, $payment);

        // Should still be exactly one row
        $this->assertEquals(1, DB::table('store_add_ons')
            ->where('store_id', $this->store->id)
            ->where('plan_add_on_id', $addon->id)
            ->count());
    }

    // ─── handleIpn ────────────────────────────────────────────────

    public function test_handle_ipn_returns_false_for_invalid_signature(): void
    {
        $this->payTabsMock
            ->shouldReceive('validateIpnSignature')
            ->with('raw-body', 'bad-sig')
            ->once()
            ->andReturn(false);

        $result = $this->service->handleIpn(['tran_ref' => 'X'], 'raw-body', 'bad-sig');

        $this->assertFalse($result);
    }

    public function test_handle_ipn_marks_payment_complete_for_approved_status(): void
    {
        $this->payTabsMock->shouldReceive('validateIpnSignature')->andReturn(true);
        $this->emailMock->shouldReceive('sendPaymentConfirmation')->once()->andReturn(true);

        $payment = ProviderPayment::create([
            'organization_id' => $this->org->id,
            'purpose' => PaymentPurpose::Other,
            'purpose_label' => 'Test',
            'amount' => 100,
            'tax_amount' => 15,
            'total_amount' => 115,
            'currency' => 'SAR',
            'gateway' => 'paytabs',
            'cart_id' => 'WP-IPN-001',
            'tran_ref' => 'TRF-001',
            'status' => ProviderPaymentStatus::Pending,
        ]);

        $result = $this->service->handleIpn([
            'tran_ref' => 'TRF-001',
            'cart_id' => 'WP-IPN-001',
            'tran_type' => 'sale',
            'payment_result' => ['response_status' => 'A'],
        ], 'raw', 'sig');

        $this->assertTrue($result);
        $this->assertDatabaseHas('provider_payments', [
            'id' => $payment->id,
            'status' => 'completed',
        ]);
    }

    public function test_handle_ipn_marks_payment_failed_for_declined_status(): void
    {
        $this->payTabsMock->shouldReceive('validateIpnSignature')->andReturn(true);
        $this->emailMock->shouldReceive('sendPaymentFailedEmail')->andReturn(true)->byDefault();

        $payment = ProviderPayment::create([
            'organization_id' => $this->org->id,
            'purpose' => PaymentPurpose::Other,
            'purpose_label' => 'Test',
            'amount' => 100,
            'tax_amount' => 15,
            'total_amount' => 115,
            'currency' => 'SAR',
            'gateway' => 'paytabs',
            'cart_id' => 'WP-IPN-FAIL-001',
            'tran_ref' => 'TRF-FAIL-001',
            'status' => ProviderPaymentStatus::Pending,
        ]);

        $result = $this->service->handleIpn([
            'tran_ref' => 'TRF-FAIL-001',
            'cart_id' => 'WP-IPN-FAIL-001',
            'tran_type' => 'sale',
            'payment_result' => ['response_status' => 'D'],
        ], 'raw', 'sig');

        $this->assertTrue($result);
        $this->assertDatabaseHas('provider_payments', [
            'id' => $payment->id,
            'status' => 'failed',
        ]);
    }

    public function test_handle_ipn_skips_already_completed_payment(): void
    {
        $this->payTabsMock->shouldReceive('validateIpnSignature')->andReturn(true);
        $this->emailMock->shouldNotReceive('sendPaymentConfirmation');

        $payment = ProviderPayment::create([
            'organization_id' => $this->org->id,
            'purpose' => PaymentPurpose::Other,
            'purpose_label' => 'Done',
            'amount' => 100,
            'tax_amount' => 15,
            'total_amount' => 115,
            'currency' => 'SAR',
            'gateway' => 'paytabs',
            'cart_id' => 'WP-DONE-001',
            'tran_ref' => 'TRF-DONE-001',
            'status' => ProviderPaymentStatus::Completed,
        ]);

        $result = $this->service->handleIpn([
            'tran_ref' => 'TRF-DONE-001',
            'cart_id' => 'WP-DONE-001',
            'tran_type' => 'sale',
            'payment_result' => ['response_status' => 'A'],
        ], 'raw', 'sig');

        $this->assertTrue($result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
