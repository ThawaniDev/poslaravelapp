<?php

namespace App\Domain\DeliveryIntegration\Services;

use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use App\Domain\DeliveryIntegration\Models\DeliveryWebhookLog;
use Illuminate\Http\Request;

class WebhookVerificationService
{
    public function verify(Request $request, DeliveryPlatformConfig $config): bool
    {
        $secret = $config->webhook_secret;
        if (empty($secret)) {
            return true;
        }

        $signature = $request->header('X-Webhook-Signature')
            ?? $request->header('X-Hub-Signature-256')
            ?? $request->header('X-Signature');

        if (! $signature) {
            return false;
        }

        $payload = $request->getContent();
        $expected = hash_hmac('sha256', $payload, $secret);

        // Support both raw hash and prefixed formats
        $signature = str_replace(['sha256=', 'hmac-sha256='], '', $signature);

        return hash_equals($expected, $signature);
    }

    public function logWebhook(Request $request, string $platform, string $storeId, bool $verified, ?string $eventType = null): DeliveryWebhookLog
    {
        return DeliveryWebhookLog::create([
            'platform' => $platform,
            'store_id' => $storeId,
            'event_type' => $eventType,
            'payload' => $request->all(),
            'headers' => $this->sanitizeHeaders($request->headers->all()),
            'ip_address' => $request->ip(),
            'signature_valid' => $verified,
            'processed' => false,
        ]);
    }

    public function markProcessed(DeliveryWebhookLog $log, bool $success, ?string $error = null): void
    {
        $log->update([
            'processed' => true,
            'processing_result' => $success ? 'success' : 'failed',
            'error_message' => $error,
        ]);
    }

    private function sanitizeHeaders(array $headers): array
    {
        $sensitive = ['authorization', 'cookie', 'x-api-key'];
        $sanitized = [];

        foreach ($headers as $key => $values) {
            if (in_array(strtolower($key), $sensitive)) {
                $sanitized[$key] = ['[REDACTED]'];
            } else {
                $sanitized[$key] = $values;
            }
        }

        return $sanitized;
    }
}
