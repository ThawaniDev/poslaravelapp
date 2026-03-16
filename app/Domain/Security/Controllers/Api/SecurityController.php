<?php

namespace App\Domain\Security\Controllers\Api;

use App\Domain\Security\Requests\AuditLogFilterRequest;
use App\Domain\Security\Requests\RecordAuditRequest;
use App\Domain\Security\Requests\RecordLoginAttemptRequest;
use App\Domain\Security\Requests\RegisterDeviceRequest;
use App\Domain\Security\Requests\SaveSecurityPolicyRequest;
use App\Domain\Security\Services\SecurityService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecurityController extends BaseApiController
{
    public function __construct(private SecurityService $service) {}

    // ─── Policies ───────────────────────────────────────────────

    public function getPolicy(Request $request): JsonResponse
    {
        $storeId = $request->query('store_id');
        if (! $storeId) {
            return $this->error(__('security.store_required'), 422);
        }

        $policy = $this->service->getPolicy($storeId);

        return $this->success($policy, __('security.policy_fetched'));
    }

    public function updatePolicy(SaveSecurityPolicyRequest $request): JsonResponse
    {
        $storeId = $request->query('store_id');
        if (! $storeId) {
            return $this->error(__('security.store_required'), 422);
        }

        $policy = $this->service->updatePolicy($storeId, $request->validated());

        return $this->success($policy, __('security.policy_updated'));
    }

    // ─── Audit Logs ─────────────────────────────────────────────

    public function listAuditLogs(AuditLogFilterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $logs = $this->service->listAuditLogs(
            storeId: $data['store_id'],
            action: $data['action'] ?? null,
            severity: $data['severity'] ?? null,
            userId: $data['user_id'] ?? null,
            perPage: $data['per_page'] ?? 50,
        );

        return $this->success($logs, __('security.audit_logs_fetched'));
    }

    public function recordAudit(RecordAuditRequest $request): JsonResponse
    {
        $log = $this->service->recordAudit($request->validated());

        return $this->created($log, __('security.audit_recorded'));
    }

    // ─── Devices ────────────────────────────────────────────────

    public function listDevices(Request $request): JsonResponse
    {
        $storeId = $request->query('store_id');
        if (! $storeId) {
            return $this->error(__('security.store_required'), 422);
        }

        $activeOnly = $request->query('active_only');
        $devices = $this->service->listDevices(
            $storeId,
            $activeOnly !== null ? filter_var($activeOnly, FILTER_VALIDATE_BOOLEAN) : null,
        );

        return $this->success($devices, __('security.devices_fetched'));
    }

    public function registerDevice(RegisterDeviceRequest $request): JsonResponse
    {
        $device = $this->service->registerDevice($request->validated());

        return $this->created($device, __('security.device_registered'));
    }

    public function deactivateDevice(string $id): JsonResponse
    {
        $device = $this->service->deactivateDevice($id);

        return $this->success($device, __('security.device_deactivated'));
    }

    public function requestRemoteWipe(string $id): JsonResponse
    {
        $device = $this->service->requestRemoteWipe($id);

        return $this->success($device, __('security.remote_wipe_requested'));
    }

    // ─── Login Attempts ─────────────────────────────────────────

    public function listLoginAttempts(Request $request): JsonResponse
    {
        $storeId = $request->query('store_id');
        if (! $storeId) {
            return $this->error(__('security.store_required'), 422);
        }

        $logs = $this->service->listLoginAttempts(
            storeId: $storeId,
            attemptType: $request->query('attempt_type'),
            successfulOnly: $request->has('is_successful')
                ? filter_var($request->query('is_successful'), FILTER_VALIDATE_BOOLEAN)
                : null,
            perPage: (int) $request->query('per_page', 50),
        );

        return $this->success($logs, __('security.login_attempts_fetched'));
    }

    public function recordLoginAttempt(RecordLoginAttemptRequest $request): JsonResponse
    {
        $attempt = $this->service->recordLoginAttempt($request->validated());

        return $this->created($attempt, __('security.login_attempt_recorded'));
    }

    public function failedAttemptCount(Request $request): JsonResponse
    {
        $storeId = $request->query('store_id');
        $userIdentifier = $request->query('user_identifier');
        if (! $storeId || ! $userIdentifier) {
            return $this->error(__('security.store_user_required'), 422);
        }

        $window = (int) $request->query('window_minutes', 15);
        $count = $this->service->recentFailedAttempts($storeId, $userIdentifier, $window);

        return $this->success(['count' => $count], __('security.failed_count_fetched'));
    }
}
