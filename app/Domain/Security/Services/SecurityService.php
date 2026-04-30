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

        // Use an in-memory stream + fputcsv to handle embedded newlines, quotes, commas safely
        $stream = fopen('php://temp', 'r+b');

        fputcsv($stream, ['timestamp', 'user_id', 'user_type', 'action', 'resource_type', 'resource_id', 'severity', 'ip_address', 'details']);

        foreach ($logs as $log) {
            $details = is_array($log->details) ? json_encode($log->details, JSON_UNESCAPED_UNICODE) : ($log->details ?? '');
            fputcsv($stream, [
                $log->created_at?->toIso8601String() ?? '',
                $log->user_id ?? '',
                $log->user_type?->value ?? $log->user_type ?? '',
                $log->action?->value ?? $log->action ?? '',
                $log->resource_type ?? '',
                $log->resource_id ?? '',
                $log->severity?->value ?? $log->severity ?? '',
                $log->ip_address ?? '',
                $details,
            ]);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
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

        $attempt = LoginAttempt::create($data);

        // Dispatch brute-force event on failed attempts after exceeding threshold
        if (! ($data['is_successful'] ?? false)) {
            $storeId        = $data['store_id'] ?? null;
            $userIdentifier = $data['user_identifier'] ?? null;

            if ($storeId && $userIdentifier) {
                $policy     = $this->getPolicy($storeId);
                $failCount  = $this->recentFailedAttempts($storeId, $userIdentifier, $policy->lockout_duration_minutes ?? 15);

                if ($failCount >= ($policy->max_failed_attempts ?? 5)) {
                    \App\Domain\Security\Events\BruteForceDetected::dispatch(
                        storeId:        $storeId,
                        userIdentifier: $userIdentifier,
                        ipAddress:      $data['ip_address'] ?? '',
                        failedAttempts: $failCount,
                        attemptType:    $data['attempt_type'] ?? 'pin',
                    );
                }
            }
        }

        return $attempt;
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
     *
     * Uses max_failed_attempts within a sliding window of
     * lockout_duration_minutes.  This fixes the prior bug where
     * lockout_duration_minutes was used as both the lockout period
     * and the detection window.
     */
    public function isLockedOut(string $storeId, string $userIdentifier): bool
    {
        $policy = $this->getPolicy($storeId);

        // Detection window = lockout_duration_minutes (how far back we look)
        $recentFails = $this->recentFailedAttempts(
            storeId:        $storeId,
            userIdentifier: $userIdentifier,
            windowMinutes:  $policy->lockout_duration_minutes ?? 15,
        );

        return $recentFails >= ($policy->max_failed_attempts ?? 5);
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
     *
     * Keys exposed here must match what SecurityOverviewWidget reads:
     *   active_devices, active_sessions, unresolved_incidents,
     *   failed_logins_today, total_audit_logs, locked_out_users,
     *   recent_activity, policy, login_stats, audit_stats, critical_audits_7d
     */
    public function getOverview(string $storeId): array
    {
        $policy = $this->getPolicy($storeId);
        $loginStats = $this->loginAttemptStats($storeId, 7);
        $auditStats = $this->auditStats($storeId, 7);

        $activeDevices = DeviceRegistration::where('store_id', $storeId)->where('is_active', true)->count();
        $activeSessions = SecuritySession::where('store_id', $storeId)->where('status', 'active')->count();
        $unresolvedIncidents = SecurityIncident::where('store_id', $storeId)->where('status', 'open')->count();

        $criticalAudits = SecurityAuditLog::where('store_id', $storeId)
            ->where('severity', 'critical')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        // Failed logins in the last 24 h
        $failedLoginsToday = LoginAttempt::where('store_id', $storeId)
            ->where('is_successful', false)
            ->where('attempted_at', '>=', now()->subHours(24))
            ->count();

        // Total audit logs (all time) for the store
        $totalAuditLogs = SecurityAuditLog::where('store_id', $storeId)->count();

        // Locked-out users: unique identifiers that hit the failed-attempt threshold
        $lockedOutUsers = $this->countLockedOutUsers($storeId);

        // Recent activity: last 10 audit log entries (type + description + timestamp)
        $recentActivity = SecurityAuditLog::where('store_id', $storeId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'action', 'resource_type', 'created_at', 'user_id'])
            ->map(fn ($log) => [
                'id'          => $log->id,
                'type'        => $log->action?->value ?? $log->action,
                'description' => $log->resource_type
                    ? (($log->action?->value ?? $log->action) . ' ' . $log->resource_type)
                    : ($log->action?->value ?? $log->action),
                'created_at'  => $log->created_at?->toIso8601String(),
                'user_id'     => $log->user_id,
            ])
            ->values()
            ->toArray();

        return [
            // KPI keys consumed by SecurityOverviewWidget
            'active_devices'       => $activeDevices,
            'active_sessions'      => $activeSessions,
            'unresolved_incidents' => $unresolvedIncidents,
            'failed_logins_today'  => $failedLoginsToday,
            'total_audit_logs'     => $totalAuditLogs,
            'locked_out_users'     => $lockedOutUsers,
            'recent_activity'      => $recentActivity,
            // Additional context
            'policy'               => $policy->toArray(),
            'login_stats'          => $loginStats,
            'audit_stats'          => $auditStats,
            'critical_audits_7d'   => $criticalAudits,
        ];
    }

    /**
     * Count unique user identifiers currently locked out.
     *
     * A user is locked out when their recent failed attempts exceed the
     * store's max_failed_attempts threshold.  We approximate this by
     * counting unique identifiers with ≥ max_failed_attempts failures
     * in the last lockout_duration_minutes window.
     */
    private function countLockedOutUsers(string $storeId): int
    {
        $policy = $this->getPolicy($storeId);
        $window = now()->subMinutes($policy->lockout_duration_minutes ?? 15);

        return LoginAttempt::where('store_id', $storeId)
            ->where('is_successful', false)
            ->where('attempted_at', '>=', $window)
            ->selectRaw('user_identifier, COUNT(*) as fail_count')
            ->groupBy('user_identifier')
            ->havingRaw('COUNT(*) >= ?', [$policy->max_failed_attempts ?? 5])
            ->get()
            ->count();
    }
}
