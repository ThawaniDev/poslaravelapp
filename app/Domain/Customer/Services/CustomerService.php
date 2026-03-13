<?php

namespace App\Domain\Customer\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Customer\Models\Customer;
use App\Domain\Customer\Models\CustomerGroup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CustomerService
{
    public function list(string $orgId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Customer::where('organization_id', $orgId);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('loyalty_code', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['group_id'])) {
            $query->where('group_id', $filters['group_id']);
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    public function find(string $customerId): Customer
    {
        return Customer::with(['loyaltyTransactions', 'storeCreditTransactions'])->findOrFail($customerId);
    }

    public function create(array $data, User $actor): Customer
    {
        $data['organization_id'] = $actor->organization_id;
        $data['sync_version'] = 1;

        // Generate loyalty code if not provided
        if (empty($data['loyalty_code'])) {
            $data['loyalty_code'] = strtoupper(substr(md5(uniqid()), 0, 8));
        }

        // Check phone uniqueness within org
        if (!empty($data['phone'])) {
            $exists = Customer::where('organization_id', $actor->organization_id)
                ->where('phone', $data['phone'])
                ->exists();
            if ($exists) {
                throw new \RuntimeException('A customer with this phone number already exists.');
            }
        }

        return Customer::create($data);
    }

    public function update(Customer $customer, array $data): Customer
    {
        // Check phone uniqueness within org if phone is changing
        if (!empty($data['phone']) && $data['phone'] !== $customer->phone) {
            $exists = Customer::where('organization_id', $customer->organization_id)
                ->where('phone', $data['phone'])
                ->where('id', '!=', $customer->id)
                ->exists();
            if ($exists) {
                throw new \RuntimeException('A customer with this phone number already exists.');
            }
        }

        $data['sync_version'] = ($customer->sync_version ?? 0) + 1;
        $customer->update($data);
        return $customer->fresh();
    }

    public function delete(Customer $customer): void
    {
        $customer->delete(); // soft delete
    }

    // ─── Groups ─────────────────────────────────────────────

    public function listGroups(string $orgId): Collection
    {
        return CustomerGroup::where('organization_id', $orgId)->orderBy('name')->get();
    }

    public function createGroup(array $data, User $actor): CustomerGroup
    {
        $data['organization_id'] = $actor->organization_id;
        return CustomerGroup::create($data);
    }

    public function updateGroup(CustomerGroup $group, array $data): CustomerGroup
    {
        $group->update($data);
        return $group->fresh();
    }

    public function deleteGroup(CustomerGroup $group): void
    {
        $customerCount = Customer::where('group_id', $group->id)->count();
        if ($customerCount > 0) {
            throw new \RuntimeException(
                "Cannot delete group '{$group->name}': {$customerCount} customer(s) assigned."
            );
        }
        $group->delete();
    }
}
