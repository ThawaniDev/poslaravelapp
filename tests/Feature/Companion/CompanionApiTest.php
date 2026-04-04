<?php

namespace Tests\Feature\Companion;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Test Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Main Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Owner',
            'email' => 'owner@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;
    }

    // ─── Dashboard ───────────────────────────────────────────

    public function test_can_get_dashboard(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'store' => ['id', 'name', 'currency', 'is_active'],
                    'today' => ['revenue', 'orders', 'average_order'],
                    'comparison' => ['yesterday_revenue', 'revenue_change_percent'],
                    'quick_stats',
                ],
            ]);
    }

    // ─── Branches ────────────────────────────────────────────

    public function test_can_get_branches(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/branches');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    // ─── Sales Summary ──────────────────────────────────────

    public function test_can_get_sales_summary(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/sales/summary?period=today');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'period' => ['from', 'to'],
                    'summary' => [
                        'total_orders',
                        'total_revenue',
                        'total_tax',
                        'total_discount',
                        'average_order',
                    ],
                    'daily_breakdown',
                ],
            ]);
    }

    public function test_sales_summary_accepts_period_param(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/sales/summary?period=week');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'period' => ['from', 'to'],
                ],
            ]);
    }

    // ─── Active Orders ──────────────────────────────────────

    public function test_can_get_active_orders(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/orders/active');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['orders', 'total'],
            ]);
    }

    // ─── Inventory Alerts ───────────────────────────────────

    public function test_can_get_inventory_alerts(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/inventory/alerts');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['low_stock_items', 'total_low_stock', 'total_out_of_stock'],
            ]);
    }

    // ─── Active Staff ───────────────────────────────────────

    public function test_can_get_active_staff(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/staff/active');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['staff', 'total', 'clocked_in'],
            ]);
    }

    // ─── Store Availability ─────────────────────────────────

    public function test_can_toggle_store_availability(): void
    {
        $response = $this->withToken($this->token)
            ->putJson('/api/v2/companion/store/availability', [
                'is_active' => false,
            ]);

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    // ─── Auth ───────────────────────────────────────────────

    public function test_companion_endpoints_require_auth(): void
    {
        $this->getJson('/api/v2/companion/dashboard')->assertUnauthorized();
        $this->getJson('/api/v2/companion/branches')->assertUnauthorized();
        $this->getJson('/api/v2/companion/sales/summary')->assertUnauthorized();
        $this->getJson('/api/v2/companion/orders/active')->assertUnauthorized();
        $this->getJson('/api/v2/companion/inventory/alerts')->assertUnauthorized();
        $this->getJson('/api/v2/companion/staff/active')->assertUnauthorized();
    }
}
