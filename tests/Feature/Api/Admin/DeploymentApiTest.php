<?php

namespace Tests\Feature\Api\Admin;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\AppUpdateManagement\Models\AppRelease;
use App\Domain\AppUpdateManagement\Models\AppUpdateStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeploymentApiTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;
    private string $prefix = '/api/v2/admin/deployment';

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
    }

    private int $releaseCounter = 0;

    private function createRelease(array $overrides = []): AppRelease
    {
        $this->releaseCounter++;
        return AppRelease::forceCreate(array_merge([
            'id'                    => Str::uuid()->toString(),
            'platform'              => 'ios',
            'version_number'        => "1.0.{$this->releaseCounter}",
            'channel'               => 'stable',
            'build_number'          => (string) (100 + $this->releaseCounter),
            'release_notes'         => 'Initial release',
            'release_notes_ar'      => 'الإصدار الأول',
            'is_force_update'       => false,
            'is_active'             => false,
            'rollout_percentage'    => 0,
            'min_supported_version' => '14.0',
            'download_url'          => 'https://example.com/app.ipa',
            'store_url'             => 'https://apps.apple.com/app/test',
        ], $overrides));
    }

    private function createStat(string $releaseId, array $overrides = []): AppUpdateStat
    {
        return AppUpdateStat::forceCreate(array_merge([
            'id'               => Str::uuid()->toString(),
            'app_release_id'   => $releaseId,
            'store_id'         => Str::uuid()->toString(),
            'status'           => 'installed',
        ], $overrides));
    }

    // ──────────────── List Releases ────────────────

    public function test_list_releases(): void
    {
        $this->createRelease(['version_number' => '1.0.0']);
        $this->createRelease(['version_number' => '2.0.0']);

        $res = $this->getJson("{$this->prefix}/releases");
        $res->assertOk()->assertJsonCount(2, 'data.data');

        $versions = collect($res->json('data.data'))->pluck('version_number')->toArray();
        $this->assertContains('1.0.0', $versions);
        $this->assertContains('2.0.0', $versions);
    }

    public function test_filter_releases_by_platform(): void
    {
        $this->createRelease(['platform' => 'ios']);
        $this->createRelease(['platform' => 'android']);

        $res = $this->getJson("{$this->prefix}/releases?platform=ios");
        $res->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_filter_releases_by_is_active(): void
    {
        $this->createRelease(['is_active' => true]);
        $this->createRelease(['is_active' => false]);

        $res = $this->getJson("{$this->prefix}/releases?is_active=1");
        $res->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_filter_releases_by_is_force_update(): void
    {
        $this->createRelease(['is_force_update' => true]);
        $this->createRelease(['is_force_update' => false]);

        $res = $this->getJson("{$this->prefix}/releases?is_force_update=1");
        $res->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_search_releases_by_version(): void
    {
        $this->createRelease(['version_number' => '3.5.1']);
        $this->createRelease(['version_number' => '1.0.0']);

        $res = $this->getJson("{$this->prefix}/releases?search=3.5");
        $res->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.version_number', '3.5.1');
    }

    public function test_search_releases_by_notes(): void
    {
        $this->createRelease(['release_notes' => 'Bug fixes']);
        $this->createRelease(['release_notes' => 'New features']);

        $res = $this->getJson("{$this->prefix}/releases?search=Bug");
        $res->assertOk()->assertJsonCount(1, 'data.data');
    }

    // ──────────────── Create Release ────────────────

    public function test_create_release(): void
    {
        $res = $this->postJson("{$this->prefix}/releases", [
            'platform'              => 'android',
            'version_number'        => '2.1.0',
            'build_number'          => '210',
            'release_notes'         => 'New features',
            'release_notes_ar'      => 'ميزات جديدة',
            'is_force_update'       => true,
            'rollout_percentage'    => 50,
            'min_supported_version' => '12.0',
            'download_url'          => 'https://example.com/app.apk',
        ]);

        $res->assertCreated()
            ->assertJsonPath('data.platform', 'android')
            ->assertJsonPath('data.version_number', '2.1.0')
            ->assertJsonPath('data.build_number', '210')
            ->assertJsonPath('data.rollout_percentage', 50)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('app_releases', ['version_number' => '2.1.0', 'platform' => 'android']);
    }

    public function test_create_release_validation(): void
    {
        $res = $this->postJson("{$this->prefix}/releases", []);
        $res->assertUnprocessable()
            ->assertJsonValidationErrors(['platform', 'version_number']);
    }

    public function test_create_release_invalid_platform(): void
    {
        $res = $this->postJson("{$this->prefix}/releases", [
            'platform'       => 'linux',
            'version_number' => '1.0.0',
            'download_url'   => 'https://example.com/app',
        ]);
        $res->assertUnprocessable()
            ->assertJsonValidationErrors(['platform']);
    }

    public function test_create_release_defaults(): void
    {
        $res = $this->postJson("{$this->prefix}/releases", [
            'platform'       => 'ios',
            'version_number' => '1.0.0',
            'download_url'   => 'https://example.com/app.ipa',
        ]);

        $res->assertCreated()
            ->assertJsonPath('data.channel', 'stable')
            ->assertJsonPath('data.is_active', false);
    }

    // ──────────────── Show Release ────────────────

    public function test_show_release(): void
    {
        $release = $this->createRelease(['version_number' => '5.0.0']);

        $res = $this->getJson("{$this->prefix}/releases/{$release->id}");
        $res->assertOk()->assertJsonPath('data.version_number', '5.0.0');
    }

    public function test_show_release_not_found(): void
    {
        $res = $this->getJson("{$this->prefix}/releases/00000000-0000-0000-0000-000000000099");
        $res->assertNotFound();
    }

    // ──────────────── Update Release ────────────────

    public function test_update_release(): void
    {
        $release = $this->createRelease();

        $res = $this->putJson("{$this->prefix}/releases/{$release->id}", [
            'version_number' => '1.1.0',
            'release_notes'  => 'Updated notes',
            'is_force_update' => true,
        ]);

        $res->assertOk()
            ->assertJsonPath('data.version_number', '1.1.0')
            ->assertJsonPath('data.release_notes', 'Updated notes');
    }

    public function test_update_release_not_found(): void
    {
        $res = $this->putJson("{$this->prefix}/releases/00000000-0000-0000-0000-000000000099", [
            'version_number' => '1.0.0',
        ]);
        $res->assertNotFound();
    }

    // ──────────────── Activate / Deactivate ────────────────

    public function test_activate_release(): void
    {
        $release = $this->createRelease(['is_active' => false]);

        $res = $this->postJson("{$this->prefix}/releases/{$release->id}/activate");
        $res->assertOk()
            ->assertJsonPath('data.is_active', true);

        $release->refresh();
        $this->assertNotNull($release->released_at);
    }

    public function test_deactivate_release(): void
    {
        $release = $this->createRelease(['is_active' => true]);

        $res = $this->postJson("{$this->prefix}/releases/{$release->id}/deactivate");
        $res->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_activate_not_found(): void
    {
        $this->postJson("{$this->prefix}/releases/00000000-0000-0000-0000-000000000099/activate")->assertNotFound();
    }

    // ──────────────── Rollout ────────────────

    public function test_update_rollout(): void
    {
        $release = $this->createRelease(['rollout_percentage' => 10]);

        $res = $this->putJson("{$this->prefix}/releases/{$release->id}/rollout", [
            'rollout_percentage' => 75,
        ]);

        $res->assertOk()->assertJsonPath('data.rollout_percentage', 75);
    }

    public function test_update_rollout_validation(): void
    {
        $release = $this->createRelease();

        $this->putJson("{$this->prefix}/releases/{$release->id}/rollout", [
            'rollout_percentage' => 150,
        ])->assertUnprocessable();
    }

    public function test_update_rollout_not_found(): void
    {
        $this->putJson("{$this->prefix}/releases/00000000-0000-0000-0000-000000000099/rollout", [
            'rollout_percentage' => 50,
        ])->assertNotFound();
    }

    // ──────────────── Delete Release ────────────────

    public function test_delete_release(): void
    {
        $release = $this->createRelease();

        $res = $this->deleteJson("{$this->prefix}/releases/{$release->id}");
        $res->assertOk();

        $this->assertDatabaseMissing('app_releases', ['id' => $release->id]);
    }

    public function test_delete_release_not_found(): void
    {
        $this->deleteJson("{$this->prefix}/releases/00000000-0000-0000-0000-000000000099")->assertNotFound();
    }

    // ──────────────── Stats ────────────────

    public function test_list_stats(): void
    {
        $release = $this->createRelease();
        $this->createStat($release->id, ['status' => 'installed']);
        $this->createStat($release->id, ['status' => 'pending']);

        $res = $this->getJson("{$this->prefix}/releases/{$release->id}/stats");
        $res->assertOk()->assertJsonCount(2, 'data.data');
    }

    public function test_list_stats_filter_by_status(): void
    {
        $release = $this->createRelease();
        $this->createStat($release->id, ['status' => 'installed']);
        $this->createStat($release->id, ['status' => 'pending']);
        $this->createStat($release->id, ['status' => 'failed']);

        $res = $this->getJson("{$this->prefix}/releases/{$release->id}/stats?status=installed");
        $res->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_list_stats_release_not_found(): void
    {
        $this->getJson("{$this->prefix}/releases/00000000-0000-0000-0000-000000000099/stats")->assertNotFound();
    }

    public function test_record_stat(): void
    {
        $release = $this->createRelease();

        $res = $this->postJson("{$this->prefix}/releases/{$release->id}/stats", [
            'store_id' => Str::uuid()->toString(),
            'status'   => 'installed',
        ]);

        $res->assertCreated()
            ->assertJsonPath('data.status', 'installed');
    }

    public function test_record_stat_validation(): void
    {
        $release = $this->createRelease();

        $this->postJson("{$this->prefix}/releases/{$release->id}/stats", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['store_id', 'status']);
    }

    public function test_record_stat_release_not_found(): void
    {
        $this->postJson("{$this->prefix}/releases/00000000-0000-0000-0000-000000000099/stats", [
            'date' => '2024-06-15',
        ])->assertNotFound();
    }

    // ──────────────── Release Summary ────────────────

    public function test_release_summary(): void
    {
        $release = $this->createRelease(['version_number' => '4.0.0']);
        $this->createStat($release->id, ['status' => 'installed']);
        $this->createStat($release->id, ['status' => 'installed']);
        $this->createStat($release->id, ['status' => 'pending']);
        $this->createStat($release->id, ['status' => 'failed']);

        $res = $this->getJson("{$this->prefix}/releases/{$release->id}/summary");
        $res->assertOk();

        $data = $res->json('data');
        $this->assertEquals(4, $data['total_stores']);
        $this->assertEquals(2, $data['installed']);
        $this->assertEquals(1, $data['pending']);
        $this->assertEquals(1, $data['failed']);
    }

    public function test_release_summary_not_found(): void
    {
        $this->getJson("{$this->prefix}/releases/00000000-0000-0000-0000-000000000099/summary")->assertNotFound();
    }

    public function test_release_summary_empty(): void
    {
        $release = $this->createRelease();

        $res = $this->getJson("{$this->prefix}/releases/{$release->id}/summary");
        $res->assertOk();

        $data = $res->json('data');
        $this->assertEquals(0, $data['total_stores']);
    }

    // ──────────────── Platform Overview ────────────────

    public function test_platform_overview(): void
    {
        $this->createRelease(['platform' => 'ios', 'is_active' => true, 'released_at' => now()]);
        $this->createRelease(['platform' => 'ios', 'is_active' => false]);
        $this->createRelease(['platform' => 'android', 'is_active' => true, 'released_at' => now()]);

        $res = $this->getJson("{$this->prefix}/overview");
        $res->assertOk();

        $data = collect($res->json('data'));
        $ios = $data->firstWhere('platform', 'ios');
        $android = $data->firstWhere('platform', 'android');
        $windows = $data->firstWhere('platform', 'windows');

        $this->assertEquals(2, $ios['total_releases']);
        $this->assertNotNull($ios['active_release']);
        $this->assertEquals(1, $android['total_releases']);
        $this->assertNotNull($android['active_release']);
        $this->assertEquals(0, $windows['total_releases']);
        $this->assertNull($windows['active_release']);
    }

    // ──────────────── Pagination ────────────────

    public function test_releases_pagination(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->createRelease(['version_number' => "1.0.{$i}"]);
        }

        $res = $this->getJson("{$this->prefix}/releases?per_page=5");
        $res->assertOk()
            ->assertJsonCount(5, 'data.data')
            ->assertJsonPath('data.last_page', 4);
    }
}
