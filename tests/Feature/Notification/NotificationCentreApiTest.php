<?php

namespace Tests\Feature\Notification;

use App\Domain\Announcement\Models\PaymentReminder;
use App\Domain\AppUpdateManagement\Models\AppRelease;
use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\SystemConfig\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationCentreApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Centre Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Centre Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Centre Admin',
            'email' => 'centre@notif.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;
    }

    public function test_payment_reminders_endpoint_returns_user_org_reminders_only(): void
    {
        $plan = SubscriptionPlan::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => 'Plan A',
            'slug' => 'plan-a',
            'monthly_price' => 100,
            'annual_price' => 1000,
            'is_active' => true,
        ]);

        $sub = StoreSubscription::forceCreate([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now()->subDays(15),
            'current_period_end' => now()->addDays(15),
        ]);

        PaymentReminder::forceCreate([
            'id' => (string) Str::uuid(),
            'store_subscription_id' => $sub->id,
            'reminder_type' => 'upcoming',
            'channel' => 'email',
            'sent_at' => now(),
        ]);

        // Reminder belonging to a different org — must not appear.
        $otherOrg = Organization::create([
            'name' => 'Other Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);
        $otherSub = StoreSubscription::forceCreate([
            'id' => (string) Str::uuid(),
            'organization_id' => $otherOrg->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now()->subDays(15),
            'current_period_end' => now()->addDays(15),
        ]);
        PaymentReminder::forceCreate([
            'id' => (string) Str::uuid(),
            'store_subscription_id' => $otherSub->id,
            'reminder_type' => 'overdue',
            'channel' => 'sms',
            'sent_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v2/payment-reminders');

        $response->assertOk()
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonPath('data.summary.upcoming', 1)
            ->assertJsonPath('data.summary.overdue', 0);

        $this->assertCount(1, $response->json('data.reminders'));
    }

    public function test_app_releases_latest_returns_active_release_for_platform(): void
    {
        AppRelease::forceCreate([
            'id' => (string) Str::uuid(),
            'version_number' => '1.2.3',
            'platform' => 'android',
            'channel' => 'stable',
            'download_url' => 'https://example.com/app-1.2.3.apk',
            'release_notes' => 'New features',
            'release_notes_ar' => 'مزايا جديدة',
            'is_force_update' => false,
            'rollout_percentage' => 100,
            'is_active' => true,
            'released_at' => now()->subDay(),
        ]);

        AppRelease::forceCreate([
            'id' => (string) Str::uuid(),
            'version_number' => '1.2.4-beta',
            'platform' => 'android',
            'channel' => 'beta',
            'download_url' => 'https://example.com/app-1.2.4-beta.apk',
            'is_force_update' => false,
            'rollout_percentage' => 50,
            'is_active' => true,
            'released_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v2/app-releases/latest?platform=android&channel=stable');

        $response->assertOk()
            ->assertJsonPath('data.release.version_number', '1.2.3')
            ->assertJsonPath('data.release.channel', 'stable');
    }

    public function test_app_releases_index_lists_active_releases(): void
    {
        AppRelease::forceCreate([
            'id' => (string) Str::uuid(),
            'version_number' => '2.0.0',
            'platform' => 'ios',
            'channel' => 'stable',
            'download_url' => 'https://example.com/app-2.0.0.ipa',
            'is_force_update' => false,
            'rollout_percentage' => 100,
            'is_active' => true,
            'released_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v2/app-releases');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data.releases')));
    }

    public function test_maintenance_status_endpoint_reflects_settings(): void
    {
        SystemSetting::updateOrCreate(
            ['key' => 'maintenance_enabled'],
            ['value' => '1', 'group' => 'maintenance'],
        );
        SystemSetting::updateOrCreate(
            ['key' => 'maintenance_banner_en'],
            ['value' => 'Scheduled maintenance', 'group' => 'maintenance'],
        );

        $response = $this->getJson('/api/v2/maintenance-status');

        $response->assertOk()
            ->assertJsonPath('data.is_enabled', true)
            ->assertJsonPath('data.banner_en', 'Scheduled maintenance');
    }

    public function test_maintenance_status_endpoint_is_public(): void
    {
        // No auth header — should still respond.
        $response = $this->getJson('/api/v2/maintenance-status');
        $response->assertOk();
    }
}
