<?php

namespace App\Domain\Core\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Enums\BusinessType;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Core\Models\StoreSettings;
use App\Domain\Core\Models\StoreWorkingHour;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StoreService
{
    // ─── Eager-load sets ─────────────────────────────────────────

    private const DETAIL_RELATIONS = [
        'storeSettings',
        'workingHours',
        'organization',
        'manager',
    ];

    private const LIST_RELATIONS = [
        'storeSettings',
        'manager',
    ];

    // ─── Store CRUD ──────────────────────────────────────────────

    /**
     * Get the authenticated user's store (with settings & working hours).
     */
    public function getStore(string $storeId): Store
    {
        return Store::with(self::DETAIL_RELATIONS)
            ->withCount(['users', 'registers'])
            ->findOrFail($storeId);
    }

    /**
     * List all stores for an organization with filtering, search & pagination.
     */
    public function listStores(
        string $organizationId,
        array  $filters = [],
    ): Collection|LengthAwarePaginator {
        $query = Store::where('organization_id', $organizationId)
            ->with(self::LIST_RELATIONS)
            ->withCount(['users', 'registers']);

        // ─── Filters ────────────────────────────────
        if (isset($filters['search']) && $filters['search'] !== '') {
            $search = mb_strtolower($filters['search']);
            $query->where(function (Builder $q) use ($search) {
                $q->whereRaw('lower(name) like ?', ["%{$search}%"])
                  ->orWhereRaw('lower(name_ar) like ?', ["%{$search}%"])
                  ->orWhereRaw('lower(branch_code) like ?', ["%{$search}%"])
                  ->orWhereRaw('lower(city) like ?', ["%{$search}%"])
                  ->orWhereRaw('lower(address) like ?', ["%{$search}%"])
                  ->orWhereRaw('lower(email) like ?', ["%{$search}%"])
                  ->orWhereRaw('lower(phone) like ?', ["%{$search}%"]);
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['is_main_branch'])) {
            $query->where('is_main_branch', filter_var($filters['is_main_branch'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['is_warehouse'])) {
            $query->where('is_warehouse', filter_var($filters['is_warehouse'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['business_type']) && $filters['business_type'] !== '') {
            $query->where('business_type', $filters['business_type']);
        }

        if (isset($filters['city']) && $filters['city'] !== '') {
            $query->where('city', $filters['city']);
        }

        if (isset($filters['region']) && $filters['region'] !== '') {
            $query->where('region', $filters['region']);
        }

        if (isset($filters['manager_id']) && $filters['manager_id'] !== '') {
            $query->where('manager_id', $filters['manager_id']);
        }

        if (isset($filters['has_delivery'])) {
            $query->where('has_delivery', filter_var($filters['has_delivery'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['accepts_online_orders'])) {
            $query->where('accepts_online_orders', filter_var($filters['accepts_online_orders'], FILTER_VALIDATE_BOOLEAN));
        }

        // ─── Sorting ────────────────────────────────
        $sortField = $filters['sort_by'] ?? 'sort_order';
        $sortDir   = $filters['sort_dir'] ?? 'asc';
        $allowedSorts = ['name', 'branch_code', 'city', 'created_at', 'sort_order', 'is_active', 'is_main_branch'];

        if (in_array($sortField, $allowedSorts, true)) {
            $query->orderBy($sortField, $sortDir === 'desc' ? 'desc' : 'asc');
        }

        // Main branch always first (secondary sort)
        $query->orderBy('is_main_branch', 'desc');
        $query->orderBy('name', 'asc');

        // ─── Pagination or all ───────────────────────
        $perPage = isset($filters['per_page']) ? min((int) $filters['per_page'], 100) : null;

        if ($perPage) {
            return $query->paginate($perPage);
        }

        return $query->get();
    }

    /**
     * Create a new store (branch) under an organization.
     */
    public function createStore(string $organizationId, array $data): Store
    {
        return DB::transaction(function () use ($organizationId, $data) {
            $data['organization_id'] = $organizationId;
            $data['slug'] = $data['slug'] ?? Str::slug($data['name'] . '-' . Str::random(4));

            // If this is marked as main branch, un-set existing main branch
            if (!empty($data['is_main_branch'])) {
                Store::where('organization_id', $organizationId)
                    ->where('is_main_branch', true)
                    ->update(['is_main_branch' => false]);
            }

            $store = Store::create($data);

            // Create default settings
            $this->initDefaultSettings($store);

            // Create default working hours (Sun–Sat, 9am–10pm, Fri off)
            $this->initDefaultWorkingHours($store);

            return $store->load(self::DETAIL_RELATIONS)
                         ->loadCount(['users', 'registers']);
        });
    }

    /**
     * Update store basic information.
     */
    public function updateStore(Store $store, array $data): Store
    {
        return DB::transaction(function () use ($store, $data) {
            // If this is being set as main branch, un-set existing
            if (!empty($data['is_main_branch']) && !$store->is_main_branch) {
                Store::where('organization_id', $store->organization_id)
                    ->where('is_main_branch', true)
                    ->where('id', '!=', $store->id)
                    ->update(['is_main_branch' => false]);
            }

            $store->update($data);

            return $store->fresh(self::DETAIL_RELATIONS)
                         ->loadCount(['users', 'registers']);
        });
    }

    /**
     * Toggle store active status.
     */
    public function toggleActive(Store $store): Store
    {
        $store->update(['is_active' => !$store->is_active]);
        return $store->fresh(self::DETAIL_RELATIONS);
    }

    /**
     * Delete a store (only if it has no transactions/orders).
     */
    public function deleteStore(Store $store): bool
    {
        // Guard: cannot delete main branch
        if ($store->is_main_branch) {
            throw new \DomainException(__('Cannot delete the main branch.'));
        }

        // Guard: cannot delete store with orders/transactions
        if ($store->transactions()->exists() || $store->orders()->exists()) {
            throw new \DomainException(__('Cannot delete a branch with existing transactions or orders.'));
        }

        return DB::transaction(function () use ($store) {
            // Clean up child records
            $store->storeSettings()->delete();
            $store->workingHours()->delete();
            $store->registers()->delete();
            $store->delete();
            return true;
        });
    }

    /**
     * Get summary statistics for all branches in an organization.
     */
    public function getOrganizationBranchStats(string $organizationId): array
    {
        $stores = Store::where('organization_id', $organizationId);

        return [
            'total_branches'    => (clone $stores)->count(),
            'active_branches'   => (clone $stores)->where('is_active', true)->count(),
            'inactive_branches' => (clone $stores)->where('is_active', false)->count(),
            'warehouses'        => (clone $stores)->where('is_warehouse', true)->count(),
            'total_staff'       => User::whereIn('store_id', (clone $stores)->pluck('id'))->count(),
            'total_registers'   => \App\Domain\Core\Models\Register::whereIn('store_id', (clone $stores)->pluck('id'))->count(),
            'cities'            => (clone $stores)->whereNotNull('city')->distinct('city')->pluck('city')->values(),
            'regions'           => (clone $stores)->whereNotNull('region')->distinct('region')->pluck('region')->values(),
        ];
    }

    /**
     * Bulk update sort order for branches.
     */
    public function updateSortOrder(string $organizationId, array $sortData): void
    {
        DB::transaction(function () use ($organizationId, $sortData) {
            foreach ($sortData as $item) {
                Store::where('id', $item['id'])
                    ->where('organization_id', $organizationId)
                    ->update(['sort_order' => $item['sort_order']]);
            }
        });
    }

    // ─── Store Settings ──────────────────────────────────────────

    /**
     * Get or create settings for a store.
     */
    public function getSettings(string $storeId): StoreSettings
    {
        return StoreSettings::firstOrCreate(
            ['store_id' => $storeId],
            $this->defaultSettingsData($storeId),
        );
    }

    /**
     * Update store settings.
     */
    public function updateSettings(string $storeId, array $data): StoreSettings
    {
        $settings = $this->getSettings($storeId);
        $settings->update($data);
        return $settings->fresh();
    }

    // ─── Working Hours ───────────────────────────────────────────

    /**
     * Get working hours for a store (all 7 days).
     */
    public function getWorkingHours(string $storeId): Collection
    {
        return StoreWorkingHour::where('store_id', $storeId)
            ->orderBy('day_of_week')
            ->get();
    }

    /**
     * Bulk-replace working hours for the store.
     */
    public function updateWorkingHours(string $storeId, array $days): Collection
    {
        return DB::transaction(function () use ($storeId, $days) {
            foreach ($days as $day) {
                StoreWorkingHour::updateOrCreate(
                    [
                        'store_id' => $storeId,
                        'day_of_week' => $day['day_of_week'],
                    ],
                    [
                        'is_open' => $day['is_open'] ?? true,
                        'open_time' => $day['open_time'] ?? null,
                        'close_time' => $day['close_time'] ?? null,
                        'break_start' => $day['break_start'] ?? null,
                        'break_end' => $day['break_end'] ?? null,
                    ],
                );
            }

            return $this->getWorkingHours($storeId);
        });
    }

    /**
     * Copy working hours from one store to another.
     */
    public function copyWorkingHours(string $sourceStoreId, string $targetStoreId): Collection
    {
        $sourceHours = $this->getWorkingHours($sourceStoreId);

        return DB::transaction(function () use ($targetStoreId, $sourceHours) {
            StoreWorkingHour::where('store_id', $targetStoreId)->delete();

            foreach ($sourceHours as $hour) {
                StoreWorkingHour::create([
                    'store_id'    => $targetStoreId,
                    'day_of_week' => $hour->day_of_week,
                    'is_open'     => $hour->is_open,
                    'open_time'   => $hour->open_time,
                    'close_time'  => $hour->close_time,
                    'break_start' => $hour->break_start,
                    'break_end'   => $hour->break_end,
                ]);
            }

            return $this->getWorkingHours($targetStoreId);
        });
    }

    // ─── Business Type Templates ─────────────────────────────────

    /**
     * Get available business type templates.
     */
    public function getBusinessTypeTemplates(): \Illuminate\Support\Collection
    {
        return \App\Domain\ProviderRegistration\Models\BusinessTypeTemplate::where('is_active', true)
            ->orderBy('display_order')
            ->get();
    }

    /**
     * Apply a business type template to a store.
     */
    public function applyBusinessType(Store $store, string $businessTypeCode): Store
    {
        return DB::transaction(function () use ($store, $businessTypeCode) {
            $businessType = BusinessType::tryFrom($businessTypeCode);
            if (!$businessType) {
                throw new \InvalidArgumentException("Invalid business type: {$businessTypeCode}");
            }

            $store->update(['business_type' => $businessType]);

            $template = \App\Domain\ProviderRegistration\Models\BusinessTypeTemplate::where('code', $businessTypeCode)->first();

            if ($template && !empty($template->template_json)) {
                $templateConfig = $template->template_json;
                $settings = $this->getSettings($store->id);
                $settingsUpdates = [];

                $applyableKeys = [
                    'tax_rate', 'prices_include_tax', 'enable_kitchen_display',
                    'enable_tips', 'require_customer_for_sale',
                ];

                foreach ($applyableKeys as $key) {
                    if (isset($templateConfig[$key])) {
                        $settingsUpdates[$key] = $templateConfig[$key];
                    }
                }

                $extraKeys = array_diff_key($templateConfig, array_flip($applyableKeys));
                if (!empty($extraKeys)) {
                    $settingsUpdates['extra'] = array_merge($settings->extra ?? [], $extraKeys);
                }

                if (!empty($settingsUpdates)) {
                    $settings->update($settingsUpdates);
                }
            }

            return $store->fresh(self::DETAIL_RELATIONS);
        });
    }

    /**
     * Copy settings from one store to another.
     */
    public function copySettings(string $sourceStoreId, string $targetStoreId): StoreSettings
    {
        $sourceSettings = $this->getSettings($sourceStoreId);
        $targetSettings = $this->getSettings($targetStoreId);

        $copyData = $sourceSettings->toArray();
        unset($copyData['id'], $copyData['store_id'], $copyData['created_at'], $copyData['updated_at']);

        $targetSettings->update($copyData);
        return $targetSettings->fresh();
    }

    /**
     * Get a list of available managers (users) for an organization.
     */
    public function getAvailableManagers(string $organizationId): Collection
    {
        return User::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->whereIn('role', ['owner', 'manager', 'admin'])
            ->select(['id', 'name', 'email', 'role', 'store_id'])
            ->orderBy('name')
            ->get();
    }

    // ─── Initialization Helpers ──────────────────────────────────

    private function initDefaultSettings(Store $store): StoreSettings
    {
        return StoreSettings::create($this->defaultSettingsData($store->id));
    }

    private function defaultSettingsData(string $storeId): array
    {
        return [
            'store_id' => $storeId,
            'tax_label' => 'VAT',
            'tax_rate' => 15.00,
            'prices_include_tax' => true,
            'currency_code' => 'SAR',
            'currency_symbol' => '﷼',
            'decimal_places' => 2,
            'thousand_separator' => ',',
            'decimal_separator' => '.',
            'allow_negative_stock' => false,
            'require_customer_for_sale' => false,
            'auto_print_receipt' => true,
            'session_timeout_minutes' => 480,
            'max_discount_percent' => 100,
            'enable_tips' => false,
            'enable_kitchen_display' => false,
            'low_stock_alert' => true,
            'low_stock_threshold' => 5,
            'extra' => [],
        ];
    }

    private function initDefaultWorkingHours(Store $store): void
    {
        $defaults = [
            0 => ['is_open' => true,  'open_time' => '09:00', 'close_time' => '22:00'],
            1 => ['is_open' => true,  'open_time' => '09:00', 'close_time' => '22:00'],
            2 => ['is_open' => true,  'open_time' => '09:00', 'close_time' => '22:00'],
            3 => ['is_open' => true,  'open_time' => '09:00', 'close_time' => '22:00'],
            4 => ['is_open' => true,  'open_time' => '09:00', 'close_time' => '22:00'],
            5 => ['is_open' => false, 'open_time' => null,    'close_time' => null],
            6 => ['is_open' => true,  'open_time' => '09:00', 'close_time' => '22:00'],
        ];

        foreach ($defaults as $day => $hours) {
            StoreWorkingHour::create([
                'store_id' => $store->id,
                'day_of_week' => $day,
                'is_open' => $hours['is_open'],
                'open_time' => $hours['open_time'],
                'close_time' => $hours['close_time'],
            ]);
        }
    }
}
