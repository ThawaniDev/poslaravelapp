<?php

namespace Tests\Feature\Api\Admin;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\BackupSync\Models\BackupHistory;
use App\Domain\BackupSync\Models\DatabaseBackup;
use App\Domain\BackupSync\Models\ProviderBackupStatus;
use App\Domain\BackupSync\Models\SyncConflict;
use App\Domain\BackupSync\Models\SyncLog;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DataManagementApiTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;
    private string $prefix = '/api/v2/admin/data-management';
    private string $storeId;
    private string $orgId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = AdminUser::forceCreate([
            'id'            => Str::uuid()->toString(),
            'name'          => 'Admin',
            'email'         => 'admin@test.com',
            'password_hash' => bcrypt('password'),
            'is_active'     => true,
        ]);
        Sanctum::actingAs($this->admin, ['*'], 'admin-api');

        $this->orgId = Str::uuid()->toString();
        \DB::table('organizations')->insert(['id' => $this->orgId, 'name' => 'Org']);

        $this->storeId = Str::uuid()->toString();
        Store::forceCreate([
            'id'              => $this->storeId,
            'organization_id' => $this->orgId,
            'name'            => 'Test Store',
        ]);
    }

    // ──────────────── Database Backups ────────────────

    public function test_list_database_backups(): void
    {
        DatabaseBackup::forceCreate([
            'id' => Str::uuid()->toString(), 'backup_type' => 'manual',
            'file_path' => '/backups/full.sql', 'status' => 'completed',
            'started_at' => now(),
        ]);
        DatabaseBackup::forceCreate([
            'id' => Str::uuid()->toString(), 'backup_type' => 'auto_daily',
            'file_path' => '/backups/inc.sql', 'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $res = $this->getJson("{$this->prefix}/database-backups");
        $res->assertOk()->assertJsonCount(2, 'data.data');
    }

    public function test_filter_database_backups_by_type(): void
    {
        DatabaseBackup::forceCreate([
            'id' => Str::uuid()->toString(), 'backup_type' => 'manual',
            'file_path' => '/a.sql', 'status' => 'completed', 'started_at' => now(),
        ]);
        DatabaseBackup::forceCreate([
            'id' => Str::uuid()->toString(), 'backup_type' => 'auto_daily',
            'file_path' => '/b.sql', 'status' => 'completed', 'started_at' => now(),
        ]);

        $res = $this->getJson("{$this->prefix}/database-backups?backup_type=manual");
        $res->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_filter_database_backups_by_status(): void
    {
        DatabaseBackup::forceCreate([
            'id' => Str::uuid()->toString(), 'backup_type' => 'manual',
            'file_path' => '/a.sql', 'status' => 'completed', 'started_at' => now(),
        ]);
        DatabaseBackup::forceCreate([
            'id' => Str::uuid()->toString(), 'backup_type' => 'manual',
            'file_path' => '/b.sql', 'status' => 'failed', 'started_at' => now(),
        ]);

        $res = $this->getJson("{$this->prefix}/database-backups?status=failed");
        $res->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_create_database_backup(): void
    {
        $res = $this->postJson("{$this->prefix}/database-backups", [
            'backup_type'    => 'manual',
            'file_path'      => '/backups/new.sql',
            'file_size_bytes' => 1024000,
        ]);

        $res->assertCreated()
            ->assertJsonPath('data.backup_type', 'manual')
            ->assertJsonPath('data.status', 'in_progress');
    }

    public function test_create_database_backup_validation(): void
    {
        $this->postJson("{$this->prefix}/database-backups", [])->assertUnprocessable()
            ->assertJsonValidationErrors(['backup_type', 'file_path']);
    }

    public function test_show_database_backup(): void
    {
        $backup = DatabaseBackup::forceCreate([
            'id' => Str::uuid()->toString(), 'backup_type' => 'manual',
            'file_path' => '/backups/show.sql', 'status' => 'completed',
            'started_at' => now(),
        ]);

        $res = $this->getJson("{$this->prefix}/database-backups/{$backup->id}");
        $res->assertOk()->assertJsonPath('data.file_path', '/backups/show.sql');
    }

    public function test_show_database_backup_not_found(): void
    {
        $this->getJson("{$this->prefix}/database-backups/nonexistent")->assertNotFound();
    }

    public function test_complete_database_backup(): void
    {
        $backup = DatabaseBackup::forceCreate([
            'id' => Str::uuid()->toString(), 'backup_type' => 'manual',
            'file_path' => '/backups/comp.sql', 'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $res = $this->postJson("{$this->prefix}/database-backups/{$backup->id}/complete", [
            'status'          => 'completed',
            'file_size_bytes' => 2048000,
        ]);

        $res->assertOk()
            ->assertJsonPath('data.status', 'completed');
        $this->assertNotNull($backup->fresh()->completed_at);
    }

    public function test_complete_database_backup_with_error(): void
    {
        $backup = DatabaseBackup::forceCreate([
            'id' => Str::uuid()->toString(), 'backup_type' => 'manual',
            'file_path' => '/backups/err.sql', 'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $res = $this->postJson("{$this->prefix}/database-backups/{$backup->id}/complete", [
            'status'        => 'failed',
            'error_message' => 'Disk full',
        ]);

        $res->assertOk()->assertJsonPath('data.status', 'failed');
    }

    // ──────────────── Backup History ────────────────

    public function test_list_backup_history(): void
    {
        BackupHistory::forceCreate([
            'id' => Str::uuid()->toString(),
            'store_id' => $this->storeId, 'terminal_id' => Str::uuid()->toString(),
            'backup_type' => 'auto', 'file_size_bytes' => 1000, 'checksum' => 'abc',
            'db_version' => 1, 'status' => 'completed', 'created_at' => now(),
        ]);

        $res = $this->getJson("{$this->prefix}/backup-history");
        $res->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_filter_backup_history_by_store(): void
    {
        $otherId = Str::uuid()->toString();
        Store::forceCreate(['id' => $otherId, 'organization_id' => $this->orgId, 'name' => 'Other']);

        BackupHistory::forceCreate([
            'id' => Str::uuid()->toString(), 'store_id' => $this->storeId,
            'terminal_id' => Str::uuid()->toString(), 'backup_type' => 'auto',
            'file_size_bytes' => 1000, 'checksum' => 'a', 'db_version' => 1,
            'status' => 'completed', 'created_at' => now(),
        ]);
        BackupHistory::forceCreate([
            'id' => Str::uuid()->toString(), 'store_id' => $otherId,
            'terminal_id' => Str::uuid()->toString(), 'backup_type' => 'auto',
            'file_size_bytes' => 1000, 'checksum' => 'b', 'db_version' => 1,
            'status' => 'completed', 'created_at' => now(),
        ]);

        $res = $this->getJson("{$this->prefix}/backup-history?store_id={$this->storeId}");
        $res->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_show_backup_history_item(): void
    {
        $item = BackupHistory::forceCreate([
            'id' => Str::uuid()->toString(), 'store_id' => $this->storeId,
            'terminal_id' => Str::uuid()->toString(), 'backup_type' => 'manual',
            'file_size_bytes' => 500, 'checksum' => 'xyz', 'db_version' => 2,
            'status' => 'completed', 'created_at' => now(),
        ]);

        $this->getJson("{$this->prefix}/backup-history/{$item->id}")->assertOk();
    }

    public function test_show_backup_history_not_found(): void
    {
        $this->getJson("{$this->prefix}/backup-history/nonexistent")->assertNotFound();
    }

    // ──────────────── Sync Logs ────────────────

    public function test_list_sync_logs(): void
    {
        SyncLog::forceCreate([
            'id' => Str::uuid()->toString(), 'store_id' => $this->storeId,
            'terminal_id' => Str::uuid()->toString(), 'direction' => 'push',
            'records_count' => 100, 'duration_ms' => 500, 'status' => 'success',
            'started_at' => now(),
        ]);

        $res = $this->getJson("{$this->prefix}/sync-logs");
        $res->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_filter_sync_logs_by_direction(): void
    {
        SyncLog::forceCreate([
            'id' => Str::uuid()->toString(), 'store_id' => $this->storeId,
            'terminal_id' => Str::uuid()->toString(), 'direction' => 'push',
            'status' => 'success', 'started_at' => now(),
        ]);
        SyncLog::forceCreate([
            'id' => Str::uuid()->toString(), 'store_id' => $this->storeId,
            'terminal_id' => Str::uuid()->toString(), 'direction' => 'pull',
            'status' => 'success', 'started_at' => now(),
        ]);

        $res = $this->getJson("{$this->prefix}/sync-logs?direction=push");
        $res->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_filter_sync_logs_by_status(): void
    {
        SyncLog::forceCreate([
            'id' => Str::uuid()->toString(), 'store_id' => $this->storeId,
            'terminal_id' => Str::uuid()->toString(), 'direction' => 'push',
            'status' => 'success', 'started_at' => now(),
        ]);
        SyncLog::forceCreate([
            'id' => Str::uuid()->toString(), 'store_id' => $this->storeId,
            'terminal_id' => Str::uuid()->toString(), 'direction' => 'push',
            'status' => 'failed', 'started_at' => now(),
        ]);

        $res = $this->getJson("{$this->prefix}/sync-logs?status=failed");
        $res->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_show_sync_log(): void
    {
        $log = SyncLog::forceCreate([
            'id' => Str::uuid()->toString(), 'store_id' => $this->storeId,
            'terminal_id' => Str::uuid()->toString(), 'direction' => 'push',
            'records_count' => 50, 'duration_ms' => 200, 'status' => 'success',
            'started_at' => now(),
        ]);

        $this->getJson("{$this->prefix}/sync-logs/{$log->id}")->assertOk();
    }

    public function test_show_sync_log_not_found(): void
    {
        $this->getJson("{$this->prefix}/sync-logs/nonexistent")->assertNotFound();
    }

    public function test_sync_log_summary(): void
    {
        SyncLog::forceCreate([
            'id' => Str::uuid()->toString(), 'store_id' => $this->storeId,
            'terminal_id' => Str::uuid()->toString(), 'direction' => 'push',
            'records_count' => 100, 'duration_ms' => 500, 'status' => 'success',
            'started_at' => now(),
        ]);
        SyncLog::forceCreate([
            'id' => Str::uuid()->toString(), 'store_id' => $this->storeId,
            'terminal_id' => Str::uuid()->toString(), 'direction' => 'pull',
            'records_count' => 50, 'duration_ms' => 300, 'status' => 'failed',
            'started_at' => now(),
        ]);

        $res = $this->getJson("{$this->prefix}/sync-logs/summary");
        $res->assertOk();

        $data = $res->json('data');
        $this->assertEquals(2, $data['total_syncs']);
        $this->assertEquals(1, $data['successful']);
        $this->assertEquals(1, $data['failed']);
        $this->assertEquals(150, $data['total_records']);
    }

    // ──────────────── Sync Conflicts ────────────────

    public function test_list_sync_conflicts(): void
    {
        SyncConflict::forceCreate([
            'id' => Str::uuid()->toString(), 'store_id' => $this->storeId,
            'table_name' => 'products', 'record_id' => Str::uuid()->toString(),
            'local_data' => json_encode(['name' => 'A']),
            'cloud_data' => json_encode(['name' => 'B']),
            'detected_at' => now(),
        ]);

        $this->getJson("{$this->prefix}/sync-conflicts")->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_filter_sync_conflicts_unresolved(): void
    {
        SyncConflict::forceCreate([
            'id' => Str::uuid()->toString(), 'store_id' => $this->storeId,
            'table_name' => 'products', 'record_id' => Str::uuid()->toString(),
            'local_data' => '{}', 'cloud_data' => '{}', 'detected_at' => now(),
        ]);
        SyncConflict::forceCreate([
            'id' => Str::uuid()->toString(), 'store_id' => $this->storeId,
            'table_name' => 'orders', 'record_id' => Str::uuid()->toString(),
            'local_data' => '{}', 'cloud_data' => '{}',
            'detected_at' => now(), 'resolved_at' => now(),
        ]);

        $res = $this->getJson("{$this->prefix}/sync-conflicts?resolved=0");
        $res->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_filter_sync_conflicts_by_table(): void
    {
        SyncConflict::forceCreate([
            'id' => Str::uuid()->toString(), 'store_id' => $this->storeId,
            'table_name' => 'products', 'record_id' => Str::uuid()->toString(),
            'local_data' => '{}', 'cloud_data' => '{}', 'detected_at' => now(),
        ]);
        SyncConflict::forceCreate([
            'id' => Str::uuid()->toString(), 'store_id' => $this->storeId,
            'table_name' => 'orders', 'record_id' => Str::uuid()->toString(),
            'local_data' => '{}', 'cloud_data' => '{}', 'detected_at' => now(),
        ]);

        $res = $this->getJson("{$this->prefix}/sync-conflicts?table_name=products");
        $res->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_show_sync_conflict(): void
    {
        $conflict = SyncConflict::forceCreate([
            'id' => Str::uuid()->toString(), 'store_id' => $this->storeId,
            'table_name' => 'products', 'record_id' => Str::uuid()->toString(),
            'local_data' => '{"name":"A"}', 'cloud_data' => '{"name":"B"}',
            'detected_at' => now(),
        ]);

        $this->getJson("{$this->prefix}/sync-conflicts/{$conflict->id}")->assertOk();
    }

    public function test_show_sync_conflict_not_found(): void
    {
        $this->getJson("{$this->prefix}/sync-conflicts/nonexistent")->assertNotFound();
    }

    public function test_resolve_sync_conflict(): void
    {
        $conflict = SyncConflict::forceCreate([
            'id' => Str::uuid()->toString(), 'store_id' => $this->storeId,
            'table_name' => 'products', 'record_id' => Str::uuid()->toString(),
            'local_data' => '{}', 'cloud_data' => '{}', 'detected_at' => now(),
        ]);

        $res = $this->postJson("{$this->prefix}/sync-conflicts/{$conflict->id}/resolve", [
            'resolution' => 'local_wins',
        ]);

        $res->assertOk()->assertJsonPath('data.resolution', 'local_wins');
        $this->assertNotNull($conflict->fresh()->resolved_at);
        $this->assertEquals($this->admin->id, $conflict->fresh()->resolved_by);
    }

    public function test_resolve_sync_conflict_validation(): void
    {
        $conflict = SyncConflict::forceCreate([
            'id' => Str::uuid()->toString(), 'store_id' => $this->storeId,
            'table_name' => 'products', 'record_id' => Str::uuid()->toString(),
            'local_data' => '{}', 'cloud_data' => '{}', 'detected_at' => now(),
        ]);

        $this->postJson("{$this->prefix}/sync-conflicts/{$conflict->id}/resolve", [
            'resolution' => 'invalid',
        ])->assertUnprocessable();
    }

    // ──────────────── Provider Backup Status ────────────────

    public function test_list_provider_backup_statuses(): void
    {
        ProviderBackupStatus::forceCreate([
            'id' => Str::uuid()->toString(), 'store_id' => $this->storeId,
            'terminal_id' => Str::uuid()->toString(), 'status' => 'healthy',
            'updated_at' => now(),
        ]);

        $this->getJson("{$this->prefix}/provider-backup-statuses")->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_filter_provider_backup_statuses_by_status(): void
    {
        ProviderBackupStatus::forceCreate([
            'id' => Str::uuid()->toString(), 'store_id' => $this->storeId,
            'terminal_id' => Str::uuid()->toString(), 'status' => 'healthy',
            'updated_at' => now(),
        ]);
        ProviderBackupStatus::forceCreate([
            'id' => Str::uuid()->toString(), 'store_id' => $this->storeId,
            'terminal_id' => Str::uuid()->toString(), 'status' => 'warning',
            'updated_at' => now(),
        ]);

        $res = $this->getJson("{$this->prefix}/provider-backup-statuses?status=healthy");
        $res->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_show_provider_backup_status(): void
    {
        $s = ProviderBackupStatus::forceCreate([
            'id' => Str::uuid()->toString(), 'store_id' => $this->storeId,
            'terminal_id' => Str::uuid()->toString(), 'status' => 'healthy',
            'updated_at' => now(),
        ]);

        $this->getJson("{$this->prefix}/provider-backup-statuses/{$s->id}")->assertOk();
    }

    public function test_show_provider_backup_status_not_found(): void
    {
        $this->getJson("{$this->prefix}/provider-backup-statuses/nonexistent")->assertNotFound();
    }

    // ──────────────── Overview ────────────────

    public function test_backup_overview(): void
    {
        DatabaseBackup::forceCreate([
            'id' => Str::uuid()->toString(), 'backup_type' => 'manual',
            'file_path' => '/a.sql', 'file_size_bytes' => 1000,
            'status' => 'completed', 'started_at' => now(),
        ]);
        DatabaseBackup::forceCreate([
            'id' => Str::uuid()->toString(), 'backup_type' => 'manual',
            'file_path' => '/b.sql', 'status' => 'failed', 'started_at' => now(),
        ]);

        SyncLog::forceCreate([
            'id' => Str::uuid()->toString(), 'store_id' => $this->storeId,
            'terminal_id' => Str::uuid()->toString(), 'direction' => 'push',
            'status' => 'success', 'started_at' => now(),
        ]);

        SyncConflict::forceCreate([
            'id' => Str::uuid()->toString(), 'store_id' => $this->storeId,
            'table_name' => 'x', 'record_id' => Str::uuid()->toString(),
            'local_data' => '{}', 'cloud_data' => '{}', 'detected_at' => now(),
        ]);

        $res = $this->getJson("{$this->prefix}/overview");
        $res->assertOk();

        $data = $res->json('data');
        $this->assertEquals(2, $data['database_backups']['total']);
        $this->assertEquals(1, $data['database_backups']['completed']);
        $this->assertEquals(1, $data['database_backups']['failed']);
        $this->assertEquals(1000, $data['database_backups']['total_size_bytes']);
        $this->assertEquals(1, $data['sync']['total_syncs']);
        $this->assertEquals(1, $data['sync']['unresolved_conflicts']);
    }

    // ──────────────── Pagination ────────────────

    public function test_database_backups_pagination(): void
    {
        for ($i = 0; $i < 20; $i++) {
            DatabaseBackup::forceCreate([
                'id' => Str::uuid()->toString(), 'backup_type' => 'manual',
                'file_path' => "/backups/{$i}.sql", 'status' => 'completed',
                'started_at' => now(),
            ]);
        }

        $res = $this->getJson("{$this->prefix}/database-backups?per_page=5");
        $res->assertOk()
            ->assertJsonCount(5, 'data.data')
            ->assertJsonPath('data.last_page', 4);
    }
}
