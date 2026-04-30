<?php

namespace Tests\Feature\PosTerminal;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Register;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTerminalApiTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;
    private User $providerUser;
    private Organization $org;
    private Store $store;
    private Store $store2;
    private string $adminToken;
    private string $providerToken;

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
            'name' => 'Main Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->store2 = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Branch Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => false,
        ]);

        // Admin user (for admin-api guard)
        $this->admin = AdminUser::create([
            'name' => 'Platform Admin',
            'email' => 'admin@wameed.test',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->adminToken = $this->admin->createToken('test', ['*'])->plainTextToken;

        // Provider user (for sanctum guard)
        $this->providerUser = User::create([
            'name' => 'Cashier',
            'email' => 'cashier@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);
        $this->providerToken = $this->providerUser->createToken('test', ['*'])->plainTextToken;
    }

    // ─── Helpers ─────────────────────────────────────────────────

    private function createTerminal(array $overrides = []): Register
    {
        return Register::create(array_merge([
            'store_id' => $this->store->id,
            'name' => 'Terminal ' . uniqid(),
            'device_id' => 'DEV-' . uniqid(),
            'platform' => 'android',
            'app_version' => '1.0.0',
            'is_active' => true,
        ], $overrides));
    }

    private function adminGet(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->adminToken)->getJson("/api/v2/admin/terminals{$uri}");
    }

    private function adminPost(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->adminToken)->postJson("/api/v2/admin/terminals{$uri}", $data);
    }

    private function adminPut(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->adminToken)->putJson("/api/v2/admin/terminals{$uri}", $data);
    }

    private function adminDelete(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->adminToken)->deleteJson("/api/v2/admin/terminals{$uri}");
    }

    // ═══════════════════════════════════════════════════════════════
    //  CRUD Tests
    // ═══════════════════════════════════════════════════════════════

    public function test_admin_can_list_terminals(): void
    {
        $this->createTerminal(['name' => 'Counter 1']);
        $this->createTerminal(['name' => 'Counter 2', 'store_id' => $this->store2->id]);

        $response = $this->adminGet('');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data.data'));
    }

    public function test_admin_can_search_terminals(): void
    {
        $this->createTerminal(['name' => 'Frontend Register']);
        $this->createTerminal(['name' => 'Backend Register']);

        $response = $this->adminGet('?search=Frontend');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('Frontend Register', $response->json('data.data.0.name'));
    }

    public function test_admin_can_filter_by_store(): void
    {
        $this->createTerminal(['store_id' => $this->store->id]);
        $this->createTerminal(['store_id' => $this->store->id]);
        $this->createTerminal(['store_id' => $this->store2->id]);

        $response = $this->adminGet("?store_id={$this->store->id}");

        $response->assertOk();
        $this->assertCount(2, $response->json('data.data'));
    }

    public function test_admin_can_filter_by_softpos_enabled(): void
    {
        $this->createTerminal(['softpos_enabled' => true]);
        $this->createTerminal(['softpos_enabled' => false]);
        $this->createTerminal(['softpos_enabled' => false]);

        $response = $this->adminGet('?softpos_enabled=true');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_admin_can_filter_by_acquirer_source(): void
    {
        $this->createTerminal(['acquirer_source' => 'hala', 'softpos_enabled' => true]);
        $this->createTerminal(['acquirer_source' => 'bank_rajhi', 'softpos_enabled' => true]);

        $response = $this->adminGet('?acquirer_source=hala');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_admin_can_create_terminal(): void
    {
        $response = $this->adminPost('', [
            'store_id' => $this->store->id,
            'name' => 'New Terminal',
            'device_id' => 'DEV-NEW-001',
            'platform' => 'android',
            'app_version' => '2.0.0',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'New Terminal')
            ->assertJsonPath('data.device_id', 'DEV-NEW-001');
    }

    public function test_admin_can_create_terminal_with_softpos(): void
    {
        $response = $this->adminPost('', [
            'store_id' => $this->store->id,
            'name' => 'SoftPOS Terminal',
            'device_id' => 'DEV-SP-001',
            'platform' => 'android',
            'softpos_enabled' => true,
            'nearpay_tid' => 'TID-12345',
            'nearpay_mid' => 'MID-67890',
            'acquirer_source' => 'hala',
            'acquirer_name' => 'HALA Payments',
            'fee_profile' => 'standard',
            'fee_mada_percentage' => 0.0150,
            'fee_visa_mc_percentage' => 0.0200,
            'device_model' => 'Samsung Galaxy A54',
            'os_version' => 'Android 14',
            'nfc_capable' => true,
            'serial_number' => 'SN-ABC123',
            'settlement_cycle' => 'T+1',
            'settlement_bank_name' => 'Al Rajhi Bank',
            'settlement_iban' => 'SA1234567890123456789012',
            'softpos_status' => 'pending',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'SoftPOS Terminal')
            ->assertJsonPath('data.softpos_enabled', true)
            ->assertJsonPath('data.nearpay_tid', 'TID-12345')
            ->assertJsonPath('data.acquirer_source', 'hala')
            ->assertJsonPath('data.acquirer_name', 'HALA Payments')
            ->assertJsonPath('data.fee_profile', 'standard')
            ->assertJsonPath('data.device_model', 'Samsung Galaxy A54')
            ->assertJsonPath('data.nfc_capable', true)
            ->assertJsonPath('data.settlement_bank_name', 'Al Rajhi Bank');
    }

    public function test_admin_create_terminal_requires_store_id(): void
    {
        $response = $this->adminPost('', [
            'name' => 'No Store Terminal',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['store_id']);
    }

    public function test_admin_create_terminal_validates_acquirer_source(): void
    {
        $response = $this->adminPost('', [
            'store_id' => $this->store->id,
            'name' => 'Bad Acquirer',
            'acquirer_source' => 'invalid_acquirer',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['acquirer_source']);
    }

    public function test_admin_create_terminal_validates_fee_profile(): void
    {
        $response = $this->adminPost('', [
            'store_id' => $this->store->id,
            'name' => 'Bad Fee',
            'fee_profile' => 'invalid_profile',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fee_profile']);
    }

    public function test_admin_create_terminal_validates_softpos_status(): void
    {
        $response = $this->adminPost('', [
            'store_id' => $this->store->id,
            'name' => 'Bad Status',
            'softpos_status' => 'invalid_status',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['softpos_status']);
    }

    public function test_admin_create_terminal_validates_fee_range(): void
    {
        $response = $this->adminPost('', [
            'store_id' => $this->store->id,
            'name' => 'Over Fee',
            'fee_mada_percentage' => 1.5, // max is 1
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fee_mada_percentage']);
    }

    public function test_admin_can_show_terminal(): void
    {
        $terminal = $this->createTerminal([
            'name' => 'Show Me',
            'softpos_enabled' => true,
            'nearpay_tid' => 'TID-SHOW',
            'acquirer_source' => 'hala',
        ]);

        $response = $this->adminGet("/{$terminal->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Show Me')
            ->assertJsonPath('data.softpos_enabled', true)
            ->assertJsonPath('data.nearpay_tid', 'TID-SHOW')
            ->assertJsonPath('data.acquirer_source', 'hala');
    }

    public function test_admin_show_includes_store_info(): void
    {
        $terminal = $this->createTerminal();

        $response = $this->adminGet("/{$terminal->id}");

        $response->assertOk()
            ->assertJsonPath('data.store.name', 'Main Store');
    }

    public function test_admin_show_terminal_not_found(): void
    {
        $response = $this->adminGet('/00000000-0000-0000-0000-000000000099');

        $response->assertStatus(404);
    }

    public function test_admin_can_update_terminal(): void
    {
        $terminal = $this->createTerminal(['name' => 'Old Name']);

        $response = $this->adminPut("/{$terminal->id}", [
            'name' => 'New Name',
            'app_version' => '3.0.0',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.app_version', '3.0.0');
    }

    public function test_admin_can_update_terminal_softpos_fields(): void
    {
        $terminal = $this->createTerminal();

        $response = $this->adminPut("/{$terminal->id}", [
            'softpos_enabled' => true,
            'nearpay_tid' => 'TID-UPD-001',
            'nearpay_mid' => 'MID-UPD-001',
            'acquirer_source' => 'bank_rajhi',
            'acquirer_name' => 'Al Rajhi Bank',
            'device_model' => 'Google Pixel 8',
            'os_version' => 'Android 15',
            'nfc_capable' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.softpos_enabled', true)
            ->assertJsonPath('data.nearpay_tid', 'TID-UPD-001')
            ->assertJsonPath('data.acquirer_source', 'bank_rajhi')
            ->assertJsonPath('data.device_model', 'Google Pixel 8')
            ->assertJsonPath('data.nfc_capable', true);
    }

    public function test_admin_can_delete_terminal(): void
    {
        $terminal = $this->createTerminal();

        $response = $this->adminDelete("/{$terminal->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('registers', ['id' => $terminal->id]);
    }

    // ═══════════════════════════════════════════════════════════════
    //  Toggle Status
    // ═══════════════════════════════════════════════════════════════

    public function test_admin_can_toggle_terminal_status_to_inactive(): void
    {
        $terminal = $this->createTerminal(['is_active' => true]);

        $response = $this->adminPost("/{$terminal->id}/toggle-status");

        $response->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_admin_can_toggle_terminal_status_to_active(): void
    {
        $terminal = $this->createTerminal(['is_active' => false]);

        $response = $this->adminPost("/{$terminal->id}/toggle-status");

        $response->assertOk()
            ->assertJsonPath('data.is_active', true);
    }

    // ═══════════════════════════════════════════════════════════════
    //  SoftPOS Lifecycle
    // ═══════════════════════════════════════════════════════════════

    public function test_admin_can_activate_softpos(): void
    {
        $terminal = $this->createTerminal([
            'softpos_enabled' => false,
            'nearpay_tid' => 'TID-ACT-001',
            'acquirer_source' => 'hala',
            'softpos_status' => 'pending',
        ]);

        $response = $this->adminPost("/{$terminal->id}/activate-softpos");

        $response->assertOk()
            ->assertJsonPath('data.softpos_enabled', true)
            ->assertJsonPath('data.softpos_status', 'active');

        $terminal->refresh();
        $this->assertNotNull($terminal->softpos_activated_at);
    }

    public function test_activate_softpos_fails_without_tid(): void
    {
        $terminal = $this->createTerminal([
            'nearpay_tid' => null,
            'acquirer_source' => 'hala',
        ]);

        $response = $this->adminPost("/{$terminal->id}/activate-softpos");

        $response->assertStatus(422);
    }

    public function test_activate_softpos_fails_without_acquirer(): void
    {
        $terminal = $this->createTerminal([
            'nearpay_tid' => 'TID-001',
            'acquirer_source' => null,
        ]);

        $response = $this->adminPost("/{$terminal->id}/activate-softpos");

        $response->assertStatus(422);
    }

    public function test_admin_can_suspend_softpos(): void
    {
        $terminal = $this->createTerminal([
            'softpos_enabled' => true,
            'softpos_status' => 'active',
            'nearpay_tid' => 'TID-SUSP-001',
            'acquirer_source' => 'hala',
        ]);

        $response = $this->adminPost("/{$terminal->id}/suspend-softpos");

        $response->assertOk()
            ->assertJsonPath('data.softpos_status', 'suspended');
    }

    public function test_admin_can_deactivate_softpos(): void
    {
        $terminal = $this->createTerminal([
            'softpos_enabled' => true,
            'softpos_status' => 'active',
            'nearpay_tid' => 'TID-DEACT-001',
            'acquirer_source' => 'hala',
        ]);

        $response = $this->adminPost("/{$terminal->id}/deactivate-softpos");

        $response->assertOk()
            ->assertJsonPath('data.softpos_enabled', false)
            ->assertJsonPath('data.softpos_status', 'deactivated');
    }

    // ═══════════════════════════════════════════════════════════════
    //  Fee Configuration
    // ═══════════════════════════════════════════════════════════════

    public function test_admin_can_update_fees(): void
    {
        $terminal = $this->createTerminal();

        $response = $this->adminPut("/{$terminal->id}/fees", [
            'fee_profile' => 'custom',
            'fee_mada_percentage' => 0.0100,
            'fee_visa_mc_percentage' => 0.0250,
            'fee_flat_per_txn' => 0.50,
            'wameed_margin_percentage' => 0.0050,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.fee_profile', 'custom');

        $terminal->refresh();
        $this->assertEquals('0.0100', $terminal->fee_mada_percentage);
        $this->assertEquals('0.0250', $terminal->fee_visa_mc_percentage);
        $this->assertEquals('0.50', $terminal->fee_flat_per_txn);
        $this->assertEquals('0.0050', $terminal->wameed_margin_percentage);
    }

    public function test_update_fees_validates_range(): void
    {
        $terminal = $this->createTerminal();

        $response = $this->adminPut("/{$terminal->id}/fees", [
            'fee_mada_percentage' => 2.0, // exceeds max 1
        ]);

        $response->assertStatus(422);
    }

    public function test_update_fees_validates_profile(): void
    {
        $terminal = $this->createTerminal();

        $response = $this->adminPut("/{$terminal->id}/fees", [
            'fee_profile' => 'nonexistent_profile',
        ]);

        $response->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════════
    //  Stats
    // ═══════════════════════════════════════════════════════════════

    public function test_admin_can_get_stats(): void
    {
        $this->createTerminal(['is_active' => true, 'is_online' => true, 'softpos_enabled' => true, 'softpos_status' => 'active', 'acquirer_source' => 'hala']);
        $this->createTerminal(['is_active' => true, 'is_online' => false, 'softpos_enabled' => true, 'softpos_status' => 'active', 'acquirer_source' => 'bank_rajhi']);
        $this->createTerminal(['is_active' => false, 'is_online' => false, 'softpos_enabled' => false]);

        $response = $this->adminGet('/stats');

        $response->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.active', 2)
            ->assertJsonPath('data.inactive', 1)
            ->assertJsonPath('data.online', 1)
            ->assertJsonPath('data.softpos_enabled', 2)
            ->assertJsonPath('data.softpos_active', 2);

        $byAcquirer = $response->json('data.by_acquirer');
        $this->assertEquals(1, $byAcquirer['hala']);
        $this->assertEquals(1, $byAcquirer['bank_rajhi']);
    }

    // ═══════════════════════════════════════════════════════════════
    //  Admin Resource Fields
    // ═══════════════════════════════════════════════════════════════

    public function test_admin_resource_includes_all_softpos_fields(): void
    {
        $terminal = $this->createTerminal([
            'softpos_enabled' => true,
            'nearpay_tid' => 'TID-FULL',
            'nearpay_mid' => 'MID-FULL',
            'acquirer_source' => 'hala',
            'acquirer_name' => 'HALA Payments',
            'acquirer_reference' => 'REF-001',
            'device_model' => 'Samsung S24',
            'os_version' => 'Android 14',
            'nfc_capable' => true,
            'serial_number' => 'SN-001',
            'fee_profile' => 'standard',
            'fee_mada_percentage' => 0.0150,
            'fee_visa_mc_percentage' => 0.0200,
            'fee_flat_per_txn' => 0.00,
            'wameed_margin_percentage' => 0.0040,
            'settlement_cycle' => 'T+1',
            'settlement_bank_name' => 'SNB',
            'settlement_iban' => 'SA9999999999999999',
            'softpos_status' => 'active',
            'admin_notes' => 'Test note',
        ]);

        $response = $this->adminGet("/{$terminal->id}");

        $response->assertOk();
        $data = $response->json('data');

        // Core fields
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('store_id', $data);

        // SoftPOS fields
        $this->assertArrayHasKey('softpos_enabled', $data);
        $this->assertArrayHasKey('nearpay_tid', $data);
        $this->assertArrayHasKey('nearpay_mid', $data);
        $this->assertArrayHasKey('acquirer_source', $data);
        $this->assertArrayHasKey('acquirer_name', $data);
        $this->assertArrayHasKey('acquirer_reference', $data);

        // Device
        $this->assertArrayHasKey('device_model', $data);
        $this->assertArrayHasKey('os_version', $data);
        $this->assertArrayHasKey('nfc_capable', $data);
        $this->assertArrayHasKey('serial_number', $data);

        // Fee config
        $this->assertArrayHasKey('fee_profile', $data);
        $this->assertArrayHasKey('fee_mada_percentage', $data);
        $this->assertArrayHasKey('fee_visa_mc_percentage', $data);
        $this->assertArrayHasKey('fee_flat_per_txn', $data);
        $this->assertArrayHasKey('wameed_margin_percentage', $data);

        // Settlement
        $this->assertArrayHasKey('settlement_cycle', $data);
        $this->assertArrayHasKey('settlement_bank_name', $data);
        $this->assertArrayHasKey('settlement_iban', $data);

        // Computed
        $this->assertArrayHasKey('is_softpos_ready', $data);
        $this->assertArrayHasKey('fee_description', $data);

        // Admin notes
        $this->assertArrayHasKey('admin_notes', $data);
    }

    public function test_admin_resource_does_not_expose_auth_key(): void
    {
        $terminal = $this->createTerminal([
            'nearpay_auth_key' => 'secret-key-12345',
        ]);

        $response = $this->adminGet("/{$terminal->id}");

        $response->assertOk();
        $this->assertArrayNotHasKey('nearpay_auth_key', $response->json('data'));
    }

    public function test_is_softpos_ready_computed_correctly(): void
    {
        $terminal = $this->createTerminal([
            'softpos_enabled' => true,
            'nearpay_tid' => 'TID-READY',
            'acquirer_source' => 'hala',
            'softpos_status' => 'active',
        ]);

        $response = $this->adminGet("/{$terminal->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_softpos_ready', true);
    }

    public function test_is_softpos_ready_false_when_missing_tid(): void
    {
        $terminal = $this->createTerminal([
            'softpos_enabled' => true,
            'nearpay_tid' => null,
            'acquirer_source' => 'hala',
            'softpos_status' => 'active',
        ]);

        $response = $this->adminGet("/{$terminal->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_softpos_ready', false);
    }

    // ═══════════════════════════════════════════════════════════════
    //  Provider Resource — does NOT expose SoftPOS internals
    // ═══════════════════════════════════════════════════════════════

    public function test_provider_resource_hides_softpos_details(): void
    {
        $terminal = $this->createTerminal([
            'softpos_enabled' => true,
            'nearpay_tid' => 'TID-HIDDEN',
            'nearpay_mid' => 'MID-HIDDEN',
            'acquirer_source' => 'hala',
            'fee_mada_percentage' => 0.0150,
            'settlement_iban' => 'SA9999999999999999',
        ]);

        $response = $this->withToken($this->providerToken)
            ->getJson("/api/v2/pos/terminals/{$terminal->id}");

        $response->assertOk();
        $data = $response->json('data');

        // Provider CAN see basic SoftPOS info
        $this->assertArrayHasKey('softpos_enabled', $data);
        $this->assertArrayHasKey('softpos_status', $data);

        // Provider CANNOT see sensitive SoftPOS details
        $this->assertArrayNotHasKey('nearpay_tid', $data);
        $this->assertArrayNotHasKey('nearpay_mid', $data);
        $this->assertArrayNotHasKey('nearpay_auth_key', $data);
        $this->assertArrayNotHasKey('acquirer_source', $data);
        $this->assertArrayNotHasKey('fee_mada_percentage', $data);
        $this->assertArrayNotHasKey('settlement_iban', $data);
        $this->assertArrayNotHasKey('admin_notes', $data);
    }

    // ═══════════════════════════════════════════════════════════════
    //  Authentication
    // ═══════════════════════════════════════════════════════════════

    public function test_admin_routes_require_admin_auth(): void
    {
        $this->getJson('/api/v2/admin/terminals')->assertStatus(401);
        $this->postJson('/api/v2/admin/terminals')->assertStatus(401);
        $this->getJson('/api/v2/admin/terminals/stats')->assertStatus(401);
    }

    public function test_provider_token_cannot_access_admin_routes(): void
    {
        $response = $this->withToken($this->providerToken)
            ->getJson('/api/v2/admin/terminals');

        $response->assertStatus(401);
    }

    public function test_admin_token_cannot_access_provider_routes(): void
    {
        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v2/pos/terminals');

        $response->assertStatus(401);
    }

    // ═══════════════════════════════════════════════════════════════
    //  Pagination
    // ═══════════════════════════════════════════════════════════════

    public function test_admin_list_is_paginated(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->createTerminal();
        }

        $response = $this->adminGet('?per_page=10');

        $response->assertOk();
        $this->assertCount(10, $response->json('data.data'));
        $this->assertEquals(25, $response->json('data.total'));
        $this->assertEquals(3, $response->json('data.last_page'));
    }

    public function test_admin_per_page_capped_at_100(): void
    {
        $response = $this->adminGet('?per_page=999');

        $response->assertOk();
        $this->assertEquals(100, $response->json('data.per_page'));
    }
}
