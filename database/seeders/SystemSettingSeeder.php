<?php

namespace Database\Seeders;

use App\Domain\SystemConfig\Enums\SystemSettingsGroup;
use App\Domain\SystemConfig\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // ─── General ─────────────────────────────────────────
            ['key' => 'general_business_name', 'value' => 'Wameed POS', 'group' => SystemSettingsGroup::General, 'description' => 'Platform business name'],
            ['key' => 'general_support_email', 'value' => 'support@thawani.om', 'group' => SystemSettingsGroup::General, 'description' => 'Support email address'],

            // ─── Locale ──────────────────────────────────────────
            ['key' => 'locale_default_language', 'value' => 'ar', 'group' => SystemSettingsGroup::Locale, 'description' => 'Default language code'],
            ['key' => 'locale_default_currency', 'value' => 'SAR', 'group' => SystemSettingsGroup::Locale, 'description' => 'Default currency code'],
            ['key' => 'locale_currency_symbol_position', 'value' => 'after', 'group' => SystemSettingsGroup::Locale, 'description' => 'Currency symbol position (before/after)'],
            ['key' => 'locale_number_format', 'value' => 'western', 'group' => SystemSettingsGroup::Locale, 'description' => 'Number format (western/arabic)'],

            // ─── VAT ─────────────────────────────────────────────
            ['key' => 'vat_rate', 'value' => '15', 'group' => SystemSettingsGroup::Vat, 'description' => 'VAT rate percentage'],
            ['key' => 'vat_registration_number', 'value' => '', 'group' => SystemSettingsGroup::Vat, 'description' => 'VAT registration number'],

            // ─── Sync ────────────────────────────────────────────
            ['key' => 'sync_conflict_policy', 'value' => 'server_wins', 'group' => SystemSettingsGroup::Sync, 'description' => 'Offline sync conflict resolution policy'],
            ['key' => 'sync_interval_seconds', 'value' => '30', 'group' => SystemSettingsGroup::Sync, 'description' => 'Sync interval in seconds'],

            // ─── ZATCA ───────────────────────────────────────────
            ['key' => 'zatca_environment', 'value' => 'sandbox', 'group' => SystemSettingsGroup::Zatca, 'description' => 'ZATCA environment (sandbox/production)'],
            ['key' => 'zatca_api_base_url', 'value' => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal', 'group' => SystemSettingsGroup::Zatca, 'description' => 'ZATCA API base URL'],
            ['key' => 'zatca_client_id', 'value' => '', 'group' => SystemSettingsGroup::Zatca, 'description' => 'ZATCA client ID'],
            ['key' => 'zatca_client_secret', 'value' => '', 'group' => SystemSettingsGroup::Zatca, 'description' => 'ZATCA client secret'],
            ['key' => 'zatca_certificate_path', 'value' => '', 'group' => SystemSettingsGroup::Zatca, 'description' => 'Path to ZATCA compliance certificate'],

            // ─── SMS ─────────────────────────────────────────────
            ['key' => 'sms_provider', 'value' => 'unifonic', 'group' => SystemSettingsGroup::Sms, 'description' => 'SMS provider (unifonic/taqnyat/msegat)'],
            ['key' => 'sms_api_key', 'value' => '', 'group' => SystemSettingsGroup::Sms, 'description' => 'SMS API key'],
            ['key' => 'sms_sender_name', 'value' => 'Thawani', 'group' => SystemSettingsGroup::Sms, 'description' => 'SMS sender name'],
            ['key' => 'sms_base_url', 'value' => '', 'group' => SystemSettingsGroup::Sms, 'description' => 'SMS provider base URL'],

            // ─── Email ───────────────────────────────────────────
            ['key' => 'email_provider', 'value' => 'smtp', 'group' => SystemSettingsGroup::Email, 'description' => 'Email provider (smtp/mailgun/ses)'],
            ['key' => 'email_host', 'value' => '', 'group' => SystemSettingsGroup::Email, 'description' => 'SMTP host'],
            ['key' => 'email_port', 'value' => '587', 'group' => SystemSettingsGroup::Email, 'description' => 'SMTP port'],
            ['key' => 'email_username', 'value' => '', 'group' => SystemSettingsGroup::Email, 'description' => 'SMTP username'],
            ['key' => 'email_password', 'value' => '', 'group' => SystemSettingsGroup::Email, 'description' => 'SMTP password'],
            ['key' => 'email_from_address', 'value' => 'noreply@thawani.om', 'group' => SystemSettingsGroup::Email, 'description' => 'From email address'],
            ['key' => 'email_from_name', 'value' => 'Wameed POS', 'group' => SystemSettingsGroup::Email, 'description' => 'From name'],

            // ─── Push ────────────────────────────────────────────
            ['key' => 'push_fcm_server_key', 'value' => '', 'group' => SystemSettingsGroup::Push, 'description' => 'FCM server key'],
            ['key' => 'push_fcm_project_id', 'value' => '', 'group' => SystemSettingsGroup::Push, 'description' => 'FCM project ID'],
            ['key' => 'push_apns_key_id', 'value' => '', 'group' => SystemSettingsGroup::Push, 'description' => 'APNs key ID'],
            ['key' => 'push_apns_team_id', 'value' => '', 'group' => SystemSettingsGroup::Push, 'description' => 'APNs team ID'],
            ['key' => 'push_apns_key_file', 'value' => '', 'group' => SystemSettingsGroup::Push, 'description' => 'APNs key file path (.p8)'],

            // ─── WhatsApp ────────────────────────────────────────
            ['key' => 'whatsapp_provider', 'value' => 'meta_cloud_api', 'group' => SystemSettingsGroup::Whatsapp, 'description' => 'WhatsApp provider (meta_cloud_api/twilio)'],
            ['key' => 'whatsapp_access_token', 'value' => '', 'group' => SystemSettingsGroup::Whatsapp, 'description' => 'WhatsApp access token'],
            ['key' => 'whatsapp_phone_number_id', 'value' => '', 'group' => SystemSettingsGroup::Whatsapp, 'description' => 'WhatsApp phone number ID'],
            ['key' => 'whatsapp_business_account_id', 'value' => '', 'group' => SystemSettingsGroup::Whatsapp, 'description' => 'WhatsApp business account ID'],
            ['key' => 'whatsapp_webhook_verify_token', 'value' => '', 'group' => SystemSettingsGroup::Whatsapp, 'description' => 'Webhook verify token'],

            // ─── Maintenance ─────────────────────────────────────
            ['key' => 'maintenance_enabled', 'value' => false, 'group' => SystemSettingsGroup::Maintenance, 'description' => 'Maintenance mode toggle'],
            ['key' => 'maintenance_banner_en', 'value' => 'System is under maintenance. We will be back shortly.', 'group' => SystemSettingsGroup::Maintenance, 'description' => 'Maintenance banner (English)'],
            ['key' => 'maintenance_banner_ar', 'value' => 'النظام تحت الصيانة. سنعود قريباً.', 'group' => SystemSettingsGroup::Maintenance, 'description' => 'Maintenance banner (Arabic)'],
            ['key' => 'maintenance_expected_end', 'value' => '', 'group' => SystemSettingsGroup::Maintenance, 'description' => 'Expected end time'],
            ['key' => 'maintenance_allowed_ips', 'value' => '', 'group' => SystemSettingsGroup::Maintenance, 'description' => 'Allowed IP addresses (newline-separated)'],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'group' => $setting['group'],
                    'description' => $setting['description'],
                ],
            );
        }
    }
}
