<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Security\Models\AdminIpAllowlist;
use App\Domain\Security\Models\AdminIpBlocklist;
use App\Domain\Security\Models\AdminSession;
use App\Domain\Security\Models\AdminTrustedDevice;
use App\Domain\Security\Models\DeviceRegistration;
use App\Domain\Security\Models\LoginAttempt;
use App\Domain\Security\Models\SecurityAlert;
use App\Domain\Security\Models\SecurityAuditLog;
use App\Domain\Security\Models\SecurityPolicy;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecurityCenterController extends BaseApiController
{
    // ── Overview ─────────────────────────────────────────────
    public function overview(): JsonResponse
    {
        return $this->success([
            'security_alerts' => [
                'total'       => SecurityAlert::count(),
                'new'         => SecurityAlert::where('status', 'new')->count(),
                'investigating' => SecurityAlert::where('status', 'investigating')->count(),
                'resolved'    => SecurityAlert::where('status', 'resolved')->count(),
            ],
            'sessions' => [
                'total'  => AdminSession::count(),
                'active' => AdminSession::whereNull('revoked_at')->count(),
            ],
            'devices' => [
                'total'  => DeviceRegistration::count(),
                'active' => DeviceRegistration::where('is_active', true)->count(),
            ],
            'login_attempts' => [
                'total'      => LoginAttempt::count(),
                'successful' => LoginAttempt::where('is_successful', true)->count(),
                'failed'     => LoginAttempt::where('is_successful', false)->count(),
            ],
            'ip_management' => [
                'allowlist_count' => AdminIpAllowlist::count(),
                'blocklist_count' => AdminIpBlocklist::count(),
            ],
        ], 'Security overview');
    }

    // ── Security Alerts ──────────────────────────────────────
    public function listAlerts(Request $request): JsonResponse
    {
        $query = SecurityAlert::query()->orderByDesc('created_at');

        if ($request->filled('status'))     $query->where('status', $request->status);
        if ($request->filled('severity'))   $query->where('severity', $request->severity);
        if ($request->filled('alert_type')) $query->where('alert_type', $request->alert_type);
        if ($request->filled('search'))     $query->where('resolution_notes', 'like', '%'.$request->search.'%');

        return $this->success($query->paginate($request->input('per_page', 15)));
    }

    public function showAlert(string $id): JsonResponse
    {
        $alert = SecurityAlert::find($id);
        if (!$alert) return $this->notFound('Security alert not found');
        return $this->success($alert);
    }

    public function resolveAlert(Request $request, string $id): JsonResponse
    {
        $alert = SecurityAlert::find($id);
        if (!$alert) return $this->notFound('Security alert not found');

        $request->validate(['resolution_notes' => 'nullable|string']);

        $alert->forceFill([
            'status'           => 'resolved',
            'resolved_by'      => $request->user('admin-api')?->id,
            'resolved_at'      => now(),
            'resolution_notes' => $request->input('resolution_notes', ''),
        ])->save();

        return $this->success($alert->fresh(), 'Alert resolved');
    }

    // ── Admin Sessions ───────────────────────────────────────
    public function listSessions(Request $request): JsonResponse
    {
        $query = AdminSession::query()->orderByDesc('last_activity_at');

        if ($request->filled('admin_user_id')) $query->where('admin_user_id', $request->admin_user_id);
        if ($request->boolean('active_only'))  $query->whereNull('ended_at');

        return $this->success($query->paginate($request->input('per_page', 15)));
    }

    public function showSession(string $id): JsonResponse
    {
        $session = AdminSession::find($id);
        if (!$session) return $this->notFound('Session not found');
        return $this->success($session);
    }

    public function revokeSession(string $id): JsonResponse
    {
        $session = AdminSession::find($id);
        if (!$session) return $this->notFound('Session not found');

        $session->forceFill(['ended_at' => now(), 'status' => 'closed'])->save();
        return $this->success($session->fresh(), 'Session revoked');
    }

    // ── Device Registrations ─────────────────────────────────
    public function listDevices(Request $request): JsonResponse
    {
        $query = DeviceRegistration::query()->orderByDesc('registered_at');

        if ($request->filled('store_id'))  $query->where('store_id', $request->store_id);
        if ($request->has('is_active'))    $query->where('is_active', $request->boolean('is_active'));

        return $this->success($query->paginate($request->input('per_page', 15)));
    }

    public function showDevice(string $id): JsonResponse
    {
        $device = DeviceRegistration::find($id);
        if (!$device) return $this->notFound('Device not found');
        return $this->success($device);
    }

    public function wipeDevice(string $id): JsonResponse
    {
        $device = DeviceRegistration::find($id);
        if (!$device) return $this->notFound('Device not found');

        $device->forceFill(['remote_wipe_requested' => true])->save();
        return $this->success($device->fresh(), 'Remote wipe requested');
    }

    // ── Login Attempts ───────────────────────────────────────
    public function listLoginAttempts(Request $request): JsonResponse
    {
        $query = LoginAttempt::query()->orderByDesc('attempted_at');

        if ($request->filled('store_id'))       $query->where('store_id', $request->store_id);
        if ($request->filled('attempt_type'))    $query->where('attempt_type', $request->attempt_type);
        if ($request->has('is_successful'))      $query->where('is_successful', $request->boolean('is_successful'));
        if ($request->filled('user_identifier')) $query->where('user_identifier', 'like', '%'.$request->user_identifier.'%');

        return $this->success($query->paginate($request->input('per_page', 15)));
    }

    public function showLoginAttempt(string $id): JsonResponse
    {
        $attempt = LoginAttempt::find($id);
        if (!$attempt) return $this->notFound('Login attempt not found');
        return $this->success($attempt);
    }

    // ── Security Audit Log ───────────────────────────────────
    public function listAuditLogs(Request $request): JsonResponse
    {
        $query = SecurityAuditLog::query()->orderByDesc('created_at');

        if ($request->filled('store_id'))      $query->where('store_id', $request->store_id);
        if ($request->filled('action'))        $query->where('action', $request->action);
        if ($request->filled('severity'))      $query->where('severity', $request->severity);
        if ($request->filled('resource_type')) $query->where('resource_type', $request->resource_type);

        return $this->success($query->paginate($request->input('per_page', 15)));
    }

    public function showAuditLog(string $id): JsonResponse
    {
        $log = SecurityAuditLog::find($id);
        if (!$log) return $this->notFound('Audit log not found');
        return $this->success($log);
    }

    // ── Security Policies ────────────────────────────────────
    public function listPolicies(Request $request): JsonResponse
    {
        $query = SecurityPolicy::query()->orderByDesc('created_at');
        if ($request->filled('store_id')) $query->where('store_id', $request->store_id);
        return $this->success($query->paginate($request->input('per_page', 15)));
    }

    public function showPolicy(string $id): JsonResponse
    {
        $policy = SecurityPolicy::find($id);
        if (!$policy) return $this->notFound('Security policy not found');
        return $this->success($policy);
    }

    public function updatePolicy(Request $request, string $id): JsonResponse
    {
        $policy = SecurityPolicy::find($id);
        if (!$policy) return $this->notFound('Security policy not found');

        $validated = $request->validate([
            'pin_min_length'                => 'sometimes|integer|min:4|max:12',
            'pin_max_length'                => 'sometimes|integer|min:4|max:12',
            'auto_lock_seconds'             => 'sometimes|integer|min:30',
            'max_failed_attempts'           => 'sometimes|integer|min:1',
            'lockout_duration_minutes'      => 'sometimes|integer|min:1',
            'require_2fa_owner'             => 'sometimes|boolean',
            'session_max_hours'             => 'sometimes|integer|min:1',
            'require_pin_override_void'     => 'sometimes|boolean',
            'require_pin_override_return'   => 'sometimes|boolean',
            'require_pin_override_discount' => 'sometimes|boolean',
            'discount_override_threshold'   => 'sometimes|numeric|min:0',
        ]);

        $policy->forceFill($validated)->save();
        return $this->success($policy->fresh(), 'Policy updated');
    }

    // ── IP Management: Allowlist ─────────────────────────────
    public function listAllowlist(Request $request): JsonResponse
    {
        $query = AdminIpAllowlist::query()->orderByDesc('created_at');
        if ($request->filled('search')) $query->where('ip_address', 'like', '%'.$request->search.'%');
        return $this->success($query->paginate($request->input('per_page', 15)));
    }

    public function createAllowlistEntry(Request $request): JsonResponse
    {
        $request->validate([
            'ip_address' => 'required|string|max:45',
            'label'      => 'nullable|string|max:255',
        ]);

        $entry = AdminIpAllowlist::forceCreate([
            'ip_address' => $request->ip_address,
            'label'      => $request->input('label', ''),
            'added_by'   => $request->user('admin-api')?->id,
        ]);

        return $this->created($entry, 'IP added to allowlist');
    }

    public function deleteAllowlistEntry(string $id): JsonResponse
    {
        $entry = AdminIpAllowlist::find($id);
        if (!$entry) return $this->notFound('Entry not found');
        $entry->delete();
        return $this->success(null, 'IP removed from allowlist');
    }

    // ── IP Management: Blocklist ─────────────────────────────
    public function listBlocklist(Request $request): JsonResponse
    {
        $query = AdminIpBlocklist::query()->orderByDesc('created_at');
        if ($request->filled('search')) $query->where('ip_address', 'like', '%'.$request->search.'%');
        return $this->success($query->paginate($request->input('per_page', 15)));
    }

    public function createBlocklistEntry(Request $request): JsonResponse
    {
        $request->validate([
            'ip_address' => 'required|string|max:45',
            'reason'     => 'nullable|string|max:500',
        ]);

        $entry = AdminIpBlocklist::forceCreate([
            'ip_address' => $request->ip_address,
            'reason'     => $request->input('reason', ''),
            'blocked_by' => $request->user('admin-api')?->id,
        ]);

        return $this->created($entry, 'IP added to blocklist');
    }

    public function deleteBlocklistEntry(string $id): JsonResponse
    {
        $entry = AdminIpBlocklist::find($id);
        if (!$entry) return $this->notFound('Entry not found');
        $entry->delete();
        return $this->success(null, 'IP removed from blocklist');
    }
}
