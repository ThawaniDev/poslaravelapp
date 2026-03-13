<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SupplierService
{
    public function list(string $organizationId, ?string $search = null, int $perPage = 25): LengthAwarePaginator
    {
        $query = Supplier::where('organization_id', $organizationId)
            ->orderBy('name');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    public function find(string $supplierId): Supplier
    {
        return Supplier::with('productSuppliers')->findOrFail($supplierId);
    }

    public function create(array $data, User $actor): Supplier
    {
        $data['organization_id'] = $actor->organization_id;

        return Supplier::create($data);
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);

        return $supplier->fresh();
    }

    public function delete(Supplier $supplier): void
    {
        $linkedProducts = $supplier->productSuppliers()->count();

        if ($linkedProducts > 0) {
            throw new \RuntimeException(
                "Cannot delete supplier '{$supplier->name}': linked to {$linkedProducts} product(s). Unlink them first."
            );
        }

        $supplier->delete();
    }
}
