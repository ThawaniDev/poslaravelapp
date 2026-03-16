<?php

namespace App\Domain\BackupSync\Controllers\Api;

use App\Domain\BackupSync\Requests\BackupListFilterRequest;
use App\Domain\BackupSync\Requests\CreateBackupRequest;
use App\Domain\BackupSync\Requests\ExportDataRequest;
use App\Domain\BackupSync\Requests\UpdateBackupScheduleRequest;
use App\Domain\BackupSync\Services\BackupService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;

class BackupController extends BaseApiController
{
    public function __construct(
        private readonly BackupService $backupService,
    ) {}

    public function create(CreateBackupRequest $request): JsonResponse
    {
        $storeId = auth()->user()->store_id;
        $result = $this->backupService->createBackup($storeId, $request->validated());

        return $this->created($result, __('backup.created'));
    }

    public function index(BackupListFilterRequest $request): JsonResponse
    {
        $storeId = auth()->user()->store_id;
        $result = $this->backupService->listBackups($storeId, $request->validated());

        return $this->success($result, __('backup.list_retrieved'));
    }

    public function show(string $backupId): JsonResponse
    {
        $storeId = auth()->user()->store_id;
        $backup = $this->backupService->getBackup($storeId, $backupId);

        if (!$backup) {
            return $this->notFound(__('backup.not_found'));
        }

        return $this->success($backup, __('backup.details_retrieved'));
    }

    public function restore(string $backupId): JsonResponse
    {
        $storeId = auth()->user()->store_id;
        $result = $this->backupService->restoreBackup($storeId, $backupId);

        if (!$result) {
            return $this->notFound(__('backup.not_found'));
        }

        if (isset($result['error'])) {
            return $this->error(__('backup.' . $result['error']), 422);
        }

        return $this->success($result, __('backup.restore_initiated'));
    }

    public function verify(string $backupId): JsonResponse
    {
        $storeId = auth()->user()->store_id;
        $result = $this->backupService->verifyBackup($storeId, $backupId);

        if (!$result) {
            return $this->notFound(__('backup.not_found'));
        }

        return $this->success($result, __('backup.verified'));
    }

    public function schedule(): JsonResponse
    {
        $storeId = auth()->user()->store_id;
        $result = $this->backupService->getSchedule($storeId);

        return $this->success($result, __('backup.schedule_retrieved'));
    }

    public function updateSchedule(UpdateBackupScheduleRequest $request): JsonResponse
    {
        $storeId = auth()->user()->store_id;
        $result = $this->backupService->updateSchedule($storeId, $request->validated());

        return $this->success($result, __('backup.schedule_updated'));
    }

    public function storageUsage(): JsonResponse
    {
        $storeId = auth()->user()->store_id;
        $result = $this->backupService->getStorageUsage($storeId);

        return $this->success($result, __('backup.storage_retrieved'));
    }

    public function destroy(string $backupId): JsonResponse
    {
        $storeId = auth()->user()->store_id;
        $deleted = $this->backupService->deleteBackup($storeId, $backupId);

        if (!$deleted) {
            return $this->notFound(__('backup.not_found'));
        }

        return $this->success(null, __('backup.deleted'));
    }

    public function export(ExportDataRequest $request): JsonResponse
    {
        $storeId = auth()->user()->store_id;
        $result = $this->backupService->exportData($storeId, $request->validated());

        return $this->created($result, __('backup.export_created'));
    }

    public function providerStatus(): JsonResponse
    {
        $storeId = auth()->user()->store_id;
        $result = $this->backupService->getProviderStatus($storeId);

        return $this->success($result, __('backup.provider_status_retrieved'));
    }
}
