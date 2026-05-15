<?php

namespace App\Domain\Notification\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Mail\NotificationMail;
use App\Domain\Notification\Models\NotificationCustom;
use App\Domain\Notification\Models\FcmToken;
use Illuminate\Support\Facades\Log;

/**
 * High-level notification dispatcher.
 *
 * Call this service from observers, listeners, or controllers to trigger
 * notifications. It creates an in-app notification AND sends an FCM push.
 *
 * Locale is resolved automatically from `users.locale`. Callers can still
 * override by passing `$locale` explicitly.
 */
class NotificationDispatcher
{
    public function __construct(
        private readonly FcmService $fcm,
        private readonly NotificationTemplateService $templates,
    ) {}

    // ─── Locale Resolution ───────────────────────────────

    /**
     * Supported locale codes. Any unsupported value falls back to 'ar'.
     */
    private const SUPPORTED_LOCALES = ['ar', 'en', 'ur', 'bn'];

    /**
     * Resolve the display locale for a user.
     *
     * Reads `users.locale` and normalises to a supported code, defaulting
     * to 'ar' (most users are Arabic-speaking).
     */
    private function localeForUser(string $userId): string
    {
        $raw = User::where('id', $userId)->value('locale') ?? 'ar';
        $code = strtolower(substr($raw, 0, 2)); // 'ar', 'en', 'ur', 'bn'
        return in_array($code, self::SUPPORTED_LOCALES, true) ? $code : 'ar';
    }

    // ─── Dispatch to User ────────────────────────────────

    /**
     * Send a notification to a single user by event key.
     *
     * Creates an in-app notification and sends a push notification.
     * Locale is auto-resolved from the user record unless explicitly provided.
     */
    public function toUser(
        string $userId,
        string $eventKey,
        array $variables = [],
        ?string $category = null,
        ?string $referenceId = null,
        ?string $referenceType = null,
        ?string $locale = null,   // null → auto-resolve from users.locale
        string $priority = 'normal',
        bool $alsoEmail = false,
    ): void {
        $resolvedLocale = $locale ?? $this->localeForUser($userId);

        // ── Render primary locale (user's language) ─────────────────────
        $rendered = $this->templates->render($eventKey, NotificationChannel::Push, $variables, $resolvedLocale);

        $events = NotificationTemplateService::allEvents();
        $meta = $events[$eventKey] ?? null;

        if (! $rendered) {
            // No template — use event catalog fallback text (locale-aware)
            $descKey = ($resolvedLocale === 'ar') ? 'description_ar' : 'description';
            $rendered = [
                'title' => ($meta[$descKey] ?? $meta['description'] ?? $eventKey),
                'body' => collect($variables)->map(fn ($v, $k) => "{$k}: {$v}")->join(', '),
            ];
        }

        // ── Render secondary locale (the other language) ─────────────────
        // Always store both EN and AR so the app can display the right one
        // regardless of what language the user's device switches to later.
        $secondaryLocale = ($resolvedLocale === 'ar') ? 'en' : 'ar';
        $renderedSecondary = $this->templates->render($eventKey, NotificationChannel::Push, $variables, $secondaryLocale);

        if (! $renderedSecondary) {
            $descKeySecondary = ($secondaryLocale === 'ar') ? 'description_ar' : 'description';
            $renderedSecondary = [
                'title' => ($meta[$descKeySecondary] ?? $meta['description'] ?? $eventKey),
                'body' => collect($variables)->map(fn ($v, $k) => "{$k}: {$v}")->join(', '),
            ];
        }

        // Map both renders to named keys so createInAppNotification can store them
        [$titleEn, $bodyEn, $titleAr, $bodyAr] = $resolvedLocale === 'ar'
            ? [$renderedSecondary['title'], $renderedSecondary['body'], $rendered['title'], $rendered['body']]
            : [$rendered['title'], $rendered['body'], $renderedSecondary['title'], $renderedSecondary['body']];

        // Primary title/body used for the FCM push payload (user's own language)
        $title = $rendered['title'];
        $body = $rendered['body'];
        $resolvedCategory = $category ?? explode('.', $eventKey)[0] ?? 'system';

        // Auto-enable email for critical events
        $isCritical = $meta['is_critical'] ?? false;
        $shouldEmail = $alsoEmail || $isCritical;

        // 1. Create in-app notification (bilingual)
        $this->createInAppNotification(
            userId: $userId,
            category: $resolvedCategory,
            title: $titleEn,
            body: $bodyEn,
            titleAr: $titleAr,
            bodyAr: $bodyAr,
            eventKey: $eventKey,
            referenceId: $referenceId,
            referenceType: $referenceType,
            priority: $priority,
            data: $variables,
        );

        // 2. Send FCM push (user's own language)
        $this->fcm->sendToUser($userId, $title, $body, [
            'event_key' => $eventKey,
            'category' => $resolvedCategory,
            'reference_id' => $referenceId ?? '',
            'reference_type' => $referenceType ?? '',
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        ]);

        // 3. Send email for critical events or when explicitly requested
        if ($shouldEmail) {
            $this->sendEmailToUser($userId, $title, $body);
        }
    }

