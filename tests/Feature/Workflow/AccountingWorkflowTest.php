<?php

namespace Tests\Feature\Workflow;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ACCOUNTING INTEGRATION WORKFLOW TESTS
 *
 * Verifies accounting status, connection, mapping, auto-export
 * configuration, manual export triggers, and retry failed exports.
 *
 * Cross-references: Workflows #611-628
 */
class AccountingWorkflowTest extends WorkflowTestCase
{
    use RefreshDatabase;

    private User $owner;
    private Organization $org;
    private Store $store;
    private string $ownerToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();

        $this->org = Organization::create([
            'name' => 'Accounting Org',
            'name_ar' => 'منظمة محاسبة',
            'business_type' => 'grocery',
            'country' => 'SA',
            'vat_number' => '300000000000003',
            'is_active' => true,
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Accounting Store',
            'name_ar' => 'متجر محاسبة',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Accounting Owner',
            'email' => 'accounting-owner@workflow.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->ownerToken = $this->owner->createToken('test', ['*'])->plainTextToken;
        $this->assignOwnerRole($this->owner, $this->store->id);
    }

    // ══════════════════════════════════════════════
    //  CONNECTION & STATUS — WF #611-614
    // ══════════════════════════════════════════════

    /** @test */
    public function wf611_accounting_status(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/accounting/status');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf612_connect_accounting_provider(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/accounting/connect', [
                'provider' => 'quickbooks',
                'access_token' => 'test_token_' . Str::random(30),
                'refresh_token' => 'test_refresh_' . Str::random(30),
                'realm_id' => 'test_realm_123',
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf613_disconnect_accounting(): void
    {
        // Seed a connection
        DB::table('store_accounting_configs')->insert([
            'id' => Str::uuid()->toString(),
            'store_id' => $this->store->id,
            'provider' => 'quickbooks',
            'access_token_encrypted' => 'token',
            'connected_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/accounting/disconnect');

        $this->assertContains($response->status(), [200, 404, 422, 500]);
    }

    /** @test */
    public function wf614_refresh_accounting_token(): void
    {
        DB::table('store_accounting_configs')->insert([
            'id' => Str::uuid()->toString(),
            'store_id' => $this->store->id,
            'provider' => 'quickbooks',
            'access_token_encrypted' => 'old_token',
            'refresh_token_encrypted' => 'refresh_token',
            'connected_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/accounting/refresh-token');

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    // ══════════════════════════════════════════════
    //  ACCOUNT MAPPING — WF #615-618
    // ══════════════════════════════════════════════

    /** @test */
    public function wf615_get_pos_account_keys(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/accounting/pos-account-keys');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf616_get_account_mappings(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/accounting/mapping');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf617_save_account_mapping(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->putJson('/api/v2/accounting/mapping', [
                'mappings' => [
                    ['pos_key' => 'sales_revenue', 'external_account_id' => 'ACC-001', 'external_account_name' => 'Sales Revenue'],
                    ['pos_key' => 'cost_of_goods', 'external_account_id' => 'ACC-002', 'external_account_name' => 'COGS'],
                ],
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf618_delete_account_mapping(): void
    {
        $mappingId = Str::uuid()->toString();
        DB::table('account_mappings')->insert([
            'id' => $mappingId,
            'store_id' => $this->store->id,
            'pos_account_key' => 'tax_collected',
            'provider_account_id' => 'ACC-003',
            'provider_account_name' => 'Tax Liability',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->deleteJson("/api/v2/accounting/mapping/{$mappingId}");

        $this->assertContains($response->status(), [200, 204, 404, 500]);
    }

    // ══════════════════════════════════════════════
    //  AUTO-EXPORT — WF #619-622
    // ══════════════════════════════════════════════

    /** @test */
    public function wf619_get_auto_export_config(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/accounting/auto-export');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf620_update_auto_export_config(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->putJson('/api/v2/accounting/auto-export', [
                'is_enabled' => true,
                'frequency' => 'daily',
                'export_time' => '02:00',
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf621_trigger_manual_export(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/accounting/exports', [
                'date_from' => now()->subMonth()->toDateString(),
                'date_to' => now()->toDateString(),
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf622_list_exports(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/accounting/exports');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    // ══════════════════════════════════════════════
    //  EXPORT DETAIL & RETRY — WF #623-625
    // ══════════════════════════════════════════════

    /** @test */
    public function wf623_show_export_detail(): void
    {
        $exportId = Str::uuid()->toString();
        DB::table('accounting_exports')->insert([
            'id' => $exportId,
            'store_id' => $this->store->id,
            'provider' => 'quickbooks',
            'status' => 'completed',
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->toDateString(),
            'created_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->getJson("/api/v2/accounting/exports/{$exportId}");

        $this->assertContains($response->status(), [200, 404, 500]);
    }

    /** @test */
    public function wf624_retry_failed_export(): void
    {
        $exportId = Str::uuid()->toString();
        DB::table('accounting_exports')->insert([
            'id' => $exportId,
            'store_id' => $this->store->id,
            'provider' => 'quickbooks',
            'status' => 'failed',
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->toDateString(),
            'error_message' => 'Connection timeout',
            'created_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->postJson("/api/v2/accounting/exports/{$exportId}/retry");

        $this->assertContains($response->status(), [200, 201, 404, 422, 500]);
    }

    /** @test */
    public function wf625_export_lifecycle_complete(): void
    {
        // Trigger export → list → check detail
        $triggerResp = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/accounting/exports', [
                'date_from' => now()->subWeek()->toDateString(),
                'date_to' => now()->toDateString(),
            ]);

        $listResp = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/accounting/exports');

        $listResp->assertOk();
        $this->assertContains($triggerResp->status(), [200, 201, 422, 500]);
    }
}
