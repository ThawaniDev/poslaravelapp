<?php

namespace Tests\Feature\Announcement;

use App\Domain\Announcement\Enums\ReminderChannel;
use App\Domain\Announcement\Enums\ReminderType;
use App\Domain\Announcement\Jobs\SendPaymentReminders;
use App\Domain\Announcement\Models\PaymentReminder;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendPaymentRemindersJobTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionPlan $plan;
    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = SubscriptionPlan::forceCreate([
            'name' => 'Basic',
            'name_ar' => 'أساسي',
            'slug' => 'basic_reminder_test',
            'monthly_price' => 29.99,
            'annual_price' => 299.99,
            'trial_days' => 14,
            'grace_period_days' => 7,
            'is_active' => true,
            'is_highlighted' => false,
            'sort_order' => 1,
        ]);

        $this->org = Organization::forceCreate([
            'name' => 'Reminder Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);
    }

    private function createSubscription(array $overrides = []): StoreSubscription
    {
        return StoreSubscription::forceCreate(array_merge([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->addDays(3),
        ], $overrides));
    }

    // ═══════════════════════════════════════════════════════════
    // UPCOMING REMINDERS
    // ═══════════════════════════════════════════════════════════

    public function test_sends_upcoming_reminders_for_expiring_subscriptions(): void
    {
        $sub = $this->createSubscription([
            'current_period_end' => now()->addDays(3)->startOfDay(),
        ]);

        (new SendPaymentReminders)->handle();

        // Should create reminders for all channels
        $reminders = PaymentReminder::where('store_subscription_id', $sub->id)
            ->where('reminder_type', ReminderType::Upcoming->value)
            ->get();

        $this->assertCount(count(ReminderChannel::cases()), $reminders);

        $channels = $reminders->pluck('channel')->map(fn ($c) => $c instanceof ReminderChannel ? $c->value : $c)->toArray();
        foreach (ReminderChannel::cases() as $channel) {
            $this->assertContains($channel->value, $channels);
        }
    }

    public function test_does_not_send_upcoming_if_not_expiring_in_3_days(): void
    {
        // Expiring in 10 days — should NOT get reminder
        $this->createSubscription([
            'current_period_end' => now()->addDays(10)->startOfDay(),
        ]);

        // Already expired — should NOT get upcoming reminder
        $this->createSubscription([
            'current_period_end' => now()->subDay()->startOfDay(),
        ]);

        (new SendPaymentReminders)->handle();

        $this->assertEquals(0, PaymentReminder::where('reminder_type', ReminderType::Upcoming->value)->count());
    }

    public function test_does_not_duplicate_upcoming_reminders_within_7_days(): void
    {
        $sub = $this->createSubscription([
            'current_period_end' => now()->addDays(3)->startOfDay(),
        ]);

        // Pre-existing reminder from 5 days ago (within 7-day window)
        PaymentReminder::forceCreate([
            'store_subscription_id' => $sub->id,
            'reminder_type' => ReminderType::Upcoming->value,
            'channel' => ReminderChannel::Email->value,
            'sent_at' => now()->subDays(5),
        ]);

        (new SendPaymentReminders)->handle();

        // Should NOT create more upcoming reminders because one exists within window
        $count = PaymentReminder::where('store_subscription_id', $sub->id)
            ->where('reminder_type', ReminderType::Upcoming->value)
            ->count();
        $this->assertEquals(1, $count);
    }

    public function test_sends_upcoming_if_old_reminder_outside_window(): void
    {
        $sub = $this->createSubscription([
            'current_period_end' => now()->addDays(3)->startOfDay(),
        ]);

        // Old reminder from 10 days ago (outside 7-day window)
        PaymentReminder::forceCreate([
            'store_subscription_id' => $sub->id,
            'reminder_type' => ReminderType::Upcoming->value,
            'channel' => ReminderChannel::Email->value,
            'sent_at' => now()->subDays(10),
        ]);

        (new SendPaymentReminders)->handle();

        // Should create new reminders for all channels
        $newReminders = PaymentReminder::where('store_subscription_id', $sub->id)
            ->where('reminder_type', ReminderType::Upcoming->value)
            ->where('sent_at', '>=', now()->subMinutes(5))
            ->count();
        $this->assertEquals(count(ReminderChannel::cases()), $newReminders);
    }

    public function test_skips_inactive_subscriptions_for_upcoming(): void
    {
        $this->createSubscription([
            'status' => 'cancelled',
            'current_period_end' => now()->addDays(3)->startOfDay(),
        ]);

        (new SendPaymentReminders)->handle();

        $this->assertEquals(0, PaymentReminder::count());
    }

    // ═══════════════════════════════════════════════════════════
    // OVERDUE REMINDERS
    // ═══════════════════════════════════════════════════════════

    public function test_sends_overdue_reminders_for_expired_subscriptions(): void
    {
        $sub = $this->createSubscription([
            'current_period_end' => now()->subDay()->startOfDay(),
        ]);

        (new SendPaymentReminders)->handle();

        $reminders = PaymentReminder::where('store_subscription_id', $sub->id)
            ->where('reminder_type', ReminderType::Overdue->value)
            ->get();

        // Overdue sends email only
        $this->assertCount(1, $reminders);
        $channel = $reminders->first()->channel;
        $channelValue = $channel instanceof ReminderChannel ? $channel->value : $channel;
        $this->assertEquals(ReminderChannel::Email->value, $channelValue);
    }

    public function test_does_not_duplicate_overdue_reminders_within_1_day(): void
    {
        $sub = $this->createSubscription([
            'current_period_end' => now()->subDay()->startOfDay(),
        ]);

        // Already sent an overdue reminder today
        PaymentReminder::forceCreate([
            'store_subscription_id' => $sub->id,
            'reminder_type' => ReminderType::Overdue->value,
            'channel' => ReminderChannel::Email->value,
            'sent_at' => now()->subHours(6),
        ]);

        (new SendPaymentReminders)->handle();

        $count = PaymentReminder::where('store_subscription_id', $sub->id)
            ->where('reminder_type', ReminderType::Overdue->value)
            ->count();
        $this->assertEquals(1, $count);
    }

    public function test_does_not_send_overdue_for_non_expired_subscriptions(): void
    {
        // Not yet expired
        $this->createSubscription([
            'current_period_end' => now()->addDays(5)->startOfDay(),
        ]);

        (new SendPaymentReminders)->handle();

        $this->assertEquals(0, PaymentReminder::where('reminder_type', ReminderType::Overdue->value)->count());
    }

    // ═══════════════════════════════════════════════════════════
    // COMBINED SCENARIOS
    // ═══════════════════════════════════════════════════════════

    public function test_handles_both_upcoming_and_overdue_in_single_run(): void
    {
        // Expiring in 3 days
        $expiring = $this->createSubscription([
            'current_period_end' => now()->addDays(3)->startOfDay(),
        ]);

        // Expired yesterday
        $org2 = Organization::forceCreate([
            'name' => 'Expired Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);
        $expired = StoreSubscription::forceCreate([
            'organization_id' => $org2->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->subDay()->startOfDay(),
        ]);

        (new SendPaymentReminders)->handle();

        // Upcoming: all channels for expiring subscription
        $upcomingCount = PaymentReminder::where('store_subscription_id', $expiring->id)
            ->where('reminder_type', ReminderType::Upcoming->value)
            ->count();
        $this->assertEquals(count(ReminderChannel::cases()), $upcomingCount);

        // Overdue: email only for expired subscription
        $overdueCount = PaymentReminder::where('store_subscription_id', $expired->id)
            ->where('reminder_type', ReminderType::Overdue->value)
            ->count();
        $this->assertEquals(1, $overdueCount);
    }

    public function test_handles_no_matching_subscriptions_gracefully(): void
    {
        // No subscriptions at all
        (new SendPaymentReminders)->handle();

        $this->assertEquals(0, PaymentReminder::count());
    }

    public function test_multiple_subscriptions_get_independent_reminders(): void
    {
        $sub1 = $this->createSubscription([
            'current_period_end' => now()->addDays(3)->startOfDay(),
        ]);

        $org2 = Organization::forceCreate([
            'name' => 'Second Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);
        $sub2 = StoreSubscription::forceCreate([
            'organization_id' => $org2->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->addDays(3)->startOfDay(),
        ]);

        (new SendPaymentReminders)->handle();

        $sub1Count = PaymentReminder::where('store_subscription_id', $sub1->id)->count();
        $sub2Count = PaymentReminder::where('store_subscription_id', $sub2->id)->count();

        $this->assertEquals(count(ReminderChannel::cases()), $sub1Count);
        $this->assertEquals(count(ReminderChannel::cases()), $sub2Count);
    }
}
