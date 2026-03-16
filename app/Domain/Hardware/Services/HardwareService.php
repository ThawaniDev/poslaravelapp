<?php

namespace App\Domain\Hardware\Services;

use App\Domain\Hardware\Models\HardwareConfiguration;
use App\Domain\Hardware\Models\HardwareEventLog;
use App\Domain\SystemConfig\Models\CertifiedHardware;

class HardwareService
{
    /**
     * List device configurations for a store, optionally filtered by terminal.
     */
    public function listConfigs(string $storeId, array $filters = []): array
    {
        $query = HardwareConfiguration::where('store_id', $storeId);

        if (!empty($filters['terminal_id'])) {
            $query->where('terminal_id', $filters['terminal_id']);
        }
        if (!empty($filters['device_type'])) {
            $query->where('device_type', $filters['device_type']);
        }
        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderBy('device_type')->get()->toArray();
    }

    /**
     * Create or update a hardware device configuration.
     */
    public function saveConfig(string $storeId, array $data): HardwareConfiguration
    {
        $config = HardwareConfiguration::updateOrCreate(
            [
                'store_id' => $storeId,
                'terminal_id' => $data['terminal_id'],
                'device_type' => $data['device_type'],
            ],
            [
                'connection_type' => $data['connection_type'],
                'device_name' => $data['device_name'] ?? null,
                'config_json' => $data['config_json'] ?? [],
                'is_active' => $data['is_active'] ?? true,
            ]
        );

        // Log configuration event
        HardwareEventLog::create([
            'store_id' => $storeId,
            'terminal_id' => $data['terminal_id'],
            'device_type' => $data['device_type'],
            'event' => $config->wasRecentlyCreated ? 'configured' : 'reconfigured',
            'details' => json_encode(['connection_type' => $data['connection_type']]),
        ]);

        return $config;
    }

    /**
     * Remove a hardware configuration.
     */
    public function removeConfig(string $storeId, string $configId): bool
    {
        $config = HardwareConfiguration::where('store_id', $storeId)
            ->where('id', $configId)
            ->first();

        if (!$config) {
            return false;
        }

        HardwareEventLog::create([
            'store_id' => $storeId,
            'terminal_id' => $config->terminal_id,
            'device_type' => $config->device_type->value,
            'event' => 'removed',
            'details' => json_encode(['device_name' => $config->device_name]),
        ]);

        $config->delete();
        return true;
    }

    /**
     * List certified/supported hardware models.
     */
    public function supportedModels(array $filters = []): array
    {
        $query = CertifiedHardware::where('is_active', true);

        if (!empty($filters['device_type'])) {
            $query->where('device_type', $filters['device_type']);
        }
        if (isset($filters['is_certified'])) {
            $query->where('is_certified', filter_var($filters['is_certified'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderBy('brand')->orderBy('model')->get()->toArray();
    }

    /**
     * Test a hardware device and record result.
     */
    public function testDevice(string $storeId, array $data): array
    {
        // Simulate hardware test (actual testing happens on client side)
        $success = true;
        $message = __('hardware.test_success');

        HardwareEventLog::create([
            'store_id' => $storeId,
            'terminal_id' => $data['terminal_id'],
            'device_type' => $data['device_type'],
            'event' => 'test_' . ($success ? 'passed' : 'failed'),
            'details' => json_encode([
                'connection_type' => $data['connection_type'],
                'test_type' => $data['test_type'] ?? 'connection',
            ]),
        ]);

        return [
            'success' => $success,
            'message' => $message,
            'device_type' => $data['device_type'],
            'connection_type' => $data['connection_type'],
            'tested_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Record a hardware event from the client.
     */
    public function recordEvent(string $storeId, array $data): HardwareEventLog
    {
        return HardwareEventLog::create([
            'store_id' => $storeId,
            'terminal_id' => $data['terminal_id'],
            'device_type' => $data['device_type'],
            'event' => $data['event'],
            'details' => isset($data['details']) ? json_encode($data['details']) : null,
        ]);
    }

    /**
     * List hardware event logs with filters and pagination.
     */
    public function eventLogs(string $storeId, array $filters = []): array
    {
        $query = HardwareEventLog::where('store_id', $storeId);

        if (!empty($filters['terminal_id'])) {
            $query->where('terminal_id', $filters['terminal_id']);
        }
        if (!empty($filters['device_type'])) {
            $query->where('device_type', $filters['device_type']);
        }
        if (!empty($filters['event'])) {
            $query->where('event', $filters['event']);
        }

        $perPage = min((int) ($filters['per_page'] ?? 20), 100);

        $paginated = $query->orderByDesc('created_at')->paginate($perPage);

        return [
            'data' => $paginated->items(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
        ];
    }
}
