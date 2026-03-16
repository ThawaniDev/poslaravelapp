<?php

namespace App\Domain\PosCustomization\Controllers\Api;

use App\Domain\PosCustomization\Requests\UpdateSettingsRequest;
use App\Domain\PosCustomization\Requests\UpdateReceiptTemplateRequest;
use App\Domain\PosCustomization\Requests\UpdateQuickAccessRequest;
use App\Domain\PosCustomization\Services\CustomizationService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;

class CustomizationController extends BaseApiController
{
    public function __construct(private readonly CustomizationService $service) {}

    // ─── Settings ────────────────────────────────────

    public function getSettings(): JsonResponse
    {
        $storeId = auth()->user()->store_id;

        return $this->success(
            $this->service->getSettings($storeId),
            __('customization.settings_loaded'),
        );
    }

    public function updateSettings(UpdateSettingsRequest $request): JsonResponse
    {
        $storeId = auth()->user()->store_id;

        return $this->success(
            $this->service->updateSettings($storeId, $request->validated()),
            __('customization.settings_updated'),
        );
    }

    public function resetSettings(): JsonResponse
    {
        $storeId = auth()->user()->store_id;

        return $this->success(
            $this->service->resetSettings($storeId),
            __('customization.settings_reset'),
        );
    }

    // ─── Receipt Template ────────────────────────────

    public function getReceiptTemplate(): JsonResponse
    {
        $storeId = auth()->user()->store_id;

        return $this->success(
            $this->service->getReceiptTemplate($storeId),
            __('customization.receipt_loaded'),
        );
    }

    public function updateReceiptTemplate(UpdateReceiptTemplateRequest $request): JsonResponse
    {
        $storeId = auth()->user()->store_id;

        return $this->success(
            $this->service->updateReceiptTemplate($storeId, $request->validated()),
            __('customization.receipt_updated'),
        );
    }

    public function resetReceiptTemplate(): JsonResponse
    {
        $storeId = auth()->user()->store_id;

        return $this->success(
            $this->service->resetReceiptTemplate($storeId),
            __('customization.receipt_reset'),
        );
    }

    // ─── Quick Access ────────────────────────────────

    public function getQuickAccess(): JsonResponse
    {
        $storeId = auth()->user()->store_id;

        return $this->success(
            $this->service->getQuickAccess($storeId),
            __('customization.quick_access_loaded'),
        );
    }

    public function updateQuickAccess(UpdateQuickAccessRequest $request): JsonResponse
    {
        $storeId = auth()->user()->store_id;

        return $this->success(
            $this->service->updateQuickAccess($storeId, $request->validated()),
            __('customization.quick_access_updated'),
        );
    }

    public function resetQuickAccess(): JsonResponse
    {
        $storeId = auth()->user()->store_id;

        return $this->success(
            $this->service->resetQuickAccess($storeId),
            __('customization.quick_access_reset'),
        );
    }

    // ─── Export All ──────────────────────────────────

    public function exportAll(): JsonResponse
    {
        $storeId = auth()->user()->store_id;

        return $this->success(
            $this->service->exportAll($storeId),
            __('customization.export_success'),
        );
    }
}
