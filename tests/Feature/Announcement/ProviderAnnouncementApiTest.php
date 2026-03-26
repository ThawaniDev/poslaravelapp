<?php

namespace Tests\Feature\Announcement;

use App\Domain\Announcement\Models\PlatformAnnouncement;
use App\Domain\Announcement\Models\PlatformAnnouncementDismissal;
use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderAnnouncementApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private string $storeId;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::forceCreate([
            'name' => 'Announce Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $store = Store::forceCreate([
            'organization_id' => $org->id,
            'name' => 'Announce Store',
            'is_active' => true,
        ]);
        $this->storeId = $store->id;

        $user = User::forceCreate([
            'name' => 'Provider User',
            'email' => 'provider-announce@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $store->id,
            'organization_id' => $org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $user->createToken('test', ['*'])->plainTextToken;
    }

    private function authGet(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->getJson("/api/v2/{$uri}", ['Authorization' => "Bearer {$this->token}"]);
    }

    private function authPost(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/v2/{$uri}", $data, ['Authorization' => "Bearer {$this->token}"]);
    }

    private function createAnnouncement(array $overrides = []): PlatformAnnouncement
    {
        return PlatformAnnouncement::forceCreate(array_merge([
            'type' => 'info',
            'title' => 'Test Announcement',
            'title_ar' => 'إعلان تجريبي',
            'body' => 'Test body content',
            'body_ar' => 'محتوى تجريبي',
            'target_filter' => json_encode(['scope' => 'all']),
            'display_start_at' => now()->subDay(),
            'display_end_at' => now()->addDays(7),
            'is_banner' => false,
            'send_push' => false,
            'send_email' => false,
        ], $overrides));
    }

    // ═══════════════════════════════════════════════════════════
    // AUTH
    // ═══════════════════════════════════════════════════════════

    public function test_unauthenticated_cannot_list_announcements(): void
    {
        $this->getJson('/api/v2/announcements')
            ->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_dismiss_announcement(): void
    {
        $this->postJson('/api/v2/announcements/fake-id/dismiss')
            ->assertUnauthorized();
    }

    // ═══════════════════════════════════════════════════════════
    // LIST ANNOUNCEMENTS
    // ═══════════════════════════════════════════════════════════

    public function test_list_active_announcements(): void
    {
        $this->createAnnouncement(['title' => 'Active One']);
        $this->createAnnouncement(['title' => 'Active Two']);

        $response = $this->authGet('announcements');
        $response->assertOk()
            ->assertJsonPath('data.total', 2);

        $titles = collect($response->json('data.announcements'))->pluck('title')->toArray();
        $this->assertContains('Active One', $titles);
        $this->assertContains('Active Two', $titles);
    }

    public function test_list_announcements_empty_when_none(): void
    {
        $response = $this->authGet('announcements');
        $response->assertOk()
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.announcements', []);
    }

    public function test_excludes_expired_announcements(): void
    {
        // Expired announcement
        $this->createAnnouncement([
            'title' => 'Expired',
            'display_start_at' => now()->subDays(10),
            'display_end_at' => now()->subDay(),
        ]);

        // Active announcement
        $this->createAnnouncement(['title' => 'Still Active']);

        $response = $this->authGet('announcements');
        $response->assertOk()
            ->assertJsonPath('data.total', 1);

        $this->assertEquals('Still Active', $response->json('data.announcements.0.title'));
    }

    public function test_excludes_future_announcements(): void
    {
        // Future announcement
        $this->createAnnouncement([
            'title' => 'Future',
            'display_start_at' => now()->addDays(5),
            'display_end_at' => now()->addDays(15),
        ]);

        // Active announcement
        $this->createAnnouncement(['title' => 'Current']);

        $response = $this->authGet('announcements');
        $response->assertOk()
            ->assertJsonPath('data.total', 1);

        $this->assertEquals('Current', $response->json('data.announcements.0.title'));
    }

    public function test_excludes_dismissed_announcements(): void
    {
        $dismissed = $this->createAnnouncement(['title' => 'Dismissed One']);
        $active = $this->createAnnouncement(['title' => 'Not Dismissed']);

        PlatformAnnouncementDismissal::forceCreate([
            'announcement_id' => $dismissed->id,
            'store_id' => $this->storeId,
            'dismissed_at' => now(),
        ]);

        $response = $this->authGet('announcements');
        $response->assertOk()
            ->assertJsonPath('data.total', 1);

        $this->assertEquals('Not Dismissed', $response->json('data.announcements.0.title'));
    }

    public function test_announcement_response_structure(): void
    {
        $this->createAnnouncement([
            'title' => 'Structured',
            'is_banner' => true,
        ]);

        $response = $this->authGet('announcements');
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'announcements' => [
                        '*' => [
                            'id',
                            'type',
                            'title',
                            'title_ar',
                            'body',
                            'body_ar',
                            'is_banner',
                            'display_start_at',
                            'display_end_at',
                            'created_at',
                        ],
                    ],
                    'total',
                ],
            ]);
    }

    public function test_announcements_ordered_by_newest_first(): void
    {
        $this->createAnnouncement([
            'title' => 'Older',
            'created_at' => now()->subDays(3),
        ]);
        $this->createAnnouncement([
            'title' => 'Newer',
            'created_at' => now()->subDay(),
        ]);

        $response = $this->authGet('announcements');
        $response->assertOk();

        $announcements = $response->json('data.announcements');
        $this->assertEquals('Newer', $announcements[0]['title']);
        $this->assertEquals('Older', $announcements[1]['title']);
    }

    public function test_includes_announcements_with_null_display_dates(): void
    {
        $this->createAnnouncement([
            'title' => 'No Date Limits',
            'display_start_at' => null,
            'display_end_at' => null,
        ]);

        $response = $this->authGet('announcements');
        $response->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    // ═══════════════════════════════════════════════════════════
    // DISMISS ANNOUNCEMENT
    // ═══════════════════════════════════════════════════════════

    public function test_dismiss_announcement(): void
    {
        $announcement = $this->createAnnouncement();

        $response = $this->authPost("announcements/{$announcement->id}/dismiss");
        $response->assertOk();

        $this->assertDatabaseHas('platform_announcement_dismissals', [
            'announcement_id' => $announcement->id,
            'store_id' => $this->storeId,
        ]);
    }

    public function test_dismiss_is_idempotent(): void
    {
        $announcement = $this->createAnnouncement();

        // Dismiss twice
        $this->authPost("announcements/{$announcement->id}/dismiss")->assertOk();
        $this->authPost("announcements/{$announcement->id}/dismiss")->assertOk();

        // Only one dismissal record
        $count = PlatformAnnouncementDismissal::where('announcement_id', $announcement->id)
            ->where('store_id', $this->storeId)
            ->count();
        $this->assertEquals(1, $count);
    }

    public function test_dismissed_announcement_no_longer_appears_in_list(): void
    {
        $announcement = $this->createAnnouncement(['title' => 'Will Dismiss']);
        $other = $this->createAnnouncement(['title' => 'Will Stay']);

        // Confirm both appear initially
        $response = $this->authGet('announcements');
        $this->assertEquals(2, $response->json('data.total'));

        // Dismiss one
        $this->authPost("announcements/{$announcement->id}/dismiss")->assertOk();

        // Only the other one remains
        $response = $this->authGet('announcements');
        $this->assertEquals(1, $response->json('data.total'));
        $this->assertEquals('Will Stay', $response->json('data.announcements.0.title'));
    }

    public function test_dismiss_does_not_affect_other_stores(): void
    {
        $announcement = $this->createAnnouncement();

        // Dismiss for this store
        $this->authPost("announcements/{$announcement->id}/dismiss")->assertOk();

        // Verify only 1 dismissal exists for the first store
        $this->assertDatabaseHas('platform_announcement_dismissals', [
            'announcement_id' => $announcement->id,
            'store_id' => $this->storeId,
        ]);

        // Create another store + user
        $org2 = Organization::forceCreate([
            'name' => 'Other Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);
        $store2 = Store::forceCreate([
            'organization_id' => $org2->id,
            'name' => 'Other Store',
            'is_active' => true,
        ]);

        // No dismissal exists for the other store
        $this->assertDatabaseMissing('platform_announcement_dismissals', [
            'announcement_id' => $announcement->id,
            'store_id' => $store2->id,
        ]);

        // The AnnouncementService should return the announcement for store2
        $service = app(\App\Domain\Announcement\Services\AnnouncementService::class);
        $activeForStore2 = $service->getActiveForStore($store2->id);
        $this->assertCount(1, $activeForStore2);
        $this->assertEquals($announcement->id, $activeForStore2->first()->id);
    }

    // ═══════════════════════════════════════════════════════════
    // ANNOUNCEMENT TYPE FILTERING
    // ═══════════════════════════════════════════════════════════

    public function test_returns_different_announcement_types(): void
    {
        $this->createAnnouncement(['type' => 'info', 'title' => 'Info']);
        $this->createAnnouncement(['type' => 'warning', 'title' => 'Warning']);
        $this->createAnnouncement(['type' => 'maintenance', 'title' => 'Maintenance']);

        $response = $this->authGet('announcements');
        $response->assertOk()
            ->assertJsonPath('data.total', 3);

        $types = collect($response->json('data.announcements'))->pluck('type')->unique()->values()->toArray();
        $this->assertContains('info', $types);
        $this->assertContains('warning', $types);
        $this->assertContains('maintenance', $types);
    }

    // ═══════════════════════════════════════════════════════════
    // EDGE CASES
    // ═══════════════════════════════════════════════════════════

    public function test_banner_announcements_included(): void
    {
        $this->createAnnouncement([
            'title' => 'Banner',
            'is_banner' => true,
        ]);

        $response = $this->authGet('announcements');
        $response->assertOk()
            ->assertJsonPath('data.announcements.0.is_banner', true);
    }

    public function test_multiple_dismissals_for_different_announcements(): void
    {
        $a1 = $this->createAnnouncement(['title' => 'First']);
        $a2 = $this->createAnnouncement(['title' => 'Second']);
        $a3 = $this->createAnnouncement(['title' => 'Third']);

        $this->authPost("announcements/{$a1->id}/dismiss")->assertOk();
        $this->authPost("announcements/{$a2->id}/dismiss")->assertOk();

        $response = $this->authGet('announcements');
        $response->assertOk()
            ->assertJsonPath('data.total', 1);
        $this->assertEquals('Third', $response->json('data.announcements.0.title'));
    }
}
