<?php

namespace App\Domain\Notification\Services;

use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationDeliveryStatus;
use App\Domain\Notification\Enums\NotificationProvider;
use App\Domain\Notification\Mail\NotificationMail;
use App\Domain\Notification\Models\NotificationDeliveryLog;
use App\Domain\Notification\Models\NotificationProviderStatus;
use App\Domain\Notification\Models\NotificationTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NotificationTemplateService
{
    // ─── Event Catalog ───────────────────────────────────────────

    /**
     * Full event catalog with descriptions and available variables.
     * This is the single source of truth for all notification events.
     */
    public static function eventCatalog(): array
    {
        return [
            'order' => [
                'label' => 'Order Events',
                'events' => [
                    'order.new' => [
                        'description' => 'New order received',
                        'default_recipients' => 'Store owner, active cashier',
                        'variables' => ['order_id', 'total', 'store_name', 'branch_name'],
                        'is_critical' => false,
                    ],
                    'order.new_external' => [
                        'description' => 'New order from delivery platform',
                        'default_recipients' => 'Store owner, active cashier',
                        'variables' => ['order_id', 'total', 'platform', 'customer_name', 'item_count', 'store_name'],
                        'is_critical' => false,
                    ],
                    'order.status_changed' => [
                        'description' => 'Order status transition',
                        'default_recipients' => 'Store owner',
                        'variables' => ['order_id', 'old_status', 'new_status', 'store_name'],
                        'is_critical' => false,
                    ],
                    'order.completed' => [
                        'description' => 'Order fulfilled',
                        'default_recipients' => 'Store owner',
                        'variables' => ['order_id', 'total', 'store_name'],
                        'is_critical' => false,
                    ],
                    'order.cancelled' => [
                        'description' => 'Order cancelled',
                        'default_recipients' => 'Store owner, cashier',
                        'variables' => ['order_id', 'total', 'reason', 'store_name'],
                        'is_critical' => false,
                    ],
                    'order.refund_requested' => [
                        'description' => 'Refund requested',
                        'default_recipients' => 'Store owner',
                        'variables' => ['order_id', 'amount', 'store_name'],
                        'is_critical' => false,
                    ],
                    'order.refund_approved' => [
                        'description' => 'Refund processed',
                        'default_recipients' => 'Store owner, cashier',
                        'variables' => ['order_id', 'amount', 'store_name'],
                        'is_critical' => false,
                    ],
                    'order.payment_failed' => [
                        'description' => 'Payment failed',
                        'default_recipients' => 'Store owner, cashier',
                        'variables' => ['order_id', 'total', 'store_name'],
                        'is_critical' => true,
                    ],
                ],
            ],
            'inventory' => [
                'label' => 'Inventory Events',
                'events' => [
                    'inventory.low_stock' => [
                        'description' => 'Product below reorder point',
                        'default_recipients' => 'Store owner, inventory manager',
                        'variables' => ['product_name', 'current_qty', 'reorder_point', 'store_name'],
                        'is_critical' => false,
                    ],
                    'inventory.out_of_stock' => [
                        'description' => 'Product at zero stock',
                        'default_recipients' => 'Store owner, inventory manager',
                        'variables' => ['product_name', 'store_name'],
                        'is_critical' => false,
                    ],
                    'inventory.expiry_warning' => [
                        'description' => 'Product expiring soon',
                        'default_recipients' => 'Store owner',
                        'variables' => ['product_name', 'expiry_date', 'days_remaining', 'store_name'],
                        'is_critical' => false,
                    ],
                    'inventory.excess_stock' => [
                        'description' => 'Stock above max threshold',
                        'default_recipients' => 'Store owner',
                        'variables' => ['product_name', 'current_qty', 'max_threshold', 'store_name'],
                        'is_critical' => false,
                    ],
                    'inventory.adjustment' => [
                        'description' => 'Manual stock change',
                        'default_recipients' => 'Store owner',
                        'variables' => ['product_name', 'old_qty', 'new_qty', 'adjusted_by', 'store_name'],
                        'is_critical' => false,
                    ],
                ],
            ],
            'finance' => [
                'label' => 'Finance Events',
                'events' => [
                    'finance.daily_summary' => [
                        'description' => 'End-of-day summary',
                        'default_recipients' => 'Store owner',
                        'variables' => ['date', 'total_sales', 'total_transactions', 'store_name'],
                        'is_critical' => false,
                    ],
                    'finance.shift_closed' => [
                        'description' => 'Shift end report',
                        'default_recipients' => 'Store owner',
                        'variables' => ['cashier_name', 'total_sales', 'cash_expected', 'cash_actual', 'store_name'],
                        'is_critical' => false,
                    ],
                    'finance.cash_discrepancy' => [
                        'description' => 'Cash doesn\'t match expected',
                        'default_recipients' => 'Store owner',
                        'variables' => ['cashier_name', 'expected', 'actual', 'difference', 'store_name'],
                        'is_critical' => true,
                    ],
                    'finance.large_transaction' => [
                        'description' => 'High-value transaction',
                        'default_recipients' => 'Store owner',
                        'variables' => ['transaction_id', 'amount', 'cashier_name', 'store_name'],
                        'is_critical' => false,
                    ],
                ],
            ],
            'system' => [
                'label' => 'System Events',
                'events' => [
                    'system.offline_mode' => [
                        'description' => 'POS went offline',
                        'default_recipients' => 'Store owner',
                        'variables' => ['device_name', 'store_name'],
                        'is_critical' => true,
                    ],
                    'system.sync_failed' => [
                        'description' => 'Sync error',
                        'default_recipients' => 'Store owner',
                        'variables' => ['platform', 'error_message', 'store_name'],
                        'is_critical' => true,
                    ],
                    'system.printer_error' => [
                        'description' => 'Printer offline',
                        'default_recipients' => 'Active cashier',
                        'variables' => ['printer_name', 'store_name'],
                        'is_critical' => false,
                    ],
                    'system.update_available' => [
                        'description' => 'New POS version available',
                        'default_recipients' => 'Store owner',
                        'variables' => ['version', 'release_notes_summary'],
                        'is_critical' => false,
                    ],
                    'system.license_expiring' => [
                        'description' => 'Subscription expiring soon',
                        'default_recipients' => 'Store owner',
                        'variables' => ['plan_name', 'expiry_date', 'days_remaining'],
                        'is_critical' => false,
                    ],
                ],
            ],
            'staff' => [
                'label' => 'Staff Events',
                'events' => [
                    'staff.login' => [
                        'description' => 'Employee login',
                        'default_recipients' => 'Store owner',
                        'variables' => ['user_name', 'device_name', 'store_name'],
                        'is_critical' => false,
                    ],
                    'staff.unauthorized_access' => [
                        'description' => 'Access denied attempt',
                        'default_recipients' => 'Store owner',
                        'variables' => ['user_name', 'attempted_action', 'store_name'],
                        'is_critical' => true,
                    ],
                    'staff.discount_applied' => [
                        'description' => 'Discount above threshold',
                        'default_recipients' => 'Store owner',
                        'variables' => ['user_name', 'discount_pct', 'order_id', 'store_name'],
                        'is_critical' => false,
                    ],
                    'staff.void_transaction' => [
                        'description' => 'Transaction voided',
                        'default_recipients' => 'Store owner',
                        'variables' => ['user_name', 'transaction_id', 'amount', 'store_name'],
                        'is_critical' => false,
                    ],
                ],
            ],
        ];
    }

    /**
     * Get flat list of all event keys → metadata.
     */
    public static function allEvents(): array
    {
        $flat = [];
        foreach (self::eventCatalog() as $category => $group) {
            foreach ($group['events'] as $key => $meta) {
                $flat[$key] = array_merge($meta, ['category' => $category, 'category_label' => $group['label']]);
            }
        }
        return $flat;
    }

    /**
     * Get available variables for a given event key.
     */
    public static function getAvailableVariables(string $eventKey): array
    {
        $events = self::allEvents();
        return $events[$eventKey]['variables'] ?? [];
    }

    /**
     * Get grouped event keys for select options.
     */
    public static function eventSelectOptions(): array
    {
        $options = [];
        foreach (self::eventCatalog() as $group) {
            foreach ($group['events'] as $key => $meta) {
                $options[$group['label']][$key] = "{$key} — {$meta['description']}";
            }
        }
        return $options;
    }

    // ─── Template Rendering ──────────────────────────────────────

    /**
     * Render a template: fetch by event_key + channel, interpolate variables.
     *
     * Returns null if no active template found.
     */
    public function render(string $eventKey, NotificationChannel $channel, array $variables, string $locale = 'en'): ?array
    {
        $template = Cache::remember(
            "notification_template:{$eventKey}:{$channel->value}",
            300,
            fn () => NotificationTemplate::where('event_key', $eventKey)
                ->where('channel', $channel)
                ->where('is_active', true)
                ->first(),
        );

        if (!$template) {
            Log::warning('NotificationTemplate not found', ['event_key' => $eventKey, 'channel' => $channel->value]);
            return null;
        }

        $title = $locale === 'ar' ? $template->title_ar : $template->title;
        $body = $locale === 'ar' ? $template->body_ar : $template->body;

        return [
            'title' => $this->interpolate($title, $variables),
            'body' => $this->interpolate($body, $variables),
            'event_key' => $eventKey,
            'channel' => $channel->value,
            'template_id' => $template->id,
        ];
    }

    /**
     * Render a template with sample data for preview purposes.
     */
    public function renderPreview(NotificationTemplate $template, string $locale = 'en'): array
    {
        $sampleData = [];
        foreach ($template->available_variables ?? [] as $var) {
            $sampleData[$var] = $this->sampleValue($var);
        }

        $title = $locale === 'ar' ? $template->title_ar : $template->title;
        $body = $locale === 'ar' ? $template->body_ar : $template->body;

        return [
            'title' => $this->interpolate($title, $sampleData),
            'body' => $this->interpolate($body, $sampleData),
            'sample_data' => $sampleData,
        ];
    }

    /**
     * Replace {{variable}} tokens with values. Undefined variables → empty string.
     */
    private function interpolate(string $text, array $variables): string
    {
        return preg_replace_callback('/\{\{(\w+)\}\}/', function ($matches) use ($variables) {
            return $variables[$matches[1]] ?? '';
        }, $text);
    }

    /**
     * Generate realistic sample values for preview.
     */
    private function sampleValue(string $variable): string
    {
        return match ($variable) {
            'order_id' => 'ORD-20260325-0042',
            'total' => '127.50 SAR',
            'store_name' => 'مطعم الشامي',
            'branch_name' => 'الفرع الرئيسي',
            'platform' => 'HungerStation',
            'customer_name' => 'أحمد محمد',
            'item_count' => '5',
            'old_status' => 'pending',
            'new_status' => 'completed',
            'reason' => 'Customer request',
            'amount' => '45.00 SAR',
            'product_name' => 'قهوة عربية',
            'current_qty' => '3',
            'reorder_point' => '10',
            'expiry_date' => '2026-04-01',
            'days_remaining' => '7',
            'max_threshold' => '500',
            'old_qty' => '50',
            'new_qty' => '45',
            'adjusted_by' => 'خالد علي',
            'date' => '2026-03-25',
            'total_sales' => '4,250.00 SAR',
            'total_transactions' => '87',
            'cashier_name' => 'سارة أحمد',
            'cash_expected' => '2,100.00 SAR',
            'cash_actual' => '2,085.00 SAR',
            'expected' => '2,100.00 SAR',
            'actual' => '2,085.00 SAR',
            'difference' => '15.00 SAR',
            'transaction_id' => 'TXN-20260325-0091',
            'device_name' => 'POS Terminal #3',
            'error_message' => 'Connection timeout after 30s',
            'printer_name' => 'Epson TM-T88VI Kitchen',
            'version' => 'v2.4.0',
            'release_notes_summary' => 'Bug fixes and performance improvements',
            'plan_name' => 'Premium',
            'user_name' => 'محمد علي',
            'attempted_action' => 'Void Transaction',
            'discount_pct' => '25%',
            default => "[{$variable}]",
        };
    }

    // ─── Dispatch with Fallback ──────────────────────────────────

    /**
     * Dispatch a notification through the appropriate channel with provider fallback.
     *
     * This is the main entry point for sending notifications. It:
     * 1. Renders the template
     * 2. Gets ordered providers for the channel
     * 3. Attempts delivery through each provider in priority order
     * 4. Logs all attempts
     */
    public function dispatch(
        string $eventKey,
        NotificationChannel $channel,
        string $recipient,
        array $variables,
        string $locale = 'en',
        ?string $notificationId = null,
    ): NotificationDeliveryLog {
        $rendered = $this->render($eventKey, $channel, $variables, $locale);

        if (!$rendered) {
            return $this->logDelivery(
                notificationId: $notificationId,
                channel: $channel,
                provider: 'none',
                recipient: $recipient,
                status: NotificationDeliveryStatus::Failed,
                errorMessage: "No active template for {$eventKey}:{$channel->value}",
                attemptedProviders: [],
            );
        }

        $providers = $this->getOrderedProviders($channel);

        if ($providers->isEmpty()) {
            Log::error('No providers available for channel', ['channel' => $channel->value]);
            return $this->logDelivery(
                notificationId: $notificationId,
                channel: $channel,
                provider: 'none',
                recipient: $recipient,
                status: NotificationDeliveryStatus::Failed,
                errorMessage: 'No providers configured for channel',
                attemptedProviders: [],
            );
        }

        $attemptedProviders = [];
        $isFallback = false;

        foreach ($providers as $providerStatus) {
            $providerName = $providerStatus->provider instanceof NotificationProvider
                ? $providerStatus->provider->value
                : (string) $providerStatus->provider;
            $attemptedProviders[] = $providerName;
            $startTime = microtime(true);

            try {
                $providerMessageId = $this->sendViaProvider(
                    $providerName,
                    $channel,
                    $recipient,
                    $rendered['title'],
                    $rendered['body'],
                );

                $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

                // Update provider success stats
                $providerStatus->update([
                    'last_success_at' => now(),
                    'success_count_24h' => $providerStatus->success_count_24h + 1,
                    'avg_latency_ms' => $providerStatus->avg_latency_ms
                        ? (int) (($providerStatus->avg_latency_ms + $latencyMs) / 2)
                        : $latencyMs,
                    'is_healthy' => true,
                ]);

                // Clear cache for this channel's providers
                Cache::forget("notification_providers:{$channel->value}");

                return $this->logDelivery(
                    notificationId: $notificationId,
                    channel: $channel,
                    provider: $providerName,
                    recipient: $recipient,
                    status: NotificationDeliveryStatus::Sent,
                    providerMessageId: $providerMessageId,
                    latencyMs: $latencyMs,
                    isFallback: $isFallback,
                    attemptedProviders: $attemptedProviders,
                );
            } catch (\Throwable $e) {
                $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

                Log::warning("Notification provider failed", [
                    'provider' => $providerName,
                    'channel' => $channel->value,
                    'event_key' => $eventKey,
                    'error' => $e->getMessage(),
                ]);

                // Update provider failure stats
                $providerStatus->update([
                    'last_failure_at' => now(),
                    'failure_count_24h' => $providerStatus->failure_count_24h + 1,
                    'is_healthy' => ($providerStatus->failure_count_24h + 1) < 20,
                ]);

                // Auto-demote if failure rate exceeds threshold
                $totalRecent = $providerStatus->success_count_24h + $providerStatus->failure_count_24h + 1;
                if ($totalRecent >= 100 && ($providerStatus->failure_count_24h + 1) / $totalRecent > 0.2) {
                    $providerStatus->update([
                        'is_healthy' => false,
                        'disabled_reason' => 'Auto-demoted: failure rate >20% over 100 messages',
                    ]);
                }

                Cache::forget("notification_providers:{$channel->value}");
                $isFallback = true;
            }
        }

        // All providers exhausted
        Log::error('All notification providers exhausted', [
            'channel' => $channel->value,
            'event_key' => $eventKey,
            'attempted' => $attemptedProviders,
        ]);

        return $this->logDelivery(
            notificationId: $notificationId,
            channel: $channel,
            provider: end($attemptedProviders) ?: 'none',
            recipient: $recipient,
            status: NotificationDeliveryStatus::Failed,
            errorMessage: 'All providers exhausted',
            attemptedProviders: $attemptedProviders,
            isFallback: true,
        );
    }

    /**
     * Get providers for a channel, ordered by priority (healthy first).
     */
    private function getOrderedProviders(NotificationChannel $channel)
    {
        return Cache::remember("notification_providers:{$channel->value}", 60, function () use ($channel) {
            return NotificationProviderStatus::where('channel', $channel)
                ->where('is_enabled', true)
                ->orderByDesc('is_healthy')
                ->orderBy('priority')
                ->get();
        });
    }

    /**
     * Attempt to deliver via a specific provider.
     * Returns the provider message ID on success or throws on failure.
     */
    private function sendViaProvider(
        string $provider,
        NotificationChannel $channel,
        string $recipient,
        string $title,
        string $body,
    ): ?string {
        return match ($provider) {
            'firebase' => $this->sendViaFirebase($recipient, $title, $body),
            'smtp', 'mailgun', 'ses', 'sendgrid' => $this->sendViaEmail($provider, $recipient, $title, $body),
            default => $this->sendViaGenericLog($provider, $channel, $recipient, $title),
        };
    }

    /**
     * Send push notification via Firebase Cloud Messaging.
     *
     * $recipient is a user_id — FCM tokens are resolved internally.
     */
    private function sendViaFirebase(string $recipient, string $title, string $body): ?string
    {
        $fcm = app(FcmService::class);
        $result = $fcm->sendToUser($recipient, $title, $body, [
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        ]);

        if ($result['success'] === 0 && $result['failure'] === 0) {
            // No tokens registered — not an error, just nothing to send
            Log::info('FCM: No tokens for user', ['user_id' => $recipient]);
            return 'no_tokens';
        }

        if ($result['success'] === 0) {
            throw new \RuntimeException("FCM delivery failed for all {$result['failure']} tokens");
        }

        Log::info('FCM: Delivered', [
            'user_id' => $recipient,
            'success' => $result['success'],
            'failure' => $result['failure'],
        ]);

        return "fcm_ok_{$result['success']}";
    }

    /**
     * Send email notification via a mail provider (smtp, mailgun, ses, sendgrid).
     *
     * $recipient is an email address.
     */
    private function sendViaEmail(string $provider, string $recipient, string $title, string $body): string
    {
        EmailService::send($recipient, new NotificationMail(
            subject: $title,
            heading: $title,
            body: $body,
        ));

        Log::info('Email: Delivered', [
            'provider' => $provider,
            'recipient' => $recipient,
        ]);

        return 'email_' . $provider . '_' . bin2hex(random_bytes(4));
    }

    /**
     * Fallback: log-only provider (for channels not yet integrated).
     */
    private function sendViaGenericLog(string $provider, NotificationChannel $channel, string $recipient, string $title): string
    {
        Log::info("Notification dispatched (log-only)", [
            'provider' => $provider,
            'channel' => $channel->value,
            'recipient' => $recipient,
            'title' => mb_substr($title, 0, 50),
        ]);

        return 'log_' . bin2hex(random_bytes(8));
    }

    /**
     * Log a delivery attempt to notification_delivery_logs.
     */
    private function logDelivery(
        ?string $notificationId,
        NotificationChannel $channel,
        string $provider,
        string $recipient,
        NotificationDeliveryStatus $status,
        ?string $providerMessageId = null,
        ?string $errorMessage = null,
        ?int $latencyMs = null,
        bool $isFallback = false,
        array $attemptedProviders = [],
    ): NotificationDeliveryLog {
        return NotificationDeliveryLog::create([
            'notification_id' => $notificationId,
            'channel' => $channel,
            'provider' => $provider,
            'recipient' => $recipient,
            'status' => $status,
            'provider_message_id' => $providerMessageId,
            'error_message' => $errorMessage,
            'latency_ms' => $latencyMs,
            'is_fallback' => $isFallback,
            'attempted_providers' => $attemptedProviders,
        ]);
    }

    // ─── Cache Management ────────────────────────────────────────

    /**
     * Flush template cache for a specific template.
     */
    public function flushTemplateCache(NotificationTemplate $template): void
    {
        Cache::forget("notification_template:{$template->event_key}:{$template->channel->value}");
    }

    /**
     * Flush all template caches.
     */
    public function flushAllTemplateCaches(): void
    {
        $templates = NotificationTemplate::select('event_key', 'channel')->get();
        foreach ($templates as $t) {
            $channelVal = $t->channel instanceof NotificationChannel ? $t->channel->value : (string) $t->channel;
            Cache::forget("notification_template:{$t->event_key}:{$channelVal}");
        }
    }
}
