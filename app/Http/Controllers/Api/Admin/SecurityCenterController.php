<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\Security\Services\AdminSecurityService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecurityCenterController extends BaseApiController
{
    public function __construct(private AdminSecurityService $service) {}

    // ── Overview ─────────────────────────────────────────────
    public function overview(): JsonResponse
    {
        return $this->success(
            $this->service->getOverview(),
            __('security.overview_fetched'),
        );
    }

    // ── Security Alerts ──────────────────────────────────────
    public function listAlerts(Request $request): JsonResponse
    {
        return $this->success(
            $this->service->listAlerts(
                status: $request->input('status'),
                severity: $request->input('severity'),
                alertType: $request->input('alert_type'),
                search: $request->input('search'),
                perPage: (int) $request->input('per_page', 15),
            ),
            __('security.alerts_fetched'),
        );
    }

    public function showAlert(string $id): JsonResponse
    {
        try {
            return $this->success(
                $this->service->showAlert($id),
                __('security.alert_fetched'),
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound(__('security.alert_not_found'));
        }
    }

    public function resolveAlert(Request $request, string $id): JsonResponse
    {
        $request->validate(['resolution_notes' => 'nullable|string|max:2000']);

        try {
            $alert = $this->service->resolveAlert(
                id: $id,
                resolvedById: $request->user('admin-api')->id,
                notes: $request->input('resolution_notes'),
            );

            return $this->success($alert, __('security.alert_resolved'));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound(__('security.alert_not_found'));
        }
    }

    // ── Admin Sessions ───────────────────────────────────────
    public function listSessions(Request $request): JsonResponse
    {
        return $this->success(
            $this->service->listSessions(
                adminUserId: $request->input('admin_user_id'),
                activeOnly: $request->boolean('active_only', false) ?: null,
                perPage: (int) $request->input('per_page', 15),
            ),
            __('security.sessions_fetched'),
        );
    }

    public function showSession(string $id): JsonResponse
    {
        try {
            return $this->success(
                $this->service->showSession($id),
                __('security.session_fetched'),
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound(__('security.session_not_found'));
        }
    }

    public function revokeSession(string $id): JsonResponse
    {
        try {
            $session = $this->service->revokeSession(
                id: $id,
                revokedById: request()->user('admin-api')->id,
            );

            return $this->success($session, __('security.session_revoked'));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound(__('security.session_not_found'));
        }
    }

    // ── Device Registrations ─────────────────────────────────
    public function listDevices(Request $request): JsonResponse
    {
        return $this->success(
            $this->service->listDevices(
                storeId: $request->input('store_id'),
                isActive: $request->has('is_active') ? $request->boolean('is_active') : null,
                search: $request->input('search'),
                perPage: (int) $request->input('per_page', 15),
            ),
            __('security.devices_fetched'),
        );
    }

    public function showDevice(string $id): JsonResponse
    {
        try {
            return $this->success(
                $this->service->showDevice($id),
                __('security.device_fetched'),
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound(__('security.device_not_found'));
        }
    }

    public function wipeDevice(string $id): JsonResponse
    {
        try {
            $device = $this->service->wipeDevice(
                id: $id,
                adminUserId: request()->user('admin-api')->id,
            );

            return $this->success($device, __('security.remote_wipe_requested'));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound(__('security.device_not_found'));
        }
    }

    // ── Login Attempts ───────────────────────────────────────
    public function listLoginAttempts(Request $request): JsonResponse
    {
        return $this->success(
            $this->service->listLoginAttempts(
                storeId: $request->input('store_id'),
                attemptType: $request->input('attempt_type'),
                isSuccessful: $request->has('is_successful') ? $request->boolean('is_successful') : null,
                userIdentifier: $request->input('user_identifier'),
                perPage: (int) $request->input('per_page', 15),
            ),
            __('security.login_attempts_fetched'),
        );
    }

    public function showLoginAttempt(string $id): JsonResponse
    {
        try {
            return $this->success(
                $this->service->showLoginAttempt($id),
                __('security.login_attempt_fetched'),
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound(__('security.login_attempt_not_found'));
        }
    }

    // ── Security Audit Log ───────────────────────────────────
    public function listAuditLogs(Request $request): JsonResponse
    {
        return $this->success(
            $this->service->listAuditLogs(
                storeId: $request->input('store_id'),
                action: $request->input('action'),
                severity: $request->input('severity'),
                resourceType: $request->input('resource_type'),
                perPage: (int) $request->input('per_page', 15),
            ),
            __('security.audit_logs_fetched'),
        );
    }

    public function showAuditLog(string $id): JsonResponse
    {
        try {
            return $this->success(
                $this->service->showAuditLog($id),
                __('security.audit_log_fetched'),
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound(__('security.audit_log_not_found'));
        }
    }

    // ── Security Policies ────────────────────────────────────
    public function listPolicies(Request $request): JsonResponse
    {
        return $this->success(
            $this->service->listPolicies(
                storeId: $request->input('store_id'),
                perPage: (int) $request->input('per_page', 15),
            ),
            __('security.policies_fetched'),
        );
    }

    public function showPolicy(string $id): JsonResponse
    {
        try {
            return $this->success(
                $this->service->showPolicy($id),
                __('security.policy_fetched'),
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound(__('security.policy_not_found'));
        }
    }

    public function updatePolicy(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'pin_min_length' => 'sometimes|integer|min:4|max:12',
            'pin_max_length' => 'sometimes|integer|min:4|max:12',
            'auto_lock_seconds' => 'sometimes|integer|min:30',
            'max_failed_attempts' => 'sometimes|integer|min:1',
            'lockout_duration_minutes' => 'sometimes|integer|min:1',
            'require_2fa_owner' => 'sometimes|boolean',
            'session_max_hours' => 'sometimes|integer|min:1',
            'require_pin_override_void' => 'sometimes|boolean',
            'require_pin_override_return' => 'sometimes|boolean',
            'require_pin_override_discount' => 'sometimes|boolean',
            'discount_override_threshold' => 'sometimes|numeric|min:0',
        ]);

        try {
            $policy = $this->service->updatePolicy(
                id: $id,
                data: $validated,
                adminUserId: $request->user('admin-api')->id,
            );

            return $this->success($policy, __('security.policy_updated'));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound(__('security.policy_not_found'));
        }
    }

    // ── IP Allowlist ─────────────────────────────────────────
    public function listAllowlist(Request $request): JsonResponse
    {
        return $this->success(
            $this->service->listAllowlist(
                search: $request->input('search'),
                perPage: (int) $request->input('per_page', 15),
            ),
            __('security.allowlist_fetched'),
        );
    }

    public function createAllowlistEntry(Request $request): JsonResponse
    {
        $request->validate([
            'ip_address' => 'required|ip|max:45',
            'label' => 'nullable|string|max:255',
        ]);

        $entry = $this->service->createAllowlistEntry(
            ipAddress: $request->input('ip_address'),
            label: $request->input('label'),
            addedById: $request->user('admin-api')->id,
        );

        return $this->created($entry, __('security.allowlist_entry_created'));
    }

    public function deleteAllowlistEntry(string $id): JsonResponse
    {
        try {
            $this->service->deleteAllowlistEntry(
                id: $id,
                deletedById: request()->user('admin-api')->id,
            );

            return $this->success(null, __('security.allowlist_entry_deleted'));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound(__('security.entry_not_found'));
        }
    }

    // ── IP Blocklist ─────────────────────────────────────────
    public function listBlocklist(Request $request): JsonResponse
    {
        return $this->success(
            $this->service->listBlocklist(
                search: $request->input('search'),
                perPage: (int) $request->input('per_page', 15),
            ),
            __('security.blocklist_fetched'),
        );
    }

    public function createBlocklistEntry(Request $request): JsonResponse
    {
        $request->validate([
            'ip_address' => 'required|ip|max:45',
            'reason' => 'nullable|string|max:500',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $entry = $this->service->createBlocklistEntry(
            ipAddress: $request->input('ip_address'),
            reason: $request->input('reason'),
            blockedById: $request->user('admin-api')->id,
            expiresAt: $request->input('expires_at') ? new \DateTime($request->input('expires_at')) : null,
        );

        return $this->created($entry, __('security.blocklist_entry_created'));
    }

    public function deleteBlocklistEntry(string $id): JsonResponse
    {
        try {
            $this->service->deleteBlocklistEntry(
                id: $id,
                deletedById: request()->user('admin-api')->id,
            );

            return $this->success(null, __('security.blocklist_entry_deleted'));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound(__('security.entry_not_found'));
        }
    }
}
