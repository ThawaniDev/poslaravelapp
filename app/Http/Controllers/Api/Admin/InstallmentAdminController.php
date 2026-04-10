<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Payment\Requests\UpdateInstallmentProviderRequest;
use App\Domain\Payment\Resources\InstallmentProviderConfigResource;
use App\Domain\Payment\Services\InstallmentService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstallmentAdminController extends BaseApiController
{
    public function __construct(
        private readonly InstallmentService $installmentService,
    ) {}

    public function index(): JsonResponse
    {
        $providers = $this->installmentService->listProviders();
        return $this->success(InstallmentProviderConfigResource::collection($providers));
    }

    public function show(string $id): JsonResponse
    {
        $provider = $this->installmentService->showProvider($id);
        return $this->success(new InstallmentProviderConfigResource($provider));
    }

    public function update(UpdateInstallmentProviderRequest $request, string $id): JsonResponse
    {
        $provider = $this->installmentService->updateProvider($id, $request->validated());
        return $this->success(new InstallmentProviderConfigResource($provider), 'Provider updated');
    }

    public function toggle(string $id): JsonResponse
    {
        $provider = $this->installmentService->toggleProvider($id);
        $state = $provider->is_enabled ? 'enabled' : 'disabled';
        return $this->success(new InstallmentProviderConfigResource($provider), "Provider {$state}");
    }

    public function setMaintenance(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'is_under_maintenance' => ['required', 'boolean'],
            'maintenance_message' => ['sometimes', 'nullable', 'string', 'max:500'],
            'maintenance_message_ar' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $provider = $this->installmentService->setMaintenance(
            $id,
            $request->boolean('is_under_maintenance'),
            $request->input('maintenance_message'),
            $request->input('maintenance_message_ar'),
        );

        return $this->success(new InstallmentProviderConfigResource($provider), 'Maintenance status updated');
    }
}