    // ─── Send Email to User ──────────────────────────────

    private function sendEmailToUser(string $userId, string $title, string $body): void
    {
        try {
            $email = User::where('id', $userId)->value('email');

            if ($email) {
                EmailService::queue($email, new NotificationMail(
                    mailSubject: $title,
                    heading: $title,
                    body: $body,
                ));
            }
        } catch (\Throwable $e) {
            Log::warning('NotificationDispatcher: Email failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ─── Dispatch to Store ───────────────────────────────

    /**
     * Send a notification to all users in a store.
     * Each user's locale is resolved individually from their profile.
     */
    public function toStore(
        string $storeId,
        string $eventKey,
        array $variables = [],
        ?string $category = null,
        ?string $referenceId = null,
        ?string $referenceType = null,
        ?string $locale = null,   // null → auto-resolve per user
        string $priority = 'normal',
    ): void {
        $userIds = User::where('store_id', $storeId)
            ->where('is_active', true)
            ->pluck('id')
            ->toArray();

        foreach ($userIds as $userId) {
            $this->toUser(
                userId: $userId,
                eventKey: $eventKey,
                variables: $variables,
                category: $category,
                referenceId: $referenceId,
                referenceType: $referenceType,
                locale: $locale, // null → each toUser() call resolves its own locale
                priority: $priority,
            );
        }
    }

    // ─── Dispatch to Store Owner ─────────────────────────

    /**
     * Send a notification to the store owner only.
     * Owner's locale is resolved from their profile.
     */
    public function toStoreOwner(
        string $storeId,
        string $eventKey,
        array $variables = [],
        ?string $category = null,
        ?string $referenceId = null,
        ?string $referenceType = null,
        ?string $locale = null,   // null → auto-resolve from owner's profile
        string $priority = 'normal',
    ): void {
        $owner = User::where('store_id', $storeId)
            ->where('role', 'owner')
            ->where('is_active', true)
            ->first();

        if (! $owner) {
            // Fallback: first active user in the store
            $owner = User::where('store_id', $storeId)
                ->where('is_active', true)
                ->first();
        }

        if ($owner) {
            $this->toUser(
                userId: $owner->id,
                eventKey: $eventKey,
                variables: $variables,
                category: $category,
                referenceId: $referenceId,
                referenceType: $referenceType,
                locale: $locale,
                priority: $priority,
            );
        }
    }

    // ─── Quick Notification (no template) ────────────────

    /**
     * Send a quick notification with explicit title/body (no template lookup).
     */
    public function quickSend(
        string $userId,
        string $title,
        string $body,
        string $category = 'system',
        ?string $referenceId = null,
        ?string $referenceType = null,
        string $priority = 'normal',
    ): void {
        $this->createInAppNotification(
            userId: $userId,
            category: $category,
            title: $title,
            body: $body,
            eventKey: 'quick_send',
            referenceId: $referenceId,
            referenceType: $referenceType,
            priority: $priority,
        );

        $this->fcm->sendToUser($userId, $title, $body, [
            'category' => $category,
            'reference_id' => $referenceId ?? '',
            'reference_type' => $referenceType ?? '',
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        ]);
    }

    // ─── Create In-App Notification ──────────────────────

    private function createInAppNotification(
        string $userId,
        string $category,
        string $title,
        string $body,
        string $eventKey,
        ?string $referenceId,
        ?string $referenceType,
        string $priority = 'normal',
        array $data = [],
        string $titleAr = '',
        string $bodyAr = '',
    ): NotificationCustom {
        // Resolve store_id from the user so store-scoped queries work
        $storeId = User::where('id', $userId)->value('store_id');

        return NotificationCustom::create([
            'user_id' => $userId,
            'store_id' => $storeId,
            'category' => $category,
            'title' => $title,
            'message' => $body,
            'priority' => $priority,
            'reference_id' => $referenceId,
            'reference_type' => $referenceType,
            'metadata' => array_filter([
                'event_key' => $eventKey,
                'reference_id' => $referenceId,
                'reference_type' => $referenceType,
                'variables' => $data ?: null,
                // Bilingual content — Arabic version stored for locale-aware serving
                'title_ar' => $titleAr ?: null,
                'body_ar' => $bodyAr ?: null,
            ]),
        ]);
    }
}
