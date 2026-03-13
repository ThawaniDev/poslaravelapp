<?php

namespace App\Domain\AccountingIntegration\Services;

use App\Domain\AccountingIntegration\Enums\AccountingExportStatus;
use App\Domain\AccountingIntegration\Enums\AccountingProvider;
use App\Domain\AccountingIntegration\Models\AccountingExport;
use App\Domain\AccountingIntegration\Models\AccountMapping;
use App\Domain\AccountingIntegration\Models\AutoExportConfig;
use App\Domain\AccountingIntegration\Models\StoreAccountingConfig;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AccountingService
{
    // ─── Connection Status ───────────────────────────────

    /**
     * Get accounting connection status for a store.
     */
    public function getStatus(string $storeId): array
    {
        $config = StoreAccountingConfig::where('store_id', $storeId)->first();

        if (!$config) {
            return [
                'connected' => false,
                'provider' => null,
                'company_name' => null,
                'connected_at' => null,
                'last_sync_at' => null,
                'token_expires_at' => null,
                'health' => 'disconnected',
            ];
        }

        $health = 'healthy';
        if ($config->token_expires_at && $config->token_expires_at->isPast()) {
            $health = 'error';
        } elseif ($config->token_expires_at && $config->token_expires_at->lt(Carbon::now()->addHour())) {
            $health = 'warning';
        }

        return [
            'connected' => true,
            'provider' => $config->provider->value,
            'company_name' => $config->company_name,
            'connected_at' => $config->connected_at?->toIso8601String(),
            'last_sync_at' => $config->last_sync_at?->toIso8601String(),
            'token_expires_at' => $config->token_expires_at?->toIso8601String(),
            'health' => $health,
        ];
    }

    // ─── Connect / Disconnect ────────────────────────────

    /**
     * Connect a store to an accounting provider.
     * Returns the created config.
     */
    public function connect(string $storeId, array $data): StoreAccountingConfig
    {
        // Disconnect existing config if any
        StoreAccountingConfig::where('store_id', $storeId)->delete();

        return StoreAccountingConfig::create([
            'store_id' => $storeId,
            'provider' => $data['provider'],
            'access_token_encrypted' => $data['access_token'],
            'refresh_token_encrypted' => $data['refresh_token'],
            'token_expires_at' => $data['token_expires_at'],
            'realm_id' => $data['realm_id'] ?? null,
            'tenant_id' => $data['tenant_id'] ?? null,
            'company_name' => $data['company_name'] ?? null,
            'connected_at' => Carbon::now(),
        ]);
    }

    /**
     * Disconnect a store's accounting integration.
     */
    public function disconnect(string $storeId): bool
    {
        $deleted = StoreAccountingConfig::where('store_id', $storeId)->delete();
        return $deleted > 0;
    }

    /**
     * Refresh access token for a store's integration.
     */
    public function refreshToken(string $storeId, array $data): ?StoreAccountingConfig
    {
        $config = StoreAccountingConfig::where('store_id', $storeId)->first();

        if (!$config) {
            return null;
        }

        $config->update([
            'access_token_encrypted' => $data['access_token'],
            'refresh_token_encrypted' => $data['refresh_token'] ?? $config->refresh_token_encrypted,
            'token_expires_at' => $data['token_expires_at'],
        ]);

        return $config->fresh();
    }

    // ─── Account Mapping ─────────────────────────────────

    /**
     * Get all account mappings for a store.
     */
    public function getMappings(string $storeId): array
    {
        return AccountMapping::where('store_id', $storeId)
            ->orderBy('pos_account_key')
            ->get()
            ->toArray();
    }

    /**
     * Save or update account mappings for a store.
     * Expects an array of mappings, each with pos_account_key, provider_account_id, provider_account_name.
     */
    public function saveMappings(string $storeId, array $mappings): array
    {
        $results = [];

        foreach ($mappings as $mapping) {
            $results[] = AccountMapping::updateOrCreate(
                [
                    'store_id' => $storeId,
                    'pos_account_key' => $mapping['pos_account_key'],
                ],
                [
                    'provider_account_id' => $mapping['provider_account_id'],
                    'provider_account_name' => $mapping['provider_account_name'],
                ],
            );
        }

        return AccountMapping::where('store_id', $storeId)
            ->orderBy('pos_account_key')
            ->get()
            ->toArray();
    }

    /**
     * Delete a specific account mapping.
     */
    public function deleteMapping(string $storeId, string $mappingId): bool
    {
        $deleted = AccountMapping::where('id', $mappingId)
            ->where('store_id', $storeId)
            ->delete();

        return $deleted > 0;
    }

    // ─── Exports ─────────────────────────────────────────

    /**
     * Trigger a manual export for a store.
     */
    public function triggerExport(string $storeId, array $data): AccountingExport
    {
        $config = StoreAccountingConfig::where('store_id', $storeId)->first();

        return AccountingExport::create([
            'store_id' => $storeId,
            'provider' => $config ? $config->provider->value : ($data['provider'] ?? 'quickbooks'),
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'export_types' => $data['export_types'] ?? ['daily_summary'],
            'status' => AccountingExportStatus::Pending->value,
            'entries_count' => 0,
            'triggered_by' => 'manual',
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * List exports for a store with optional filters.
     */
    public function listExports(string $storeId, array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = AccountingExport::where('store_id', $storeId)
            ->orderByDesc('created_at');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $limit = min((int) ($filters['limit'] ?? 50), 200);

        return $query->limit($limit)->get();
    }

    /**
     * Get a single export by ID, scoped to store.
     */
    public function getExport(string $storeId, string $exportId): ?AccountingExport
    {
        return AccountingExport::where('id', $exportId)
            ->where('store_id', $storeId)
            ->first();
    }

    /**
     * Retry a failed export. Creates a new export with same params.
     */
    public function retryExport(string $storeId, string $exportId): ?AccountingExport
    {
        $original = AccountingExport::where('id', $exportId)
            ->where('store_id', $storeId)
            ->first();

        if (!$original) {
            return null;
        }

        if ($original->status !== AccountingExportStatus::Failed) {
            return null;
        }

        // Mark original as superseded (leave as failed, create new)
        return AccountingExport::create([
            'store_id' => $storeId,
            'provider' => $original->provider instanceof AccountingProvider
                ? $original->provider->value
                : $original->provider,
            'start_date' => $original->start_date,
            'end_date' => $original->end_date,
            'export_types' => $original->export_types,
            'status' => AccountingExportStatus::Pending->value,
            'entries_count' => 0,
            'triggered_by' => 'manual',
            'created_at' => Carbon::now(),
        ]);
    }

    // ─── Auto-Export Config ──────────────────────────────

    /**
     * Get auto-export configuration for a store.
     */
    public function getAutoExportConfig(string $storeId): array
    {
        $config = AutoExportConfig::where('store_id', $storeId)->first();

        if (!$config) {
            return [
                'store_id' => $storeId,
                'enabled' => false,
                'frequency' => 'daily',
                'day_of_week' => null,
                'day_of_month' => null,
                'time' => '23:00',
                'export_types' => ['daily_summary'],
                'notify_email' => null,
                'retry_on_failure' => true,
                'last_run_at' => null,
                'next_run_at' => null,
            ];
        }

        return [
            'store_id' => $config->store_id,
            'enabled' => (bool) $config->enabled,
            'frequency' => $config->frequency->value,
            'day_of_week' => $config->day_of_week,
            'day_of_month' => $config->day_of_month,
            'time' => $config->time,
            'export_types' => $config->export_types ?? ['daily_summary'],
            'notify_email' => $config->notify_email,
            'retry_on_failure' => (bool) $config->retry_on_failure,
            'last_run_at' => $config->last_run_at?->toIso8601String(),
            'next_run_at' => $config->next_run_at?->toIso8601String(),
        ];
    }

    /**
     * Update auto-export configuration for a store.
     */
    public function updateAutoExportConfig(string $storeId, array $data): array
    {
        $config = AutoExportConfig::updateOrCreate(
            ['store_id' => $storeId],
            [
                'enabled' => $data['enabled'] ?? false,
                'frequency' => $data['frequency'] ?? 'daily',
                'day_of_week' => $data['day_of_week'] ?? null,
                'day_of_month' => $data['day_of_month'] ?? null,
                'time' => $data['time'] ?? '23:00',
                'export_types' => $data['export_types'] ?? ['daily_summary'],
                'notify_email' => $data['notify_email'] ?? null,
                'retry_on_failure' => $data['retry_on_failure'] ?? true,
            ],
        );

        return $this->getAutoExportConfig($storeId);
    }

    // ─── POS Account Keys ────────────────────────────────

    /**
     * Return the list of all POS account keys available for mapping.
     */
    public static function posAccountKeys(): array
    {
        return [
            'sales_revenue' => ['label' => 'Sales Revenue', 'direction' => 'credit', 'required' => true],
            'cash_received' => ['label' => 'Cash Payments', 'direction' => 'debit', 'required' => true],
            'card_received' => ['label' => 'Card Payments', 'direction' => 'debit', 'required' => true],
            'store_credit_issued' => ['label' => 'Store Credit Issued', 'direction' => 'credit', 'required' => false],
            'store_credit_redeemed' => ['label' => 'Store Credit Redeemed', 'direction' => 'debit', 'required' => false],
            'gift_card_issued' => ['label' => 'Gift Card Issued', 'direction' => 'credit', 'required' => false],
            'gift_card_redeemed' => ['label' => 'Gift Card Redeemed', 'direction' => 'debit', 'required' => false],
            'vat_collected' => ['label' => 'VAT Collected', 'direction' => 'credit', 'required' => true],
            'discounts' => ['label' => 'Discounts Given', 'direction' => 'debit', 'required' => false],
            'refunds' => ['label' => 'Refunds', 'direction' => 'debit', 'required' => false],
            'cogs' => ['label' => 'Cost of Goods Sold', 'direction' => 'debit', 'required' => false],
            'staff_commissions' => ['label' => 'Staff Commissions', 'direction' => 'debit', 'required' => false],
            'tips_collected' => ['label' => 'Tips Collected', 'direction' => 'credit', 'required' => false],
        ];
    }
}
