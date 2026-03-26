<?php

namespace App\Domain\Announcement\Jobs;

use App\Domain\Announcement\Enums\ReminderChannel;
use App\Domain\Announcement\Enums\ReminderType;
use App\Domain\Announcement\Models\PaymentReminder;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendPaymentReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $this->sendUpcomingReminders();
        $this->sendOverdueReminders();
    }

    private function sendUpcomingReminders(): void
    {
        // Subscriptions expiring in 3 days that haven't been reminded
        $subscriptions = StoreSubscription::where('status', 'active')
            ->whereDate('current_period_end', now()->addDays(3)->toDateString())
            ->whereDoesntHave('paymentReminders', fn ($q) =>
                $q->where('reminder_type', ReminderType::Upcoming->value)
                  ->where('sent_at', '>=', now()->subDays(7))
            )
            ->get();

        foreach ($subscriptions as $subscription) {
            foreach (ReminderChannel::cases() as $channel) {
                PaymentReminder::create([
                    'store_subscription_id' => $subscription->id,
                    'reminder_type' => ReminderType::Upcoming->value,
                    'channel' => $channel->value,
                    'sent_at' => now(),
                ]);
            }

            Log::info("Sent upcoming payment reminder for subscription {$subscription->id}");
        }
    }

    private function sendOverdueReminders(): void
    {
        // Subscriptions that expired yesterday and haven't been reminded
        $subscriptions = StoreSubscription::where('status', 'active')
            ->whereDate('current_period_end', now()->subDay()->toDateString())
            ->whereDoesntHave('paymentReminders', fn ($q) =>
                $q->where('reminder_type', ReminderType::Overdue->value)
                  ->where('sent_at', '>=', now()->subDays(1))
            )
            ->get();

        foreach ($subscriptions as $subscription) {
            PaymentReminder::create([
                'store_subscription_id' => $subscription->id,
                'reminder_type' => ReminderType::Overdue->value,
                'channel' => ReminderChannel::Email->value,
                'sent_at' => now(),
            ]);

            Log::info("Sent overdue payment reminder for subscription {$subscription->id}");
        }
    }
}
