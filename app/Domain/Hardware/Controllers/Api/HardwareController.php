<?php

namespace App\Domain\Hardware\Controllers\Api;

use App\Domain\Hardware\Requests\EventLogFilterRequest;
use App\Domain\Hardware\Requests\EventLogRequest;
use App\Domain\Hardware\Requests\HardwareConfigRequest;
use App\Domain\Hardware\Requests\TestDeviceRequest;
use App\Domain\Hardware\Services\HardwareService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HardwareController extends BaseApiController
{
    public function __construct(private readonly HardwareService $service) {}

    /**
     * List hardware configs for the authenticated store.
     */
    public function listConfigs(Request $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $configs = $this->service->listConfigs($storeId, $request->only(['terminal_id', 'device_type', 'is_active']));
        return $this->success($configs, __('hardware.configs_listed'));
    }

    /**
     * Create or update a device configuration.
     */
    public function saveConfig(HardwareConfigRequest $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $config = $this->service->saveConfig($storeId, $request->validated());
        return $this->success($config->toArray(), __('hardware.config_saved'));
    }

    /**
     * Remove a device configuration.
     */
    public function removeConfig(Request $request, string $id): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $removed = $this->service->removeConfig($storeId, $id);

        if (!$removed) {
            return $this->notFound();
        }

        return $this->success(null, __('hardware.config_removed'));
    }

    /**
     * List certified/supported hardware models.
     */
    public function supportedModels(Request $request): JsonResponse
    {
        $models = $this->service->supportedModels($request->only(['device_type', 'is_certified']));
        return $this->success($models, __('hardware.models_listed'));
    }

    /**
     * Test a hardware device.
     */
    public function testDevice(TestDeviceRequest $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $result = $this->service->testDevice($storeId, $request->validated());
        return $this->success($result, __('hardware.test_success'));
    }

    /**
     * Record a hardware event.
     */
    public function recordEvent(EventLogRequest $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $event = $this->service->recordEvent($storeId, $request->validated());
        return $this->created($event->toArray(), __('hardware.event_recorded'));
    }

    /**
     * List hardware event logs.
     */
    public function eventLogs(EventLogFilterRequest $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $logs = $this->service->eventLogs($storeId, $request->validated());
        return $this->success($logs, __('hardware.logs_listed'));
    }
}
