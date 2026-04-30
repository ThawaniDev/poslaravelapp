<?php

namespace Tests\Feature\BackupSync;

use App\Domain\Auth\Models\User;
use App\Domain\BackupSync\Models\BackupHistory;
use App\Domain\BackupSync\Models\DatabaseBackup;
use App\Domain\BackupSync\Models\ProviderBackupStatus;
use App\Domain\BackupSync\Models\StoreBackupSettings;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BackupApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private string $storeId;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('organizations')) {
            Schema::create('organizations', function ($t) {
                $t->uuid('id')->primary();
                $t->string('name');
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('stores')) {
            Schema::create('stores', function ($t) {
                $t->uuid('id')->primary();
                $t->foreignUuid('organization_id')->nullable();
                $t->string('name');
                $t->string('name_ar')->nullable();
                $t->string('slug')->nullable();
                $t->string('branch_code')->nullable();
                $t->text('address')->nullable();
                $t->string('city')->nullable();
                $t->decimal('latitude', 10, 7)->nullable();
                $t->decimal('longitude', 10, 7)->nullable();
                $t->string('phone')->nullable();
                $t->string('email')->nullable();
                $t->string('timezone')->default('UTC');
                $t->string('currency')->default('SAR');
                $t->string('locale')->default('en');
                $t->string('business_type')->nullable();
                $t->boolean('is_active')->default(true);
                $t->boolean('is_main_branch')->default(false);
                $t->decimal('storage_used_mb', 10, 2)->default(0);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('users')) {
            Schema::create('users', function ($t) {
                $t->uuid('id')->primary();
                $t->string('name');
                $t->string('email')->unique();
                $t->foreignUuid('store_id')->nullable();
                $t->string('password_hash')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function ($t) {
                $t->id();
                $t->uuidMorphs('tokenable');
                $t->string('name');
                $t->string('token', 64)->unique();
                $t->text('abilities')->nullable();
                $t->timestamp('last_used_at')->nullable();
                $t->timestamp('expires_at')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('database_backups')) {
            Schema::create('database_backups', function ($t) {
                $t->uuid('id')->primary();
                $t->string('backup_type');
                $t->string('file_path')->nullable();
                $t->bigInteger('file_size_bytes')->default(0);
                $t->string('status');
                $t->text('error_message')->nullable();
                $t->timestamp('started_at')->nullable();
                $t->timestamp('completed_at')->nullable();
            });
        }

        if (!Schema::hasTable('backup_history')) {
            Schema::create('backup_history', function ($t) {
                $t->uuid('id')->primary();
                $t->foreignUuid('store_id');
                $t->string('terminal_id')->nullable();
                $t->string('backup_type');
                $t->string('storage_location')->nullable();
                $t->string('local_path')->nullable();
                $t->string('cloud_key')->nullable();
                $t->bigInteger('file_size_bytes')->default(0);
                $t->string('checksum')->nullable();
                $t->string('db_version')->nullable();
                $t->integer('records_count')->default(0);
                $t->boolean('is_verified')->default(false);
                $t->boolean('is_encrypted')->default(false);
                $t->string('status');
                $t->text('error_message')->nullable();
            });
        }

        if (!Schema::hasTable('provider_backup_status')) {
            Schema::create('provider_backup_status', function ($t) {
                $t->uuid('id')->primary();
                $t->foreignUuid('store_id');
                $t->string('terminal_id');
                $t->timestamp('last_successful_sync')->nullable();
                $t->timestamp('last_cloud_backup')->nullable();
                $t->bigInteger('storage_used_bytes')->default(0);
                $t->string('status')->nullable();
            });
        }

        if (!Schema::hasTable('store_backup_settings')) {
            Schema::create('store_backup_settings', function ($t) {
                $t->uuid('id')->primary();
                $t->foreignUuid('store_id')->unique();
                $t->boolean('auto_backup_enabled')->default(true);
                $t->string('frequency')->default('daily');
                $t->integer('retention_days')->default(30);
                $t->boolean('encrypt_backups')->default(false);
                $t->boolean('local_backup_enabled')->default(true);
                $t->boolean('cloud_backup_enabled')->default(true);
                $t->integer('backup_hour')->default(2);
                $t->timestamps();
            });
        }

        $org = Organization::create(['name' => 'Backup Org']);
        $store = Store::create([
            'organization_id' => $org->id,
            'name' => 'Backup Store',
        ]);
        $this->storeId = $store->id;

        $user = User::create([
            'name' => 'Backup User',
            'email' => 'backup@test.com',
            'store_id' => $store->id,
            'password_hash' => bcrypt('password'),
        ]);
        $this->token = $user->createToken('test', ['*'])->plainTextToken;
    }

    private function auth(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    // ── Create Backup ────────────────────────────────────────

    public function test_create_backup_success(): void
    {
        $res = $this->postJson('/api/v2/backup/create', [
            'terminal_id' => fake()->uuid(),
            'backup_type' => 'manual',
        ], $this->auth());

        $res->assertStatus(201);
        $body = json_decode($res->getContent(), true);
        $this->assertEquals('completed', $body['data']['status']);
        $this->assertNotEmpty($body['data']['backup_id']);
        $this->assertDatabaseHas('backup_history', ['store_id' => $this->storeId]);
    }

    public function test_create_backup_encrypted(): void
    {
        $res = $this->postJson('/api/v2/backup/create', [
            'terminal_id' => fake()->uuid(),
            'backup_type' => 'pre_update',
            'encrypt' => true,
        ], $this->auth());

        $res->assertStatus(201);
    }

    public function test_create_backup_validation_fails(): void
    {
        $res = $this->postJson('/api/v2/backup/create', [], $this->auth());
        $res->assertStatus(422);
    }

    public function test_create_backup_unauthenticated(): void
    {
        $res = $this->postJson('/api/v2/backup/create', ['terminal_id' => fake()->uuid()]);
        $res->assertStatus(401);
    }

    // ── List Backups ─────────────────────────────────────────

    public function test_list_backups_empty(): void
    {
        $res = $this->getJson('/api/v2/backup/list', $this->auth());

        $res->assertOk();
        $body = json_decode($res->getContent(), true);
        $this->assertEmpty($body['data']['backups']);
    }

    public function test_list_backups_with_data(): void
    {
        BackupHistory::create([
            'store_id' => $this->storeId,
            'terminal_id' => '00000000-0000-0000-0000-000000000001',
            'backup_type' => 'manual',
            'status' => 'completed',
        ]);

        $res = $this->getJson('/api/v2/backup/list', $this->auth());

        $res->assertOk();
        $body = json_decode($res->getContent(), true);
        $this->assertCount(1, $body['data']['backups']);
    }

    public function test_list_backups_filter_by_type(): void
    {
        BackupHistory::create(['store_id' => $this->storeId, 'terminal_id' => '00000000-0000-0000-0000-000000000041', 'backup_type' => 'manual', 'status' => 'completed']);
        BackupHistory::create(['store_id' => $this->storeId, 'terminal_id' => '00000000-0000-0000-0000-000000000094', 'backup_type' => 'auto', 'status' => 'completed']);

        $res = $this->getJson('/api/v2/backup/list?backup_type=manual', $this->auth());

        $res->assertOk();
        $body = json_decode($res->getContent(), true);
        $this->assertEquals(1, $body['data']['pagination']['total']);
    }

    // ── Show Backup ──────────────────────────────────────────

    public function test_show_backup_success(): void
    {
        $backup = BackupHistory::create([
            'store_id' => $this->storeId,
            'terminal_id' => '00000000-0000-0000-0000-000000000001',
            'backup_type' => 'manual',
            'status' => 'completed',
            'checksum' => 'abc123',
        ]);

        $res = $this->getJson("/api/v2/backup/{$backup->id}", $this->auth());

        $res->assertOk();
    }

    public function test_show_backup_not_found(): void
    {
        $res = $this->getJson('/api/v2/backup/' . fake()->uuid(), $this->auth());
        $res->assertStatus(404);
    }

    // ── Restore Backup ───────────────────────────────────────

    public function test_restore_backup_success(): void
    {
        $backup = BackupHistory::create([
            'store_id' => $this->storeId,
            'terminal_id' => '00000000-0000-0000-0000-000000000001',
            'backup_type' => 'manual',
            'status' => 'completed',
            'records_count' => 5000,
        ]);

        $res = $this->postJson("/api/v2/backup/{$backup->id}/restore", [], $this->auth());

        $res->assertOk();
        $body = json_decode($res->getContent(), true);
        $this->assertTrue($body['data']['restore_initiated']);
    }

    public function test_restore_failed_backup_rejected(): void
    {
        $backup = BackupHistory::create([
            'store_id' => $this->storeId,
            'terminal_id' => '00000000-0000-0000-0000-000000000001',
            'backup_type' => 'manual',
            'status' => 'failed',
        ]);

        $res = $this->postJson("/api/v2/backup/{$backup->id}/restore", [], $this->auth());

        $res->assertStatus(422);
    }

    public function test_restore_backup_not_found(): void
    {
        $res = $this->postJson('/api/v2/backup/' . fake()->uuid() . '/restore', [], $this->auth());
        $res->assertStatus(404);
    }

    // ── Verify Backup ────────────────────────────────────────

    public function test_verify_backup_success(): void
    {
        $backup = BackupHistory::create([
            'store_id' => $this->storeId,
            'terminal_id' => '00000000-0000-0000-0000-000000000001',
            'backup_type' => 'manual',
            'status' => 'completed',
            'checksum' => str_repeat('a', 64), // valid 64-char checksum
        ]);

        $res = $this->postJson("/api/v2/backup/{$backup->id}/verify", [], $this->auth());

        $res->assertOk();
        $body = json_decode($res->getContent(), true);
        $this->assertTrue($body['data']['is_valid']);
    }

    public function test_verify_backup_not_found(): void
    {
        $res = $this->postJson('/api/v2/backup/' . fake()->uuid() . '/verify', [], $this->auth());
        $res->assertStatus(404);
    }

    // ── Schedule ─────────────────────────────────────────────

    public function test_get_schedule(): void
    {
        $res = $this->getJson('/api/v2/backup/schedule', $this->auth());

        $res->assertOk();
        $body = json_decode($res->getContent(), true);
        $this->assertArrayHasKey('auto_backup_enabled', $body['data']);
        $this->assertArrayHasKey('frequency', $body['data']);
    }

    public function test_update_schedule_success(): void
    {
        $res = $this->putJson('/api/v2/backup/schedule', [
            'auto_backup_enabled' => true,
            'frequency' => 'weekly',
            'retention_days' => 60,
            'encrypt_backups' => true,
        ], $this->auth());

        $res->assertOk();
        $body = json_decode($res->getContent(), true);
        $this->assertEquals('weekly', $body['data']['frequency']);
        $this->assertEquals(60, $body['data']['retention_days']);
        // updateSchedule must now return full stats (same as getSchedule)
        $this->assertArrayHasKey('total_backups', $body['data']);
        $this->assertArrayHasKey('total_size_bytes', $body['data']);
        $this->assertArrayHasKey('next_scheduled', $body['data']);
        $this->assertArrayHasKey('last_backup', $body['data']);
        $this->assertArrayHasKey('last_auto_backup', $body['data']);
    }

    public function test_update_schedule_validation_fails(): void
    {
        $res = $this->putJson('/api/v2/backup/schedule', ['frequency' => 'monthly'], $this->auth());
        $res->assertStatus(422);
    }

    // ── Storage ──────────────────────────────────────────────

    public function test_storage_usage(): void
    {
        $res = $this->getJson('/api/v2/backup/storage', $this->auth());

        $res->assertOk();
        $body = json_decode($res->getContent(), true);
        $this->assertArrayHasKey('total_backup_bytes', $body['data']);
        $this->assertArrayHasKey('quota_bytes', $body['data']);
    }

    // ── Delete Backup ────────────────────────────────────────

    public function test_delete_backup_success(): void
    {
        $backup = BackupHistory::create([
            'store_id' => $this->storeId,
            'terminal_id' => '00000000-0000-0000-0000-000000000001',
            'backup_type' => 'manual',
            'status' => 'completed',
        ]);

        $res = $this->deleteJson("/api/v2/backup/{$backup->id}", [], $this->auth());

        $res->assertOk();
        $this->assertDatabaseMissing('backup_history', ['id' => $backup->id]);
    }

    public function test_delete_backup_not_found(): void
    {
        $res = $this->deleteJson('/api/v2/backup/' . fake()->uuid(), [], $this->auth());
        $res->assertStatus(404);
    }

    // ── Export ────────────────────────────────────────────────

    public function test_export_data_success(): void
    {
        $res = $this->postJson('/api/v2/backup/export', [
            'tables' => ['orders', 'products'],
            'format' => 'json',
        ], $this->auth());

        $res->assertStatus(201);
        $body = json_decode($res->getContent(), true);
        $this->assertEquals('json', $body['data']['format']);
        $this->assertCount(2, $body['data']['tables']);
    }

    public function test_export_data_csv(): void
    {
        $res = $this->postJson('/api/v2/backup/export', [
            'tables' => ['customers'],
            'format' => 'csv',
            'include_images' => true,
        ], $this->auth());

        $res->assertStatus(201);
        $body = json_decode($res->getContent(), true);
        $this->assertEquals('csv', $body['data']['format']);
        $this->assertTrue($body['data']['include_images']);
    }

    public function test_export_data_validation_fails(): void
    {
        $res = $this->postJson('/api/v2/backup/export', [], $this->auth());
        $res->assertStatus(422);
    }

    // ── Provider Status ──────────────────────────────────────

    public function test_provider_status_empty(): void
    {
        $res = $this->getJson('/api/v2/backup/provider-status', $this->auth());

        $res->assertOk();
        $body = json_decode($res->getContent(), true);
        $this->assertEmpty($body['data']['terminals']);
    }

    public function test_provider_status_with_data(): void
    {
        ProviderBackupStatus::create([
            'store_id' => $this->storeId,
            'terminal_id' => '00000000-0000-0000-0000-000000000020',
            'status' => 'healthy',
            'storage_used_bytes' => 1048576,
        ]);

        $res = $this->getJson('/api/v2/backup/provider-status', $this->auth());

        $res->assertOk();
        $body = json_decode($res->getContent(), true);
        $this->assertCount(1, $body['data']['terminals']);
        $this->assertEquals('healthy', $body['data']['terminals'][0]['status']);
    }

    // ── Schedule persistence ─────────────────────────────────

    public function test_update_schedule_persists_to_database(): void
    {
        $this->putJson('/api/v2/backup/schedule', [
            'auto_backup_enabled' => false,
            'frequency' => 'hourly',
            'retention_days' => 14,
            'encrypt_backups' => true,
            'local_backup_enabled' => false,
            'cloud_backup_enabled' => true,
            'backup_hour' => 3,
        ], $this->auth());

        $this->assertDatabaseHas('store_backup_settings', [
            'store_id' => $this->storeId,
            'frequency' => 'hourly',
            'retention_days' => 14,
            'encrypt_backups' => true,
            'local_backup_enabled' => false,
            'cloud_backup_enabled' => true,
            'backup_hour' => 3,
        ]);
    }

    public function test_get_schedule_returns_persisted_settings(): void
    {
        // First update
        $this->putJson('/api/v2/backup/schedule', [
            'auto_backup_enabled' => true,
            'frequency' => 'weekly',
            'retention_days' => 90,
            'encrypt_backups' => false,
            'local_backup_enabled' => true,
            'cloud_backup_enabled' => false,
            'backup_hour' => 6,
        ], $this->auth());

        // Then read back
        $res = $this->getJson('/api/v2/backup/schedule', $this->auth());

        $res->assertOk();
        $body = $res->json('data');
        $this->assertEquals('weekly', $body['frequency']);
        $this->assertEquals(90, $body['retention_days']);
        $this->assertFalse($body['cloud_backup_enabled']);
        $this->assertEquals(6, $body['backup_hour']);
    }

    public function test_update_schedule_all_new_fields(): void
    {
        $res = $this->putJson('/api/v2/backup/schedule', [
            'auto_backup_enabled' => true,
            'frequency' => 'daily',
            'retention_days' => 30,
            'encrypt_backups' => false,
            'local_backup_enabled' => true,
            'cloud_backup_enabled' => true,
            'backup_hour' => 0,
        ], $this->auth());

        $res->assertOk();
        $body = $res->json('data');
        $this->assertTrue($body['local_backup_enabled']);
        $this->assertTrue($body['cloud_backup_enabled']);
        $this->assertEquals(0, $body['backup_hour']);
    }

    // ── Verify updates DB flag ───────────────────────────────

    public function test_verify_backup_updates_is_verified_flag(): void
    {
        $backup = BackupHistory::create([
            'store_id' => $this->storeId,
            'terminal_id' => '00000000-0000-0000-0000-000000000001',
            'backup_type' => 'manual',
            'status' => 'completed',
            'checksum' => str_repeat('b', 64), // valid 64-char checksum
            'is_verified' => false,
        ]);

        $this->postJson("/api/v2/backup/{$backup->id}/verify", [], $this->auth());

        $this->assertDatabaseHas('backup_history', [
            'id' => $backup->id,
            'is_verified' => true,
        ]);
    }

    // ── List includes summary ────────────────────────────────

    public function test_list_backups_response_includes_summary(): void
    {
        BackupHistory::create([
            'store_id' => $this->storeId,
            'terminal_id' => '00000000-0000-0000-0000-000000000001',
            'backup_type' => 'manual',
            'status' => 'completed',
            'file_size_bytes' => 1024,
        ]);

        $res = $this->getJson('/api/v2/backup/list', $this->auth());

        $res->assertOk();
        $body = $res->json('data');
        $this->assertArrayHasKey('summary', $body);
        $this->assertArrayHasKey('total_count', $body['summary']);
        $this->assertArrayHasKey('total_size_bytes', $body['summary']);
        $this->assertEquals(1, $body['summary']['total_count']);
    }

    // ── Restore returns estimated duration ───────────────────

    public function test_restore_backup_response_has_estimated_duration(): void
    {
        $backup = BackupHistory::create([
            'store_id' => $this->storeId,
            'terminal_id' => '00000000-0000-0000-0000-000000000001',
            'backup_type' => 'manual',
            'status' => 'completed',
            'records_count' => 10000,
        ]);

        $res = $this->postJson("/api/v2/backup/{$backup->id}/restore", [], $this->auth());

        $res->assertOk();
        $body = $res->json('data');
        $this->assertArrayHasKey('estimated_duration_seconds', $body);
        $this->assertGreaterThan(0, $body['estimated_duration_seconds']);
    }

    // ── Export all tables ────────────────────────────────────

    public function test_export_all_tables(): void
    {
        $res = $this->postJson('/api/v2/backup/export', [
            'tables' => ['products', 'customers', 'orders', 'inventory', 'settings', 'staff', 'categories'],
            'format' => 'json',
            'include_images' => false,
        ], $this->auth());

        $res->assertStatus(201);
        $body = $res->json('data');
        $this->assertArrayHasKey('export_id', $body);
        $this->assertArrayHasKey('file_path', $body);
        $this->assertArrayHasKey('total_records', $body);
        $this->assertCount(7, $body['tables']);
    }

    public function test_export_data_invalid_table_rejected(): void
    {
        // tables field is required; an empty array should fail validation
        $res = $this->postJson('/api/v2/backup/export', [
            'tables' => [],
            'format' => 'json',
        ], $this->auth());

        $res->assertStatus(422);
    }

    // ── Backup checksum is sha256 ────────────────────────────

    public function test_create_backup_has_valid_checksum(): void
    {
        $res = $this->postJson('/api/v2/backup/create', [
            'terminal_id' => fake()->uuid(),
            'backup_type' => 'manual',
        ], $this->auth());

        $res->assertStatus(201);
        $body = $res->json('data');
        $this->assertNotEmpty($body['checksum']);
        $this->assertEquals(64, strlen($body['checksum']), 'Checksum should be SHA-256 hex (64 chars)');
    }

    // ── Backup type stored correctly ─────────────────────────

    public function test_create_backup_stores_correct_backup_type(): void
    {
        $res = $this->postJson('/api/v2/backup/create', [
            'terminal_id' => fake()->uuid(),
            'backup_type' => 'pre_update',
        ], $this->auth());

        $res->assertStatus(201);
        $this->assertDatabaseHas('backup_history', [
            'store_id' => $this->storeId,
            'backup_type' => 'pre_update',
        ]);
    }
}
