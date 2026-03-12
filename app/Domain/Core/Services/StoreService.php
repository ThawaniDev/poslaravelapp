<?php

namespace App\Domain\Core\Services;

use App\Domain\Core\Enums\BusinessType;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Core\Models\StoreSettings;
use App\Domain\Core\Models\StoreWorkingHour;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StoreService
{
    // ─── Store CRUD ──────────────────────────────────────────────

    /**
     * Get the authenticated user's store (with settings & working hours).
     */
    public function getStore(string $storeId): Store
    {
        return Store::with(['storeSettings', 'workingHours', 'organization'])
            ->findOrFail($storeId);
    }

    /**
     * List all stores for an organization.
     */
    public function listStores(string $organizationId): \Illuminate\Database\Eloquent\Collection
    {
        return Store::where('organization_id', $organizationId)
            ->with(['storeSettings'])
            ->orderBy('is_main_branch', 'desc')
            ->orderBy('name')
            ->get();
    }

    /**
     * Create a new store (branch) under an organization.
     */
    public function createStore(array $data): Store
    {
        return DB::transaction(function () use ($data) {
            $data['slug'] = $data['slug'] ?? Str::slug($data['name'] . '-' . Str::random(4));

            $store = Store::create($data);

            // Create default settings
            $this->initDefaultSettings($store);

            // Create default working hours (Sun–Sat, 9am–10pm, Fri off)
            $this->initDefaultWorkingHours($store);

            return $store->load(['storeSettings', 'workingHours']);
        });
    }

    /**
     * Update store basic information.
     */
    public function updateStore(Store $store, array $data): Store
    {
        $store->update($data);
        return $store->fresh(['storeSettings', 'workingHours', 'organization']);
    }

    /**
     * Deactivate a store (soft-disable).
     */
    public function deactivateStore(Store $store): Store
    {
        $store->update(['is_active' => false]);
        return $store;
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
    public function getWorkingHours(string $storeId): \Illuminate\Database\Eloquent\Collection
    {
        return StoreWorkingHour::where('store_id', $storeId)
            ->orderBy('day_of_week')
            ->get();
    }

    /**
     * Bulk-replace working hours for the store.
     * Expects an array of 7 entries: [{day_of_week, is_open, open_time, close_time, break_start?, break_end?}]
     */
    public function updateWorkingHours(string $storeId, array $days): \Illuminate\Database\Eloquent\Collection
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
     * Sets business_type and applies the template's default settings.
     */
    public function applyBusinessType(Store $store, string $businessTypeCode): Store
    {
        return DB::transaction(function () use ($store, $businessTypeCode) {
            $businessType = BusinessType::tryFrom($businessTypeCode);
            if (!$businessType) {
                throw new \InvalidArgumentException("Invalid business type: {$businessTypeCode}");
            }

            // Update store
            $store->update(['business_type' => $businessType]);

            // Find the template for defaults
            $template = \App\Domain\ProviderRegistration\Models\BusinessTypeTemplate::where('code', $businessTypeCode)->first();

            if ($template && !empty($template->template_json)) {
                $templateConfig = $template->template_json;

                // Apply template settings where applicable
                $settings = $this->getSettings($store->id);
                $settingsUpdates = [];

                if (isset($templateConfig['tax_rate'])) {
                    $settingsUpdates['tax_rate'] = $templateConfig['tax_rate'];
                }
                if (isset($templateConfig['prices_include_tax'])) {
                    $settingsUpdates['prices_include_tax'] = $templateConfig['prices_include_tax'];
                }
                if (isset($templateConfig['enable_kitchen_display'])) {
                    $settingsUpdates['enable_kitchen_display'] = $templateConfig['enable_kitchen_display'];
                }
                if (isset($templateConfig['enable_tips'])) {
                    $settingsUpdates['enable_tips'] = $templateConfig['enable_tips'];
                }
                if (isset($templateConfig['require_customer_for_sale'])) {
                    $settingsUpdates['require_customer_for_sale'] = $templateConfig['require_customer_for_sale'];
                }

                // Store remaining template config in extra
                $extraKeys = array_diff_key($templateConfig, array_flip(array_keys($settingsUpdates)));
                if (!empty($extraKeys)) {
                    $settingsUpdates['extra'] = array_merge($settings->extra ?? [], $extraKeys);
                }

                if (!empty($settingsUpdates)) {
                    $settings->update($settingsUpdates);
                }
            }

            return $store->fresh(['storeSettings', 'organization']);
        });
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
            0 => ['is_open' => true,  'open_time' => '09:00', 'close_time' => '22:00'], // Sunday
            1 => ['is_open' => true,  'open_time' => '09:00', 'close_time' => '22:00'], // Monday
            2 => ['is_open' => true,  'open_time' => '09:00', 'close_time' => '22:00'], // Tuesday
            3 => ['is_open' => true,  'open_time' => '09:00', 'close_time' => '22:00'], // Wednesday
            4 => ['is_open' => true,  'open_time' => '09:00', 'close_time' => '22:00'], // Thursday
            5 => ['is_open' => false, 'open_time' => null,    'close_time' => null],     // Friday (often off in ME)
            6 => ['is_open' => true,  'open_time' => '09:00', 'close_time' => '22:00'], // Saturday
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
