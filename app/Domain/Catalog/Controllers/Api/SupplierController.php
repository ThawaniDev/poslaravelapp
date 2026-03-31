<?php

namespace App\Domain\Catalog\Controllers\Api;

use App\Domain\Catalog\Requests\CreateSupplierRequest;
use App\Domain\Catalog\Resources\SupplierResource;
use App\Domain\Catalog\Services\SupplierService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends BaseApiController
{
    public function __construct(
        private readonly SupplierService $supplierService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $paginator = $this->supplierService->list(
            organizationId: $request->user()->organization_id,
            search: $request->input('search'),
            perPage: $request->integer('per_page', 25),
        );

        $data = $paginator->toArray();
        $data['data'] = SupplierResource::collection($paginator->items())->resolve();

        return $this->success($data);
    }

    public function store(CreateSupplierRequest $request): JsonResponse
    {
        $supplier = $this->supplierService->create(
            $request->validated(),
            $request->user(),
        );

        return $this->created(new SupplierResource($supplier));
    }

    public function show(string $supplier): JsonResponse
    {
        $found = $this->supplierService->find($supplier);

        return $this->success(new SupplierResource($found));
    }

    public function update(Request $request, string $supplier): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|nullable|string|max:20',
            'email' => 'sometimes|nullable|email|max:255',
            'address' => 'sometimes|nullable|string|max:500',
            'notes' => 'sometimes|nullable|string|max:1000',
            'contact_person' => 'sometimes|nullable|string|max:255',
            'tax_number' => 'sometimes|nullable|string|max:50',
            'payment_terms' => 'sometimes|nullable|string|max:100',
            'is_active' => 'sometimes|boolean',
        ]);

        $found = $this->supplierService->find($supplier);

        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Supplier not found.');
        }

        $updated = $this->supplierService->update($found, $validated);

        return $this->success(new SupplierResource($updated));
    }

    public function destroy(Request $request, string $supplier): JsonResponse
    {
        $found = $this->supplierService->find($supplier);

        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Supplier not found.');
        }

        try {
            $this->supplierService->delete($found);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(null, 'Supplier deleted successfully.');
    }
}
