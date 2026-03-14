<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\ProviderRegistration\Services\ProviderManagementService;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\AddProviderNoteRequest;
use App\Http\Requests\Admin\CreateStoreManualRequest;
use App\Http\Requests\Admin\RejectRegistrationRequest;
use App\Http\Requests\Admin\SetLimitOverrideRequest;
use App\Http\Requests\Admin\SuspendStoreRequest;
use App\Http\Resources\Admin\LimitOverrideResource;
use App\Http\Resources\Admin\ProviderNoteResource;
use App\Http\Resources\Admin\ProviderRegistrationResource;
use App\Http\Resources\Admin\StoreAdminResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderManagementController extends BaseApiController
{
    public function __construct(
        private readonly ProviderManagementService $service
    ) {}

    // ─── Store Endpoints ─────────────────────────────────────────

    /**
     * GET /admin/providers/stores
     * List all stores with filters, search, and pagination.
     */
    public function listStores(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'is_active', 'business_type', 'organization_id']);

        if (isset($filters['is_active'])) {
            $filters['is_active'] = filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        $stores = $this->service->listStores($filters, (int) $request->get('per_page', 15));

        return $this->success([
            'stores' => StoreAdminResource::collection($stores->items()),
            'pagination' => [
                'total' => $stores->total(),
                'per_page' => $stores->perPage(),
                'current_page' => $stores->currentPage(),
                'last_page' => $stores->lastPage(),
            ],
        ]);
    }

    /**
     * GET /admin/providers/stores/{storeId}
     * Get store detail with related platform data.
     */
    public function showStore(string $storeId): JsonResponse
    {
        $store = $this->service->getStoreDetail($storeId);

        if (!$store) {
            return $this->notFound('Store not found');
        }

        return $this->success(new StoreAdminResource($store));
    }

    /**
     * GET /admin/providers/stores/{storeId}/metrics
     * Get live usage metrics for a store.
     */
    public function storeMetrics(string $storeId): JsonResponse
    {
        $metrics = $this->service->getStoreMetrics($storeId);

        if (empty($metrics)) {
            return $this->notFound('Store not found');
        }

        return $this->success($metrics);
    }

    /**
     * POST /admin/providers/stores/{storeId}/suspend
     * Suspend a store.
     */
    public function suspendStore(SuspendStoreRequest $request, string $storeId): JsonResponse
    {
        try {
            $store = $this->service->suspendStore(
                $storeId,
                $request->user()->id,
                $request->input('reason')
            );

            return $this->success(
                new StoreAdminResource($store),
                'Store suspended successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFound('Store not found');
        }
    }

    /**
     * POST /admin/providers/stores/{storeId}/activate
     * Activate a suspended store.
     */
    public function activateStore(Request $request, string $storeId): JsonResponse
    {
        try {
            $store = $this->service->activateStore($storeId, $request->user()->id);

            return $this->success(
                new StoreAdminResource($store),
                'Store activated successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFound('Store not found');
        }
    }

    /**
     * POST /admin/providers/stores/create
     * Manually onboard a new store with organization.
     */
    public function createStore(CreateStoreManualRequest $request): JsonResponse
    {
        $result = $this->service->createStoreManually(
            [
                'name' => $request->input('organization_name'),
                'business_type' => $request->input('organization_business_type', 'retail'),
                'country' => $request->input('organization_country', 'OM'),
            ],
            [
                'name' => $request->input('store_name'),
                'business_type' => $request->input('store_business_type'),
                'currency' => $request->input('store_currency', 'OMR'),
                'is_active' => $request->input('store_is_active', true),
            ],
            $request->user()->id
        );

        return $this->created([
            'organization' => [
                'id' => $result['organization']->id,
                'name' => $result['organization']->name,
            ],
            'store' => new StoreAdminResource($result['store']->load('organization')),
        ], 'Store created successfully');
    }

    /**
     * POST /admin/providers/stores/export
     * Export store data.
     */
    public function exportStores(Request $request): JsonResponse
    {
        $filters = $request->only(['is_active', 'business_type']);
        $data = $this->service->exportStoreData($filters);

        return $this->success([
            'export' => $data,
            'count' => count($data),
        ], 'Store data exported');
    }

    // ─── Registration Endpoints ──────────────────────────────────

    /**
     * GET /admin/providers/registrations
     * List provider registrations.
     */
    public function listRegistrations(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'search']);
        $registrations = $this->service->listRegistrations($filters, (int) $request->get('per_page', 15));

        return $this->success([
            'registrations' => ProviderRegistrationResource::collection($registrations->items()),
            'pagination' => [
                'total' => $registrations->total(),
                'per_page' => $registrations->perPage(),
                'current_page' => $registrations->currentPage(),
                'last_page' => $registrations->lastPage(),
            ],
        ]);
    }

    /**
     * POST /admin/providers/registrations/{id}/approve
     * Approve a pending registration.
     */
    public function approveRegistration(Request $request, string $registrationId): JsonResponse
    {
        try {
            $registration = $this->service->approveRegistration($registrationId, $request->user()->id);

            return $this->success(
                new ProviderRegistrationResource($registration),
                'Registration approved successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFound('Registration not found');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * POST /admin/providers/registrations/{id}/reject
     * Reject a pending registration.
     */
    public function rejectRegistration(RejectRegistrationRequest $request, string $registrationId): JsonResponse
    {
        try {
            $registration = $this->service->rejectRegistration(
                $registrationId,
                $request->user()->id,
                $request->input('rejection_reason')
            );

            return $this->success(
                new ProviderRegistrationResource($registration),
                'Registration rejected'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFound('Registration not found');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    // ─── Notes Endpoints ─────────────────────────────────────────

    /**
     * POST /admin/providers/notes
     * Add an internal note.
     */
    public function addNote(AddProviderNoteRequest $request): JsonResponse
    {
        $note = $this->service->addNote(
            $request->input('organization_id'),
            $request->user()->id,
            $request->input('note_text')
        );

        return $this->created(new ProviderNoteResource($note), 'Note added');
    }

    /**
     * GET /admin/providers/notes/{organizationId}
     * List notes for an organization.
     */
    public function listNotes(string $organizationId): JsonResponse
    {
        $notes = $this->service->listNotes($organizationId);

        return $this->success(ProviderNoteResource::collection($notes));
    }

    // ─── Limit Override Endpoints ────────────────────────────────

    /**
     * POST /admin/providers/stores/{storeId}/limits
     * Set or update a limit override.
     */
    public function setLimitOverride(SetLimitOverrideRequest $request, string $storeId): JsonResponse
    {
        $override = $this->service->setLimitOverride(
            $storeId,
            $request->input('limit_key'),
            $request->input('override_value'),
            $request->user()->id,
            $request->input('reason'),
            $request->input('expires_at')
        );

        return $this->success(
            new LimitOverrideResource($override),
            'Limit override set'
        );
    }

    /**
     * GET /admin/providers/stores/{storeId}/limits
     * List limit overrides for a store.
     */
    public function listLimitOverrides(string $storeId): JsonResponse
    {
        $overrides = $this->service->listLimitOverrides($storeId);

        return $this->success(LimitOverrideResource::collection($overrides));
    }

    /**
     * DELETE /admin/providers/stores/{storeId}/limits/{limitKey}
     * Remove a limit override.
     */
    public function removeLimitOverride(Request $request, string $storeId, string $limitKey): JsonResponse
    {
        $removed = $this->service->removeLimitOverride($storeId, $limitKey, $request->user()->id);

        if (!$removed) {
            return $this->notFound('Limit override not found');
        }

        return $this->success(null, 'Limit override removed');
    }
}
