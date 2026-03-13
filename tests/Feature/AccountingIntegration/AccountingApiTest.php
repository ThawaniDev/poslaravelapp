<?php

namespace Tests\Feature\AccountingIntegration;

use App\Domain\AccountingIntegration\Models\AccountingExport;
use App\Domain\AccountingIntegration\Models\AccountMapping;
use App\Domain\AccountingIntegration\Models\AutoExportConfig;
use App\Domain\AccountingIntegration\Models\StoreAccountingConfig;
use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AccountingApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;

    private User $otherUser;
    private Organization $otherOrg;
    private Store $otherStore;
    private string $otherToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Accounting Org',
            'business_type' => 'retail',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Main Store',
            'business_type' => 'retail',
            'currency' => 'OMR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Admin',
            'email' => 'admin@accounting.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;

        // Other user for isolation tests
        $this->otherOrg = Organization::create([
            'name' => 'Other Org',
            'business_type' => 'retail',
            'country' => 'OM',
        ]);
        $this->otherStore = Store::create([
            'organization_id' => $this->otherOrg->id,
            'name' => 'Other Store',
            'business_type' => 'retail',
            'currency' => 'OMR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);
        $this->otherUser = User::create([
            'name' => 'Other',
            'email' => 'other@accounting.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->otherStore->id,
            'organization_id' => $this->otherOrg->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $this->otherToken = $this->otherUser->createToken('test', ['*'])->plainTextToken;
    }

    // ════════════════════════════════════════════════════════
    // AUTH
    // ════════════════════════════════════════════════════════

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v2/accounting/status')
            ->assertStatus(401);
    }

    // ════════════════════════════════════════════════════════
    // STATUS
    // ════════════════════════════════════════════════════════

    public function test_status_when_not_connected(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v2/accounting/status');

        $response->assertOk()
            ->assertJsonPath('data.connected', false)
            ->assertJsonPath('data.health', 'disconnected')
            ->assertJsonPath('data.provider', null);
    }

    public function test_status_when_connected_healthy(): void
    {
        StoreAccountingConfig::forceCreate([
            'id' => fake()->uuid(),
            'store_id' => $this->store->id,
            'provider' => 'quickbooks',
            'access_token_encrypted' => 'enc_token',
            'refresh_token_encrypted' => 'enc_refresh',
            'token_expires_at' => Carbon::now()->addDays(30),
            'company_name' => 'Test Corp',
            'connected_at' => Carbon::now()->subDays(5),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v2/accounting/status');

        $response->assertOk()
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.provider', 'quickbooks')
            ->assertJsonPath('data.company_name', 'Test Corp')
            ->assertJsonPath('data.health', 'healthy');
    }

    public function test_status_expired_token_shows_error_health(): void
    {
        StoreAccountingConfig::forceCreate([
            'id' => fake()->uuid(),
            'store_id' => $this->store->id,
            'provider' => 'xero',
            'access_token_encrypted' => 'enc_token',
            'refresh_token_encrypted' => 'enc_refresh',
            'token_expires_at' => Carbon::now()->subHours(1),
            'connected_at' => Carbon::now()->subDays(5),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v2/accounting/status');

        $response->assertOk()
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.health', 'error');
    }

    public function test_status_expiring_soon_shows_warning(): void
    {
        StoreAccountingConfig::forceCreate([
            'id' => fake()->uuid(),
            'store_id' => $this->store->id,
            'provider' => 'qoyod',
            'access_token_encrypted' => 'enc_token',
            'refresh_token_encrypted' => 'enc_refresh',
            'token_expires_at' => Carbon::now()->addMinutes(30),
            'connected_at' => Carbon::now()->subDays(1),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v2/accounting/status');

        $response->assertOk()
            ->assertJsonPath('data.health', 'warning');
    }

    // ════════════════════════════════════════════════════════
    // CONNECT / DISCONNECT
    // ════════════════════════════════════════════════════════

    public function test_connect_provider(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v2/accounting/connect', [
                'provider' => 'quickbooks',
                'access_token' => 'access_tok_123',
                'refresh_token' => 'refresh_tok_456',
                'token_expires_at' => '2026-12-31T23:59:59Z',
                'realm_id' => 'realm_abc',
                'company_name' => 'My Company',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.provider', 'quickbooks')
            ->assertJsonPath('data.company_name', 'My Company')
            ->assertJsonPath('data.realm_id', 'realm_abc');

        $this->assertDatabaseHas('store_accounting_configs', [
            'store_id' => $this->store->id,
            'provider' => 'quickbooks',
        ]);
    }

    public function test_connect_requires_provider(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v2/accounting/connect', [
                'access_token' => 'tok',
                'refresh_token' => 'ref',
                'token_expires_at' => '2026-12-31T23:59:59Z',
            ]);

        $response->assertStatus(422);
    }

    public function test_connect_validates_provider_values(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v2/accounting/connect', [
                'provider' => 'sage',
                'access_token' => 'tok',
                'refresh_token' => 'ref',
                'token_expires_at' => '2026-12-31T23:59:59Z',
            ]);

        $response->assertStatus(422);
    }

    public function test_connect_replaces_existing_connection(): void
    {
        // First connection
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v2/accounting/connect', [
                'provider' => 'quickbooks',
                'access_token' => 'tok1',
                'refresh_token' => 'ref1',
                'token_expires_at' => '2026-12-31T23:59:59Z',
                'company_name' => 'Old Company',
            ]);

        // Second connection (replaces)
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v2/accounting/connect', [
                'provider' => 'xero',
                'access_token' => 'tok2',
                'refresh_token' => 'ref2',
                'token_expires_at' => '2027-06-30T23:59:59Z',
                'company_name' => 'New Company',
            ]);

        $this->assertDatabaseCount('store_accounting_configs', 1);
        $this->assertDatabaseHas('store_accounting_configs', [
            'store_id' => $this->store->id,
            'provider' => 'xero',
            'company_name' => 'New Company',
        ]);
    }

    public function test_disconnect(): void
    {
        StoreAccountingConfig::forceCreate([
            'id' => fake()->uuid(),
            'store_id' => $this->store->id,
            'provider' => 'quickbooks',
            'access_token_encrypted' => 'enc_tok',
            'refresh_token_encrypted' => 'enc_ref',
            'token_expires_at' => Carbon::now()->addDays(30),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v2/accounting/disconnect');

        $response->assertOk()
            ->assertJsonPath('message', 'Accounting provider disconnected');

        $this->assertDatabaseMissing('store_accounting_configs', [
            'store_id' => $this->store->id,
        ]);
    }

    public function test_disconnect_when_not_connected(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v2/accounting/disconnect');

        $response->assertStatus(404);
    }

    public function test_disconnect_store_isolation(): void
    {
        // Connect other store
        StoreAccountingConfig::forceCreate([
            'id' => fake()->uuid(),
            'store_id' => $this->otherStore->id,
            'provider' => 'quickbooks',
            'access_token_encrypted' => 'enc_tok',
            'refresh_token_encrypted' => 'enc_ref',
            'token_expires_at' => Carbon::now()->addDays(30),
        ]);

        // Try to disconnect from main user's perspective
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v2/accounting/disconnect');

        $response->assertStatus(404);

        // Other store's config should still exist
        $this->assertDatabaseHas('store_accounting_configs', [
            'store_id' => $this->otherStore->id,
        ]);
    }

    // ════════════════════════════════════════════════════════
    // REFRESH TOKEN
    // ════════════════════════════════════════════════════════

    public function test_refresh_token(): void
    {
        StoreAccountingConfig::forceCreate([
            'id' => fake()->uuid(),
            'store_id' => $this->store->id,
            'provider' => 'quickbooks',
            'access_token_encrypted' => 'old_token',
            'refresh_token_encrypted' => 'old_refresh',
            'token_expires_at' => Carbon::now()->subHours(1),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v2/accounting/refresh-token', [
                'access_token' => 'new_access',
                'refresh_token' => 'new_refresh',
                'token_expires_at' => '2027-01-01T00:00:00Z',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.provider', 'quickbooks');

        $this->assertDatabaseHas('store_accounting_configs', [
            'store_id' => $this->store->id,
            'access_token_encrypted' => 'new_access',
        ]);
    }

    public function test_refresh_token_not_connected(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v2/accounting/refresh-token', [
                'access_token' => 'new_access',
                'token_expires_at' => '2027-01-01T00:00:00Z',
            ]);

        $response->assertStatus(404);
    }

    // ════════════════════════════════════════════════════════
    // ACCOUNT MAPPING
    // ════════════════════════════════════════════════════════

    public function test_get_mappings_empty(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v2/accounting/mapping');

        $response->assertOk()
            ->assertJsonPath('data.mappings', [])
            ->assertJsonStructure(['data' => ['mappings', 'pos_account_keys']]);
    }

    public function test_get_mappings_includes_pos_account_keys(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v2/accounting/mapping');

        $response->assertOk();
        $keys = $response->json('data.pos_account_keys');
        $this->assertArrayHasKey('sales_revenue', $keys);
        $this->assertArrayHasKey('cash_received', $keys);
        $this->assertArrayHasKey('vat_collected', $keys);
        $this->assertTrue($keys['sales_revenue']['required']);
    }

    public function test_save_mappings(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson('/api/v2/accounting/mapping', [
                'mappings' => [
                    [
                        'pos_account_key' => 'sales_revenue',
                        'provider_account_id' => 'qb:100',
                        'provider_account_name' => 'Sales Income',
                    ],
                    [
                        'pos_account_key' => 'cash_received',
                        'provider_account_id' => 'qb:200',
                        'provider_account_name' => 'Cash Account',
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Account mappings saved');

        $this->assertDatabaseHas('account_mappings', [
            'store_id' => $this->store->id,
            'pos_account_key' => 'sales_revenue',
            'provider_account_id' => 'qb:100',
        ]);
    }

    public function test_save_mappings_upsert(): void
    {
        // Create initial mapping
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson('/api/v2/accounting/mapping', [
                'mappings' => [
                    [
                        'pos_account_key' => 'sales_revenue',
                        'provider_account_id' => 'qb:100',
                        'provider_account_name' => 'Old Name',
                    ],
                ],
            ]);

        // Update same key
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson('/api/v2/accounting/mapping', [
                'mappings' => [
                    [
                        'pos_account_key' => 'sales_revenue',
                        'provider_account_id' => 'qb:101',
                        'provider_account_name' => 'New Name',
                    ],
                ],
            ]);

        // Should only have one mapping for this key
        $count = AccountMapping::where('store_id', $this->store->id)
            ->where('pos_account_key', 'sales_revenue')
            ->count();
        $this->assertEquals(1, $count);

        $this->assertDatabaseHas('account_mappings', [
            'store_id' => $this->store->id,
            'pos_account_key' => 'sales_revenue',
            'provider_account_id' => 'qb:101',
            'provider_account_name' => 'New Name',
        ]);
    }

    public function test_save_mappings_requires_array(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson('/api/v2/accounting/mapping', []);

        $response->assertStatus(422);
    }

    public function test_delete_mapping(): void
    {
        $mapping = AccountMapping::forceCreate([
            'id' => fake()->uuid(),
            'store_id' => $this->store->id,
            'pos_account_key' => 'sales_revenue',
            'provider_account_id' => 'qb:100',
            'provider_account_name' => 'Sales',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/v2/accounting/mapping/{$mapping->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Account mapping deleted');

        $this->assertDatabaseMissing('account_mappings', ['id' => $mapping->id]);
    }

    public function test_delete_mapping_store_isolation(): void
    {
        $mapping = AccountMapping::forceCreate([
            'id' => fake()->uuid(),
            'store_id' => $this->otherStore->id,
            'pos_account_key' => 'sales_revenue',
            'provider_account_id' => 'qb:100',
            'provider_account_name' => 'Sales',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/v2/accounting/mapping/{$mapping->id}");

        $response->assertStatus(404);
    }

    // ════════════════════════════════════════════════════════
    // EXPORTS
    // ════════════════════════════════════════════════════════

    public function test_trigger_export(): void
    {
        StoreAccountingConfig::forceCreate([
            'id' => fake()->uuid(),
            'store_id' => $this->store->id,
            'provider' => 'quickbooks',
            'access_token_encrypted' => 'tok',
            'refresh_token_encrypted' => 'ref',
            'token_expires_at' => Carbon::now()->addDays(30),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v2/accounting/exports', [
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-07',
                'export_types' => ['daily_summary', 'payment_breakdown'],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.triggered_by', 'manual')
            ->assertJsonPath('data.provider', 'quickbooks');
    }

    public function test_trigger_export_requires_dates(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v2/accounting/exports', []);

        $response->assertStatus(422);
    }

    public function test_trigger_export_validates_end_after_start(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v2/accounting/exports', [
                'start_date' => '2026-03-07',
                'end_date' => '2026-03-01',
            ]);

        $response->assertStatus(422);
    }

    public function test_trigger_export_validates_export_types(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v2/accounting/exports', [
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-07',
                'export_types' => ['invalid_type'],
            ]);

        $response->assertStatus(422);
    }

    public function test_list_exports_empty(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v2/accounting/exports');

        $response->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_list_exports_newest_first(): void
    {
        AccountingExport::forceCreate([
            'id' => fake()->uuid(),
            'store_id' => $this->store->id,
            'provider' => 'quickbooks',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-01',
            'status' => 'success',
            'triggered_by' => 'manual',
            'created_at' => Carbon::now()->subDays(2),
        ]);
        AccountingExport::forceCreate([
            'id' => fake()->uuid(),
            'store_id' => $this->store->id,
            'provider' => 'quickbooks',
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-02',
            'status' => 'pending',
            'triggered_by' => 'scheduled',
            'created_at' => Carbon::now()->subDays(1),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v2/accounting/exports');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);
        // Newest first
        $this->assertEquals('2026-03-02', $data[0]['start_date']);
    }

    public function test_list_exports_filter_by_status(): void
    {
        AccountingExport::forceCreate([
            'id' => fake()->uuid(),
            'store_id' => $this->store->id,
            'provider' => 'quickbooks',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-01',
            'status' => 'success',
            'triggered_by' => 'manual',
            'created_at' => Carbon::now(),
        ]);
        AccountingExport::forceCreate([
            'id' => fake()->uuid(),
            'store_id' => $this->store->id,
            'provider' => 'quickbooks',
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-02',
            'status' => 'failed',
            'triggered_by' => 'manual',
            'created_at' => Carbon::now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v2/accounting/exports?status=failed');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
    }

    public function test_list_exports_limit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            AccountingExport::forceCreate([
                'id' => fake()->uuid(),
                'store_id' => $this->store->id,
                'provider' => 'quickbooks',
                'start_date' => '2026-03-0' . ($i + 1),
                'end_date' => '2026-03-0' . ($i + 1),
                'status' => 'success',
                'triggered_by' => 'manual',
                'created_at' => Carbon::now()->subDays($i),
            ]);
        }

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v2/accounting/exports?limit=3');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_list_exports_store_isolation(): void
    {
        AccountingExport::forceCreate([
            'id' => fake()->uuid(),
            'store_id' => $this->otherStore->id,
            'provider' => 'xero',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-01',
            'status' => 'success',
            'triggered_by' => 'manual',
            'created_at' => Carbon::now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v2/accounting/exports');

        $response->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_get_single_export(): void
    {
        $export = AccountingExport::forceCreate([
            'id' => fake()->uuid(),
            'store_id' => $this->store->id,
            'provider' => 'quickbooks',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-07',
            'export_types' => json_encode(['daily_summary']),
            'status' => 'success',
            'entries_count' => 15,
            'triggered_by' => 'manual',
            'created_at' => Carbon::now(),
            'completed_at' => Carbon::now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v2/accounting/exports/{$export->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $export->id)
            ->assertJsonPath('data.provider', 'quickbooks')
            ->assertJsonPath('data.entries_count', 15);
    }

    public function test_get_export_not_found(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v2/accounting/exports/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_get_export_store_isolation(): void
    {
        $export = AccountingExport::forceCreate([
            'id' => fake()->uuid(),
            'store_id' => $this->otherStore->id,
            'provider' => 'xero',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-01',
            'status' => 'success',
            'triggered_by' => 'manual',
            'created_at' => Carbon::now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v2/accounting/exports/{$export->id}");

        $response->assertStatus(404);
    }

    public function test_retry_failed_export(): void
    {
        $export = AccountingExport::forceCreate([
            'id' => fake()->uuid(),
            'store_id' => $this->store->id,
            'provider' => 'quickbooks',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-07',
            'export_types' => json_encode(['daily_summary']),
            'status' => 'failed',
            'error_message' => 'API timeout',
            'triggered_by' => 'manual',
            'created_at' => Carbon::now()->subHours(1),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v2/accounting/exports/{$export->id}/retry");

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.provider', 'quickbooks');

        // Should now have 2 exports
        $this->assertEquals(2, AccountingExport::where('store_id', $this->store->id)->count());
    }

    public function test_retry_non_failed_export_returns_404(): void
    {
        $export = AccountingExport::forceCreate([
            'id' => fake()->uuid(),
            'store_id' => $this->store->id,
            'provider' => 'quickbooks',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-01',
            'status' => 'success',
            'triggered_by' => 'manual',
            'created_at' => Carbon::now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v2/accounting/exports/{$export->id}/retry");

        $response->assertStatus(404);
    }

    // ════════════════════════════════════════════════════════
    // AUTO-EXPORT CONFIG
    // ════════════════════════════════════════════════════════

    public function test_get_auto_export_defaults(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v2/accounting/auto-export');

        $response->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.frequency', 'daily')
            ->assertJsonPath('data.time', '23:00')
            ->assertJsonPath('data.retry_on_failure', true);
    }

    public function test_update_auto_export(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson('/api/v2/accounting/auto-export', [
                'enabled' => true,
                'frequency' => 'weekly',
                'day_of_week' => 1,
                'time' => '22:00',
                'export_types' => ['daily_summary', 'payment_breakdown'],
                'notify_email' => 'admin@store.com',
                'retry_on_failure' => false,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.frequency', 'weekly')
            ->assertJsonPath('data.day_of_week', 1)
            ->assertJsonPath('data.time', '22:00')
            ->assertJsonPath('data.notify_email', 'admin@store.com')
            ->assertJsonPath('data.retry_on_failure', false);

        $this->assertDatabaseHas('auto_export_configs', [
            'store_id' => $this->store->id,
            'frequency' => 'weekly',
        ]);
    }

    public function test_update_auto_export_persists(): void
    {
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson('/api/v2/accounting/auto-export', [
                'enabled' => true,
                'frequency' => 'monthly',
                'day_of_month' => 15,
                'time' => '02:00',
            ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v2/accounting/auto-export');

        $response->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.frequency', 'monthly')
            ->assertJsonPath('data.day_of_month', 15)
            ->assertJsonPath('data.time', '02:00');
    }

    public function test_update_auto_export_validates_frequency(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson('/api/v2/accounting/auto-export', [
                'frequency' => 'hourly',
            ]);

        $response->assertStatus(422);
    }

    public function test_update_auto_export_validates_email(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson('/api/v2/accounting/auto-export', [
                'notify_email' => 'not-an-email',
            ]);

        $response->assertStatus(422);
    }

    public function test_auto_export_store_isolation(): void
    {
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson('/api/v2/accounting/auto-export', [
                'enabled' => true,
                'frequency' => 'weekly',
            ]);

        $this->app['auth']->forgetGuards();

        $response = $this->withHeader('Authorization', "Bearer {$this->otherToken}")
            ->getJson('/api/v2/accounting/auto-export');

        $response->assertOk()
            ->assertJsonPath('data.enabled', false);
    }

    // ════════════════════════════════════════════════════════
    // POS ACCOUNT KEYS
    // ════════════════════════════════════════════════════════

    public function test_pos_account_keys(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v2/accounting/pos-account-keys');

        $response->assertOk();
        $keys = $response->json('data');
        $this->assertArrayHasKey('sales_revenue', $keys);
        $this->assertArrayHasKey('cogs', $keys);
        $this->assertArrayHasKey('tips_collected', $keys);
        $this->assertCount(13, $keys);
    }

    // ════════════════════════════════════════════════════════
    // FULL WORKFLOW
    // ════════════════════════════════════════════════════════

    public function test_full_accounting_workflow(): void
    {
        // 1. Status — not connected
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v2/accounting/status')
            ->assertJsonPath('data.connected', false);

        // 2. Connect
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v2/accounting/connect', [
                'provider' => 'quickbooks',
                'access_token' => 'tok',
                'refresh_token' => 'ref',
                'token_expires_at' => '2027-01-01T00:00:00Z',
                'realm_id' => 'realm1',
                'company_name' => 'Test Corp',
            ])->assertStatus(201);

        // 3. Status — connected
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v2/accounting/status')
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.provider', 'quickbooks');

        // 4. Save mappings
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson('/api/v2/accounting/mapping', [
                'mappings' => [
                    ['pos_account_key' => 'sales_revenue', 'provider_account_id' => 'qb:1', 'provider_account_name' => 'Sales'],
                    ['pos_account_key' => 'cash_received', 'provider_account_id' => 'qb:2', 'provider_account_name' => 'Cash'],
                ],
            ])->assertOk();

        // 5. Trigger export
        $exportResponse = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v2/accounting/exports', [
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-07',
                'export_types' => ['daily_summary'],
            ]);
        $exportResponse->assertStatus(201);
        $exportId = $exportResponse->json('data.id');

        // 6. Get export detail
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v2/accounting/exports/{$exportId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        // 7. Configure auto-export
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson('/api/v2/accounting/auto-export', [
                'enabled' => true,
                'frequency' => 'daily',
                'time' => '23:00',
                'export_types' => ['daily_summary'],
                'notify_email' => 'admin@test.com',
            ])->assertOk();

        // 8. Disconnect
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v2/accounting/disconnect')
            ->assertOk();

        // 9. Status — disconnected
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v2/accounting/status')
            ->assertJsonPath('data.connected', false);
    }
}
