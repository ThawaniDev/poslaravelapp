<?php

namespace App\Domain\Notification\Services;

use App\Domain\SystemConfig\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailService
{
    /**
     * Get email settings from SystemSetting (cached for 5 minutes).
     */
    public static function getSettings(): array
    {
        return Cache::remember('email_provider_settings', 300, function () {
            return SystemSetting::where('group', 'email')
                ->get()
                ->pluck('value', 'key')
                ->toArray();
        });
    }

    /**
     * Dynamically configure the mail driver from database settings and return the mailer name to use.
     */
    public static function configureMailer(): string
    {
        $settings = self::getSettings();
        $provider = $settings['email_provider'] ?? 'smtp';

        match ($provider) {
            'mailtrap' => self::configureMailtrap($settings),
            'mailgun' => self::configureMailgun($settings),
            'ses' => self::configureSes($settings),
            'postmark' => self::configurePostmark($settings),
            'resend' => self::configureResend($settings),
            default => self::configureSmtp($settings),
        };

        // Set the from address from DB settings
        if (! empty($settings['email_from_address'])) {
            Config::set('mail.from.address', $settings['email_from_address']);
            Config::set('mail.from.name', $settings['email_from_name'] ?? config('app.name'));
        }

        return $provider === 'smtp' ? 'smtp' : $provider;
    }

    /**
     * Send an email using the database-configured provider.
     */
    public static function send(string $to, \Illuminate\Mail\Mailable $mailable): void
    {
        $mailerName = self::configureMailer();

        // Check daily limit
        if (self::isDailyLimitReached()) {
            Log::warning('EmailService: Daily email limit reached, skipping', ['to' => $to]);
            return;
        }

        Mail::mailer($mailerName)->to($to)->send($mailable);
        self::incrementDailyCount();
    }

    /**
     * Queue an email using the database-configured provider.
     */
    public static function queue(string $to, \Illuminate\Mail\Mailable $mailable): void
    {
        $mailerName = self::configureMailer();

        if (self::isDailyLimitReached()) {
            Log::warning('EmailService: Daily email limit reached, skipping', ['to' => $to]);
            return;
        }

        Mail::mailer($mailerName)->to($to)->queue($mailable);
        self::incrementDailyCount();
    }

    // ─── Provider Configurations ─────────────────────────

    private static function configureMailtrap(array $settings): void
    {
        Config::set('services.mailtrap-sdk.apiKey', $settings['email_api_key'] ?? '');
        Config::set('services.mailtrap-sdk.host', 'send.api.mailtrap.io');

        // Mailtrap SDK transport is registered by MailtrapSdkProvider
        // Configure the mailer to use it
        Config::set('mail.mailers.mailtrap', [
            'transport' => 'mailtrap-sdk',
        ]);
        Config::set('mail.default', 'mailtrap');
    }

    private static function configureSmtp(array $settings): void
    {
        Config::set('mail.mailers.smtp.host', $settings['email_host'] ?? '127.0.0.1');
        Config::set('mail.mailers.smtp.port', (int) ($settings['email_port'] ?? 587));
        Config::set('mail.mailers.smtp.username', $settings['email_username'] ?? null);
        Config::set('mail.mailers.smtp.password', $settings['email_password'] ?? null);

        $encryption = $settings['email_encryption'] ?? 'tls';
        if ($encryption === 'none') {
            Config::set('mail.mailers.smtp.scheme', null);
        } else {
            Config::set('mail.mailers.smtp.scheme', $encryption);
        }

        Config::set('mail.default', 'smtp');
    }

    private static function configureMailgun(array $settings): void
    {
        Config::set('services.mailgun.secret', $settings['email_api_key'] ?? '');
        Config::set('services.mailgun.domain', $settings['email_api_domain'] ?? '');
        Config::set('mail.default', 'mailgun');
    }

    private static function configureSes(array $settings): void
    {
        Config::set('services.ses.key', $settings['email_username'] ?? '');
        Config::set('services.ses.secret', $settings['email_password'] ?? '');
        Config::set('services.ses.region', $settings['email_api_domain'] ?? 'us-east-1');
        Config::set('mail.default', 'ses');
    }

    private static function configurePostmark(array $settings): void
    {
        Config::set('services.postmark.token', $settings['email_api_key'] ?? '');
        Config::set('mail.default', 'postmark');
    }

    private static function configureResend(array $settings): void
    {
        Config::set('services.resend.key', $settings['email_api_key'] ?? '');
        Config::set('mail.default', 'resend');
    }

    // ─── Rate Limiting ───────────────────────────────────

    private static function isDailyLimitReached(): bool
    {
        $settings = self::getSettings();
        $limit = (int) ($settings['email_daily_limit'] ?? 0);

        if ($limit <= 0) {
            return false; // No limit
        }

        $count = (int) Cache::get('email_daily_count:' . date('Y-m-d'), 0);
        return $count >= $limit;
    }

    private static function incrementDailyCount(): void
    {
        $key = 'email_daily_count:' . date('Y-m-d');
        $count = (int) Cache::get($key, 0);
        Cache::put($key, $count + 1, now()->endOfDay());
    }

    /**
     * Flush the cached email settings (call after admin saves settings).
     */
    public static function flushCache(): void
    {
        Cache::forget('email_provider_settings');
    }
}
