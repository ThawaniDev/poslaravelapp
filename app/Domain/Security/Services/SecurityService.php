<?php

namespace App\Domain\Security\Services;

use App\Domain\Security\Models\DeviceRegistration;
use App\Domain\Security\Models\LoginAttempt;
use App\Domain\Security\Models\SecurityAuditLog;
use App\Domain\Security\Models\SecurityIncident;
use App\Domain\Security\Models\SecurityPolicy;
use App\Domain\Security\Models\SecuritySession;

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
            SecurityPolicy::$defaults,
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
        ?string $resourceType = null,
        ?string $since = null,
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
        if ($resourceType) {
            $query->where('resource_type', $resourceType);
        }
        if ($since) {
            $query->where('created_at', '>=', $since);
        }

        return $query->orderByDesc('created_at')->paginate(min($perPage, 200));
    }

    /**
     * Get audit statistics for a store.
     */
    public function auditStats(string $storeId, int $days = 7): array
    {
        $since = now()->subDays($days);
        $base = SecurityAuditLog::where('store_id', $storeId)->where('created_at', '>=', $since);

        $total = (clone $base)->count();
        $bySeverity = (clone $base)
            ->selectRaw('severity, count(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();
        $byAction = (clone $base)
            ->selectRaw('action, count(*) as count')
            ->groupBy('action')
            ->pluck('count', 'action')
            ->toArray();

        return [
            'period_days' => $days,
            'total' => $total,
            'by_severity' => $bySeverity,
            'by_action' => $byAction,
        ];
    }

    /**
     * Export audit logs to CSV content for a store.
     */
    public function exportAuditLogs(
        string $storeId,
        ?string $action = null,
        ?string $severity = null,
        ?string $since = null,
        int $limit = 5000,
    ): string {
        $query = SecurityAuditLog::where('store_id', $storeId);

        if ($action) {
            $query->where('action', $action);
        }
        if ($severity) {
            $query->where('severity', $severity);
        }
        if ($since) {
            $query->where('created_at', '>=', $since);
        }

        $logs = $query->orderByDesc('created_at')->limit(min($limit, 5000))->get();

        $lines = [];
        $lines[] = implode(',', ['timestamp', 'user_id', 'user_type', 'action', 'resource_type', 'resource_id', 'severity', 'ip_address', 'details']);

        foreach ($logs as $log) {
            $details = is_array($log->details) ? json_encode($log->details) : ($log->details ?? '');
            $lines[] = implode(',', [
                '"' . ($log->created_at?->toIso8601String() ?? '') . '"',
                '"' . ($log->user_id ?? '') . '"',
                '"' . ($log->user_type?->value ?? $log->user_type ?? '') . '"',
                '"' . ($log->action?->value ?? $log->action ?? '') . '"',
                '"' . ($log->resource_type ?? '') . '"',
                '"' . ($log->resource_id ?? '') . '"',
                '"' . ($log->severity?->value ?? $log->severity ?? '') . '"',
                '"' . ($log->ip_address ?? '') . '"',
                '"' . str_replace('"', '""', $details) . '"',
            ]);
        }

        return implode("\n", $lines);
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
     * Get a single device by ID.
     */
    public function getDevice(string $deviceId): DeviceRegistration
    {
        return DeviceRegistration::findOrFail($deviceId);
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

    /**
     * Update device heartbeat / activity.
     */
    public function touchDevice(string $deviceId): DeviceRegistration
    {
        $device = DeviceRegistration::findOrFail($deviceId);
        $device->update(['last_active_at' => now()]);

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
     * Check if a user is currently locked out.
     */
    public function isLockedOut(string $storeId, string $userIdentifier): bool
    {
        $policy = $this->getPolicy($storeId);
        $recentFails = $this->recentFailedAttempts(
            $storeId,
            $userIdentifier,
            $policy->lockout_duration_minutes,
        );

        return $recentFails >= $policy->max_failed_attempts;
    }

    /**
     * List login attempts for a store.
     */
    public function listLoginAttempts(
        string $storeId,
        ?string $attemptType = null,
        ?bool $successfulOnly = null,
        ?string $since = null,
        int $perPage = 50,
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator {
        $query = LoginAttempt::where('store_id', $storeId);

        if ($attemptType) {
            $query->where('attempt_type', $attemptType);
        }
        if ($successfulOnly !== null) {
            $query->where('is_successful', $successfulOnly);
        }
        if ($since) {
            $query->where('attempted_at', '>=', $since);
        }

        return $query->orderByDesc('attempted_at')->paginate(min($perPage, 200));
    }

    /**
     * Get login attempt statistics.
     */
    public function loginAttemptStats(string $storeId, int $days = 7): array
    {
        $since = now()->subDays($days);
        $base = LoginAttempt::where('store_id', $storeId)->where('attempted_at', '>=', $since);

        $total = (clone $base)->count();
        $successful = (clone $base)->where('is_successful', true)->count();
        $failed = (clone $base)->where('is_successful', false)->count();

        return [
            'period_days' => $days,
            'total' => $total,
            'successful' => $successful,
            'failed' => $failed,
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 1) : 0,
        ];
    }

    // ─── Sessions ───────────────────────────────────────────────

    /**
     * Start a new security session.
     */
    public function startSession(array $data): SecuritySession
    {
        return SecuritySession::create(array_merge($data, [
            'started_at' => now(),
            'last_activity_at' => now(),
            'status' => 'active',
        ]));
    }

    /**
     * End a security session.
     */
    public function endSession(string $sessionId, string $reason = 'manual'): SecuritySession
    {
        $session = SecuritySession::findOrFail($sessionId);
        $session->end($reason);

        return $session->fresh();
    }

    /**
     * End all active sessions for a user in a store.
     */
    public function endAllSessions(string $storeId, string $userId, string $reason = 'force_logout'): int
    {
        return SecuritySession::where('store_id', $storeId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->update([
                'status' => 'ended',
                'ended_at' => now(),
                'end_reason' => $reason,
            ]);
    }

    /**
     * Update session heartbeat.
     */
    public function sessionHeartbeat(string $sessionId): SecuritySession
    {
        $session = SecuritySession::findOrFail($sessionId);
        $session->heartbeat();

        return $session->fresh();
    }

    /**
     * List active sessions for a store.
     */
    public function listSessions(string $storeId, ?bool $activeOnly = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = SecuritySession::where('store_id', $storeId);

        if ($activeOnly !== null) {
            $query->where('status', $activeOnly ? 'active' : 'ended');
        }

        return $query->orderByDesc('last_activity_at')->get();
    }

    // ─── Incidents ──────────────────────────────────────────────

    /**
     * Create a security incident.
     */
    public function createIncident(array $data): SecurityIncident
    {
        $data['status'] = 'open';

        return SecurityIncident::create($data);
    }

    /**
     * Resolve a security incident.
     */
    public function resolveIncident(string $incidentId, string $resolvedBy, ?string $notes = null): SecurityIncident
    {
        $incident = SecurityIncident::findOrFail($incidentId);
        $incident->resolve($resolvedBy, $notes);

        return $incident->fresh();
    }

    /**
     * List security incidents for a store.
     */
    public function listIncidents(
        string $storeId,
        ?string $status = null,
        ?string $severity = null,
        ?string $type = null,
        int $perPage = 50,
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator {
        $query = SecurityIncident::where('store_id', $storeId);

        if ($status) {
            $query->where('status', $status);
        }
        if ($severity) {
            $query->where('severity', $severity);
        }
        if ($type) {
            $query->where('incident_type', $type);
        }

        return $query->orderByDesc('created_at')->paginate(min($perPage, 200));
    }

    // ─── Security Overview ──────────────────────────────────────

    /**
     * Get a comprehensive security overview for a store.
     */
    public function getOverview(string $storeId): array
    {
        $policy = $this->getPolicy($storeId);
        $loginStats = $this->loginAttemptStats($storeId, 7);
        $auditStats = $this->auditStats($storeId, 7);

        $activeDevices = DeviceRegistration::where('store_id', $storeId)->where('is_active', true)->count();
        $activeSessions = SecuritySession::where('store_id', $storeId)->where('status', 'active')->count();
        $openIncidents = SecurityIncident::where('store_id', $storeId)->where('status', 'open')->count();
        $criticalAudits = SecurityAuditLog::where('store_id', $storeId)
            ->where('severity', 'critical')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return [
            'policy' => $policy->toArray(),
            'login_stats' => $loginStats,
            'audit_stats' => $auditStats,
            'active_devices' => $activeDevices,
            'active_sessions' => $activeSessions,
            'open_incidents' => $openIncidents,
            'critical_audits_7d' => $criticalAudits,
        ];
    }
}
