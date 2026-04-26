<?php

namespace App\Domain\Security\Controllers\Api;

use App\Domain\Security\Requests\AuditLogFilterRequest;
use App\Domain\Security\Requests\RecordAuditRequest;
use App\Domain\Security\Requests\RecordLoginAttemptRequest;
use App\Domain\Security\Requests\RegisterDeviceRequest;
use App\Domain\Security\Requests\SaveSecurityPolicyRequest;
use App\Domain\Security\Resources\SecurityIncidentResource;
use App\Domain\Security\Resources\SecuritySessionResource;
use App\Domain\Security\Services\SecurityService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecurityController extends BaseApiController
{
    public function __construct(private SecurityService $service) {}

    // ─── Overview ───────────────────────────────────────────────

    public function overview(Request $request): JsonResponse
    {
        $storeId = $request->query('store_id');
        if (!$storeId) {
            return $this->error(__('security.store_required'), 422);
        }

        $overview = $this->service->getOverview($storeId);

        return $this->success($overview, __('security.overview_fetched'));
    }

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
            resourceType: $data['resource_type'] ?? null,
            since: $data['since'] ?? null,
            perPage: $data['per_page'] ?? 50,
        );

        return $this->success($logs, __('security.audit_logs_fetched'));
    }

    public function recordAudit(RecordAuditRequest $request): JsonResponse
    {
        $log = $this->service->recordAudit($request->validated());

        return $this->created($log, __('security.audit_recorded'));
    }

    public function auditStats(Request $request): JsonResponse
    {
        $storeId = $request->query('store_id');
        if (!$storeId) {
            return $this->error(__('security.store_required'), 422);
        }

        $days = (int) $request->query('days', 7);
        $stats = $this->service->auditStats($storeId, $days);

        return $this->success($stats, __('security.audit_stats_fetched'));
    }

    public function exportAuditLogs(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $storeId = $request->query('store_id');
        if (!$storeId) {
            abort(422, __('security.store_required'));
        }

        $csv = $this->service->exportAuditLogs(
            storeId: $storeId,
            action: $request->query('action'),
            severity: $request->query('severity'),
            since: $request->query('since'),
        );

        $filename = 'audit-log-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(
            function () use ($csv) { echo $csv; },
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
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

    public function showDevice(string $id): JsonResponse
    {
        $device = $this->service->getDevice($id);

        return $this->success($device, __('security.device_fetched'));
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

    public function touchDevice(string $id): JsonResponse
    {
        $device = $this->service->touchDevice($id);

        return $this->success($device, __('security.device_heartbeat'));
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
            since: $request->query('since'),
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

    public function isLockedOut(Request $request): JsonResponse
    {
        $storeId = $request->query('store_id');
        $userIdentifier = $request->query('user_identifier');
        if (!$storeId || !$userIdentifier) {
            return $this->error(__('security.store_user_required'), 422);
        }

        $locked = $this->service->isLockedOut($storeId, $userIdentifier);

        return $this->success(['is_locked_out' => $locked]);
    }

    public function loginAttemptStats(Request $request): JsonResponse
    {
        $storeId = $request->query('store_id');
        if (!$storeId) {
            return $this->error(__('security.store_required'), 422);
        }

        $days = (int) $request->query('days', 7);
        $stats = $this->service->loginAttemptStats($storeId, $days);

        return $this->success($stats, __('security.login_stats_fetched'));
    }

    // ─── Sessions ───────────────────────────────────────────────

    public function listSessions(Request $request): JsonResponse
    {
        $storeId = $request->query('store_id');
        if (!$storeId) {
            return $this->error(__('security.store_required'), 422);
        }

        $activeOnly = $request->query('active_only');
        $sessions = $this->service->listSessions(
            $storeId,
            $activeOnly !== null ? filter_var($activeOnly, FILTER_VALIDATE_BOOLEAN) : null,
        );

        return $this->success(SecuritySessionResource::collection($sessions), __('security.sessions_fetched'));
    }

    public function startSession(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => 'required|string|uuid',
            'device_id' => 'nullable|string|uuid',
            'ip_address' => 'nullable|string|ip',
            'user_agent' => 'nullable|string|max:500',
        ]);

        $session = $this->service->startSession(array_merge(
            $request->only(['store_id', 'device_id', 'ip_address', 'user_agent']),
            ['user_id' => $request->user()->id],
        ));

        return $this->created(new SecuritySessionResource($session), __('security.session_started'));
    }

    public function endSession(string $id, Request $request): JsonResponse
    {
        $reason = $request->input('reason', 'manual');
        $session = $this->service->endSession($id, $reason);

        return $this->success(new SecuritySessionResource($session), __('security.session_ended'));
    }

    public function endAllSessions(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => 'required|string|uuid',
            'user_id'  => ['nullable', 'string', 'uuid'],   // optional — falls back to the authenticated user
            'reason'   => 'nullable|string|max:100',
        ]);

        // Use the provided user_id or fall back to the authenticated user's ID
        $userId = $request->input('user_id') ?? $request->user()->id;

        $count = $this->service->endAllSessions(
            $request->input('store_id'),
            $userId,
            $request->input('reason', 'force_logout'),
        );

        return $this->success(['ended_count' => $count], __('security.sessions_ended'));
    }

    public function sessionHeartbeat(string $id): JsonResponse
    {
        $session = $this->service->sessionHeartbeat($id);

        return $this->success(new SecuritySessionResource($session), __('security.session_heartbeat'));
    }

    // ─── Incidents ──────────────────────────────────────────────

    public function listIncidents(Request $request): JsonResponse
    {
        $storeId = $request->query('store_id');
        if (!$storeId) {
            return $this->error(__('security.store_required'), 422);
        }

        // Accept is_resolved boolean to translate to status filter (for Flutter compatibility)
        $status = $request->query('status');
        if ($status === null && $request->has('is_resolved')) {
            $isResolved = filter_var($request->query('is_resolved'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isResolved !== null) {
                $status = $isResolved ? 'resolved' : 'open';
            }
        }

        $incidents = $this->service->listIncidents(
            storeId: $storeId,
            status: $status,
            severity: $request->query('severity'),
            type: $request->query('type'),
            perPage: (int) $request->query('per_page', 50),
        );

        return $this->successPaginated(
            SecurityIncidentResource::collection($incidents->items()),
            $incidents,
            __('security.incidents_fetched'),
        );
    }

    public function createIncident(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id'      => 'required|string|uuid',
            'incident_type' => 'required|string|max:100',
            'severity'      => 'required|string|in:low,medium,high,critical',
            'title'         => 'required|string|max:255',
            'description'   => 'nullable|string',
            'device_id'     => 'nullable|string|uuid',
            'ip_address'    => 'nullable|string|ip',
            'metadata'      => 'nullable|array',
        ]);

        $incident = $this->service->createIncident(
            array_merge($validated, ['user_id' => $request->user()->id]),
        );

        return $this->created(new SecurityIncidentResource($incident), __('security.incident_created'));
    }

    public function resolveIncident(string $id, Request $request): JsonResponse
    {
        $request->validate([
            'resolution_notes' => 'nullable|string',
        ]);

        $incident = $this->service->resolveIncident(
            $id,
            $request->user()->id,
            $request->input('resolution_notes'),
        );

        return $this->success(new SecurityIncidentResource($incident), __('security.incident_resolved'));
    }
}
