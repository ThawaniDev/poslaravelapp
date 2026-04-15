<?php

use App\Domain\Announcement\Jobs\SendPaymentReminders;
use App\Domain\AppUpdateManagement\Jobs\CheckAutoRollback;
use App\Domain\Notification\Jobs\ProcessScheduledNotificationsJob;
use App\Domain\ProviderSubscription\Jobs\ExpireSubscriptionsJob;
use App\Domain\ProviderSubscription\Jobs\GenerateRenewalInvoicesJob;
use App\Domain\ProviderSubscription\Jobs\RenewPaidSubscriptionsJob;
use App\Domain\ProviderSubscription\Jobs\RetryFailedPaymentsJob;
use App\Domain\Report\Jobs\RefreshDailySummariesJob;
use App\Domain\ThawaniIntegration\Jobs\ProcessThawaniSyncQueue;
use App\Domain\WameedAI\Jobs\CalculateEfficiencyScoreJob;
use App\Domain\WameedAI\Jobs\DetectAnomaliesJob;
use App\Domain\WameedAI\Jobs\DetectCashierErrorsJob;
use App\Domain\WameedAI\Jobs\GenerateDailySummaryJob;
use App\Domain\WameedAI\Jobs\GenerateExpiryAlertsJob;
use App\Domain\WameedAI\Jobs\GenerateReorderSuggestionsJob;
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

// ─── Report Summary Schedules ────────────────────────────────

// Refresh store-level daily & product sales summaries (daily at 2 AM)
Schedule::job(new RefreshDailySummariesJob)
    ->dailyAt('02:00')
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

// ─── Thawani Integration Schedules ───────────────────────────

// Process Thawani sync queue (every 5 minutes)
Schedule::job(new ProcessThawaniSyncQueue)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// ─── Wameed AI Schedules ─────────────────────────────────────

// Aggregate store-level daily AI usage (daily at 00:05)
Schedule::command('ai:aggregate-daily')
    ->dailyAt('00:05')
    ->withoutOverlapping()
    ->onOneServer();

// Aggregate monthly AI usage (1st of month at 00:30)
Schedule::command('ai:aggregate-monthly')
    ->monthlyOn(1, '00:30')
    ->withoutOverlapping()
    ->onOneServer();

// Aggregate platform-wide AI usage (daily at 00:15)
Schedule::command('ai:aggregate-platform')
    ->dailyAt('00:15')
    ->withoutOverlapping()
    ->onOneServer();

// Cleanup expired AI cache entries (daily at 03:00)
Schedule::command('ai:cleanup-cache')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onOneServer();

// Generate smart reorder suggestions for all stores (daily at 05:00)
Schedule::job(new GenerateReorderSuggestionsJob)
    ->dailyAt('05:00')
    ->withoutOverlapping()
    ->onOneServer();

// Generate daily business summary (daily at 23:30)
Schedule::job(new GenerateDailySummaryJob)
    ->dailyAt('23:30')
    ->withoutOverlapping()
    ->onOneServer();

// Detect revenue anomalies (daily at 04:00)
Schedule::job(new DetectAnomaliesJob)
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->onOneServer();

// Check for expiring products and generate alerts (daily at 06:30)
Schedule::job(new GenerateExpiryAlertsJob)
    ->dailyAt('06:30')
    ->withoutOverlapping()
    ->onOneServer();

// Detect cashier errors from previous day (daily at 01:00)
Schedule::job(new DetectCashierErrorsJob)
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->onOneServer();

// Calculate store efficiency scores (daily at 04:30)
Schedule::job(new CalculateEfficiencyScoreJob)
    ->dailyAt('04:30')
    ->withoutOverlapping()
    ->onOneServer();

// ─── Wameed AI Billing Schedules ─────────────────────────────

// Generate AI billing invoices (1st of month at 01:00)
Schedule::command('ai-billing:generate-invoices')
    ->monthlyOn(1, '01:00')
    ->withoutOverlapping()
    ->onOneServer();

// Check overdue AI billing invoices (daily at 02:00)
Schedule::command('ai-billing:check-overdue')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer();

// ─── Notification Schedules ──────────────────────────────────

// Process due scheduled notifications (every minute)
Schedule::job(new ProcessScheduledNotificationsJob)
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();
