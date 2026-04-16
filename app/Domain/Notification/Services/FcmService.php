<?php

namespace App\Domain\Notification\Services;

use App\Domain\Notification\Models\FcmToken;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FcmService
{
    private ?Messaging $messaging = null;

    // ─── Firebase Messaging Instance ─────────────────────

    private function messaging(): Messaging
    {
        if ($this->messaging === null) {
            $credentialsPath = config('firebase.credentials');

            // Support both absolute and relative paths
            if (! str_starts_with($credentialsPath, '/')) {
                $credentialsPath = base_path($credentialsPath);
            }

            $this->messaging = (new Factory)
                ->withServiceAccount($credentialsPath)
                ->createMessaging();
        }

        return $this->messaging;
    }

    // ─── Send to Single Token ────────────────────────────

    /**
     * Send a push notification to a single FCM token.
     *
     * @return string|null  The FCM message name (ID) on success, null on failure.
     */
    public function sendToToken(
        string $token,
        string $title,
        string $body,
        array $data = [],
    ): ?string {
        try {
            $message = CloudMessage::withTarget('token', $token)
                ->withNotification(Notification::create($title, $body))
                ->withData($this->sanitizeData($data));

            $result = $this->messaging()->send($message);

            return is_array($result) ? ($result['name'] ?? 'sent') : (string) $result;
        } catch (\Kreait\Firebase\Exception\Messaging\NotFound $e) {
            // Token is stale — remove it
            $this->removeStaleToken($token);
            Log::info('FCM: Removed stale token', ['token' => substr($token, 0, 20) . '...']);
            return null;
        } catch (\Kreait\Firebase\Exception\Messaging\InvalidMessage $e) {
            Log::warning('FCM: Invalid message', ['error' => $e->getMessage()]);
            return null;
        } catch (\Throwable $e) {
            Log::error('FCM: Send failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ─── Send to Multiple Tokens ─────────────────────────

    /**
     * Send a push notification to multiple FCM tokens.
     *
     * @return array{success: int, failure: int, stale_tokens: string[]}
     */
    public function sendToTokens(
        array $tokens,
        string $title,
        string $body,
        array $data = [],
    ): array {
        if (empty($tokens)) {
            return ['success' => 0, 'failure' => 0, 'stale_tokens' => []];
        }

        $message = CloudMessage::new()
            ->withNotification(Notification::create($title, $body))
            ->withData($this->sanitizeData($data));

        try {
            $report = $this->messaging()->sendMulticast($message, $tokens);

            $staleTokens = [];
            $failures = $report->failures();

            foreach ($failures->getItems() as $failure) {
                $failedToken = $failure->target()->value();
                $error = $failure->error();

                // Remove tokens that are no longer valid
                if ($error && (
                    str_contains($error->getMessage(), 'not-found') ||
                    str_contains($error->getMessage(), 'UNREGISTERED') ||
                    str_contains($error->getMessage(), 'invalid-registration-token')
                )) {
                    $staleTokens[] = $failedToken;
                }
            }

            // Clean up stale tokens
            if (! empty($staleTokens)) {
                FcmToken::whereIn('token', $staleTokens)->delete();
                Log::info('FCM: Cleaned stale tokens', ['count' => count($staleTokens)]);
            }

            return [
                'success' => $report->successes()->count(),
                'failure' => $report->failures()->count(),
                'stale_tokens' => $staleTokens,
            ];
        } catch (\Throwable $e) {
            Log::error('FCM: Multicast failed', ['error' => $e->getMessage(), 'token_count' => count($tokens)]);
            return ['success' => 0, 'failure' => count($tokens), 'stale_tokens' => []];
        }
    }

    // ─── Send to User ────────────────────────────────────

    /**
     * Send a push notification to all devices registered for a user.
     *
     * @return array{success: int, failure: int}
     */
    public function sendToUser(
        string $userId,
        string $title,
        string $body,
        array $data = [],
    ): array {
        $tokens = FcmToken::where('user_id', $userId)->pluck('token')->toArray();

        if (empty($tokens)) {
            return ['success' => 0, 'failure' => 0];
        }

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    // ─── Send to Multiple Users ──────────────────────────

    /**
     * Send a push notification to all devices of multiple users.
     *
     * @param  string[]  $userIds
     * @return array{success: int, failure: int}
     */
    public function sendToUsers(
        array $userIds,
        string $title,
        string $body,
        array $data = [],
    ): array {
        $tokens = FcmToken::whereIn('user_id', $userIds)->pluck('token')->toArray();

        if (empty($tokens)) {
            return ['success' => 0, 'failure' => 0];
        }

        // FCM multicast supports max 500 tokens per call
        $totalSuccess = 0;
        $totalFailure = 0;

        foreach (array_chunk($tokens, 500) as $chunk) {
            $result = $this->sendToTokens($chunk, $title, $body, $data);
            $totalSuccess += $result['success'];
            $totalFailure += $result['failure'];
        }

        return ['success' => $totalSuccess, 'failure' => $totalFailure];
    }

    // ─── Send to Store ───────────────────────────────────

    /**
     * Send a push notification to all users of a store.
     *
     * @return array{success: int, failure: int}
     */
    public function sendToStore(
        string $storeId,
        string $title,
        string $body,
        array $data = [],
    ): array {
        $userIds = \App\Domain\Auth\Models\User::where('store_id', $storeId)
            ->pluck('id')
            ->toArray();

        return $this->sendToUsers($userIds, $title, $body, $data);
    }

    // ─── Helpers ─────────────────────────────────────────

    /**
     * FCM data values must be strings.
     */
    private function sanitizeData(array $data): array
    {
        return array_map(fn ($v) => is_string($v) ? $v : json_encode($v), $data);
    }

    /**
     * Remove a stale token from the database.
     */
    private function removeStaleToken(string $token): void
    {
        FcmToken::where('token', $token)->delete();
    }
}
