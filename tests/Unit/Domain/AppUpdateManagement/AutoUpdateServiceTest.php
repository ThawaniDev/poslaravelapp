<?php

namespace Tests\Unit\Domain\AppUpdateManagement;

use App\Domain\AppUpdateManagement\Models\AppRelease;
use App\Domain\AppUpdateManagement\Models\AppUpdateStat;
use App\Domain\AppUpdateManagement\Services\AutoUpdateService;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AutoUpdateService Unit Tests
 *
 * Directly tests the service layer with real DB (PostgreSQL test database)
 * to verify business logic independently from HTTP layer.
 */
class AutoUpdateServiceTest extends TestCase
{
    use RefreshDatabase;

    private AutoUpdateService $service;
    private string $storeId;
    private Organization $org;
    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AutoUpdateService();

        $this->org = Organization::create([
            'name' => 'Service Test Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Service Test Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->storeId = $this->store->id;
    }

    private function makeRelease(array $overrides = []): AppRelease
    {
        return AppRelease::create(array_merge([
            'version_number'   => '2.0.0',
            'platform'         => 'ios',
            'channel'          => 'stable',
            'download_url'     => 'https://cdn.example.com/v2.ipa',
            'is_force_update'  => false,
            'is_active'        => true,
            'released_at'      => now(),
        ], $overrides));
    }

    // ─── checkForUpdate ──────────────────────────────────────────────────────

    public function test_check_no_release_returns_no_update(): void
    {
        $result = $this->service->checkForUpdate($this->storeId, '1.0.0', 'ios');

        $this->assertFalse($result['update_available']);
        $this->assertEquals('1.0.0', $result['current_version']);
    }

    public function test_check_newer_release_returns_update_available(): void
    {
        $this->makeRelease(['version_number' => '2.0.0']);

        $result = $this->service->checkForUpdate($this->storeId, '1.0.0', 'ios');

        $this->assertTrue($result['update_available']);
        $this->assertEquals('2.0.0', $result['latest_version']);
    }

    public function test_check_same_version_returns_no_update(): void
    {
        $this->makeRelease(['version_number' => '2.0.0']);

        $result = $this->service->checkForUpdate($this->storeId, '2.0.0', 'ios');

        $this->assertFalse($result['update_available']);
    }

    public function test_check_returns_release_id_and_download_url(): void
    {
        $release = $this->makeRelease([
            'download_url' => 'https://cdn.example.com/v2-special.ipa',
        ]);

        $result = $this->service->checkForUpdate($this->storeId, '1.0.0', 'ios');

        $this->assertEquals($release->id, $result['release_id']);
        $this->assertEquals('https://cdn.example.com/v2-special.ipa', $result['download_url']);
    }

    public function test_check_force_update_when_below_min_version(): void
    {
        $this->makeRelease([
            'is_force_update' => true,
            'min_supported_version' => '1.5.0',
        ]);

        $result = $this->service->checkForUpdate($this->storeId, '1.0.0', 'ios');
        $this->assertTrue($result['is_force_update']);
    }

    public function test_check_not_force_when_above_min_version(): void
    {
        $this->makeRelease([
            'is_force_update' => true,
            'min_supported_version' => '1.5.0',
        ]);

        $result = $this->service->checkForUpdate($this->storeId, '1.9.0', 'ios');
        $this->assertFalse($result['is_force_update']);
    }

    public function test_check_filters_by_platform(): void
    {
        $this->makeRelease(['platform' => 'android']);

        // Request for iOS — android release should not be returned
        $result = $this->service->checkForUpdate($this->storeId, '1.0.0', 'ios');
        $this->assertFalse($result['update_available']);
    }

    public function test_check_filters_by_channel(): void
    {
        $this->makeRelease(['channel' => 'beta', 'version_number' => '3.0.0-beta']);

        $stableResult = $this->service->checkForUpdate($this->storeId, '1.0.0', 'ios', 'stable');
        $this->assertFalse($stableResult['update_available']);

        $betaResult = $this->service->checkForUpdate($this->storeId, '1.0.0', 'ios', 'beta');
        $this->assertTrue($betaResult['update_available']);
    }

    public function test_check_excludes_inactive_releases(): void
    {
        $this->makeRelease(['is_active' => false]);

        $result = $this->service->checkForUpdate($this->storeId, '1.0.0', 'ios');
        $this->assertFalse($result['update_available']);
    }

    public function test_check_picks_latest_released_at_when_multiple(): void
    {
        AppRelease::create([
            'version_number' => '1.5.0', 'platform' => 'ios', 'channel' => 'stable',
            'download_url' => 'https://cdn.example.com/v1.5.ipa',
            'is_active' => true, 'is_force_update' => false,
            'released_at' => now()->subHour(),
        ]);
        AppRelease::create([
            'version_number' => '2.0.0', 'platform' => 'ios', 'channel' => 'stable',
            'download_url' => 'https://cdn.example.com/v2.ipa',
            'is_active' => true, 'is_force_update' => false,
            'released_at' => now(),
        ]);

        $result = $this->service->checkForUpdate($this->storeId, '1.0.0', 'ios');
        $this->assertEquals('2.0.0', $result['latest_version']);
    }

    // ─── reportStatus ────────────────────────────────────────────────────────

    public function test_report_status_creates_new_stat(): void
    {
        $release = $this->makeRelease();

        $result = $this->service->reportStatus($this->storeId, $release->id, 'downloading');

        $this->assertEquals($this->storeId, $result['store_id']);
        $this->assertEquals($release->id, $result['app_release_id']);

        $dbStat = AppUpdateStat::where('store_id', $this->storeId)
            ->where('app_release_id', $release->id)
            ->first();
        $this->assertNotNull($dbStat);
    }

    public function test_report_status_updates_existing_stat(): void
    {
        $release = $this->makeRelease();

        $this->service->reportStatus($this->storeId, $release->id, 'downloading');
        $this->service->reportStatus($this->storeId, $release->id, 'installed');

        $statCount = AppUpdateStat::where('store_id', $this->storeId)
            ->where('app_release_id', $release->id)
            ->count();

        $this->assertEquals(1, $statCount, 'reportStatus must upsert, not insert duplicates.');
    }

    public function test_report_status_stores_error_message_on_failure(): void
    {
        $release = $this->makeRelease();

        $this->service->reportStatus($this->storeId, $release->id, 'failed', 'Disk full');

        $stat = AppUpdateStat::where('store_id', $this->storeId)->first();
        $this->assertEquals('Disk full', $stat->error_message);
    }

    public function test_report_status_clears_error_on_subsequent_success(): void
    {
        $release = $this->makeRelease();

        $this->service->reportStatus($this->storeId, $release->id, 'failed', 'Previous error');
        $this->service->reportStatus($this->storeId, $release->id, 'installed', null);

        $stat = AppUpdateStat::where('store_id', $this->storeId)->first();
        $this->assertNull($stat->error_message);
    }

    // ─── getChangelog ────────────────────────────────────────────────────────

    public function test_get_changelog_returns_active_releases_only(): void
    {
        $this->makeRelease(['version_number' => '1.0.0', 'is_active' => true, 'released_at' => now()->subDay()]);
        $this->makeRelease(['version_number' => '2.0.0', 'is_active' => false]);

        $changelog = $this->service->getChangelog('ios');
        $versions = array_column($changelog, 'version_number');
        $this->assertContains('1.0.0', $versions);
        $this->assertNotContains('2.0.0', $versions);
    }

    public function test_get_changelog_limited_to_10_by_default(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            AppRelease::create([
                'version_number' => "1.0.{$i}", 'platform' => 'ios', 'channel' => 'stable',
                'download_url' => "https://cdn.example.com/v1.0.{$i}.ipa",
                'is_active' => true, 'is_force_update' => false,
                'released_at' => now()->subMinutes($i),
            ]);
        }

        $changelog = $this->service->getChangelog('ios');
        $this->assertCount(10, $changelog);
    }

