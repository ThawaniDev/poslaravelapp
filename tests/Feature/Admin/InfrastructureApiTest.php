<?php

namespace Tests\Feature\Admin;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\AdminPanel\Models\SystemHealthCheck;
use App\Domain\BackupSync\Models\DatabaseBackup;
use App\Domain\BackupSync\Models\ProviderBackupStatus;
use App\Domain\Core\Models\FailedJob;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\SystemConfig\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InfrastructureApiTest extends TestCase
{
    use RefreshDatabase;

    private string $prefix = '/api/v2/admin/infrastructure';

    protected function setUp(): void
    {
        parent::setUp();

        $admin = AdminUser::forceCreate([
            'id'            => Str::uuid(),
            'name'          => 'Admin',
            'email'         => 'admin@test.com',
            'password_hash' => bcrypt('password'),
            'is_active'     => true,
        ]);

        Sanctum::actingAs($admin, ['*'], 'admin-api');
    }

    private function createStore(): Store
    {
        $org = Organization::forceCreate([
            'id'   => Str::uuid(),
            'name' => 'Test Org',
        ]);

        return Store::forceCreate([
            'id'              => Str::uuid(),
            'organization_id' => $org->id,
            'name'            => 'Test Store',
        ]);
    }

    // ────────────────────────────────────────────────────────────
    // OVERVIEW
    // ────────────────────────────────────────────────────────────
    public function test_overview_returns_aggregated_stats(): void
    {
        $store = $this->createStore();

        FailedJob::forceCreate([
            'uuid'       => Str::uuid(),
            'connection' => 'redis',
            'queue'      => 'default',
            'payload'    => '{}',
            'exception'  => 'Test exception',
            'failed_at'  => now(),
        ]);

        DatabaseBackup::forceCreate([
            'id'          => Str::uuid(),
            'backup_type' => 'auto_daily',
            'file_path'   => '/backups/test.sql',
            'status'      => 'completed',
            'started_at'  => now(),
            'completed_at' => now(),
        ]);

        SystemHealthCheck::forceCreate([
            'id'        => Str::uuid(),
            'service'   => 'database',
            'status'    => 'healthy',
            'checked_at' => now(),
        ]);

        ProviderBackupStatus::forceCreate([
            'id'          => Str::uuid(),
            'store_id'    => $store->id,
            'terminal_id' => Str::uuid(),
            'status'      => 'healthy',
        ]);

        $resp = $this->getJson("{$this->prefix}/overview");
        $resp->assertOk()
             ->assertJsonPath('data.failed_jobs.total', 1)
             ->assertJsonPath('data.failed_jobs.last_24h', 1)
             ->assertJsonPath('data.database_backups.total', 1)
             ->assertJsonPath('data.database_backups.completed', 1)
             ->assertJsonPath('data.health_checks.total', 1)
             ->assertJsonPath('data.health_checks.healthy', 1)
             ->assertJsonPath('data.provider_backups.total', 1)
             ->assertJsonPath('data.provider_backups.healthy', 1);
    }

    public function test_overview_returns_zeros_when_empty(): void
    {
        $resp = $this->getJson("{$this->prefix}/overview");
        $resp->assertOk()
             ->assertJsonPath('data.failed_jobs.total', 0)
             ->assertJsonPath('data.database_backups.total', 0)
             ->assertJsonPath('data.health_checks.total', 0);
    }

    // ────────────────────────────────────────────────────────────
    // FAILED JOBS
    // ────────────────────────────────────────────────────────────
    public function test_failed_jobs_returns_paginated(): void
    {
        FailedJob::forceCreate([
            'uuid'       => Str::uuid(),
            'connection' => 'redis',
            'queue'      => 'default',
            'payload'    => '{}',
            'exception'  => 'Test exception',
            'failed_at'  => now(),
        ]);

        $resp = $this->getJson("{$this->prefix}/failed-jobs");
        $resp->assertOk()
             ->assertJsonPath('data.total', 1);
    }

    public function test_failed_jobs_filters_by_queue(): void
    {
        FailedJob::forceCreate([
            'uuid' => Str::uuid(), 'connection' => 'redis',
            'queue' => 'notifications', 'payload' => '{}',
            'exception' => 'err', 'failed_at' => now(),
        ]);
        FailedJob::forceCreate([
            'uuid' => Str::uuid(), 'connection' => 'redis',
            'queue' => 'billing', 'payload' => '{}',
            'exception' => 'err', 'failed_at' => now(),
        ]);

        $resp = $this->getJson("{$this->prefix}/failed-jobs?queue=notifications");
        $resp->assertOk()
             ->assertJsonPath('data.total', 1);
    }

    public function test_failed_jobs_search_by_exception(): void
    {
        FailedJob::forceCreate([
            'uuid' => Str::uuid(), 'connection' => 'redis',
            'queue' => 'default', 'payload' => '{}',
            'exception' => 'UniqueException: something broke',
            'failed_at' => now(),
        ]);

        $resp = $this->getJson("{$this->prefix}/failed-jobs?search=UniqueException");
        $resp->assertOk()
             ->assertJsonPath('data.total', 1);
    }

    public function test_show_failed_job(): void
    {
        $job = FailedJob::forceCreate([
            'uuid'       => Str::uuid(),
            'connection' => 'redis',
            'queue'      => 'default',
            'payload'    => '{"job":"test"}',
            'exception'  => 'Full exception trace',
            'failed_at'  => now(),
        ]);

        $resp = $this->getJson("{$this->prefix}/failed-jobs/{$job->id}");
        $resp->assertOk()
             ->assertJsonPath('data.queue', 'default');
    }

    public function test_show_failed_job_not_found(): void
    {
        $resp = $this->getJson("{$this->prefix}/failed-jobs/999999");
        $resp->assertNotFound();
    }

    public function test_retry_failed_job(): void
    {
        $job = FailedJob::forceCreate([
            'uuid'       => Str::uuid(),
            'connection' => 'redis',
            'queue'      => 'default',
            'payload'    => '{}',
            'exception'  => 'err',
            'failed_at'  => now(),
        ]);

        $resp = $this->postJson("{$this->prefix}/failed-jobs/{$job->id}/retry");
        $resp->assertOk();

        $this->assertDatabaseMissing('failed_jobs', ['id' => $job->id]);
    }

    public function test_delete_failed_job(): void
    {
        $job = FailedJob::forceCreate([
            'uuid'       => Str::uuid(),
            'connection' => 'redis',
            'queue'      => 'default',
            'payload'    => '{}',
            'exception'  => 'err',
            'failed_at'  => now(),
        ]);

        $resp = $this->deleteJson("{$this->prefix}/failed-jobs/{$job->id}");
        $resp->assertOk();

        $this->assertDatabaseMissing('failed_jobs', ['id' => $job->id]);
    }

    // ────────────────────────────────────────────────────────────
    // DATABASE BACKUPS
    // ────────────────────────────────────────────────────────────
    public function test_database_backups_returns_paginated(): void
    {
        DatabaseBackup::forceCreate([
            'id'          => Str::uuid(),
            'backup_type' => 'auto_daily',
            'file_path'   => '/backups/test.sql',
            'status'      => 'completed',
            'started_at'  => now(),
        ]);

        $resp = $this->getJson("{$this->prefix}/database-backups");
        $resp->assertOk()
             ->assertJsonPath('data.total', 1);
    }

    public function test_database_backups_filters_by_status(): void
    {
        DatabaseBackup::forceCreate([
            'id' => Str::uuid(), 'backup_type' => 'auto_daily',
            'file_path' => '/b/1.sql', 'status' => 'completed', 'started_at' => now(),
        ]);
        DatabaseBackup::forceCreate([
            'id' => Str::uuid(), 'backup_type' => 'manual',
            'file_path' => '/b/2.sql', 'status' => 'failed', 'started_at' => now(),
        ]);

        $resp = $this->getJson("{$this->prefix}/database-backups?status=failed");
        $resp->assertOk()
             ->assertJsonPath('data.total', 1);
    }

    public function test_database_backups_filters_by_type(): void
    {
        DatabaseBackup::forceCreate([
            'id' => Str::uuid(), 'backup_type' => 'auto_daily',
            'file_path' => '/b/1.sql', 'status' => 'completed', 'started_at' => now(),
        ]);
        DatabaseBackup::forceCreate([
            'id' => Str::uuid(), 'backup_type' => 'manual',
            'file_path' => '/b/2.sql', 'status' => 'completed', 'started_at' => now(),
        ]);

        $resp = $this->getJson("{$this->prefix}/database-backups?backup_type=manual");
        $resp->assertOk()
             ->assertJsonPath('data.total', 1);
    }

    public function test_show_database_backup(): void
    {
        $backup = DatabaseBackup::forceCreate([
            'id'               => Str::uuid(),
            'backup_type'      => 'auto_daily',
            'file_path'        => '/backups/test.sql',
            'file_size_bytes'  => 1024000,
            'status'           => 'completed',
            'started_at'       => now(),
            'completed_at'     => now(),
        ]);

        $resp = $this->getJson("{$this->prefix}/database-backups/{$backup->id}");
        $resp->assertOk()
             ->assertJsonPath('data.file_path', '/backups/test.sql');
    }

    public function test_show_database_backup_not_found(): void
    {
        $resp = $this->getJson("{$this->prefix}/database-backups/" . Str::uuid());
        $resp->assertNotFound();
    }

    // ────────────────────────────────────────────────────────────
    // HEALTH CHECKS
    // ────────────────────────────────────────────────────────────
    public function test_health_checks_returns_paginated(): void
    {
        SystemHealthCheck::forceCreate([
            'id'        => Str::uuid(),
            'service'   => 'database',
            'status'    => 'healthy',
            'checked_at' => now(),
        ]);

        $resp = $this->getJson("{$this->prefix}/health-checks");
        $resp->assertOk()
             ->assertJsonPath('data.total', 1);
    }

    public function test_health_checks_filters_by_status(): void
    {
        SystemHealthCheck::forceCreate(['id' => Str::uuid(), 'service' => 'database', 'status' => 'healthy', 'checked_at' => now()]);
        SystemHealthCheck::forceCreate(['id' => Str::uuid(), 'service' => 'redis', 'status' => 'critical', 'checked_at' => now()]);

        $resp = $this->getJson("{$this->prefix}/health-checks?status=critical");
        $resp->assertOk()
             ->assertJsonPath('data.total', 1);
    }

    public function test_health_checks_filters_by_service(): void
    {
        SystemHealthCheck::forceCreate(['id' => Str::uuid(), 'service' => 'database', 'status' => 'healthy', 'checked_at' => now()]);
        SystemHealthCheck::forceCreate(['id' => Str::uuid(), 'service' => 'redis', 'status' => 'healthy', 'checked_at' => now()]);

        $resp = $this->getJson("{$this->prefix}/health-checks?service=redis");
        $resp->assertOk()
             ->assertJsonPath('data.total', 1);
    }

    public function test_show_health_check(): void
    {
        $check = SystemHealthCheck::forceCreate([
            'id'              => Str::uuid(),
            'service'         => 'database',
            'status'          => 'healthy',
            'response_time_ms' => 45,
            'checked_at'      => now(),
        ]);

        $resp = $this->getJson("{$this->prefix}/health-checks/{$check->id}");
        $resp->assertOk()
             ->assertJsonPath('data.service', 'database');
    }

    // ────────────────────────────────────────────────────────────
    // PROVIDER BACKUP STATUS
    // ────────────────────────────────────────────────────────────
    public function test_provider_backups_returns_paginated(): void
    {
        $store = $this->createStore();
        ProviderBackupStatus::forceCreate([
            'id'          => Str::uuid(),
            'store_id'    => $store->id,
            'terminal_id' => Str::uuid(),
            'status'      => 'healthy',
        ]);

        $resp = $this->getJson("{$this->prefix}/provider-backups");
        $resp->assertOk()
             ->assertJsonPath('data.total', 1);
    }

    public function test_provider_backups_filters_by_status(): void
    {
        $store = $this->createStore();
        ProviderBackupStatus::forceCreate([
            'id' => Str::uuid(), 'store_id' => $store->id,
            'terminal_id' => Str::uuid(), 'status' => 'healthy',
        ]);
        ProviderBackupStatus::forceCreate([
            'id' => Str::uuid(), 'store_id' => $store->id,
            'terminal_id' => Str::uuid(), 'status' => 'critical',
        ]);

        $resp = $this->getJson("{$this->prefix}/provider-backups?status=critical");
        $resp->assertOk()
             ->assertJsonPath('data.total', 1);
    }

    public function test_provider_backups_filters_by_store(): void
    {
        $store1 = $this->createStore();
        $store2 = $this->createStore();

        ProviderBackupStatus::forceCreate([
            'id' => Str::uuid(), 'store_id' => $store1->id,
            'terminal_id' => Str::uuid(), 'status' => 'healthy',
        ]);
        ProviderBackupStatus::forceCreate([
            'id' => Str::uuid(), 'store_id' => $store2->id,
            'terminal_id' => Str::uuid(), 'status' => 'healthy',
        ]);

        $resp = $this->getJson("{$this->prefix}/provider-backups?store_id={$store1->id}");
        $resp->assertOk()
             ->assertJsonPath('data.total', 1);
    }

    public function test_show_provider_backup(): void
    {
        $store = $this->createStore();
        $backup = ProviderBackupStatus::forceCreate([
            'id'                   => Str::uuid(),
            'store_id'             => $store->id,
            'terminal_id'          => Str::uuid(),
            'last_successful_sync' => now(),
            'storage_used_bytes'   => 5000000,
            'status'               => 'healthy',
        ]);

        $resp = $this->getJson("{$this->prefix}/provider-backups/{$backup->id}");
        $resp->assertOk()
             ->assertJsonPath('data.status', 'healthy');
    }

    // ────────────────────────────────────────────────────────────
    // SYSTEM SETTINGS
    // ────────────────────────────────────────────────────────────
    public function test_system_settings_returns_paginated(): void
    {
        SystemSetting::forceCreate([
            'id'    => Str::uuid(),
            'key'   => 'app.timezone',
            'value' => json_encode('Asia/Muscat'),
            'group' => 'locale',
        ]);

        $resp = $this->getJson("{$this->prefix}/system-settings");
        $resp->assertOk()
             ->assertJsonPath('data.total', 1);
    }

    public function test_system_settings_filters_by_group(): void
    {
        SystemSetting::forceCreate([
            'id' => Str::uuid(), 'key' => 'app.timezone',
            'value' => json_encode('Asia/Muscat'), 'group' => 'locale',
        ]);
        SystemSetting::forceCreate([
            'id' => Str::uuid(), 'key' => 'mail.provider',
            'value' => json_encode('smtp'), 'group' => 'email',
        ]);

        $resp = $this->getJson("{$this->prefix}/system-settings?group=email");
        $resp->assertOk()
             ->assertJsonPath('data.total', 1);
    }

    public function test_show_system_setting(): void
    {
        $setting = SystemSetting::forceCreate([
            'id'    => Str::uuid(),
            'key'   => 'app.name',
            'value' => json_encode('Wameed POS'),
            'group' => 'locale',
        ]);

        $resp = $this->getJson("{$this->prefix}/system-settings/{$setting->id}");
        $resp->assertOk()
             ->assertJsonPath('data.key', 'app.name');
    }

    // ────────────────────────────────────────────────────────────
    // SERVER METRICS
    // ────────────────────────────────────────────────────────────
    public function test_server_metrics_returns_runtime_info(): void
    {
        $resp = $this->getJson("{$this->prefix}/server-metrics");
        $resp->assertOk()
             ->assertJsonStructure([
                 'data' => ['php_version', 'laravel_version', 'memory_usage', 'cache_driver', 'queue_driver'],
             ]);
    }

    // ────────────────────────────────────────────────────────────
    // STORAGE USAGE
    // ────────────────────────────────────────────────────────────
    public function test_storage_usage_returns_totals(): void
    {
        DatabaseBackup::forceCreate([
            'id' => Str::uuid(), 'backup_type' => 'auto_daily',
            'file_path' => '/b/1.sql', 'file_size_bytes' => 1000,
            'status' => 'completed', 'started_at' => now(),
        ]);

        $store = $this->createStore();
        ProviderBackupStatus::forceCreate([
            'id'                => Str::uuid(),
            'store_id'          => $store->id,
            'terminal_id'       => Str::uuid(),
            'storage_used_bytes' => 2000,
            'status'            => 'healthy',
        ]);

        $resp = $this->getJson("{$this->prefix}/storage-usage");
        $resp->assertOk()
             ->assertJsonPath('data.backup_storage_bytes', 1000)
             ->assertJsonPath('data.provider_storage_bytes', 2000)
             ->assertJsonPath('data.total_bytes', 3000);
    }

    public function test_storage_usage_empty(): void
    {
        $resp = $this->getJson("{$this->prefix}/storage-usage");
        $resp->assertOk()
             ->assertJsonPath('data.total_bytes', 0);
    }

    // ────────────────────────────────────────────────────────────
    // CACHE MANAGEMENT
    // ────────────────────────────────────────────────────────────
    public function test_cache_stats(): void
    {
        $resp = $this->getJson("{$this->prefix}/cache/stats");
        $resp->assertOk()
             ->assertJsonStructure(['data' => ['driver', 'prefix']]);
    }

    public function test_flush_cache(): void
    {
        $resp = $this->postJson("{$this->prefix}/cache/flush");
        $resp->assertOk()
             ->assertJsonPath('message', 'Cache flushed successfully');
    }

    // ────────────────────────────────────────────────────────────
    // AUTH
    // ────────────────────────────────────────────────────────────
    public function test_unauthenticated_returns_401(): void
    {
        // Reset auth
        $this->app['auth']->forgetGuards();

        $resp = $this->getJson("{$this->prefix}/overview");
        $resp->assertUnauthorized();
    }
}
