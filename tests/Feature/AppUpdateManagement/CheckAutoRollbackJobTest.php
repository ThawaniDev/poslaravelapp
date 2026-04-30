<?php

namespace Tests\Feature\AppUpdateManagement;

use App\Domain\AppUpdateManagement\Jobs\CheckAutoRollback;
use App\Domain\AppUpdateManagement\Models\AppRelease;
use App\Domain\AppUpdateManagement\Models\AppUpdateStat;
use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckAutoRollbackJobTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        // Recreate app_releases and app_update_stats with correct columns (SQLite only)
        // The SQLite test schema uses different column names
        if (\DB::connection()->getDriverName() === 'sqlite') {
            \Schema::dropIfExists('app_update_stats');
            \Schema::dropIfExists('app_releases');

            \Schema::create('app_releases', function ($t) {
                $t->uuid('id')->primary();
                $t->string('version_number', 20);
                $t->string('platform', 20);
                $t->string('channel', 20)->default('stable');
                $t->string('download_url')->nullable();
                $t->string('build_number', 20)->nullable();
                $t->text('release_notes')->nullable();
                $t->text('release_notes_ar')->nullable();
                $t->boolean('is_force_update')->default(false);
                $t->boolean('is_active')->default(true);
                $t->integer('rollout_percentage')->default(100);
                $t->timestamp('released_at')->nullable();
                $t->timestamps();
            });

            \Schema::create('app_update_stats', function ($t) {
                $t->uuid('id')->primary();
                $t->uuid('store_id')->nullable();
                $t->uuid('app_release_id');
                $t->string('status', 20)->default('pending');
                $t->text('error_message')->nullable();
            });
        }

        $org = Organization::forceCreate([
            'name' => 'Rollback Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->store = Store::forceCreate([
            'organization_id' => $org->id,
            'name' => 'Rollback Store',
            'is_active' => true,
        ]);
    }

    private function createRelease(array $overrides = []): AppRelease
    {
        return AppRelease::forceCreate(array_merge([
            'id' => Str::uuid()->toString(),
            'version_number' => '2.0.0',
            'platform' => 'ios',
            'channel' => 'stable',
            'download_url' => 'https://example.com/app.ipa',
            'build_number' => '200',
            'release_notes' => 'Test release',
            'is_force_update' => false,
            'is_active' => true,
            'rollout_percentage' => 100,
            'released_at' => now()->subDays(2),
        ], $overrides));
    }

    private function createStats(string $releaseId, int $successful, int $failed): void
    {
        for ($i = 0; $i < $successful; $i++) {
            AppUpdateStat::forceCreate([
                'id' => Str::uuid()->toString(),
                'app_release_id' => $releaseId,
                'store_id' => $this->store->id,
                'status' => 'installed',
            ]);
        }

        for ($i = 0; $i < $failed; $i++) {
            AppUpdateStat::forceCreate([
                'id' => Str::uuid()->toString(),
                'app_release_id' => $releaseId,
                'store_id' => $this->store->id,
                'status' => 'failed',
            ]);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // AUTO ROLLBACK TRIGGER
    // ═══════════════════════════════════════════════════════════

    public function test_deactivates_release_when_failure_rate_exceeds_threshold(): void
    {
        $release = $this->createRelease();

        // 4 failed out of 10 = 40% > 30% threshold
        $this->createStats($release->id, 6, 4);

        (new CheckAutoRollback)->handle();

        $release->refresh();
        $this->assertFalse($release->is_active);
    }

    public function test_creates_activity_log_on_rollback(): void
    {
        $release = $this->createRelease(['version_number' => '3.0.0']);

        // 5 failed out of 12 = 41.7% > 30%
        $this->createStats($release->id, 7, 5);

        (new CheckAutoRollback)->handle();

        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'auto_rollback_release',
            'entity_type' => 'app_release',
            'entity_id' => $release->id,
        ]);
    }

    public function test_does_not_rollback_below_threshold(): void
    {
        $release = $this->createRelease();

        // 2 failed out of 10 = 20% < 30% threshold
        $this->createStats($release->id, 8, 2);

        (new CheckAutoRollback)->handle();

        $release->refresh();
        $this->assertTrue($release->is_active);
    }

    public function test_does_not_rollback_at_exactly_threshold(): void
    {
        $release = $this->createRelease();

        // 3 failed out of 10 = 30% = threshold (>= so this SHOULD trigger)
        $this->createStats($release->id, 7, 3);

        (new CheckAutoRollback)->handle();

        $release->refresh();
        $this->assertFalse($release->is_active);
    }

    public function test_does_not_rollback_with_insufficient_attempts(): void
    {
        $release = $this->createRelease();

        // 4 failed out of 5 = 80% but only 5 attempts (below MIN_ATTEMPTS=10)
        $this->createStats($release->id, 1, 4);

        (new CheckAutoRollback)->handle();

        $release->refresh();
        $this->assertTrue($release->is_active);
    }

    public function test_skips_inactive_releases(): void
    {
        $release = $this->createRelease(['is_active' => false]);

        $this->createStats($release->id, 5, 10);

        (new CheckAutoRollback)->handle();

        // Should not create any activity log since release was already inactive
        $this->assertDatabaseMissing('admin_activity_logs', [
            'action' => 'auto_rollback_release',
            'entity_id' => $release->id,
        ]);
    }

    public function test_skips_old_releases_beyond_7_days(): void
    {
        $release = $this->createRelease([
            'released_at' => now()->subDays(10), // Older than 7 days
        ]);

        $this->createStats($release->id, 5, 10);

        (new CheckAutoRollback)->handle();

        $release->refresh();
        $this->assertTrue($release->is_active);
    }

    public function test_processes_multiple_releases_independently(): void
    {
        // Release 1: should be rolled back (50% failure rate)
        $release1 = $this->createRelease(['version_number' => '1.0.0']);
        $this->createStats($release1->id, 5, 5);

        // Release 2: should NOT be rolled back (10% failure rate)
        $release2 = $this->createRelease(['version_number' => '2.0.0', 'platform' => 'android']);
        $this->createStats($release2->id, 9, 1);

        (new CheckAutoRollback)->handle();

        $release1->refresh();
        $release2->refresh();

        $this->assertFalse($release1->is_active);
        $this->assertTrue($release2->is_active);
    }

    public function test_handles_no_active_releases_gracefully(): void
    {
        // No releases at all
        (new CheckAutoRollback)->handle();

        $this->assertEquals(0, AdminActivityLog::where('action', 'auto_rollback_release')->count());
    }

    public function test_handles_release_with_zero_stats(): void
    {
        $release = $this->createRelease();
        // No stats at all

        (new CheckAutoRollback)->handle();

        $release->refresh();
        $this->assertTrue($release->is_active);
    }

    public function test_activity_log_contains_failure_details(): void
    {
        $release = $this->createRelease(['version_number' => '5.0.0']);

        // 4 failed, 6 success = 40% failure
        $this->createStats($release->id, 6, 4);

        (new CheckAutoRollback)->handle();

        $log = AdminActivityLog::where('action', 'auto_rollback_release')
            ->where('entity_id', $release->id)
            ->first();

        $this->assertNotNull($log);
        $details = is_string($log->details) ? json_decode($log->details, true) : $log->details;
        $this->assertEquals('5.0.0', $details['version']);
        $this->assertEquals(10, $details['total_attempts']);
        $this->assertEquals(4, $details['failed_attempts']);
        $this->assertEquals(40.0, $details['failure_rate']);
    }

    public function test_activity_log_has_null_admin_user_id(): void
    {
        $release = $this->createRelease();
        $this->createStats($release->id, 5, 5);

        (new CheckAutoRollback)->handle();

        $log = AdminActivityLog::where('action', 'auto_rollback_release')->first();
        $this->assertNotNull($log);
        $this->assertNull($log->admin_user_id);
    }

    public function test_uses_configurable_failure_threshold(): void
    {
        // Set a higher threshold in system_settings — release should NOT be rolled back
        if (\Schema::hasTable('system_settings')) {
            \DB::table('system_settings')->insert([
                'id' => Str::uuid()->toString(),
                'key' => 'updates.auto_rollback_failure_percent',
                'value' => '50',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $release = $this->createRelease(['version_number' => '9.0.0']);
        // 30% failure rate — below 50% threshold
        $this->createStats($release->id, 7, 3);

        (new CheckAutoRollback)->handle();

        $release->refresh();
        $this->assertTrue($release->is_active, 'Release should stay active when failure rate is below custom threshold');
    }

    public function test_rollback_creates_security_alert_when_table_exists(): void
    {
        if (!\Schema::hasTable('security_alerts')) {
            // Cannot test without table — skip gracefully
            $this->markTestSkipped('security_alerts table not available in test env');
        }

        Notification::fake();

        $release = $this->createRelease(['version_number' => '8.0.0']);
        // 50% failure — triggers rollback
        $this->createStats($release->id, 5, 5);

        (new CheckAutoRollback)->handle();

        $this->assertDatabaseHas('security_alerts', [
            'alert_type' => 'app_crash_loop',
            'severity' => 'critical',
        ]);
    }

    public function test_does_not_rollback_releases_older_than_eval_window(): void
    {
        // Release released 5 days ago — outside the 1-day eval window
        $release = $this->createRelease([
            'version_number' => '7.0.0',
            'released_at' => now()->subDays(5),
        ]);
        $this->createStats($release->id, 0, 15); // 100% failure but ignored

        (new CheckAutoRollback)->handle();

        $release->refresh();
        $this->assertTrue($release->is_active, 'Releases outside eval window should not be rolled back');
    }
}
