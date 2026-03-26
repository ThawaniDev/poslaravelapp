<?php

namespace Tests\Feature\BackupSync;

use App\Domain\Auth\Models\User;
use App\Domain\BackupSync\Enums\SyncConflictResolution;
use App\Domain\BackupSync\Enums\SyncDirection;
use App\Domain\BackupSync\Enums\SyncLogStatus;
use App\Domain\BackupSync\Models\SyncConflict;
use App\Domain\BackupSync\Models\SyncLog;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SyncApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Store $store;
    private string $token;
    private string $terminalId;

    protected function setUp(): void
    {
        parent::setUp();

        // Create sync tables for SQLite (migration uses raw PostgreSQL)
        if (\DB::getDriverName() === 'sqlite') {
            if (!\Schema::hasTable('sync_log')) {
                \Schema::create('sync_log', function ($table) {
                    $table->uuid('id')->primary();
                    $table->uuid('store_id');
                    $table->uuid('terminal_id');
                    $table->string('direction', 10);
                    $table->integer('records_count')->default(0);
                    $table->integer('duration_ms')->default(0);
                    $table->string('status', 20);
                    $table->text('error_message')->nullable();
                    $table->timestamp('started_at')->nullable();
                    $table->timestamp('completed_at')->nullable();
                });
            }

            if (!\Schema::hasTable('sync_conflicts')) {
                \Schema::create('sync_conflicts', function ($table) {
                    $table->uuid('id')->primary();
                    $table->uuid('store_id');
                    $table->string('table_name', 100);
                    $table->uuid('record_id');
                    $table->json('local_data');
                    $table->json('cloud_data');
                    $table->string('resolution', 20)->nullable();
                    $table->uuid('resolved_by')->nullable();
                    $table->timestamp('detected_at')->nullable();
                    $table->timestamp('resolved_at')->nullable();
                });
            }
        }

        $org = Organization::create([
            'name' => 'Sync Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $org->id,
            'name' => 'Sync Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Sync User',
            'email' => 'sync@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;
        $this->terminalId = Str::uuid()->toString();
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    // ═══════════════════════════════════════════════════════════
    // Authentication
    // ═══════════════════════════════════════════════════════════

    public function test_push_requires_authentication(): void
    {
        $response = $this->postJson('/api/v2/sync/push', []);
        $response->assertStatus(401);
    }

    public function test_pull_requires_authentication(): void
    {
        $response = $this->getJson('/api/v2/sync/pull');
        $response->assertStatus(401);
    }

    public function test_status_requires_authentication(): void
    {
        $response = $this->getJson('/api/v2/sync/status');
        $response->assertStatus(401);
    }

    // ═══════════════════════════════════════════════════════════
    // Push
    // ═══════════════════════════════════════════════════════════

    public function test_push_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v2/sync/push', [], $this->authHeaders());
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['terminal_id', 'changes']);
    }

    public function test_push_validates_changes_structure(): void
    {
        $response = $this->postJson('/api/v2/sync/push', [
            'terminal_id' => $this->terminalId,
            'changes' => [
                ['records' => []], // missing 'table'
            ],
        ], $this->authHeaders());
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['changes.0.table']);
    }

    public function test_push_success_without_conflicts(): void
    {
        $recordId = Str::uuid()->toString();
        $response = $this->postJson('/api/v2/sync/push', [
            'terminal_id' => $this->terminalId,
            'changes' => [
                [
                    'table' => 'products',
                    'records' => [
                        ['id' => $recordId, 'name' => 'Test Product', 'price' => 10.00],
                    ],
                ],
            ],
        ], $this->authHeaders());

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'sync_log_id',
                'records_synced',
                'conflicts_count',
                'conflicts',
                'sync_token',
                'server_timestamp',
            ],
        ]);
        $response->assertJsonPath('data.records_synced', 1);
        $response->assertJsonPath('data.conflicts_count', 0);

        $this->assertDatabaseHas('sync_log', [
            'store_id' => $this->store->id,
            'terminal_id' => $this->terminalId,
            'direction' => 'push',
            'records_count' => 1,
            'status' => 'success',
        ]);
    }

    public function test_push_detects_conflicts(): void
    {
        $recordId = Str::uuid()->toString();
        $response = $this->postJson('/api/v2/sync/push', [
            'terminal_id' => $this->terminalId,
            'changes' => [
                [
                    'table' => 'products',
                    'records' => [
                        [
                            'id' => $recordId,
                            'name' => 'Local Version',
                            '_conflict' => true,
                            '_cloud_data' => ['name' => 'Cloud Version'],
                        ],
                    ],
                ],
            ],
        ], $this->authHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.conflicts_count', 1);
        $response->assertJsonPath('data.records_synced', 0);

        $this->assertDatabaseHas('sync_conflicts', [
            'store_id' => $this->store->id,
            'table_name' => 'products',
            'record_id' => $recordId,
        ]);
    }

    public function test_push_multiple_tables(): void
    {
        $response = $this->postJson('/api/v2/sync/push', [
            'terminal_id' => $this->terminalId,
            'changes' => [
                [
                    'table' => 'products',
                    'records' => [
                        ['id' => Str::uuid()->toString(), 'name' => 'P1'],
                        ['id' => Str::uuid()->toString(), 'name' => 'P2'],
                    ],
                ],
                [
                    'table' => 'customers',
                    'records' => [
                        ['id' => Str::uuid()->toString(), 'name' => 'C1'],
                    ],
                ],
            ],
        ], $this->authHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.records_synced', 3);
    }

    // ═══════════════════════════════════════════════════════════
    // Pull
    // ═══════════════════════════════════════════════════════════

    public function test_pull_validates_terminal_id(): void
    {
        $response = $this->getJson('/api/v2/sync/pull?' . http_build_query([
            'tables' => ['products'],
        ]), $this->authHeaders());
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['terminal_id']);
    }

    public function test_pull_success(): void
    {
        $response = $this->getJson('/api/v2/sync/pull?' . http_build_query([
            'terminal_id' => $this->terminalId,
            'tables' => ['products', 'customers'],
        ]), $this->authHeaders());

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'sync_log_id',
                'changes',
                'records_count',
                'sync_token',
                'server_timestamp',
            ],
        ]);

        $this->assertDatabaseHas('sync_log', [
            'store_id' => $this->store->id,
            'direction' => 'pull',
            'status' => 'success',
        ]);
    }

    public function test_pull_with_sync_token(): void
    {
        $response = $this->getJson('/api/v2/sync/pull?' . http_build_query([
            'terminal_id' => $this->terminalId,
            'sync_token' => Str::uuid()->toString(),
            'tables' => ['products'],
        ]), $this->authHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.records_count', 0);
    }

    // ═══════════════════════════════════════════════════════════
    // Full Sync
    // ═══════════════════════════════════════════════════════════

    public function test_full_sync_requires_terminal_id(): void
    {
        $response = $this->getJson('/api/v2/sync/full', $this->authHeaders());
        $response->assertStatus(422);
    }

    public function test_full_sync_success(): void
    {
        $response = $this->getJson('/api/v2/sync/full?terminal_id=' . $this->terminalId, $this->authHeaders());

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'sync_log_id',
                'data' => [
                    'transactions',
                    'inventory',
                    'catalog',
                    'customers',
                    'settings',
                ],
                'records_count',
                'sync_token',
                'server_timestamp',
            ],
        ]);

        $this->assertDatabaseHas('sync_log', [
            'store_id' => $this->store->id,
            'direction' => 'full',
            'status' => 'success',
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // Status
    // ═══════════════════════════════════════════════════════════

    public function test_status_empty(): void
    {
        $response = $this->getJson('/api/v2/sync/status', $this->authHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.server_online', true);
        $response->assertJsonPath('data.last_sync', null);
        $response->assertJsonPath('data.pending_conflicts', 0);
        $response->assertJsonPath('data.failed_syncs_24h', 0);
    }

    public function test_status_with_sync_history(): void
    {
        // Create some sync logs
        SyncLog::create([
            'store_id' => $this->store->id,
            'terminal_id' => $this->terminalId,
            'direction' => SyncDirection::Push,
            'records_count' => 10,
            'duration_ms' => 200,
            'status' => SyncLogStatus::Success,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now()->subMinutes(5),
        ]);

        SyncLog::create([
            'store_id' => $this->store->id,
            'terminal_id' => $this->terminalId,
            'direction' => SyncDirection::Pull,
            'records_count' => 0,
            'duration_ms' => 50,
            'status' => SyncLogStatus::Failed,
            'error_message' => 'Timeout',
            'started_at' => now()->subMinutes(2),
            'completed_at' => now()->subMinutes(2),
        ]);

        $response = $this->getJson('/api/v2/sync/status', $this->authHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.server_online', true);
        $response->assertJsonPath('data.failed_syncs_24h', 1);
        $this->assertNotNull($response->json('data.last_sync'));
        $this->assertCount(2, $response->json('data.recent_logs'));
    }

    // ═══════════════════════════════════════════════════════════
    // Conflicts
    // ═══════════════════════════════════════════════════════════

    public function test_list_conflicts_empty(): void
    {
        $response = $this->getJson('/api/v2/sync/conflicts', $this->authHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.conflicts', []);
        $response->assertJsonPath('data.pagination.total', 0);
    }

    public function test_list_conflicts_with_data(): void
    {
        SyncConflict::create([
            'store_id' => $this->store->id,
            'table_name' => 'products',
            'record_id' => Str::uuid()->toString(),
            'local_data' => ['name' => 'Local'],
            'cloud_data' => ['name' => 'Cloud'],
            'detected_at' => now(),
        ]);

        SyncConflict::create([
            'store_id' => $this->store->id,
            'table_name' => 'customers',
            'record_id' => Str::uuid()->toString(),
            'local_data' => ['phone' => '123'],
            'cloud_data' => ['phone' => '456'],
            'detected_at' => now(),
        ]);

        $response = $this->getJson('/api/v2/sync/conflicts', $this->authHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.pagination.total', 2);
    }

    public function test_list_conflicts_filter_unresolved(): void
    {
        SyncConflict::create([
            'store_id' => $this->store->id,
            'table_name' => 'products',
            'record_id' => Str::uuid()->toString(),
            'local_data' => ['a' => 1],
            'cloud_data' => ['a' => 2],
            'detected_at' => now(),
        ]);

        SyncConflict::create([
            'store_id' => $this->store->id,
            'table_name' => 'products',
            'record_id' => Str::uuid()->toString(),
            'local_data' => ['b' => 1],
            'cloud_data' => ['b' => 2],
            'resolution' => SyncConflictResolution::CloudWins,
            'resolved_by' => $this->user->id,
            'detected_at' => now()->subHour(),
            'resolved_at' => now(),
        ]);

        $response = $this->getJson('/api/v2/sync/conflicts?status=unresolved', $this->authHeaders());
        $response->assertOk();
        $response->assertJsonPath('data.pagination.total', 1);
    }

    public function test_list_conflicts_filter_by_table(): void
    {
        SyncConflict::create([
            'store_id' => $this->store->id,
            'table_name' => 'products',
            'record_id' => Str::uuid()->toString(),
            'local_data' => [],
            'cloud_data' => [],
            'detected_at' => now(),
        ]);

        SyncConflict::create([
            'store_id' => $this->store->id,
            'table_name' => 'customers',
            'record_id' => Str::uuid()->toString(),
            'local_data' => [],
            'cloud_data' => [],
            'detected_at' => now(),
        ]);

        $response = $this->getJson('/api/v2/sync/conflicts?table_name=products', $this->authHeaders());
        $response->assertOk();
        $response->assertJsonPath('data.pagination.total', 1);
    }

    // ═══════════════════════════════════════════════════════════
    // Resolve Conflict
    // ═══════════════════════════════════════════════════════════

    public function test_resolve_conflict_not_found(): void
    {
        $fakeId = Str::uuid()->toString();
        $response = $this->postJson("/api/v2/sync/resolve-conflict/{$fakeId}", [
            'resolution' => 'local_wins',
        ], $this->authHeaders());

        $response->assertNotFound();
    }

    public function test_resolve_conflict_validates_resolution(): void
    {
        $fakeId = Str::uuid()->toString();
        $response = $this->postJson("/api/v2/sync/resolve-conflict/{$fakeId}", [
            'resolution' => 'invalid_value',
        ], $this->authHeaders());

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['resolution']);
    }

    public function test_resolve_conflict_success(): void
    {
        $conflict = SyncConflict::create([
            'store_id' => $this->store->id,
            'table_name' => 'products',
            'record_id' => Str::uuid()->toString(),
            'local_data' => ['name' => 'Local'],
            'cloud_data' => ['name' => 'Cloud'],
            'detected_at' => now(),
        ]);

        $response = $this->postJson("/api/v2/sync/resolve-conflict/{$conflict->id}", [
            'resolution' => 'local_wins',
        ], $this->authHeaders());

        $response->assertOk();

        $conflict->refresh();
        $this->assertEquals(SyncConflictResolution::LocalWins, $conflict->resolution);
        $this->assertNotNull($conflict->resolved_at);
        $this->assertEquals($this->user->id, $conflict->resolved_by);
    }

    public function test_resolve_conflict_already_resolved(): void
    {
        $conflict = SyncConflict::create([
            'store_id' => $this->store->id,
            'table_name' => 'products',
            'record_id' => Str::uuid()->toString(),
            'local_data' => ['x' => 1],
            'cloud_data' => ['x' => 2],
            'resolution' => SyncConflictResolution::CloudWins,
            'resolved_by' => $this->user->id,
            'detected_at' => now()->subHour(),
            'resolved_at' => now(),
        ]);

        $response = $this->postJson("/api/v2/sync/resolve-conflict/{$conflict->id}", [
            'resolution' => 'local_wins',
        ], $this->authHeaders());

        $response->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════
    // Heartbeat
    // ═══════════════════════════════════════════════════════════

    public function test_heartbeat_simple(): void
    {
        $response = $this->postJson('/api/v2/sync/heartbeat', [], $this->authHeaders());

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['alive', 'server_timestamp', 'records_pushed', 'pending_conflicts'],
        ]);
        $response->assertJsonPath('data.alive', true);
        $response->assertJsonPath('data.records_pushed', 0);
    }

    public function test_heartbeat_with_small_push(): void
    {
        $response = $this->postJson('/api/v2/sync/heartbeat', [
            'terminal_id' => $this->terminalId,
            'changes' => [
                [
                    'table' => 'settings',
                    'records' => [
                        ['id' => Str::uuid()->toString(), 'key' => 'theme', 'value' => 'dark'],
                    ],
                ],
            ],
        ], $this->authHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.alive', true);
        $response->assertJsonPath('data.records_pushed', 1);

        $this->assertDatabaseHas('sync_log', [
            'store_id' => $this->store->id,
            'direction' => 'push',
            'records_count' => 1,
        ]);
    }

    public function test_heartbeat_reports_pending_conflicts(): void
    {
        SyncConflict::create([
            'store_id' => $this->store->id,
            'table_name' => 'orders',
            'record_id' => Str::uuid()->toString(),
            'local_data' => [],
            'cloud_data' => [],
            'detected_at' => now(),
        ]);

        $response = $this->postJson('/api/v2/sync/heartbeat', [], $this->authHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.pending_conflicts', 1);
    }

    // ═══════════════════════════════════════════════════════════
    // Data Isolation
    // ═══════════════════════════════════════════════════════════

    public function test_sync_data_isolated_between_stores(): void
    {
        // Push data as first user
        $this->postJson('/api/v2/sync/push', [
            'terminal_id' => $this->terminalId,
            'changes' => [
                [
                    'table' => 'products',
                    'records' => [['id' => Str::uuid()->toString(), 'name' => 'P1']],
                ],
            ],
        ], $this->authHeaders());

        // Create second store/user
        $org2 = Organization::create([
            'name' => 'Other Sync Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);
        $store2 = Store::create([
            'organization_id' => $org2->id,
            'name' => 'Other Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);
        $user2 = User::create([
            'name' => 'Other User',
            'email' => 'sync2@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $store2->id,
            'organization_id' => $org2->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $token2 = $user2->createToken('test', ['*'])->plainTextToken;

        $this->app['auth']->forgetGuards();

        // Second user status should show no logs
        $response = $this->getJson('/api/v2/sync/status', [
            'Authorization' => 'Bearer ' . $token2,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.last_sync', null);
    }

    public function test_conflicts_isolated_between_stores(): void
    {
        SyncConflict::create([
            'store_id' => $this->store->id,
            'table_name' => 'products',
            'record_id' => Str::uuid()->toString(),
            'local_data' => [],
            'cloud_data' => [],
            'detected_at' => now(),
        ]);

        $org2 = Organization::create([
            'name' => 'Isolation Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);
        $store2 = Store::create([
            'organization_id' => $org2->id,
            'name' => 'Isolation Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);
        $user2 = User::create([
            'name' => 'Isolation User',
            'email' => 'sync-iso@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $store2->id,
            'organization_id' => $org2->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $token2 = $user2->createToken('test', ['*'])->plainTextToken;

        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v2/sync/conflicts', [
            'Authorization' => 'Bearer ' . $token2,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.pagination.total', 0);
    }
}
