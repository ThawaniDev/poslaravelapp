<?php

namespace App\Domain\AccountingIntegration\Controllers\Api;

use App\Domain\AccountingIntegration\Requests\ConnectProviderRequest;
use App\Domain\AccountingIntegration\Requests\ListExportsRequest;
use App\Domain\AccountingIntegration\Requests\RefreshTokenRequest;
use App\Domain\AccountingIntegration\Requests\SaveMappingsRequest;
use App\Domain\AccountingIntegration\Requests\TriggerExportRequest;
use App\Domain\AccountingIntegration\Requests\UpdateAutoExportRequest;
use App\Domain\AccountingIntegration\Resources\AccountingConfigResource;
use App\Domain\AccountingIntegration\Resources\AccountingExportResource;
use App\Domain\AccountingIntegration\Services\AccountingService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountingController extends BaseApiController
{
    public function __construct(
        private readonly AccountingService $accountingService,
    ) {}

    // ─── Connection ──────────────────────────────────────

    /**
     * GET /api/v2/accounting/status
     * Get accounting connection status for the user's store.
     */
    public function status(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;

        return $this->success(
            $this->accountingService->getStatus($storeId),
        );
    }

    /**
     * POST /api/v2/accounting/connect
     * Connect a store to an accounting provider.
     */
    public function connect(ConnectProviderRequest $request): JsonResponse
    {
        $storeId = $request->user()->store_id;

        $config = $this->accountingService->connect(
            $storeId,
            $request->validated(),
        );

        return $this->created(
            new AccountingConfigResource($config),
            'Accounting provider connected',
        );
    }

    /**
     * POST /api/v2/accounting/disconnect
     * Disconnect a store's accounting integration.
     */
    public function disconnect(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $disconnected = $this->accountingService->disconnect($storeId);

        if (!$disconnected) {
            return $this->notFound('No accounting integration found');
        }

        return $this->success(null, 'Accounting provider disconnected');
    }

    /**
     * POST /api/v2/accounting/refresh-token
     * Refresh the OAuth access token.
     */
    public function refreshToken(RefreshTokenRequest $request): JsonResponse
    {
        $storeId = $request->user()->store_id;

        $config = $this->accountingService->refreshToken(
            $storeId,
            $request->validated(),
        );

        if (!$config) {
            return $this->notFound('No accounting integration found');
        }

        return $this->success(
            new AccountingConfigResource($config),
            'Token refreshed',
        );
    }

    // ─── Account Mapping ─────────────────────────────────

    /**
     * GET /api/v2/accounting/mapping
     * Get account mappings for the user's store.
     */
    public function getMappings(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;

        return $this->success([
            'mappings' => $this->accountingService->getMappings($storeId),
            'pos_account_keys' => AccountingService::posAccountKeys(),
        ]);
    }

    /**
     * PUT /api/v2/accounting/mapping
     * Save account mappings.
     */
    public function saveMappings(SaveMappingsRequest $request): JsonResponse
    {
        $storeId = $request->user()->store_id;

        $mappings = $this->accountingService->saveMappings(
            $storeId,
            $request->validated()['mappings'],
        );

        return $this->success(
            ['mappings' => $mappings],
            'Account mappings saved',
        );
    }

    /**
     * DELETE /api/v2/accounting/mapping/{id}
     * Delete a specific account mapping.
     */
    public function deleteMapping(Request $request, string $id): JsonResponse
    {
        $storeId = $request->user()->store_id;

        $deleted = $this->accountingService->deleteMapping($storeId, $id);

        if (!$deleted) {
            return $this->notFound('Account mapping not found');
        }

        return $this->success(null, 'Account mapping deleted');
    }

    // ─── Exports ─────────────────────────────────────────

    /**
     * POST /api/v2/accounting/exports
     * Trigger a manual export.
     */
    public function triggerExport(TriggerExportRequest $request): JsonResponse
    {
        $storeId = $request->user()->store_id;

        $export = $this->accountingService->triggerExport(
            $storeId,
            $request->validated(),
        );

        return $this->created(
            new AccountingExportResource($export),
            'Export triggered',
        );
    }

    /**
     * GET /api/v2/accounting/exports
     * List exports for the user's store.
     */
    public function listExports(ListExportsRequest $request): JsonResponse
    {
        $storeId = $request->user()->store_id;

        $exports = $this->accountingService->listExports(
            $storeId,
            $request->validated(),
        );

        return $this->success(
            AccountingExportResource::collection($exports)->resolve(),
        );
    }

    /**
     * GET /api/v2/accounting/exports/{id}
     * Get a single export detail.
     */
    public function getExport(Request $request, string $id): JsonResponse
    {
        $storeId = $request->user()->store_id;

        $export = $this->accountingService->getExport($storeId, $id);

        if (!$export) {
            return $this->notFound('Export not found');
        }

        return $this->success(new AccountingExportResource($export));
    }

    /**
     * POST /api/v2/accounting/exports/{id}/retry
     * Retry a failed export.
     */
    public function retryExport(Request $request, string $id): JsonResponse
    {
        $storeId = $request->user()->store_id;

        $export = $this->accountingService->retryExport($storeId, $id);

        if (!$export) {
            return $this->notFound('Export not found or not eligible for retry');
        }

        return $this->created(
            new AccountingExportResource($export),
            'Export retry triggered',
        );
    }

    // ─── Auto-Export ─────────────────────────────────────

    /**
     * GET /api/v2/accounting/auto-export
     * Get auto-export configuration.
     */
    public function getAutoExport(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;

        return $this->success(
            $this->accountingService->getAutoExportConfig($storeId),
        );
    }

    /**
     * PUT /api/v2/accounting/auto-export
     * Update auto-export configuration.
     */
    public function updateAutoExport(UpdateAutoExportRequest $request): JsonResponse
    {
        $storeId = $request->user()->store_id;

        $config = $this->accountingService->updateAutoExportConfig(
            $storeId,
            $request->validated(),
        );

        return $this->success($config, 'Auto-export settings updated');
    }

    // ─── POS Account Keys ────────────────────────────────

    /**
     * GET /api/v2/accounting/pos-account-keys
     * Get the list of all POS account keys available for mapping.
     */
    public function posAccountKeys(): JsonResponse
    {
        return $this->success(AccountingService::posAccountKeys());
    }
}
