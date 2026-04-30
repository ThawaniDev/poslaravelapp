<?php

namespace App\Domain\Security\Services;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Security\Enums\SecurityAlertStatus;
use App\Domain\Security\Models\AdminIpAllowlist;
use App\Domain\Security\Models\AdminIpBlocklist;
use App\Domain\Security\Models\AdminSession;
use App\Domain\Security\Models\AdminTrustedDevice;
use App\Domain\Security\Models\DeviceRegistration;
use App\Domain\Security\Models\LoginAttempt;
use App\Domain\Security\Models\SecurityAlert;
use App\Domain\Security\Models\SecurityAuditLog;
use App\Domain\Security\Models\SecurityPolicy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AdminSecurityService
{
    // ─── Overview ────────────────────────────────────────────────

    public function getOverview(): array
    {
        return [
            'security_alerts' => [
                'total' => SecurityAlert::count(),
                'new' => SecurityAlert::new()->count(),
                'investigating' => SecurityAlert::where('status', 'investigating')->count(),
                'resolved' => SecurityAlert::where('status', 'resolved')->count(),
                'critical_unresolved' => SecurityAlert::unresolved()->critical()->count(),
            ],
            'sessions' => [
                'total' => AdminSession::count(),
                'active' => AdminSession::active()->count(),
            ],
            'devices' => [
                'total' => DeviceRegistration::count(),
                'active' => DeviceRegistration::active()->count(),
                'wipe_pending' => DeviceRegistration::wipePending()->count(),
            ],
            'login_attempts' => [
                'total' => LoginAttempt::count(),
                'successful' => LoginAttempt::successful()->count(),
                'failed' => LoginAttempt::failed()->count(),
                'failed_24h' => LoginAttempt::failed()->where('attempted_at', '>=', now()->subHours(24))->count(),
            ],
            'ip_management' => [
                'allowlist_count' => AdminIpAllowlist::count(),
                'blocklist_count' => AdminIpBlocklist::count(),
            ],
            'trusted_devices' => [
                'total' => AdminTrustedDevice::count(),
            ],
        ];
    }

    // ─── Security Alerts ─────────────────────────────────────────

    public function listAlerts(
        ?string $status = null,
        ?string $severity = null,
        ?string $alertType = null,
        ?string $search = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = SecurityAlert::with('adminUser', 'resolvedBy');

        if ($status) {
            $query->where('status', $status);
        }
        if ($severity) {
            $query->where('severity', $severity);
        }
        if ($alertType) {
            $query->where('alert_type', $alertType);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function showAlert(string $id): SecurityAlert
    {
        return SecurityAlert::with('adminUser', 'resolvedBy')->findOrFail($id);
    }

    public function resolveAlert(string $id, string $resolvedById, ?string $notes = null): SecurityAlert
    {
        $alert = SecurityAlert::findOrFail($id);
        $alert->resolve($resolvedById, $notes);

        AdminActivityLog::record(
            adminUserId: $resolvedById,
            action: 'resolve_alert',
            entityType: 'security_alert',
            entityId: $id,
            details: ['notes' => $notes],
        );

        return $alert->fresh(['adminUser', 'resolvedBy']);
    }

    public function investigateAlert(string $id, string $adminUserId): SecurityAlert
    {
        $alert = SecurityAlert::findOrFail($id);
        $alert->startInvestigation();

        AdminActivityLog::record(
            adminUserId: $adminUserId,
            action: 'investigate_alert',
            entityType: 'security_alert',
            entityId: $id,
        );

        return $alert->fresh(['adminUser', 'resolvedBy']);
    }

    public function createAlert(array $data): SecurityAlert
    {
        $data['created_at'] = $data['created_at'] ?? now();
        $data['status'] = $data['status'] ?? SecurityAlertStatus::New->value;

        return SecurityAlert::create($data);
    }

    // ─── Admin Sessions ──────────────────────────────────────────

    public function listSessions(
        ?string $adminUserId = null,
        ?bool $activeOnly = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = AdminSession::with('adminUser');

        if ($adminUserId) {
            $query->forAdmin($adminUserId);
        }
        if ($activeOnly) {
            $query->active();
        }

        return $query->orderByDesc('last_activity_at')->paginate($perPage);
    }

    public function showSession(string $id): AdminSession
    {
        return AdminSession::with('adminUser')->findOrFail($id);
    }

    public function revokeSession(string $id, string $revokedById): AdminSession
    {
        $session = AdminSession::findOrFail($id);
        $session->revoke();

        AdminActivityLog::record(
            adminUserId: $revokedById,
            action: 'revoke_session',
            entityType: 'admin_session',
            entityId: $id,
            details: ['target_admin_id' => $session->admin_user_id],
        );

        \App\Domain\Security\Events\AdminSessionRevoked::dispatch($session, $revokedById);

        return $session->fresh(['adminUser']);
    }

    public function revokeAllSessionsForAdmin(string $adminUserId, string $revokedById): int
    {
        $sessions = AdminSession::forAdmin($adminUserId)->active()->get();
        $count = 0;

        foreach ($sessions as $session) {
            $session->revoke();
            $count++;
        }

        if ($count > 0) {
            AdminActivityLog::record(
                adminUserId: $revokedById,
                action: 'revoke_all_sessions',
                entityType: 'admin_user',
                entityId: $adminUserId,
                details: ['sessions_revoked' => $count],
            );
        }

        return $count;
    }

    // ─── Trusted Devices ─────────────────────────────────────────

    public function listTrustedDevices(
        ?string $adminUserId = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = AdminTrustedDevice::with('adminUser');

        if ($adminUserId) {
            $query->forAdmin($adminUserId);
        }

        return $query->orderByDesc('last_used_at')->paginate($perPage);
    }

    public function revokeTrust(string $id, string $revokedById): void
    {
        $device = AdminTrustedDevice::findOrFail($id);

        AdminActivityLog::record(
            adminUserId: $revokedById,
            action: 'revoke_device_trust',
            entityType: 'admin_trusted_device',
            entityId: $id,
            details: [
                'device_name' => $device->device_name,
                'target_admin_id' => $device->admin_user_id,
            ],
        );

        $device->delete();
    }

    // ─── Device Registrations (provider devices) ─────────────────

    public function listDevices(
        ?string $storeId = null,
        ?bool $isActive = null,
        ?string $search = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = DeviceRegistration::query();

        if ($storeId) {
            $query->forStore($storeId);
        }
        if ($isActive !== null) {
            $isActive ? $query->active() : $query->where('is_active', false);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('device_name', 'like', "%{$search}%")
                  ->orWhere('hardware_id', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('last_active_at')->paginate($perPage);
    }

    public function showDevice(string $id): DeviceRegistration
    {
        return DeviceRegistration::findOrFail($id);
    }

    public function wipeDevice(string $id, string $adminUserId): DeviceRegistration
    {
        $device = DeviceRegistration::findOrFail($id);
        $device->requestWipe();

        AdminActivityLog::record(
            adminUserId: $adminUserId,
            action: 'remote_wipe_device',
            entityType: 'device_registration',
            entityId: $id,
            details: ['device_name' => $device->device_name, 'store_id' => $device->store_id],
        );

        return $device->fresh();
    }

    // ─── Login Attempts ──────────────────────────────────────────

    public function listLoginAttempts(
        ?string $storeId = null,
        ?string $attemptType = null,
        ?bool $isSuccessful = null,
        ?string $userIdentifier = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = LoginAttempt::query();

        if ($storeId) {
            $query->forStore($storeId);
        }
        if ($attemptType) {
            $query->byType($attemptType);
        }
        if ($isSuccessful !== null) {
            $isSuccessful ? $query->successful() : $query->failed();
        }
        if ($userIdentifier) {
            $query->where('user_identifier', 'like', "%{$userIdentifier}%");
        }

        return $query->orderByDesc('attempted_at')->paginate($perPage);
    }

    public function showLoginAttempt(string $id): LoginAttempt
    {
        return LoginAttempt::findOrFail($id);
    }

    // ─── Security Audit Logs ─────────────────────────────────────

    public function listAuditLogs(
        ?string $storeId = null,
        ?string $action = null,
        ?string $severity = null,
        ?string $resourceType = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = SecurityAuditLog::with('device');

        if ($storeId) {
            $query->forStore($storeId);
        }
        if ($action) {
            $query->byAction($action);
        }
        if ($severity) {
            $query->bySeverity($severity);
        }
        if ($resourceType) {
            $query->where('resource_type', $resourceType);
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function showAuditLog(string $id): SecurityAuditLog
    {
        return SecurityAuditLog::with('device')->findOrFail($id);
    }

    // ─── Security Policies ───────────────────────────────────────

    public function listPolicies(
        ?string $storeId = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = SecurityPolicy::query();

        if ($storeId) {
            $query->forStore($storeId);
        }

        return $query->orderByDesc('updated_at')->paginate($perPage);
    }

    public function showPolicy(string $id): SecurityPolicy
    {
        return SecurityPolicy::findOrFail($id);
    }

    public function updatePolicy(string $id, array $data, string $adminUserId): SecurityPolicy
    {
        $policy = SecurityPolicy::findOrFail($id);
        $oldValues = $policy->only(array_keys($data));
        $policy->update($data);

        AdminActivityLog::record(
            adminUserId: $adminUserId,
            action: 'update_security_policy',
            entityType: 'security_policy',
            entityId: $id,
            details: ['old' => $oldValues, 'new' => $data],
        );

        return $policy->fresh();
    }

    // ─── IP Allowlist ────────────────────────────────────────────

    public function listAllowlist(?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = AdminIpAllowlist::with('addedBy');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('ip_address', 'like', "%{$search}%")
                  ->orWhere('label', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function createAllowlistEntry(string $ipAddress, ?string $label, string $addedById): AdminIpAllowlist
    {
        $entry = AdminIpAllowlist::create([
            'ip_address' => $ipAddress,
            'label' => $label,
            'added_by' => $addedById,
            'created_at' => now(),
        ]);

        AdminActivityLog::record(
            adminUserId: $addedById,
            action: 'add_ip_allowlist',
            entityType: 'admin_ip_allowlist',
            entityId: $entry->id,
            details: ['ip_address' => $ipAddress, 'label' => $label],
        );

        return $entry;
    }

    public function deleteAllowlistEntry(string $id, string $deletedById): void
    {
        $entry = AdminIpAllowlist::findOrFail($id);

        AdminActivityLog::record(
            adminUserId: $deletedById,
            action: 'remove_ip_allowlist',
            entityType: 'admin_ip_allowlist',
            entityId: $id,
            details: ['ip_address' => $entry->ip_address],
        );

        $entry->delete();
    }

    // ─── IP Blocklist ────────────────────────────────────────────

    public function listBlocklist(?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = AdminIpBlocklist::with('blockedBy');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('ip_address', 'like', "%{$search}%")
                  ->orWhere('reason', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('blocked_at')->paginate($perPage);
    }

    public function createBlocklistEntry(
        string $ipAddress,
        ?string $reason,
        string $blockedById,
        ?\DateTimeInterface $expiresAt = null,
    ): AdminIpBlocklist {
        $entry = AdminIpBlocklist::create([
            'ip_address' => $ipAddress,
            'reason' => $reason,
            'blocked_by' => $blockedById,
            'blocked_at' => now(),
            'expires_at' => $expiresAt,
            'created_at' => now(),
        ]);

        AdminActivityLog::record(
            adminUserId: $blockedById,
            action: 'add_ip_blocklist',
            entityType: 'admin_ip_blocklist',
            entityId: $entry->id,
            details: ['ip_address' => $ipAddress, 'reason' => $reason],
        );

        return $entry;
    }

    public function deleteBlocklistEntry(string $id, string $deletedById): void
    {
        $entry = AdminIpBlocklist::findOrFail($id);

        AdminActivityLog::record(
            adminUserId: $deletedById,
            action: 'remove_ip_blocklist',
            entityType: 'admin_ip_blocklist',
            entityId: $id,
            details: ['ip_address' => $entry->ip_address],
        );

        $entry->delete();
    }

    // ─── Activity Logs (admin-side) ──────────────────────────────

    public function listActivityLogs(
        ?string $adminUserId = null,
        ?string $action = null,
        ?string $entityType = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = AdminActivityLog::with('adminUser');

        if ($adminUserId) {
            $query->forAdmin($adminUserId);
        }
        if ($action) {
            $query->byAction($action);
        }
        if ($entityType) {
            $query->byEntityType($entityType);
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function showActivityLog(string $id): AdminActivityLog
    {
        return AdminActivityLog::with('adminUser')->findOrFail($id);
    }

    // ─── Stats / Analytics ───────────────────────────────────────

    public function getAlertStats(int $days = 30): array
    {
        $since = now()->subDays($days);

        return [
            'by_type' => SecurityAlert::where('created_at', '>=', $since)
                ->selectRaw('alert_type, count(*) as count')
                ->groupBy('alert_type')
                ->pluck('count', 'alert_type')
                ->toArray(),
            'by_severity' => SecurityAlert::where('created_at', '>=', $since)
                ->selectRaw('severity, count(*) as count')
                ->groupBy('severity')
                ->pluck('count', 'severity')
                ->toArray(),
            'by_status' => SecurityAlert::selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
        ];
    }

    public function getLoginAttemptStats(int $days = 30): array
    {
        $since = now()->subDays($days);

        return [
            'total' => LoginAttempt::where('attempted_at', '>=', $since)->count(),
            'successful' => LoginAttempt::successful()->where('attempted_at', '>=', $since)->count(),
            'failed' => LoginAttempt::failed()->where('attempted_at', '>=', $since)->count(),
            'by_type' => LoginAttempt::where('attempted_at', '>=', $since)
                ->selectRaw('attempt_type, count(*) as count')
                ->groupBy('attempt_type')
                ->pluck('count', 'attempt_type')
                ->toArray(),
        ];
    }

    // ─── IP Check Helpers ────────────────────────────────────────

    public function isIpAllowed(string $ip): bool
    {
        $allowlistCount = AdminIpAllowlist::count();
        if ($allowlistCount === 0) {
            return true; // no allowlist configured means all IPs allowed
        }

        return AdminIpAllowlist::where('ip_address', $ip)->exists();
    }

    public function isIpBlocked(string $ip): bool
    {
        return AdminIpBlocklist::where('ip_address', $ip)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }
}
