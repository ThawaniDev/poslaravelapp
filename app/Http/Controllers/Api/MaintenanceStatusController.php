<?php

namespace App\Http\Controllers\Api;

use App\Domain\SystemConfig\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceStatusController extends BaseApiController
{
    /**
     * GET /api/v2/maintenance-status
     *
     * Returns the current platform maintenance mode status so the Flutter
     * app can render a banner and (optionally) block destructive actions.
     */
    public function show(Request $request): JsonResponse
    {
        $settings = SystemSetting::where('group', 'maintenance')
            ->get()
            ->pluck('value', 'key');

        $enabled = filter_var(
            $settings->get('maintenance_enabled', false),
            FILTER_VALIDATE_BOOLEAN,
        );

        return $this->success([
            'is_enabled' => $enabled,
            'banner_en' => $settings->get('maintenance_banner_en'),
            'banner_ar' => $settings->get('maintenance_banner_ar'),
            'expected_end_at' => $settings->get('maintenance_expected_end'),
            'allowed_ips' => $this->parseAllowedIps($settings->get('maintenance_allowed_ips')),
            'is_ip_allowed' => $this->isIpAllowed(
                $request->ip(),
                $settings->get('maintenance_allowed_ips'),
            ),
        ]);
    }

    private function parseAllowedIps(mixed $raw): array
    {
        if (empty($raw)) {
            return [];
        }

        $value = is_string($raw) ? $raw : (string) $raw;

        return collect(preg_split('/[\s,]+/', $value))
            ->map(fn ($ip) => trim($ip))
            ->filter()
            ->values()
            ->all();
    }

    private function isIpAllowed(?string $ip, mixed $rawAllowed): bool
    {
        if (! $ip) {
            return false;
        }

        return in_array($ip, $this->parseAllowedIps($rawAllowed), true);
    }
}
