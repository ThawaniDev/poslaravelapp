<?php

namespace App\Domain\Security\Services;

use App\Domain\Security\Models\DeviceRegistration;
use App\Domain\Security\Models\LoginAttempt;
use App\Domain\Security\Models\SecurityAuditLog;
use App\Domain\Security\Models\SecurityPolicy;

class SecurityService
{
    // ─── Security Policies ──────────────────────────────────────

    /**
     * Get or create security policy for a store.
     */
    public function getPolicy(string $storeId): SecurityPolicy
    {
        return SecurityPolicy::firstOrCreate(
            ['store_id' => $storeId],
            [
                'pin_min_length' => 4,
                'pin_max_length' => 6,
                'auto_lock_seconds' => 300,
                'max_failed_attempts' => 5,
                'lockout_duration_minutes' => 15,
                'require_2fa_owner' => false,
                'session_max_hours' => 12,
                'require_pin_override_void' => true,
                'require_pin_override_return' => true,
                'require_pin_override_discount' => false,
                'discount_override_threshold' => 20.00,
            ],
        );
    }

    /**
     * Update security policy for a store.
     */
    public function updatePolicy(string $storeId, array $data): SecurityPolicy
    {
        $policy = $this->getPolicy($storeId);
        $policy->update($data);

        return $policy->fresh();
    }

    // ─── Audit Logs ─────────────────────────────────────────────

    /**
     * Record a security audit event.
     */
    public function recordAudit(array $data): SecurityAuditLog
    {
        $data['created_at'] = now();

        return SecurityAuditLog::create($data);
    }

    /**
     * List audit logs for a store with optional filters.
     */
    public function listAuditLogs(
        string $storeId,
        ?string $action = null,
        ?string $severity = null,
        ?string $userId = null,
        int $perPage = 50,
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator {
        $query = SecurityAuditLog::where('store_id', $storeId);

        if ($action) {
            $query->where('action', $action);
        }
        if ($severity) {
            $query->where('severity', $severity);
        }
        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    // ─── Device Registration ────────────────────────────────────

    /**
     * Register or update a device.
     */
    public function registerDevice(array $data): DeviceRegistration
    {
        return DeviceRegistration::updateOrCreate(
            [
                'store_id' => $data['store_id'],
                'hardware_id' => $data['hardware_id'],
            ],
            array_merge($data, [
                'last_active_at' => now(),
                'registered_at' => now(),
                'is_active' => true,
            ]),
        );
    }

    /**
     * List devices for a store.
     */
    public function listDevices(string $storeId, ?bool $activeOnly = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = DeviceRegistration::where('store_id', $storeId);

        if ($activeOnly !== null) {
            $query->where('is_active', $activeOnly);
        }

        return $query->orderByDesc('last_active_at')->get();
    }

    /**
     * Request remote wipe for a device.
     */
    public function requestRemoteWipe(string $deviceId): DeviceRegistration
    {
        $device = DeviceRegistration::findOrFail($deviceId);
        $device->update(['remote_wipe_requested' => true, 'is_active' => false]);

        return $device->fresh();
    }

    /**
     * Deactivate a device.
     */
    public function deactivateDevice(string $deviceId): DeviceRegistration
    {
        $device = DeviceRegistration::findOrFail($deviceId);
        $device->update(['is_active' => false]);

        return $device->fresh();
    }

    // ─── Login Attempts ─────────────────────────────────────────

    /**
     * Record a login attempt.
     */
    public function recordLoginAttempt(array $data): LoginAttempt
    {
        $data['attempted_at'] = now();

        return LoginAttempt::create($data);
    }

    /**
     * Get recent failed login attempts for brute-force detection.
     */
    public function recentFailedAttempts(string $storeId, string $userIdentifier, int $windowMinutes = 15): int
    {
        return LoginAttempt::where('store_id', $storeId)
            ->where('user_identifier', $userIdentifier)
            ->where('is_successful', false)
            ->where('attempted_at', '>=', now()->subMinutes($windowMinutes))
            ->count();
    }

    /**
     * List login attempts for a store.
     */
    public function listLoginAttempts(
        string $storeId,
        ?string $attemptType = null,
        ?bool $successfulOnly = null,
        int $perPage = 50,
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator {
        $query = LoginAttempt::where('store_id', $storeId);

        if ($attemptType) {
            $query->where('attempt_type', $attemptType);
        }
        if ($successfulOnly !== null) {
            $query->where('is_successful', $successfulOnly);
        }

        return $query->orderByDesc('attempted_at')->paginate($perPage);
    }
}
