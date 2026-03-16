<?php

namespace Tests\Feature\AppUpdateManagement;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoUpdateApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private string $storeId;

    protected function setUp(): void
    {
        parent::setUp();

        if (! \Schema::hasTable('organizations')) {
            \Schema::create('organizations', function ($t) {
                $t->uuid('id')->primary();
                $t->string('name');
                $t->string('slug')->unique();
                $t->timestamps();
            });
        }

        if (! \Schema::hasTable('stores')) {
            \Schema::create('stores', function ($t) {
                $t->uuid('id')->primary();
                $t->foreignUuid('organization_id')->constrained('organizations');
                $t->string('name');
                $t->string('name_ar')->nullable();
                $t->string('slug')->unique();
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }

        if (! \Schema::hasTable('users')) {
            \Schema::create('users', function ($t) {
                $t->uuid('id')->primary();
                $t->foreignUuid('store_id')->constrained('stores');
                $t->string('name');
                $t->string('email')->unique();
                $t->string('password_hash');
                $t->timestamps();
            });
        }

        if (! \Schema::hasTable('personal_access_tokens')) {
            \Schema::create('personal_access_tokens', function ($t) {
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

        // Drop outdated SQLite test schema and recreate with correct columns
        \Schema::dropIfExists('app_update_stats');
        \Schema::dropIfExists('app_releases');

        \Schema::create('app_releases', function ($t) {
            $t->uuid('id')->primary();
            $t->string('version_number');
            $t->string('platform');
            $t->string('channel')->default('stable');
            $t->string('download_url')->nullable();
            $t->string('store_url')->nullable();
            $t->string('build_number')->nullable();
            $t->string('submission_status')->default('not_applicable');
            $t->text('release_notes')->nullable();
            $t->text('release_notes_ar')->nullable();
            $t->boolean('is_force_update')->default(false);
            $t->string('min_supported_version')->nullable();
            $t->integer('rollout_percentage')->default(100);
            $t->boolean('is_active')->default(true);
            $t->timestamp('released_at')->nullable();
            $t->timestamps();
        });

        \Schema::create('app_update_stats', function ($t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('store_id')->constrained('stores');
            $t->foreignUuid('app_release_id')->constrained('app_releases');
            $t->string('status');
            $t->text('error_message')->nullable();
        });

        $org = Organization::create(['name' => 'Test Org', 'slug' => 'test-org']);
        $store = Store::create([
            'organization_id' => $org->id,
            'name' => 'Test Store',
            'slug' => 'test-store',
        ]);
        $this->storeId = $store->id;

        $user = User::create([
            'name' => 'Test User',
            'email' => 'autoupdate@test.com',
            'store_id' => $store->id,
            'password_hash' => bcrypt('password'),
        ]);
        $this->token = $user->createToken('test', ['*'])->plainTextToken;
    }

    private function authPost(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/v2/{$uri}", $data, ['Authorization' => "Bearer {$this->token}"]);
    }

    private function authGet(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->getJson("/api/v2/{$uri}", ['Authorization' => "Bearer {$this->token}"]);
    }

    private function createRelease(array $overrides = []): string
    {
        $release = \App\Domain\AppUpdateManagement\Models\AppRelease::create(array_merge([
            'version_number' => '2.0.0',
            'platform' => 'ios',
            'channel' => 'stable',
            'download_url' => 'https://example.com/app-2.0.0.ipa',
            'build_number' => '200',
            'release_notes' => 'Bug fixes and improvements',
            'release_notes_ar' => 'إصلاحات وتحسينات',
            'is_force_update' => false,
            'is_active' => true,
            'released_at' => now(),
        ], $overrides));

        return $release->id;
    }

    // ═══════════════ Check for Update ═══════════════

    public function test_check_no_update_available(): void
    {
        $res = $this->authPost('auto-update/check', [
            'current_version' => '1.0.0',
            'platform' => 'ios',
        ]);
        $res->assertOk();
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertFalse($data['update_available']);
    }

    public function test_check_update_available(): void
    {
        $this->createRelease();

        $res = $this->authPost('auto-update/check', [
            'current_version' => '1.0.0',
            'platform' => 'ios',
        ]);
        $res->assertOk();
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertTrue($data['update_available']);
        $this->assertEquals('2.0.0', $data['latest_version']);
        $this->assertFalse($data['is_force_update']);
    }

    public function test_check_no_update_when_current(): void
    {
        $this->createRelease();

        $res = $this->authPost('auto-update/check', [
            'current_version' => '2.0.0',
            'platform' => 'ios',
        ]);
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertFalse($data['update_available']);
    }

    public function test_check_force_update(): void
    {
        $this->createRelease([
            'is_force_update' => true,
            'min_supported_version' => '1.5.0',
        ]);

        $res = $this->authPost('auto-update/check', [
            'current_version' => '1.0.0',
            'platform' => 'ios',
        ]);
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertTrue($data['is_force_update']);
    }

    public function test_check_validation(): void
    {
        $res = $this->authPost('auto-update/check', [
            'platform' => 'invalid',
        ]);
        $res->assertStatus(422);
    }

    public function test_check_different_platform(): void
    {
        $this->createRelease(['platform' => 'android']);

        $res = $this->authPost('auto-update/check', [
            'current_version' => '1.0.0',
            'platform' => 'ios',
        ]);
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertFalse($data['update_available']);
    }

    // ═══════════════ Report Status ═══════════════

    public function test_report_status(): void
    {
        $releaseId = $this->createRelease();

        $res = $this->authPost('auto-update/report-status', [
            'release_id' => $releaseId,
            'status' => 'downloading',
        ]);
        $res->assertOk();
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertEquals('downloading', $data['status']);
    }

    public function test_report_failed_with_error(): void
    {
        $releaseId = $this->createRelease();

        $res = $this->authPost('auto-update/report-status', [
            'release_id' => $releaseId,
            'status' => 'failed',
            'error_message' => 'Disk full',
        ]);
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertEquals('failed', $data['status']);
        $this->assertEquals('Disk full', $data['error_message']);
    }

    public function test_report_status_validation(): void
    {
        $res = $this->authPost('auto-update/report-status', [
            'release_id' => 'not-a-uuid',
            'status' => 'invalid',
        ]);
        $res->assertStatus(422);
    }

    // ═══════════════ Changelog ═══════════════

    public function test_changelog(): void
    {
        $this->createRelease(['version_number' => '1.0.0']);
        $this->createRelease(['version_number' => '2.0.0']);

        $res = $this->authGet('auto-update/changelog?platform=ios&channel=stable');
        $res->assertOk();
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertCount(2, $data);
    }

    public function test_changelog_empty(): void
    {
        $res = $this->authGet('auto-update/changelog?platform=windows');
        $res->assertOk();
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertEmpty($data);
    }

    // ═══════════════ History ═══════════════

    public function test_update_history(): void
    {
        $releaseId = $this->createRelease();
        $this->authPost('auto-update/report-status', [
            'release_id' => $releaseId,
            'status' => 'installed',
        ]);

        $res = $this->authGet('auto-update/history');
        $res->assertOk();
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertNotEmpty($data);
    }

    public function test_update_history_empty(): void
    {
        $res = $this->authGet('auto-update/history');
        $res->assertOk();
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertEmpty($data);
    }

    // ═══════════════ Current Version ═══════════════

    public function test_current_version(): void
    {
        $releaseId = $this->createRelease();
        $this->authPost('auto-update/report-status', [
            'release_id' => $releaseId,
            'status' => 'installed',
        ]);

        $res = $this->authGet('auto-update/current-version?platform=ios');
        $res->assertOk();
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertEquals('2.0.0', $data['version']);
    }

    public function test_current_version_none(): void
    {
        $res = $this->authGet('auto-update/current-version?platform=ios');
        $res->assertOk();
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertNull($data['version']);
    }

    // ═══════════════ Auth ═══════════════

    public function test_unauthenticated(): void
    {
        $this->postJson('/api/v2/auto-update/check', [
            'current_version' => '1.0.0',
            'platform' => 'ios',
        ])->assertUnauthorized();
    }
}
