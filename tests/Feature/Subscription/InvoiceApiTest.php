<?php

namespace Tests\Feature\Subscription;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderSubscription\Models\Invoice;
use App\Domain\ProviderSubscription\Models\InvoiceLineItem;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Store $store;
    private string $token;
    private SubscriptionPlan $plan;
    private StoreSubscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::create([
            'name' => 'Test Organization',
            'business_type' => 'retail',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $org->id,
            'name' => 'Test Store',
            'business_type' => 'retail',
            'currency' => 'OMR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Store Owner',
            'email' => 'owner@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->owner->createToken('test', ['*'])->plainTextToken;

        $this->plan = SubscriptionPlan::create([
            'name' => 'Growth',
            'slug' => 'growth',
            'monthly_price' => 29.99,
            'annual_price' => 299.99,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->subscription = StoreSubscription::create([
            'store_id' => $this->store->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);
    }

    // ─── List Invoices ───────────────────────────────────────────

    public function test_can_list_invoices(): void
    {
        $invoice = Invoice::create([
            'store_subscription_id' => $this->subscription->id,
            'invoice_number' => 'INV-TEST001',
            'amount' => 29.99,
            'tax' => 4.50,
            'total' => 34.49,
            'status' => 'paid',
            'due_date' => now()->addDays(7),
            'paid_at' => now(),
        ]);

        InvoiceLineItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Growth — monthly subscription',
            'quantity' => 1,
            'unit_price' => 29.99,
            'total' => 29.99,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/subscription/invoices');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => ['id', 'invoice_number', 'amount', 'tax', 'total', 'status', 'due_date'],
                    ],
                    'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                ],
            ]);
    }

    public function test_list_invoices_returns_empty_when_none(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/subscription/invoices');

        $response->assertOk();
        $this->assertEmpty($response->json('data.data'));
    }

    public function test_list_invoices_pagination_works(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            Invoice::create([
                'store_subscription_id' => $this->subscription->id,
                'invoice_number' => "INV-PAG{$i}",
                'amount' => 29.99,
                'tax' => 4.50,
                'total' => 34.49,
                'status' => 'pending',
                'due_date' => now()->addDays(7),
            ]);
        }

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/subscription/invoices?per_page=10');

        $response->assertOk();
        $this->assertEquals(10, count($response->json('data.data')));
        $this->assertEquals(25, $response->json('data.meta.total'));
        $this->assertEquals(3, $response->json('data.meta.last_page'));
    }

    public function test_list_invoices_ordered_by_newest_first(): void
    {
        $old = Invoice::create([
            'store_subscription_id' => $this->subscription->id,
            'invoice_number' => 'INV-OLD001',
            'amount' => 10.00,
            'tax' => 1.50,
            'total' => 11.50,
            'status' => 'paid',
            'due_date' => now()->subMonth(),
            'created_at' => now()->subMonth(),
        ]);

        $new = Invoice::create([
            'store_subscription_id' => $this->subscription->id,
            'invoice_number' => 'INV-NEW001',
            'amount' => 29.99,
            'tax' => 4.50,
            'total' => 34.49,
            'status' => 'pending',
            'due_date' => now()->addDays(7),
            'created_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/subscription/invoices');

        $response->assertOk();
        $ids = collect($response->json('data.data'))->pluck('id')->all();
        $this->assertEquals($new->id, $ids[0]);
        $this->assertEquals($old->id, $ids[1]);
    }

    // ─── Show Invoice ────────────────────────────────────────────

    public function test_can_get_single_invoice(): void
    {
        $invoice = Invoice::create([
            'store_subscription_id' => $this->subscription->id,
            'invoice_number' => 'INV-SHOW001',
            'amount' => 29.99,
            'tax' => 4.50,
            'total' => 34.49,
            'status' => 'pending',
            'due_date' => now()->addDays(7),
        ]);

        InvoiceLineItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Growth Plan — monthly',
            'quantity' => 1,
            'unit_price' => 29.99,
            'total' => 29.99,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/subscription/invoices/{$invoice->id}");

        $response->assertOk()
            ->assertJsonPath('data.invoice_number', 'INV-SHOW001')
            ->assertJsonPath('data.total', 34.49)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'invoice_number',
                    'amount',
                    'tax',
                    'total',
                    'status',
                    'due_date',
                    'line_items' => [
                        '*' => ['id', 'description', 'quantity', 'unit_price', 'total'],
                    ],
                ],
            ]);
    }

    public function test_show_invoice_returns_404_for_invalid_id(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/subscription/invoices/00000000-0000-0000-0000-000000000000');

        $response->assertNotFound();
    }

    public function test_show_invoice_includes_line_items(): void
    {
        $invoice = Invoice::create([
            'store_subscription_id' => $this->subscription->id,
            'invoice_number' => 'INV-LINES01',
            'amount' => 34.98,
            'tax' => 5.25,
            'total' => 40.23,
            'status' => 'pending',
            'due_date' => now()->addDays(7),
        ]);

        InvoiceLineItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Growth Plan',
            'quantity' => 1,
            'unit_price' => 29.99,
            'total' => 29.99,
        ]);

        InvoiceLineItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Extra Storage Add-On',
            'quantity' => 1,
            'unit_price' => 4.99,
            'total' => 4.99,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/subscription/invoices/{$invoice->id}");

        $response->assertOk();
        $this->assertCount(2, $response->json('data.line_items'));
    }

    // ─── Invoice generated on subscribe ──────────────────────────

    public function test_subscribing_to_paid_plan_creates_invoice(): void
    {
        // Remove existing subscription first
        $this->subscription->delete();

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/subscribe', [
            'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertCreated();

        // Verify invoice was created
        $this->assertDatabaseHas('invoices', [
            'amount' => 29.99,
            'status' => 'pending',
        ]);

        // Verify line item
        $invoice = Invoice::where('amount', 29.99)->first();
        $this->assertNotNull($invoice);
        $this->assertDatabaseHas('invoice_line_items', [
            'invoice_id' => $invoice->id,
        ]);
    }

    // ─── Auth Guard ──────────────────────────────────────────────

    public function test_unauthenticated_cannot_list_invoices(): void
    {
        $response = $this->getJson('/api/v2/subscription/invoices');
        $response->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_show_invoice(): void
    {
        $response = $this->getJson('/api/v2/subscription/invoices/some-id');
        $response->assertUnauthorized();
    }
}
