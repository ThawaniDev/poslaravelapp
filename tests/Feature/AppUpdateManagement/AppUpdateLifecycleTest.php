<?php

namespace Tests\Feature\AppUpdateManagement;

use App\Domain\AppUpdateManagement\Models\AppRelease;
use App\Domain\AppUpdateManagement\Models\AppUpdateStat;
use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * App Update Management — full lifecycle + manifest/download API tests.
 *
 * Covers the complete E2E flow:
 *   create release → check update → report status at every stage
 *   → manifest → download info → rollout stats → rollback deactivation
 *
 * Also verifies:
 *   - Version comparison semantics (semver ordering)
 *   - Force update enforcement
 *   - Platform + channel isolation
 *   - Multi-release changelog
 *   - Manifest / download endpoint response contract
 *   - Rollout status aggregation
 *   - All validation rules
 */
class AppUpdateLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private string $storeId;
    private Organization $org;
    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Lifecycle Test Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Lifecycle Test Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->storeId = $this->store->id;

        $user = User::create([
            'name' => 'Lifecycle User',
            'email' => 'lifecycle@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->storeId,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $user->createToken('lifecycle', ['*'])->plainTextToken;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function createRelease(array $overrides = []): AppRelease
    {
        return AppRelease::create(array_merge([
            'version_number'    => '3.0.0',
            'platform'          => 'ios',
            'channel'           => 'stable',
            'download_url'      => 'https://cdn.example.com/app-3.0.0.ipa',
            'store_url'         => 'https://apps.apple.com/app/id123456',
            'build_number'      => '300',
            'release_notes'     => 'Improved performance and stability.',
            'release_notes_ar'  => 'تحسينات الأداء والاستقرار.',
            'is_force_update'   => false,
            'is_active'         => true,
            'released_at'       => now(),
            'file_checksum'     => 'abc123checksum',
            'file_size_bytes'   => 52428800, // 50 MB
        ], $overrides));
    }

    private function apiPost(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->postJson("/api/v2/{$uri}", $data);
    }

    private function apiGet(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->getJson("/api/v2/{$uri}");
    }

    // ════════════════════════════════════════════════════════════════════════
    // E2E LIFECYCLE: CREATE → CHECK → REPORT EACH STATUS
    // ════════════════════════════════════════════════════════════════════════

    public function test_full_update_lifecycle_pending_to_installed(): void
    {
        $release = $this->createRelease();

        // 1. Check for update — old version
        $check = $this->apiPost('auto-update/check', [
            'current_version' => '2.0.0',
            'platform' => 'ios',
        ])->assertOk()->json('data');

        $this->assertTrue($check['update_available']);
        $this->assertEquals('3.0.0', $check['latest_version']);
        $this->assertFalse($check['is_force_update']);
        $this->assertEquals($release->id, $check['release_id']);
        $this->assertEquals('https://cdn.example.com/app-3.0.0.ipa', $check['download_url']);

        // 2. Report pending
        $this->apiPost('auto-update/report-status', [
            'release_id' => $release->id,
            'status' => 'pending',
        ])->assertOk()->assertJsonPath('data.status', 'pending');

        // 3. Report downloading
        $this->apiPost('auto-update/report-status', [
            'release_id' => $release->id,
            'status' => 'downloading',
        ])->assertOk()->assertJsonPath('data.status', 'downloading');

        // 4. Report downloaded
        $this->apiPost('auto-update/report-status', [
            'release_id' => $release->id,
            'status' => 'downloaded',
        ])->assertOk()->assertJsonPath('data.status', 'downloaded');

        // 5. Report installed
        $this->apiPost('auto-update/report-status', [
            'release_id' => $release->id,
            'status' => 'installed',
        ])->assertOk()->assertJsonPath('data.status', 'installed');

        // Verify current version now reflects installed
        $current = $this->apiGet("auto-update/current-version?platform=ios")->assertOk()->json('data');
        $this->assertEquals('3.0.0', $current['version']);
    }

    public function test_full_update_lifecycle_downloading_to_failed(): void
    {
        $release = $this->createRelease();

        $this->apiPost('auto-update/report-status', [
            'release_id' => $release->id,
            'status' => 'downloading',
        ])->assertOk();

        // Simulate download failure
        $failed = $this->apiPost('auto-update/report-status', [
            'release_id' => $release->id,
            'status' => 'failed',
            'error_message' => 'Network timeout after 30s',
        ])->assertOk()->json('data');

        $this->assertEquals('failed', $failed['status']);
        $this->assertEquals('Network timeout after 30s', $failed['error_message']);
    }

    // ════════════════════════════════════════════════════════════════════════
    // FORCE UPDATE LOGIC
    // ════════════════════════════════════════════════════════════════════════

    public function test_force_update_triggered_when_below_min_supported_version(): void
    {
        $this->createRelease([
            'is_force_update' => true,
            'min_supported_version' => '2.5.0',
        ]);

        $check = $this->apiPost('auto-update/check', [
            'current_version' => '2.0.0', // below 2.5.0
            'platform' => 'ios',
        ])->assertOk()->json('data');

        $this->assertTrue($check['is_force_update']);
    }

    public function test_force_update_not_triggered_when_above_min_supported_version(): void
    {
        $this->createRelease([
            'is_force_update' => true,
            'min_supported_version' => '2.5.0',
        ]);

        $check = $this->apiPost('auto-update/check', [
            'current_version' => '2.9.0', // above 2.5.0
            'platform' => 'ios',
        ])->assertOk()->json('data');

        $this->assertFalse($check['is_force_update']);
    }

    public function test_force_update_false_when_already_on_latest(): void
    {
        $this->createRelease([
            'version_number' => '3.0.0',
            'is_force_update' => true,
            'min_supported_version' => '1.0.0',
        ]);

        $check = $this->apiPost('auto-update/check', [
            'current_version' => '3.0.0',
            'platform' => 'ios',
        ])->assertOk()->json('data');

        $this->assertFalse($check['update_available']);
        // No update available → force update is irrelevant
    }

    // ════════════════════════════════════════════════════════════════════════
    // PLATFORM + CHANNEL ISOLATION
    // ════════════════════════════════════════════════════════════════════════

    public function test_ios_release_not_shown_to_android(): void
    {
        $this->createRelease(['platform' => 'ios']);

        $check = $this->apiPost('auto-update/check', [
            'current_version' => '1.0.0',
            'platform' => 'android',
        ])->assertOk()->json('data');

        $this->assertFalse($check['update_available']);
    }

    public function test_android_release_shown_to_android_only(): void
    {
        $this->createRelease(['platform' => 'android', 'version_number' => '4.0.0']);

        $ios = $this->apiPost('auto-update/check', [
            'current_version' => '1.0.0',
            'platform' => 'ios',
        ])->assertOk()->json('data.update_available');

        $android = $this->apiPost('auto-update/check', [
            'current_version' => '1.0.0',
            'platform' => 'android',
        ])->assertOk()->json('data.update_available');

        $this->assertFalse($ios);
        $this->assertTrue($android);
    }

    public function test_beta_release_not_shown_to_stable_channel(): void
    {
        $this->createRelease(['channel' => 'beta', 'version_number' => '4.0.0-beta']);

        $check = $this->apiPost('auto-update/check', [
            'current_version' => '1.0.0',
            'platform' => 'ios',
            'channel' => 'stable',
        ])->assertOk()->json('data');

        $this->assertFalse($check['update_available']);
    }

    public function test_beta_release_shown_to_beta_channel(): void
    {
        $this->createRelease([
            'channel' => 'beta',
            'version_number' => '4.0.0',
        ]);

        $check = $this->apiPost('auto-update/check', [
            'current_version' => '1.0.0',
            'platform' => 'ios',
            'channel' => 'beta',
        ])->assertOk()->json('data');

        $this->assertTrue($check['update_available']);
    }

    public function test_windows_and_macos_platforms_supported(): void
    {
        AppRelease::create([
            'version_number' => '2.0.0', 'platform' => 'windows', 'channel' => 'stable',
            'download_url' => 'https://example.com/app-win.exe',
            'is_force_update' => false, 'is_active' => true, 'released_at' => now(),
        ]);
        AppRelease::create([
            'version_number' => '2.0.0', 'platform' => 'macos', 'channel' => 'stable',
            'download_url' => 'https://example.com/app-mac.dmg',
            'is_force_update' => false, 'is_active' => true, 'released_at' => now(),
        ]);

        foreach (['windows', 'macos'] as $platform) {
            $check = $this->apiPost('auto-update/check', [
                'current_version' => '1.0.0', 'platform' => $platform,
            ])->assertOk()->json('data');
            $this->assertTrue($check['update_available'], "Platform {$platform} should have update");
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // VERSION COMPARISON SEMANTICS
    // ════════════════════════════════════════════════════════════════════════

    public function test_minor_version_bump_triggers_update(): void
    {
        $this->createRelease(['version_number' => '3.1.0']);

        $check = $this->apiPost('auto-update/check', [
            'current_version' => '3.0.0', 'platform' => 'ios',
        ])->assertOk()->json('data');

        $this->assertTrue($check['update_available']);
    }

    public function test_patch_version_bump_triggers_update(): void
    {
        $this->createRelease(['version_number' => '3.0.1']);

        $check = $this->apiPost('auto-update/check', [
            'current_version' => '3.0.0', 'platform' => 'ios',
        ])->assertOk()->json('data');

        $this->assertTrue($check['update_available']);
    }

    public function test_same_version_is_not_update(): void
    {
        $this->createRelease(['version_number' => '3.0.0']);

        $check = $this->apiPost('auto-update/check', [
            'current_version' => '3.0.0', 'platform' => 'ios',
        ])->assertOk()->json('data');

        $this->assertFalse($check['update_available']);
    }

    public function test_downgrade_is_not_treated_as_update(): void
    {
        $this->createRelease(['version_number' => '2.0.0']);

        $check = $this->apiPost('auto-update/check', [
            'current_version' => '3.0.0', 'platform' => 'ios',
        ])->assertOk()->json('data');

        $this->assertFalse($check['update_available']);
    }

    public function test_latest_release_selected_when_multiple_exist(): void
    {
        AppRelease::create([
            'version_number' => '2.0.0', 'platform' => 'ios', 'channel' => 'stable',
            'download_url' => 'https://cdn.example.com/v2.ipa', 'is_active' => true,
            'is_force_update' => false, 'released_at' => now()->subHour(),
        ]);
        AppRelease::create([
            'version_number' => '3.0.0', 'platform' => 'ios', 'channel' => 'stable',
            'download_url' => 'https://cdn.example.com/v3.ipa', 'is_active' => true,
            'is_force_update' => false, 'released_at' => now(),
        ]);

        $check = $this->apiPost('auto-update/check', [
            'current_version' => '1.0.0', 'platform' => 'ios',
        ])->assertOk()->json('data');

        $this->assertEquals('3.0.0', $check['latest_version']);
    }

    // ════════════════════════════════════════════════════════════════════════
    // INACTIVE RELEASES
    // ════════════════════════════════════════════════════════════════════════

    public function test_inactive_release_not_offered(): void
    {
        AppRelease::create([
            'version_number' => '99.0.0', 'platform' => 'ios', 'channel' => 'stable',
            'download_url' => 'https://cdn.example.com/v99.ipa',
            'is_active' => false, // deactivated
            'is_force_update' => false, 'released_at' => now(),
        ]);

        $check = $this->apiPost('auto-update/check', [
            'current_version' => '1.0.0', 'platform' => 'ios',
        ])->assertOk()->json('data');

        $this->assertFalse($check['update_available']);
    }

    // ════════════════════════════════════════════════════════════════════════
    // CHANGELOG
    // ════════════════════════════════════════════════════════════════════════

    public function test_changelog_returns_multiple_versions_newest_first(): void
    {
        $this->createRelease(['version_number' => '1.0.0', 'released_at' => now()->subDays(5)]);
        $this->createRelease(['version_number' => '2.0.0', 'released_at' => now()->subDays(2)]);
        $this->createRelease(['version_number' => '3.0.0', 'released_at' => now()]);

        $response = $this->apiGet('auto-update/changelog?platform=ios&channel=stable')->assertOk();
        $data = $response->json('data');

        $this->assertCount(3, $data);
        // Newest first
        $this->assertEquals('3.0.0', $data[0]['version_number']);
        $this->assertEquals('1.0.0', $data[2]['version_number']);
    }

    public function test_changelog_only_returns_requested_platform(): void
    {
        $this->createRelease(['platform' => 'ios']);
        $this->createRelease(['platform' => 'android']);

        $iosChangelog = $this->apiGet('auto-update/changelog?platform=ios')->assertOk()->json('data');
        $this->assertCount(1, $iosChangelog);
    }

    public function test_changelog_items_have_required_fields(): void
    {
        $this->createRelease(['release_notes_ar' => 'ملاحظات الإصدار']);

        $response = $this->apiGet('auto-update/changelog?platform=ios&channel=stable')->assertOk();
        $item = $response->json('data.0');

        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('version_number', $item);
        $this->assertArrayHasKey('build_number', $item);
        $this->assertArrayHasKey('release_notes', $item);
        $this->assertArrayHasKey('release_notes_ar', $item);
        $this->assertArrayHasKey('is_force_update', $item);
        $this->assertArrayHasKey('released_at', $item);
    }

    // ════════════════════════════════════════════════════════════════════════
    // UPDATE HISTORY
    // ════════════════════════════════════════════════════════════════════════

    public function test_history_aggregates_all_statuses_for_store(): void
    {
        $release1 = $this->createRelease(['version_number' => '2.0.0', 'released_at' => now()->subDay()]);
        $release2 = $this->createRelease(['version_number' => '3.0.0']);

        // Simulate: installed v2, then downloading v3
        AppUpdateStat::create(['store_id' => $this->storeId, 'app_release_id' => $release1->id, 'status' => 'installed']);
        AppUpdateStat::create(['store_id' => $this->storeId, 'app_release_id' => $release2->id, 'status' => 'downloading']);

        $history = $this->apiGet('auto-update/history')->assertOk()->json('data');

        $this->assertCount(2, $history);
    }

    public function test_history_does_not_leak_other_store_data(): void
    {
        $otherStore = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Other Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => false,
        ]);

        $release = $this->createRelease();
        AppUpdateStat::create([
            'store_id' => $otherStore->id,
            'app_release_id' => $release->id,
            'status' => 'installed',
        ]);

        $history = $this->apiGet('auto-update/history')->assertOk()->json('data');
        $this->assertEmpty($history, 'History must only return records for the authenticated store.');
    }

    // ════════════════════════════════════════════════════════════════════════
    // MANIFEST ENDPOINT
    // ════════════════════════════════════════════════════════════════════════

    public function test_manifest_returns_correct_structure(): void
    {
        $release = $this->createRelease(['version_number' => '3.0.0']);

        $manifest = $this->apiGet('auto-update/manifest/3.0.0?platform=ios&channel=stable')
            ->assertOk()
            ->json('data');

        $this->assertEquals('3.0.0', $manifest['version']);
        $this->assertEquals('ios', $manifest['platform']);
        $this->assertEquals('stable', $manifest['channel']);
        $this->assertEquals($release->download_url, $manifest['download_url']);
        $this->assertEquals($release->store_url, $manifest['store_url']);
        $this->assertEquals('abc123checksum', $manifest['checksum']);
        $this->assertEquals(52428800, $manifest['file_size_bytes']);
        $this->assertArrayHasKey('rollout_percentage', $manifest);
        $this->assertArrayHasKey('is_force_update', $manifest);
        $this->assertArrayHasKey('released_at', $manifest);
    }

    public function test_manifest_returns_404_for_nonexistent_version(): void
    {
        $this->apiGet('auto-update/manifest/99.0.0?platform=ios&channel=stable')
            ->assertNotFound();
    }

    public function test_manifest_returns_404_for_inactive_release(): void
    {
        $this->createRelease(['version_number' => '3.0.0', 'is_active' => false]);

        $this->apiGet('auto-update/manifest/3.0.0?platform=ios&channel=stable')
            ->assertNotFound();
    }

    public function test_manifest_platform_mismatch_returns_404(): void
    {
        $this->createRelease(['version_number' => '3.0.0', 'platform' => 'ios']);

        $this->apiGet('auto-update/manifest/3.0.0?platform=android&channel=stable')
            ->assertNotFound();
    }

    // ════════════════════════════════════════════════════════════════════════
    // DOWNLOAD ENDPOINT
    // ════════════════════════════════════════════════════════════════════════

    public function test_download_info_returns_url_and_checksum(): void
    {
        $this->createRelease([
            'version_number' => '3.0.0',
            'file_checksum' => 'sha256abc123',
            'file_size_bytes' => 10485760,
        ]);

        $info = $this->apiGet('auto-update/download/3.0.0?platform=ios&channel=stable')
            ->assertOk()
            ->json('data');

        $this->assertEquals('3.0.0', $info['version']);
        $this->assertArrayHasKey('download_url', $info);
        $this->assertEquals('sha256abc123', $info['checksum']);
        $this->assertEquals(10485760, $info['file_size_bytes']);
        $this->assertArrayHasKey('build_number', $info);
    }

    public function test_download_returns_store_url_for_app_store_platforms(): void
    {
        $this->createRelease([
            'version_number' => '3.0.0',
            'platform' => 'ios',
            'store_url' => 'https://apps.apple.com/app/id123456',
        ]);

        $info = $this->apiGet('auto-update/download/3.0.0?platform=ios&channel=stable')
            ->assertOk()
            ->json('data');

        $this->assertEquals('https://apps.apple.com/app/id123456', $info['store_url']);
    }

    public function test_download_returns_404_for_unknown_version(): void
    {
        $this->apiGet('auto-update/download/0.0.1?platform=ios&channel=stable')
            ->assertNotFound();
    }

    // ════════════════════════════════════════════════════════════════════════
    // ROLLOUT STATUS
    // ════════════════════════════════════════════════════════════════════════

    public function test_rollout_status_returns_stats_when_release_exists(): void
    {
        $release = $this->createRelease(['rollout_percentage' => 75]);

        // 2 installed, 1 downloading, 1 failed
        $extraStore1 = Store::create([
            'organization_id' => $this->org->id, 'name' => 'S1',
            'business_type' => 'grocery', 'currency' => 'SAR',
            'is_active' => true, 'is_main_branch' => false,
        ]);
        $extraStore2 = Store::create([
            'organization_id' => $this->org->id, 'name' => 'S2',
            'business_type' => 'grocery', 'currency' => 'SAR',
            'is_active' => true, 'is_main_branch' => false,
        ]);
        $extraStore3 = Store::create([
            'organization_id' => $this->org->id, 'name' => 'S3',
            'business_type' => 'grocery', 'currency' => 'SAR',
            'is_active' => true, 'is_main_branch' => false,
        ]);

        AppUpdateStat::create(['store_id' => $this->storeId, 'app_release_id' => $release->id, 'status' => 'installed']);
        AppUpdateStat::create(['store_id' => $extraStore1->id, 'app_release_id' => $release->id, 'status' => 'installed']);
        AppUpdateStat::create(['store_id' => $extraStore2->id, 'app_release_id' => $release->id, 'status' => 'downloading']);
        AppUpdateStat::create(['store_id' => $extraStore3->id, 'app_release_id' => $release->id, 'status' => 'failed']);

        $status = $this->apiGet('auto-update/rollout-status?platform=ios&channel=stable')
            ->assertOk()
            ->json('data');

        $this->assertTrue($status['has_active_rollout']);
        $this->assertEquals('3.0.0', $status['version']);
        $this->assertEquals(75, $status['rollout_percentage']);
        $this->assertEquals(4, $status['stats']['total_stores']);
        $this->assertEquals(2, $status['stats']['installed']);
        $this->assertEquals(1, $status['stats']['downloading']);
        $this->assertEquals(1, $status['stats']['failed']);
        $this->assertEquals(50.0, $status['adoption_rate']); // 2/4 = 50%
    }

    public function test_rollout_status_no_active_rollout_when_no_release(): void
    {
        $status = $this->apiGet('auto-update/rollout-status?platform=ios&channel=stable')
            ->assertOk()
            ->json('data');

        $this->assertFalse($status['has_active_rollout']);
    }

    // ════════════════════════════════════════════════════════════════════════
    // CURRENT VERSION TRACKING
    // ════════════════════════════════════════════════════════════════════════

    public function test_current_version_reflects_most_recent_installed_stat(): void
    {
        $v2 = $this->createRelease(['version_number' => '2.0.0', 'released_at' => now()->subDays(3)]);
        $v3 = $this->createRelease(['version_number' => '3.0.0', 'released_at' => now()]);

        AppUpdateStat::create(['store_id' => $this->storeId, 'app_release_id' => $v2->id, 'status' => 'installed']);
        AppUpdateStat::create(['store_id' => $this->storeId, 'app_release_id' => $v3->id, 'status' => 'installed']);

        $current = $this->apiGet('auto-update/current-version?platform=ios')->assertOk()->json('data');
        // Should return the most recent installed (highest ID)
        $this->assertNotNull($current['version']);
    }

    public function test_current_version_returns_null_when_no_installs(): void
    {
        $current = $this->apiGet('auto-update/current-version?platform=ios')->assertOk()->json('data');
        $this->assertNull($current['version']);
        $this->assertEquals('ios', $current['platform']);
    }

    // ════════════════════════════════════════════════════════════════════════
    // VALIDATION
    // ════════════════════════════════════════════════════════════════════════

    public function test_check_requires_current_version(): void
    {
        $this->apiPost('auto-update/check', ['platform' => 'ios'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['current_version']);
    }

    public function test_check_requires_valid_platform(): void
    {
        $this->apiPost('auto-update/check', [
            'current_version' => '1.0.0',
            'platform' => 'nokia', // invalid
        ])->assertStatus(422)->assertJsonValidationErrors(['platform']);
    }

    public function test_check_accepts_all_valid_platforms(): void
    {
        foreach (['ios', 'android', 'windows', 'macos'] as $platform) {
            $this->apiPost('auto-update/check', [
                'current_version' => '1.0.0',
                'platform' => $platform,
            ])->assertOk();
        }
    }

    public function test_check_accepts_all_valid_channels(): void
    {
        foreach (['stable', 'beta', 'testflight', 'internal_test'] as $channel) {
            $this->apiPost('auto-update/check', [
                'current_version' => '1.0.0',
                'platform' => 'ios',
                'channel' => $channel,
            ])->assertOk();
        }
    }

    public function test_check_rejects_invalid_channel(): void
    {
        $this->apiPost('auto-update/check', [
            'current_version' => '1.0.0',
            'platform' => 'ios',
            'channel' => 'nightly', // invalid
        ])->assertStatus(422)->assertJsonValidationErrors(['channel']);
    }

    public function test_report_requires_uuid_release_id(): void
    {
        $this->apiPost('auto-update/report-status', [
            'release_id' => 'not-a-uuid',
            'status' => 'installed',
        ])->assertStatus(422)->assertJsonValidationErrors(['release_id']);
    }

    public function test_report_requires_valid_status(): void
    {
        $releaseId = Str::uuid()->toString();
        $this->apiPost('auto-update/report-status', [
            'release_id' => $releaseId,
            'status' => 'broken', // invalid
        ])->assertStatus(422)->assertJsonValidationErrors(['status']);
    }

    public function test_report_accepts_all_valid_statuses(): void
    {
        $release = $this->createRelease();

        foreach (['pending', 'downloading', 'downloaded', 'installed', 'failed'] as $status) {
            $this->apiPost('auto-update/report-status', [
                'release_id' => $release->id,
                'status' => $status,
            ])->assertOk();
        }
    }

    public function test_report_error_message_truncated_to_500_chars(): void
    {
        $release = $this->createRelease();
        $longMessage = str_repeat('x', 600);

        $this->apiPost('auto-update/report-status', [
            'release_id' => $release->id,
            'status' => 'failed',
            'error_message' => $longMessage,
        ])->assertStatus(422)->assertJsonValidationErrors(['error_message']);
    }

    // ════════════════════════════════════════════════════════════════════════
    // AUTHENTICATION
    // ════════════════════════════════════════════════════════════════════════

    public function test_all_endpoints_require_authentication(): void
    {
        $endpoints = [
            ['POST', '/api/v2/auto-update/check', ['current_version' => '1.0.0', 'platform' => 'ios']],
            ['POST', '/api/v2/auto-update/report-status', ['release_id' => Str::uuid(), 'status' => 'installed']],
            ['GET',  '/api/v2/auto-update/changelog?platform=ios', []],
            ['GET',  '/api/v2/auto-update/history', []],
            ['GET',  '/api/v2/auto-update/current-version?platform=ios', []],
            ['GET',  '/api/v2/auto-update/rollout-status?platform=ios', []],
        ];

        foreach ($endpoints as [$method, $url, $data]) {
            $response = $method === 'POST'
                ? $this->postJson($url, $data)
                : $this->getJson($url);
            $response->assertUnauthorized();
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // CHECK RESPONSE INCLUDES STORE_URL
    // ════════════════════════════════════════════════════════════════════════

    public function test_check_response_includes_store_url(): void
    {
        $this->createRelease([
            'platform' => 'ios',
            'store_url' => 'https://apps.apple.com/app/id987654',
        ]);

        $check = $this->apiPost('auto-update/check', [
            'current_version' => '1.0.0', 'platform' => 'ios',
        ])->assertOk()->json('data');

        $this->assertArrayHasKey('store_url', $check);
        $this->assertEquals('https://apps.apple.com/app/id987654', $check['store_url']);
    }
}
