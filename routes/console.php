<?php

use App\Domain\Announcement\Jobs\SendPaymentReminders;
use App\Domain\AppUpdateManagement\Jobs\CheckAutoRollback;
use App\Domain\ProviderSubscription\Jobs\ExpireSubscriptionsJob;
use App\Domain\ProviderSubscription\Jobs\GenerateRenewalInvoicesJob;
use App\Domain\ProviderSubscription\Jobs\RenewPaidSubscriptionsJob;
use App\Domain\ProviderSubscription\Jobs\RetryFailedPaymentsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ─── Billing & Subscription Schedules ────────────────────────

// Generate renewal invoices 3 days before subscription ends (daily at 6 AM)
Schedule::job(new GenerateRenewalInvoicesJob(daysBeforeExpiry: 3))
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->onOneServer();

// Renew subscriptions that have paid invoices (daily at 7 AM, after renewals)
Schedule::job(new RenewPaidSubscriptionsJob)
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->onOneServer();

// Retry failed payments per retry rules (every 6 hours)
Schedule::job(new RetryFailedPaymentsJob)
    ->everySixHours()
    ->withoutOverlapping()
    ->onOneServer();

// Expire grace & trial subscriptions past their end date (daily at midnight)
Schedule::job(new ExpireSubscriptionsJob)
    ->dailyAt('00:00')
    ->withoutOverlapping()
    ->onOneServer();

// ─── Platform Analytics Schedules ────────────────────────────

// Aggregate daily platform statistics (daily at 1 AM)
Schedule::command('platform:aggregate-daily-stats')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->onOneServer();

// ─── Payment Reminder Schedules ──────────────────────────────

// Send upcoming & overdue payment reminders (daily at 8 AM)
Schedule::job(new SendPaymentReminders)
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->onOneServer();

// ─── App Update Schedules ────────────────────────────────────

// Check for auto-rollback of failing releases (every 30 minutes)
Schedule::job(new CheckAutoRollback)
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->onOneServer();
