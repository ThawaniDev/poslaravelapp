<?php

namespace App\Domain\Announcement\Jobs;

use App\Domain\Announcement\Enums\ReminderChannel;
use App\Domain\Announcement\Enums\ReminderType;
use App\Domain\Announcement\Models\PaymentReminder;
use App\Domain\Notification\Mail\PaymentReminderMail;
use App\Domain\Notification\Services\EmailService;
use App\Domain\Notification\Services\NotificationDispatcher;
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

    public function handle(NotificationDispatcher $dispatcher): void
    {
        $this->sendUpcomingReminders($dispatcher);
        $this->sendOverdueReminders($dispatcher);
    }

    private function sendUpcomingReminders(NotificationDispatcher $dispatcher): void
    {
        // Subscriptions expiring in 3 days that haven't been reminded
        $subscriptions = StoreSubscription::with(['organization', 'subscriptionPlan'])
            ->where('status', 'active')
            ->whereDate('current_period_end', now()->addDays(3)->toDateString())
            ->whereDoesntHave('paymentReminders', fn ($q) =>
                $q->where('reminder_type', ReminderType::Upcoming->value)
                  ->where('sent_at', '>=', now()->subDays(7))
            )
            ->get();

        foreach ($subscriptions as $subscription) {
            $planName = $subscription->subscriptionPlan?->name ?? 'Your Plan';
            $expiryDate = $subscription->current_period_end->format('M d, Y');
            $orgName = $subscription->organization?->name ?? 'Your Organization';

            foreach (ReminderChannel::cases() as $channel) {
                PaymentReminder::create([
                    'store_subscription_id' => $subscription->id,
                    'reminder_type' => ReminderType::Upcoming->value,
                    'channel' => $channel->value,
                    'sent_at' => now(),
                ]);
            }

            // Send push notification to store owners in this organization
            $this->sendPushToOrganization($dispatcher, $subscription, 'upcoming', $planName, $expiryDate);

            // Send email to organization owner
            $this->sendEmailToOrganization($subscription, 'upcoming', $planName, $expiryDate, $orgName);

            Log::info("Payment reminder (upcoming) sent for subscription {$subscription->id}");
        }
    }

    private function sendOverdueReminders(NotificationDispatcher $dispatcher): void
    {
        // Subscriptions that expired yesterday and haven't been reminded
        $subscriptions = StoreSubscription::with(['organization', 'subscriptionPlan'])
            ->where('status', 'active')
            ->whereDate('current_period_end', now()->subDay()->toDateString())
            ->whereDoesntHave('paymentReminders', fn ($q) =>
                $q->where('reminder_type', ReminderType::Overdue->value)
                  ->where('sent_at', '>=', now()->subDays(1))
            )
            ->get();

        foreach ($subscriptions as $subscription) {
            $planName = $subscription->subscriptionPlan?->name ?? 'Your Plan';
            $expiryDate = $subscription->current_period_end->format('M d, Y');
            $orgName = $subscription->organization?->name ?? 'Your Organization';

            PaymentReminder::create([
                'store_subscription_id' => $subscription->id,
                'reminder_type' => ReminderType::Overdue->value,
                'channel' => ReminderChannel::Email->value,
                'sent_at' => now(),
            ]);

            // Send push notification
            $this->sendPushToOrganization($dispatcher, $subscription, 'overdue', $planName, $expiryDate);

            // Send email
            $this->sendEmailToOrganization($subscription, 'overdue', $planName, $expiryDate, $orgName);

            Log::info("Payment reminder (overdue) sent for subscription {$subscription->id}");
        }
    }

    private function sendPushToOrganization(
        NotificationDispatcher $dispatcher,
        StoreSubscription $subscription,
        string $type,
        string $planName,
        string $expiryDate,
    ): void {
        try {
            // Get all stores for this organization
            $storeIds = \App\Domain\Core\Models\Store::where('organization_id', $subscription->organization_id)
                ->where('is_active', true)
                ->pluck('id');

            foreach ($storeIds as $storeId) {
                $dispatcher->toStoreOwner(
                    storeId: $storeId,
                    eventKey: 'system.license_expiring',
                    variables: [
                        'plan_name' => $planName,
                        'expiry_date' => $expiryDate,
                        'days_remaining' => $type === 'upcoming' ? '3' : '0',
                    ],
                    priority: $type === 'overdue' ? 'high' : 'normal',
                );
            }
        } catch (\Throwable $e) {
            Log::warning("SendPaymentReminders: Push failed for subscription {$subscription->id}", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendEmailToOrganization(
        StoreSubscription $subscription,
        string $type,
        string $planName,
        string $expiryDate,
        string $orgName,
    ): void {
        try {
            // Find the organization owner (first user of the main store)
            $mainStore = \App\Domain\Core\Models\Store::where('organization_id', $subscription->organization_id)
                ->where('is_main_branch', true)
                ->first();

            if (! $mainStore) {
                return;
            }

            $owner = \App\Domain\Auth\Models\User::where('store_id', $mainStore->id)
                ->whereHas('roles', fn ($q) => $q->where('name', 'store_owner'))
                ->first();

            $recipient = $owner ?? \App\Domain\Auth\Models\User::where('store_id', $mainStore->id)->first();

            if ($recipient?->email) {
                EmailService::queue($recipient->email, new PaymentReminderMail(
                    reminderType: $type,
                    planName: $planName,
                    expiryDate: $expiryDate,
                    organizationName: $orgName,
                ));
            }
        } catch (\Throwable $e) {
            Log::warning("SendPaymentReminders: Email failed for subscription {$subscription->id}", [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
