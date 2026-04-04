<?php

namespace App\Domain\Inventory\Controllers\Api;

use App\Domain\Inventory\Requests\CreateSupplierReturnRequest;
use App\Domain\Inventory\Resources\SupplierReturnResource;
use App\Domain\Inventory\Services\SupplierReturnService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierReturnController extends BaseApiController
{
    public function __construct(
        private readonly SupplierReturnService $supplierReturnService,
    ) {}

    /**
     * GET /api/v2/inventory/supplier-returns
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|string|in:draft,submitted,approved,completed,cancelled',
            'supplier_id' => 'nullable|uuid',
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $paginator = $this->supplierReturnService->list(
            organizationId: $request->user()->organization_id,
            status: $request->input('status'),
            supplierId: $request->input('supplier_id'),
            search: $request->input('search'),
            perPage: $request->integer('per_page', 25),
        );

        $data = $paginator->toArray();
        $data['data'] = SupplierReturnResource::collection($paginator->items())->resolve();

        return $this->success($data);
    }

    /**
     * POST /api/v2/inventory/supplier-returns
     */
    public function store(CreateSupplierReturnRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $return = $this->supplierReturnService->create(
            array_merge($validated, [
                'organization_id' => $request->user()->organization_id,
                'created_by' => $request->user()->id,
            ]),
            $validated['items'],
        );

        return $this->created(new SupplierReturnResource($return));
    }

    /**
     * GET /api/v2/inventory/supplier-returns/{id}
     */
    public function show(string $supplierReturn): JsonResponse
    {
        $return = $this->supplierReturnService->find($supplierReturn);

        return $this->success(new SupplierReturnResource($return));
    }

    /**
     * PUT /api/v2/inventory/supplier-returns/{id}
     */
    public function update(Request $request, string $supplierReturn): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'sometimes|uuid|exists:suppliers,id',
            'reference_number' => 'sometimes|nullable|string|max:50',
            'reason' => 'sometimes|nullable|string|max:255',
            'notes' => 'sometimes|nullable|string|max:2000',
            'items' => 'sometimes|array|min:1',
            'items.*.product_id' => 'required_with:items|uuid|exists:products,id',
            'items.*.quantity' => 'required_with:items|numeric|gt:0',
            'items.*.unit_cost' => 'required_with:items|numeric|min:0',
            'items.*.reason' => 'nullable|string|max:255',
            'items.*.batch_number' => 'nullable|string|max:100',
        ]);

        $return = $this->supplierReturnService->find($supplierReturn);

        if ($return->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Supplier return not found.');
        }

        try {
            $updated = $this->supplierReturnService->update(
                $return,
                $validated,
                $validated['items'] ?? null,
            );

            return $this->success(new SupplierReturnResource($updated));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/v2/inventory/supplier-returns/{id}/submit
     */
    public function submit(Request $request, string $supplierReturn): JsonResponse
    {
        try {
            $return = $this->supplierReturnService->submit($supplierReturn);

            return $this->success(new SupplierReturnResource($return), 'Supplier return submitted.');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/v2/inventory/supplier-returns/{id}/approve
     */
    public function approve(Request $request, string $supplierReturn): JsonResponse
    {
        try {
            $return = $this->supplierReturnService->approve($supplierReturn, $request->user()->id);

            return $this->success(new SupplierReturnResource($return), 'Supplier return approved.');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/v2/inventory/supplier-returns/{id}/complete
     */
    public function complete(Request $request, string $supplierReturn): JsonResponse
    {
        try {
            $return = $this->supplierReturnService->complete($supplierReturn);

            return $this->success(new SupplierReturnResource($return), 'Supplier return completed. Stock deducted.');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/v2/inventory/supplier-returns/{id}/cancel
     */
    public function cancel(Request $request, string $supplierReturn): JsonResponse
    {
        try {
            $return = $this->supplierReturnService->cancel($supplierReturn);

            return $this->success(new SupplierReturnResource($return), 'Supplier return cancelled.');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * DELETE /api/v2/inventory/supplier-returns/{id}
     */
    public function destroy(Request $request, string $supplierReturn): JsonResponse
    {
        $return = $this->supplierReturnService->find($supplierReturn);

        if ($return->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Supplier return not found.');
        }

        try {
            $this->supplierReturnService->delete($return);

            return $this->success(null, 'Supplier return deleted.');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
