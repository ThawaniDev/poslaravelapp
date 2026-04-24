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

    public function find(string $organizationId, string $customerId): Customer
    {
        return Customer::with(['loyaltyTransactions', 'storeCreditTransactions'])
            ->where('organization_id', $organizationId)
            ->findOrFail($customerId);
    }

    public function create(array $data, User $actor): Customer
    {
        $data['organization_id'] = $actor->organization_id;
        $data['sync_version'] = 1;

        // Check phone uniqueness within org first (rule #1)
        if (!empty($data['phone'])) {
            $exists = Customer::where('organization_id', $actor->organization_id)
                ->where('phone', $data['phone'])
                ->exists();
            if ($exists) {
                throw new \RuntimeException(__('customers.duplicate_phone'));
            }
        }

        // Generate unique 8-char alphanumeric loyalty code (rule #9)
        if (empty($data['loyalty_code'])) {
            $data['loyalty_code'] = $this->generateLoyaltyCode();
        }

        return Customer::create($data);
    }

    /**
     * Generate a unique 8-character alphanumeric loyalty code (rule #9).
     */
    public function generateLoyaltyCode(int $maxAttempts = 8): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // omit 0/O/1/I for readability
        for ($i = 0; $i < $maxAttempts; $i++) {
            $code = '';
            for ($j = 0; $j < 8; $j++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            if (! Customer::where('loyalty_code', $code)->exists()) {
                return $code;
            }
        }
        // fallback to timestamp suffix
        return strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
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
                throw new \RuntimeException(__('customers.duplicate_phone'));
            }
        }

        $data['sync_version'] = ($customer->sync_version ?? 0) + 1;
        $customer->update($data);
        return $customer->fresh();
    }

    /**
     * Quick search for POS lookup (matches phone, name, email, loyalty_code).
     */
    public function quickSearch(string $orgId, string $query, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        $q = trim($query);
        if ($q === '') {
            return new \Illuminate\Database\Eloquent\Collection();
        }
        return Customer::where('organization_id', $orgId)
            ->where(function ($w) use ($q) {
                $w->where('phone', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%")
                  ->orWhere('loyalty_code', 'like', "%{$q}%");
            })
            ->orderByDesc('last_visit_at')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    /**
     * Delta sync: customers updated/created since the given timestamp.
     */
    public function delta(string $orgId, ?string $sinceIso, int $limit = 500): array
    {
        $query = Customer::withTrashed()->where('organization_id', $orgId);
        if ($sinceIso) {
            try {
                $since = \Carbon\Carbon::parse($sinceIso);
                $query->where(function ($w) use ($since) {
                    $w->where('updated_at', '>', $since)
                      ->orWhere('deleted_at', '>', $since);
                });
            } catch (\Throwable $e) {
                // ignore invalid timestamp, return everything
            }
        }
        $rows = $query->orderBy('updated_at')->limit($limit)->get();
        return [
            'data' => $rows,
            'server_time' => now()->toIso8601String(),
            'count' => $rows->count(),
        ];
    }

    /**
     * Customer purchase history (orders attached to customer).
     */
    public function customerOrders(string $customerId, int $perPage = 20): LengthAwarePaginator
    {
        return \App\Domain\Order\Models\Order::where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Update running totals after an order completion (rule #2: net amount).
     */
    public function recordOrderCompletion(Customer $customer, float $netAmount): void
    {
        $customer->total_spend = (float) $customer->total_spend + $netAmount;
        $customer->visit_count = (int) $customer->visit_count + 1;
        $customer->last_visit_at = now();
        $customer->sync_version = (int) ($customer->sync_version ?? 0) + 1;
        $customer->save();
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
            throw new \RuntimeException(__('customers.group_in_use', [
                'name' => $group->name,
                'count' => $customerCount,
            ]));
        }
        $group->delete();
    }
}