    public function test_get_changelog_accepts_custom_limit(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            AppRelease::create([
                'version_number' => "1.0.{$i}", 'platform' => 'ios', 'channel' => 'stable',
                'download_url' => "https://cdn.example.com/v1.0.{$i}.ipa",
                'is_active' => true, 'is_force_update' => false,
                'released_at' => now()->subMinutes($i),
            ]);
        }

        $changelog = $this->service->getChangelog('ios', 'stable', 3);
        $this->assertCount(3, $changelog);
    }

    // ─── getUpdateHistory ─────────────────────────────────────────────────────

    public function test_get_update_history_returns_only_store_records(): void
    {
        $release = $this->makeRelease();
        $otherStore = Store::create([
            'organization_id' => $this->org->id, 'name' => 'Other',
            'business_type' => 'grocery', 'currency' => 'SAR',
            'is_active' => true, 'is_main_branch' => false,
        ]);

        AppUpdateStat::create(['store_id' => $this->storeId, 'app_release_id' => $release->id, 'status' => 'installed']);
        AppUpdateStat::create(['store_id' => $otherStore->id, 'app_release_id' => $release->id, 'status' => 'installed']);

        $history = $this->service->getUpdateHistory($this->storeId);
        $this->assertCount(1, $history);
    }

    // ─── getCurrentVersion ───────────────────────────────────────────────────

    public function test_get_current_version_null_when_no_installs(): void
    {
        $result = $this->service->getCurrentVersion($this->storeId, 'ios');
        $this->assertNull($result['version']);
    }

    public function test_get_current_version_returns_installed_version(): void
    {
        $release = $this->makeRelease(['version_number' => '2.5.0']);
        AppUpdateStat::create([
            'store_id' => $this->storeId,
            'app_release_id' => $release->id,
            'status' => 'installed',
        ]);

        $result = $this->service->getCurrentVersion($this->storeId, 'ios');
        $this->assertEquals('2.5.0', $result['version']);
    }

    // ─── getManifest ─────────────────────────────────────────────────────────

    public function test_get_manifest_returns_all_required_fields(): void
    {
        $this->makeRelease([
            'version_number' => '2.0.0',
            'file_checksum' => 'sha256:abcdef',
            'file_size_bytes' => 20971520,
            'rollout_percentage' => 80,
        ]);

        $manifest = $this->service->getManifest('2.0.0', 'ios');

        $this->assertNotNull($manifest);
        $this->assertEquals('2.0.0', $manifest['version']);
        $this->assertEquals('ios', $manifest['platform']);
        $this->assertEquals('stable', $manifest['channel']);
        $this->assertEquals('sha256:abcdef', $manifest['checksum']);
        $this->assertEquals(20971520, $manifest['file_size_bytes']);
        $this->assertEquals(80, $manifest['rollout_percentage']);
        $this->assertArrayHasKey('download_url', $manifest);
        $this->assertArrayHasKey('is_force_update', $manifest);
        $this->assertArrayHasKey('released_at', $manifest);
    }

    public function test_get_manifest_returns_null_when_not_found(): void
    {
        $manifest = $this->service->getManifest('99.0.0', 'ios');
        $this->assertNull($manifest);
    }

    // ─── getDownloadInfo ─────────────────────────────────────────────────────

    public function test_get_download_info_returns_url_and_checksum(): void
    {
        $this->makeRelease([
            'version_number' => '2.0.0',
            'download_url' => 'https://cdn.example.com/v2.ipa',
            'file_checksum' => 'sha256:xyz',
            'file_size_bytes' => 5242880,
        ]);

        $info = $this->service->getDownloadInfo('2.0.0', 'ios');

        $this->assertNotNull($info);
        $this->assertEquals('https://cdn.example.com/v2.ipa', $info['download_url']);
        $this->assertEquals('sha256:xyz', $info['checksum']);
        $this->assertEquals(5242880, $info['file_size_bytes']);
    }

    // ─── getRolloutStatus ────────────────────────────────────────────────────

    public function test_get_rollout_status_no_release_returns_false(): void
    {
        $status = $this->service->getRolloutStatus('ios');
        $this->assertFalse($status['has_active_rollout']);
    }

    public function test_get_rollout_status_zero_stats_returns_zero_adoption(): void
    {
        $this->makeRelease(['rollout_percentage' => 100]);

        $status = $this->service->getRolloutStatus('ios');
        $this->assertTrue($status['has_active_rollout']);
        $this->assertEquals(0, $status['adoption_rate']);
        $this->assertEquals(0, $status['stats']['total_stores']);
    }

    public function test_get_rollout_status_calculates_adoption_rate_correctly(): void
    {
        $release = $this->makeRelease(['rollout_percentage' => 50]);

        $otherStore = Store::create([
            'organization_id' => $this->org->id, 'name' => 'RO Store',
            'business_type' => 'grocery', 'currency' => 'SAR',
            'is_active' => true, 'is_main_branch' => false,
        ]);

        AppUpdateStat::create(['store_id' => $this->storeId, 'app_release_id' => $release->id, 'status' => 'installed']);
        AppUpdateStat::create(['store_id' => $otherStore->id, 'app_release_id' => $release->id, 'status' => 'failed']);

        $status = $this->service->getRolloutStatus('ios');
        // 1 installed out of 2 = 50%
        $this->assertEquals(50.0, $status['adoption_rate']);
    }
}
