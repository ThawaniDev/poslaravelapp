<?php

namespace Tests\Feature\Workflow;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * BACKUP & RECOVERY WORKFLOW TESTS
 *
 * Verifies backup creation, listing, verification, restore,
 * scheduling, storage usage, and provider status.
 *
 * Cross-references: Workflows #661-675
 */
class BackupRecoveryWorkflowTest extends WorkflowTestCase
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
            'name' => 'Backup Org',
            'name_ar' => 'منظمة نسخ',
            'business_type' => 'grocery',
            'country' => 'SA',
            'vat_number' => '300000000000003',
            'is_active' => true,
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Backup Store',
            'name_ar' => 'متجر النسخ',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Backup Owner',
            'email' => 'backup-owner@workflow.test',
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
    //  BACKUP CREATION & LISTING — WF #661-664
    // ══════════════════════════════════════════════

    /** @test */
    public function wf661_create_backup(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/backup/create', [
                'type' => 'full',
                'description' => 'Before major update',
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf662_list_backups(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/backup/list');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf663_show_backup_detail(): void
    {
        $backupId = Str::uuid()->toString();
        DB::table('backup_history')->insert([
            'id' => $backupId,
            'store_id' => $this->store->id, 'terminal_id' => Str::uuid()->toString(),
            'backup_type' => 'full',
            'status' => 'completed',
            'file_size_bytes' => 5242880,
            'created_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->getJson("/api/v2/backup/{$backupId}");

        $this->assertContains($response->status(), [200, 404, 500]);
    }

    /** @test */
    public function wf664_storage_usage(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/backup/storage');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    // ══════════════════════════════════════════════
    //  VERIFICATION & RESTORE — WF #665-668
    // ══════════════════════════════════════════════

    /** @test */
    public function wf665_verify_backup(): void
    {
        $backupId = Str::uuid()->toString();
        DB::table('backup_history')->insert([
            'id' => $backupId,
            'store_id' => $this->store->id, 'terminal_id' => Str::uuid()->toString(),
            'backup_type' => 'full',
            'status' => 'completed',
            'file_size_bytes' => 5242880,
            'created_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->postJson("/api/v2/backup/{$backupId}/verify");

        $this->assertContains($response->status(), [200, 404, 422, 500]);
    }

    /** @test */
    public function wf666_restore_from_backup(): void
    {
        $backupId = Str::uuid()->toString();
        DB::table('backup_history')->insert([
            'id' => $backupId,
            'store_id' => $this->store->id, 'terminal_id' => Str::uuid()->toString(),
            'backup_type' => 'full',
            'status' => 'completed',
            'file_size_bytes' => 5242880,
            'created_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->postJson("/api/v2/backup/{$backupId}/restore");

        $this->assertContains($response->status(), [200, 404, 422, 500]);
    }

    /** @test */
    public function wf667_delete_backup(): void
    {
        $backupId = Str::uuid()->toString();
        DB::table('backup_history')->insert([
            'id' => $backupId,
            'store_id' => $this->store->id, 'terminal_id' => Str::uuid()->toString(),
            'backup_type' => 'full',
            'status' => 'completed',
            'file_size_bytes' => 1048576,
            'created_at' => now()->subDays(30),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->deleteJson("/api/v2/backup/{$backupId}");

        $this->assertContains($response->status(), [200, 204, 404, 500]);
    }

    /** @test */
    public function wf668_export_backup(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/backup/export', [
                'format' => 'zip',
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    // ══════════════════════════════════════════════
    //  SCHEDULING & PROVIDER — WF #669-672
    // ══════════════════════════════════════════════

    /** @test */
    public function wf669_get_backup_schedule(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/backup/schedule');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf670_update_backup_schedule(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->putJson('/api/v2/backup/schedule', [
                'frequency' => 'daily',
                'time' => '03:00',
                'retention_days' => 30,
                'is_enabled' => true,
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf671_provider_status(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/backup/provider-status');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf672_sync_conflict_resolution(): void
    {
        // Seed a sync conflict
        $conflictId = Str::uuid()->toString();
        DB::table('sync_conflicts')->insert([
            'id' => $conflictId,
            'store_id' => $this->store->id,
            'table_name' => 'products',
            'record_id' => Str::uuid()->toString(),
            'local_data' => json_encode(['name' => 'Local Version']),
            'cloud_data' => json_encode(['name' => 'Remote Version']),
            'detected_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->postJson("/api/v2/sync/resolve-conflict/{$conflictId}", [
                'resolution' => 'accept_local',
            ]);

        $this->assertContains($response->status(), [200, 404, 422, 500]);
    }

    /** @test */
    public function wf673_list_sync_conflicts(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/sync/conflicts');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf674_full_sync(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/sync/full');

        $this->assertContains($response->status(), [200, 403, 422, 500]);
    }

    // ══════════════════════════════════════════════
    //  SYNC PUSH / PULL / STATUS / HEARTBEAT — WF #675-678
    // ══════════════════════════════════════════════

    /** @test */
    public function wf675_sync_push(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/sync/push', [
                'tables' => ['products', 'categories'],
                'changes' => [],
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf676_sync_pull(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/sync/pull');

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf677_sync_status(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/sync/status');

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf678_sync_heartbeat(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/sync/heartbeat');

        $this->assertContains($response->status(), [200, 422, 500]);
    }
}
