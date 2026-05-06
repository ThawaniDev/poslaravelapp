<?php

namespace Tests\Feature\Payment;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderPayment\Enums\ProviderPaymentStatus;
use App\Domain\ProviderPayment\Models\ProviderPayment;
use App\Domain\ProviderPayment\Services\PayTabsService;
use App\Domain\ProviderPayment\Services\PaymentEmailService;
use App\Domain\ProviderSubscription\Services\BillingService;
use App\Domain\Subscription\Models\PlanAddOn;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ProviderPaymentApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Organization $org;
    private Store $store;
    private string $token;
    private SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->owner->createToken('test', ['*'])->plainTextToken;

        $this->plan = SubscriptionPlan::create([
            'name' => 'Growth',
            'slug' => 'growth',
            'monthly_price' => 49.99,
            'annual_price' => 499.99,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Stub PayTabs to avoid real HTTP calls in all tests
        $this->app->bind(PayTabsService::class, function () {
            $mock = Mockery::mock(PayTabsService::class);
            $mock->shouldReceive('createPaymentPage')->andReturn([
                'success' => true,
                'redirect_url' => 'https://pay.paytabs.com/test/checkout',
                'tran_ref' => 'TEST-TRAN-' . uniqid(),
                'error' => null,
            ])->byDefault();
            $mock->shouldReceive('validateIpnSignature')->andReturn(true)->byDefault();
            $mock->shouldReceive('queryTransaction')->andReturn(null)->byDefault();
            $mock->shouldReceive('refund')->andReturn(null)->byDefault();
            return $mock;
        });

        // Stub email service to avoid mail delivery in tests
        $this->app->bind(PaymentEmailService::class, function () {
            $mock = Mockery::mock(PaymentEmailService::class);
            $mock->shouldReceive('sendPaymentConfirmation')->andReturn(true)->byDefault();
            $mock->shouldReceive('sendInvoiceEmail')->andReturn(true)->byDefault();
            $mock->shouldReceive('sendPaymentFailedEmail')->andReturn(true)->byDefault();
            $mock->shouldReceive('sendRefundConfirmation')->andReturn(true)->byDefault();
            return $mock;
        });
    }

    // ─── List ──────────────────────────────────────────────────────

    public function test_can_list_payments(): void
    {
        ProviderPayment::create([
            'organization_id' => $this->org->id,
            'purpose' => 'other',
            'purpose_label' => 'Test Payment',
            'amount' => 100,
            'tax_amount' => 15,
            'total_amount' => 115,
            'currency' => 'SAR',
            'gateway' => 'paytabs',
            'cart_id' => 'WP-TEST-001',
            'status' => ProviderPaymentStatus::Pending,
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v2/provider-payments');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 1);
    }

    public function test_list_requires_auth(): void
    {
        $response = $this->getJson('/api/v2/provider-payments');

        $response->assertUnauthorized();
    }

    public function test_list_is_scoped_to_own_organization(): void
    {
        $otherOrg = Organization::create(['name' => 'Other Org', 'business_type' => 'grocery', 'country' => 'SA']);
        ProviderPayment::create([
            'organization_id' => $otherOrg->id,
            'purpose' => 'other',
            'purpose_label' => 'Other Payment',
            'amount' => 200,
            'tax_amount' => 30,
            'total_amount' => 230,
            'currency' => 'SAR',
            'gateway' => 'paytabs',
            'cart_id' => 'WP-OTHER-001',
            'status' => ProviderPaymentStatus::Pending,
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v2/provider-payments');

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.total'));
    }

    public function test_list_can_filter_by_status(): void
    {
        ProviderPayment::create([
            'organization_id' => $this->org->id,
            'purpose' => 'other',
            'purpose_label' => 'Pending',
            'amount' => 100,
            'tax_amount' => 15,
            'total_amount' => 115,
            'currency' => 'SAR',
            'gateway' => 'paytabs',
            'cart_id' => 'WP-P-001',
            'status' => ProviderPaymentStatus::Pending,
        ]);

        ProviderPayment::create([
            'organization_id' => $this->org->id,
            'purpose' => 'other',
            'purpose_label' => 'Completed',
            'amount' => 200,
            'tax_amount' => 30,
            'total_amount' => 230,
            'currency' => 'SAR',
            'gateway' => 'paytabs',
            'cart_id' => 'WP-C-001',
            'status' => ProviderPaymentStatus::Completed,
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v2/provider-payments?status=pending');

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.total'));
        $this->assertEquals('pending', $response->json('data.data.0.status'));
    }

    // ─── Show ──────────────────────────────────────────────────────

    public function test_can_get_own_payment(): void
    {
        $payment = ProviderPayment::create([
            'organization_id' => $this->org->id,
            'purpose' => 'other',
            'purpose_label' => 'Test',
            'amount' => 100,
            'tax_amount' => 15,
            'total_amount' => 115,
            'currency' => 'SAR',
            'gateway' => 'paytabs',
            'cart_id' => 'WP-SHOW-001',
            'status' => ProviderPaymentStatus::Pending,
        ]);

        $response = $this->withToken($this->token)->getJson("/api/v2/provider-payments/{$payment->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $payment->id);
    }

    public function test_cannot_get_other_org_payment(): void
    {
        $otherOrg = Organization::create(['name' => 'Other', 'business_type' => 'grocery', 'country' => 'SA']);
        $payment = ProviderPayment::create([
            'organization_id' => $otherOrg->id,
            'purpose' => 'other',
            'purpose_label' => 'Other',
            'amount' => 100,
            'tax_amount' => 15,
            'total_amount' => 115,
            'currency' => 'SAR',
            'gateway' => 'paytabs',
            'cart_id' => 'WP-O-001',
            'status' => ProviderPaymentStatus::Pending,
        ]);

        $response = $this->withToken($this->token)->getJson("/api/v2/provider-payments/{$payment->id}");

        $response->assertNotFound();
    }

    // ─── Initiate ─────────────────────────────────────────────────

    public function test_initiate_subscription_payment_succeeds(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v2/provider-payments/initiate', [
            'purpose' => 'subscription',
            'purpose_label' => 'Growth (monthly)',
            'amount' => 49.99,
            'purpose_reference_id' => $this->plan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.purpose', 'subscription');

        $this->assertNotNull($response->json('data.redirect_url'));
        $this->assertDatabaseHas('provider_payments', [
            'organization_id' => $this->org->id,
            'purpose' => 'subscription',
            'status' => 'pending',
        ]);
    }

    public function test_initiate_stores_payment_context(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v2/provider-payments/initiate', [
            'purpose' => 'subscription',
            'purpose_label' => 'Growth (yearly)',
            'amount' => 499.99,
            'purpose_reference_id' => $this->plan->id,
            'billing_cycle' => 'yearly',
            'discount_code' => 'SAVE10',
        ]);

        $response->assertOk();
        $paymentId = $response->json('data.id');
        $payment = ProviderPayment::find($paymentId);

        $this->assertEquals('yearly', $payment->payment_context['billing_cycle'] ?? null);
        $this->assertEquals('SAVE10', $payment->payment_context['discount_code'] ?? null);
    }

    public function test_initiate_rejects_inactive_subscription_plan(): void
    {
        $inactivePlan = SubscriptionPlan::create([
            'name' => 'Old Plan',
            'slug' => 'old',
            'monthly_price' => 9.99,
            'is_active' => false,
            'sort_order' => 99,
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/provider-payments/initiate', [
            'purpose' => 'subscription',
            'purpose_label' => 'Old Plan',
            'amount' => 9.99,
            'purpose_reference_id' => $inactivePlan->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_initiate_rejects_missing_subscription_plan_reference(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v2/provider-payments/initiate', [
            'purpose' => 'subscription',
            'purpose_label' => 'Growth',
            'amount' => 49.99,
            // purposeReferenceId intentionally omitted
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_initiate_rejects_inactive_plan_addon(): void
    {
        $inactiveAddon = PlanAddOn::create([
            'name' => 'Old Addon',
            'slug' => 'old-addon',
            'monthly_price' => 5.00,
            'is_active' => false,
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/provider-payments/initiate', [
            'purpose' => 'plan_addon',
            'purpose_label' => 'Old Addon',
            'amount' => 5.00,
            'purpose_reference_id' => $inactiveAddon->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_initiate_rejects_foreign_invoice_id(): void
    {
        $otherOrg = Organization::create(['name' => 'Other', 'business_type' => 'grocery', 'country' => 'SA']);
        // A random non-existent UUID
        $fakeInvoiceId = \Illuminate\Support\Str::uuid()->toString();

        $response = $this->withToken($this->token)->postJson('/api/v2/provider-payments/initiate', [
            'purpose' => 'other',
            'purpose_label' => 'Test',
            'amount' => 50.00,
            'invoice_id' => $fakeInvoiceId,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_initiate_requires_auth(): void
    {
        $response = $this->postJson('/api/v2/provider-payments/initiate', [
            'purpose' => 'subscription',
            'purpose_label' => 'Growth',
            'amount' => 49.99,
        ]);

        $response->assertUnauthorized();
    }

    public function test_initiate_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v2/provider-payments/initiate', []);

        $response->assertUnprocessable();
    }

    public function test_initiate_applies_usd_conversion(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v2/provider-payments/initiate', [
            'purpose' => 'other',
            'purpose_label' => 'USD Payment',
            'amount' => 10.00,
            'currency' => 'USD',
        ]);

        $response->assertOk();
        $paymentId = $response->json('data.id');
        $payment = ProviderPayment::find($paymentId);

        $this->assertEquals('SAR', $payment->currency);
        $this->assertEquals('USD', $payment->original_currency);
        $this->assertGreaterThan(10, (float) $payment->amount);
    }

    // ─── IPN ──────────────────────────────────────────────────────

    public function test_ipn_marks_payment_completed_on_approved_status(): void
    {
        $payment = ProviderPayment::create([
            'organization_id' => $this->org->id,
            'purpose' => 'other',
            'purpose_label' => 'Test',
            'amount' => 100,
            'tax_amount' => 15,
            'total_amount' => 115,
            'currency' => 'SAR',
            'gateway' => 'paytabs',
            'cart_id' => 'WP-IPN-001',
            'tran_ref' => 'TEST-TRAN-IPN-001',
            'status' => ProviderPaymentStatus::Pending,
        ]);

        $response = $this->postJson('/api/v2/provider-payments/ipn', [
            'tran_ref' => 'TEST-TRAN-IPN-001',
            'cart_id' => 'WP-IPN-001',
            'tran_type' => 'sale',
            'payment_result' => [
                'response_status' => 'A',
                'response_code' => '000',
                'response_message' => 'Authorised',
            ],
        ], ['Signature' => 'valid-sig']);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('provider_payments', [
            'id' => $payment->id,
            'status' => 'completed',
        ]);
    }

    public function test_ipn_marks_payment_failed_on_declined_status(): void
    {
        $payment = ProviderPayment::create([
            'organization_id' => $this->org->id,
            'purpose' => 'other',
            'purpose_label' => 'Test',
            'amount' => 100,
            'tax_amount' => 15,
            'total_amount' => 115,
            'currency' => 'SAR',
            'gateway' => 'paytabs',
            'cart_id' => 'WP-IPN-FAIL-001',
            'tran_ref' => 'TEST-TRAN-IPN-FAIL-001',
            'status' => ProviderPaymentStatus::Pending,
        ]);

        $response = $this->postJson('/api/v2/provider-payments/ipn', [
            'tran_ref' => 'TEST-TRAN-IPN-FAIL-001',
            'cart_id' => 'WP-IPN-FAIL-001',
            'tran_type' => 'sale',
            'payment_result' => [
                'response_status' => 'D',
                'response_code' => '001',
                'response_message' => 'Declined',
            ],
        ], ['Signature' => 'valid-sig']);

        $response->assertOk();

        $this->assertDatabaseHas('provider_payments', [
            'id' => $payment->id,
            'status' => 'failed',
        ]);
    }

    public function test_ipn_rejects_invalid_signature(): void
    {
        // Bind a PayTabs mock that rejects the signature
        $this->app->bind(PayTabsService::class, function () {
            $mock = Mockery::mock(PayTabsService::class);
            $mock->shouldReceive('validateIpnSignature')->andReturn(false);
            return $mock;
        });

        $response = $this->postJson('/api/v2/provider-payments/ipn', [
            'tran_ref' => 'SOME-REF',
            'tran_type' => 'sale',
        ], ['Signature' => 'bad-sig']);

        $response->assertStatus(400);
    }

    public function test_ipn_is_idempotent_for_terminal_payments(): void
    {
        $payment = ProviderPayment::create([
            'organization_id' => $this->org->id,
            'purpose' => 'other',
            'purpose_label' => 'Done',
            'amount' => 100,
            'tax_amount' => 15,
            'total_amount' => 115,
            'currency' => 'SAR',
            'gateway' => 'paytabs',
            'cart_id' => 'WP-DONE-001',
            'tran_ref' => 'TEST-TRAN-DONE-001',
            'status' => ProviderPaymentStatus::Completed,
        ]);

        $response = $this->postJson('/api/v2/provider-payments/ipn', [
            'tran_ref' => 'TEST-TRAN-DONE-001',
            'cart_id' => 'WP-DONE-001',
            'tran_type' => 'sale',
            'payment_result' => ['response_status' => 'A'],
        ], ['Signature' => 'valid-sig']);

        $response->assertOk();

        // Still completed, not double-processed
        $this->assertDatabaseHas('provider_payments', [
            'id' => $payment->id,
            'status' => 'completed',
        ]);
    }

    // ─── Resend Email ──────────────────────────────────────────────

    public function test_resend_email_succeeds_for_completed_payment(): void
    {
        $payment = ProviderPayment::create([
            'organization_id' => $this->org->id,
            'purpose' => 'other',
            'purpose_label' => 'Done',
            'amount' => 100,
            'tax_amount' => 15,
            'total_amount' => 115,
            'currency' => 'SAR',
            'gateway' => 'paytabs',
            'cart_id' => 'WP-RESEND-001',
            'status' => ProviderPaymentStatus::Completed,
        ]);

        $emailMock = Mockery::mock(PaymentEmailService::class);
        $emailMock->shouldReceive('sendPaymentConfirmation')->once()->andReturn(true);
        $emailMock->shouldReceive('sendInvoiceEmail')->andReturn(true)->byDefault();
        $this->app->instance(PaymentEmailService::class, $emailMock);

        $response = $this->withToken($this->token)->postJson("/api/v2/provider-payments/{$payment->id}/resend-email");

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_resend_email_rejected_for_pending_payment(): void
    {
        $payment = ProviderPayment::create([
            'organization_id' => $this->org->id,
            'purpose' => 'other',
            'purpose_label' => 'Pending',
            'amount' => 100,
            'tax_amount' => 15,
            'total_amount' => 115,
            'currency' => 'SAR',
            'gateway' => 'paytabs',
            'cart_id' => 'WP-RESEND-FAIL-001',
            'status' => ProviderPaymentStatus::Pending,
        ]);

        $response = $this->withToken($this->token)->postJson("/api/v2/provider-payments/{$payment->id}/resend-email");

        $response->assertStatus(422);
    }

    public function test_resend_email_rejected_for_other_org_payment(): void
    {
        $otherOrg = Organization::create(['name' => 'Other', 'business_type' => 'grocery', 'country' => 'SA']);
        $payment = ProviderPayment::create([
            'organization_id' => $otherOrg->id,
            'purpose' => 'other',
            'purpose_label' => 'Done',
            'amount' => 100,
            'tax_amount' => 15,
            'total_amount' => 115,
            'currency' => 'SAR',
            'gateway' => 'paytabs',
            'cart_id' => 'WP-RESEND-OO-001',
            'status' => ProviderPaymentStatus::Completed,
        ]);

        $response = $this->withToken($this->token)->postJson("/api/v2/provider-payments/{$payment->id}/resend-email");

        $response->assertNotFound();
    }

    // ─── Statistics ────────────────────────────────────────────────

    public function test_can_get_payment_statistics(): void
    {
        ProviderPayment::create([
            'organization_id' => $this->org->id,
            'purpose' => 'other',
            'purpose_label' => 'Done',
            'amount' => 100,
            'tax_amount' => 15,
            'total_amount' => 115,
            'currency' => 'SAR',
            'gateway' => 'paytabs',
            'cart_id' => 'WP-STAT-001',
            'status' => ProviderPaymentStatus::Completed,
            'confirmation_email_sent' => true,
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v2/provider-payments/statistics');

        $response->assertOk()
            ->assertJsonPath('data.total_payments', 1)
            ->assertJsonPath('data.emails_sent', 1);

        $this->assertGreaterThanOrEqual(115, $response->json('data.total_paid'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
