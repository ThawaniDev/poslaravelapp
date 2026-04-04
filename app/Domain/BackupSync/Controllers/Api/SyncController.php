<?php

namespace App\Domain\BackupSync\Controllers\Api;

use App\Domain\BackupSync\Requests\ConflictFilterRequest;
use App\Domain\BackupSync\Requests\ResolveConflictRequest;
use App\Domain\BackupSync\Requests\SyncPullRequest;
use App\Domain\BackupSync\Requests\SyncPushRequest;
use App\Domain\BackupSync\Services\SyncService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends BaseApiController
{
    public function __construct(
        private readonly SyncService $syncService,
    ) {}

    /**
     * Push local changes to the cloud.
     */
    public function push(SyncPushRequest $request): JsonResponse
    {
        $storeId = auth()->user()->store_id;
        $data = $request->validated();
        // Preserve full record payloads (validated() strips fields not in rules)
        $data['changes'] = $request->input('changes', []);
        $result = $this->syncService->push($storeId, $data);

        return $this->success($result, __('sync.push_success'));
    }

    /**
     * Pull cloud changes since last sync.
     */
    public function pull(SyncPullRequest $request): JsonResponse
    {
        $storeId = auth()->user()->store_id;
        $result = $this->syncService->pull($storeId, $request->validated());

        return $this->success($result, __('sync.pull_success'));
    }

    /**
     * Full sync — initial download.
     */
    public function full(Request $request): JsonResponse
    {
        $storeId = auth()->user()->store_id;
        $terminalId = $request->query('terminal_id');

        if (!$terminalId) {
            return $this->error(__('sync.terminal_required'), 422);
        }

        $result = $this->syncService->fullSync($storeId, $terminalId);

        return $this->success($result, __('sync.full_success'));
    }

    /**
     * Get sync status and health.
     */
    public function status(): JsonResponse
    {
        $storeId = auth()->user()->store_id;
        $result = $this->syncService->status($storeId);

        return $this->success($result, __('sync.status_retrieved'));
    }

    /**
     * Resolve a specific conflict.
     */
    public function resolveConflict(ResolveConflictRequest $request, string $conflictId): JsonResponse
    {
        $storeId = auth()->user()->store_id;
        $conflict = $this->syncService->resolveConflict($storeId, $conflictId, $request->validated());

        if (!$conflict) {
            return $this->notFound(__('sync.conflict_not_found'));
        }

        return $this->success($conflict, __('sync.conflict_resolved'));
    }

    /**
     * List conflicts for the store.
     */
    public function conflicts(ConflictFilterRequest $request): JsonResponse
    {
        $storeId = auth()->user()->store_id;
        $result = $this->syncService->listConflicts($storeId, $request->validated());

        return $this->success($result, __('sync.conflicts_retrieved'));
    }

    /**
     * Heartbeat — connectivity check.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $storeId = auth()->user()->store_id;
        $result = $this->syncService->heartbeat($storeId, $request->all());

        return $this->success($result, __('sync.heartbeat_ok'));
    }
}
