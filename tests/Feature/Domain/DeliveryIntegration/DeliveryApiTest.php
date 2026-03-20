<?php

namespace Tests\Feature\Domain\DeliveryIntegration;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Store $store;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::create([
            'name'          => 'Test Org',
            'business_type' => 'retail',
            'country'       => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $org->id,
            'name'            => 'Test Store',
            'business_type'   => 'retail',
            'currency'        => 'OMR',
        ]);

        $this->owner = User::create([
            'name'            => 'Owner',
            'email'           => 'owner@delivery-test.com',
            'password_hash'   => bcrypt('password'),
            'store_id'        => $this->store->id,
            'organization_id' => $org->id,
            'role'            => 'owner',
            'is_active'       => true,
        ]);

        $this->token = $this->owner->createToken('test', ['*'])->plainTextToken;
    }

    // ─── Authentication ───────────────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v2/delivery/stats')->assertUnauthorized();
        $this->getJson('/api/v2/delivery/configs')->assertUnauthorized();
    }

    // ─── Stats ────────────────────────────────────────────────────

    public function test_can_get_delivery_stats(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/delivery/stats');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    // ─── Configs ──────────────────────────────────────────────────

    public function test_can_list_configs(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/delivery/configs');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_can_save_config(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/delivery/configs', [
                'platform'    => 'jahez',
                'api_key'     => 'test-api-key-123',
                'merchant_id' => 'MERCHANT001',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('delivery_platform_configs', [
            'store_id' => $this->store->id,
            'platform' => 'jahez',
        ]);
    }

    public function test_save_config_validates_platform(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/delivery/configs', [
                'platform' => 'invalid_platform',
                'api_key'  => 'test-key',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['platform']);
    }

    public function test_save_config_requires_platform(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/delivery/configs', [
                'api_key' => 'test-key',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['platform']);
    }

    // ─── Toggle Config ────────────────────────────────────────────

    public function test_can_toggle_config(): void
    {
        $config = DeliveryPlatformConfig::create([
            'store_id'    => $this->store->id,
            'platform'    => 'hungerstation',
            'api_key'     => 'key-123',
            'is_enabled'  => true,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/delivery/configs/{$config->id}/toggle");

        $response->assertOk();

        $config->refresh();
        $this->assertFalse($config->is_enabled);
    }

    public function test_toggle_returns_404_for_invalid_config(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000000';

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/delivery/configs/{$fakeId}/toggle");

        $response->assertNotFound();
    }

    // ─── Orders ───────────────────────────────────────────────────

    public function test_can_list_delivery_orders(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/delivery/orders');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    // ─── Sync Logs ────────────────────────────────────────────────

    public function test_can_list_sync_logs(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/delivery/sync-logs');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }
}
