<?php

namespace App\Domain\AppUpdateManagement\Controllers\Api;

use App\Domain\AppUpdateManagement\Requests\CheckUpdateRequest;
use App\Domain\AppUpdateManagement\Requests\ReportUpdateStatusRequest;
use App\Domain\AppUpdateManagement\Services\AutoUpdateService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutoUpdateController extends BaseApiController
{
    public function __construct(private readonly AutoUpdateService $service) {}

    public function checkForUpdate(CheckUpdateRequest $request): JsonResponse
    {
        $storeId = auth()->user()->store_id;

        return $this->success(
            $this->service->checkForUpdate(
                $storeId,
                $request->validated('current_version'),
                $request->validated('platform'),
                $request->validated('channel', 'stable'),
            ),
            __('auto_update.check_complete'),
        );
    }

    public function reportStatus(ReportUpdateStatusRequest $request): JsonResponse
    {
        $storeId = auth()->user()->store_id;

        return $this->success(
            $this->service->reportStatus(
                $storeId,
                $request->validated('release_id'),
                $request->validated('status'),
                $request->validated('error_message'),
            ),
            __('auto_update.status_reported'),
        );
    }

    public function changelog(Request $request): JsonResponse
    {
        $platform = $request->query('platform', 'ios');
        $channel = $request->query('channel', 'stable');

        return $this->success(
            $this->service->getChangelog($platform, $channel),
            __('auto_update.changelog_loaded'),
        );
    }

    public function updateHistory(): JsonResponse
    {
        $storeId = auth()->user()->store_id;

        return $this->success(
            $this->service->getUpdateHistory($storeId),
            __('auto_update.history_loaded'),
        );
    }

    public function currentVersion(Request $request): JsonResponse
    {
        $storeId = auth()->user()->store_id;
        $platform = $request->query('platform', 'ios');

        return $this->success(
            $this->service->getCurrentVersion($storeId, $platform),
            __('auto_update.current_loaded'),
        );
    }
}
