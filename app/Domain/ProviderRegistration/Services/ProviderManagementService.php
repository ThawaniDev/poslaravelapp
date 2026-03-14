<?php

namespace App\Domain\ProviderRegistration\Services;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderRegistration\Enums\ProviderRegistrationStatus;
use App\Domain\ProviderRegistration\Models\ProviderNote;
use App\Domain\ProviderRegistration\Models\ProviderRegistration;
use App\Domain\ProviderSubscription\Models\ProviderLimitOverride;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProviderManagementService
{
    // ─── Store Listing ───────────────────────────────────────────

    /**
     * List stores with filters, search, and pagination.
     */
    public function listStores(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Store::with(['organization']);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('organization', function ($oq) use ($search) {
                        $oq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (!empty($filters['business_type'])) {
            $query->where('business_type', $filters['business_type']);
        }

        if (!empty($filters['organization_id'])) {
            $query->where('organization_id', $filters['organization_id']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    // ─── Store Detail ────────────────────────────────────────────

    /**
     * Get a single store with all related platform data.
     */
    public function getStoreDetail(string $storeId): ?Store
    {
        return Store::with(['organization'])
            ->find($storeId);
    }

    // ─── Store Metrics ───────────────────────────────────────────

    /**
     * Get live usage metrics for a store.
     */
    public function getStoreMetrics(string $storeId): array
    {
        $store = Store::find($storeId);
        if (!$store) {
            return [];
        }

        $subscription = StoreSubscription::where('store_id', $storeId)->first();
        $overrides = ProviderLimitOverride::where('store_id', $storeId)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get();
        $notes = ProviderNote::where('organization_id', $store->organization_id)->count();

        return [
            'store_id' => $storeId,
            'store_name' => $store->name,
            'is_active' => $store->is_active,
            'subscription' => $subscription ? [
                'plan_id' => $subscription->subscription_plan_id,
                'status' => $subscription->status,
                'billing_cycle' => $subscription->billing_cycle,
                'current_period_end' => $subscription->current_period_end?->toIso8601String(),
            ] : null,
            'active_overrides' => $overrides->count(),
            'internal_notes_count' => $notes,
        ];
    }

    // ─── Suspend / Activate ──────────────────────────────────────

    /**
     * Suspend a store (cascade: deactivate POS login).
     */
    public function suspendStore(string $storeId, string $adminUserId, ?string $reason = null): Store
    {
        $store = Store::findOrFail($storeId);

        $store->update(['is_active' => false]);

        $this->logActivity($adminUserId, 'store.suspend', 'store', $storeId, [
            'reason' => $reason,
            'previous_status' => true,
        ]);

        return $store->refresh();
    }

    /**
     * Activate a suspended store.
     */
    public function activateStore(string $storeId, string $adminUserId): Store
    {
        $store = Store::findOrFail($storeId);

        $store->update(['is_active' => true]);

        $this->logActivity($adminUserId, 'store.activate', 'store', $storeId, [
            'previous_status' => false,
        ]);

        return $store->refresh();
    }

    // ─── Registration Queue ──────────────────────────────────────

    /**
     * List provider registrations with filters.
     */
    public function listRegistrations(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ProviderRegistration::query();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('organization_name', 'like', "%{$search}%")
                    ->orWhere('owner_name', 'like', "%{$search}%")
                    ->orWhere('owner_email', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Approve a pending provider registration.
     */
    public function approveRegistration(string $registrationId, string $adminUserId): ProviderRegistration
    {
        $registration = ProviderRegistration::findOrFail($registrationId);

        if ($registration->status !== ProviderRegistrationStatus::Pending) {
            throw new \InvalidArgumentException('Only pending registrations can be approved.');
        }

        $registration->update([
            'status' => ProviderRegistrationStatus::Approved,
            'reviewed_by' => $adminUserId,
            'reviewed_at' => now(),
        ]);

        $this->logActivity($adminUserId, 'registration.approve', 'provider_registration', $registrationId, [
            'organization_name' => $registration->organization_name,
        ]);

        return $registration->refresh();
    }

    /**
     * Reject a pending provider registration.
     */
    public function rejectRegistration(
        string $registrationId,
        string $adminUserId,
        string $rejectionReason
    ): ProviderRegistration {
        $registration = ProviderRegistration::findOrFail($registrationId);

        if ($registration->status !== ProviderRegistrationStatus::Pending) {
            throw new \InvalidArgumentException('Only pending registrations can be rejected.');
        }

        $registration->update([
            'status' => ProviderRegistrationStatus::Rejected,
            'reviewed_by' => $adminUserId,
            'reviewed_at' => now(),
            'rejection_reason' => $rejectionReason,
        ]);

        $this->logActivity($adminUserId, 'registration.reject', 'provider_registration', $registrationId, [
            'organization_name' => $registration->organization_name,
            'rejection_reason' => $rejectionReason,
        ]);

        return $registration->refresh();
    }

    // ─── Internal Notes ──────────────────────────────────────────

    /**
     * Add an internal note on a provider/organization.
     */
    public function addNote(string $organizationId, string $adminUserId, string $noteText): ProviderNote
    {
        $note = ProviderNote::create([
            'organization_id' => $organizationId,
            'admin_user_id' => $adminUserId,
            'note_text' => $noteText,
            'created_at' => now(),
        ]);

        $this->logActivity($adminUserId, 'provider_note.create', 'provider_note', $note->id, [
            'organization_id' => $organizationId,
        ]);

        return $note;
    }

    /**
     * List notes for an organization.
     */
    public function listNotes(string $organizationId): Collection
    {
        return ProviderNote::where('organization_id', $organizationId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    // ─── Limit Overrides ─────────────────────────────────────────

    /**
     * Set or update a limit override for a store.
     */
    public function setLimitOverride(
        string $storeId,
        string $limitKey,
        int $overrideValue,
        string $adminUserId,
        ?string $reason = null,
        ?string $expiresAt = null
    ): ProviderLimitOverride {
        $override = ProviderLimitOverride::updateOrCreate(
            ['store_id' => $storeId, 'limit_key' => $limitKey],
            [
                'override_value' => $overrideValue,
                'reason' => $reason,
                'set_by' => $adminUserId,
                'expires_at' => $expiresAt,
                'created_at' => now(),
            ]
        );

        $this->logActivity($adminUserId, 'limit_override.set', 'provider_limit_override', $override->id, [
            'store_id' => $storeId,
            'limit_key' => $limitKey,
            'override_value' => $overrideValue,
        ]);

        return $override;
    }

    /**
     * Remove a limit override for a store.
     */
    public function removeLimitOverride(string $storeId, string $limitKey, string $adminUserId): bool
    {
        $override = ProviderLimitOverride::where('store_id', $storeId)
            ->where('limit_key', $limitKey)
            ->first();

        if (!$override) {
            return false;
        }

        $this->logActivity($adminUserId, 'limit_override.remove', 'provider_limit_override', $override->id, [
            'store_id' => $storeId,
            'limit_key' => $limitKey,
        ]);

        $override->delete();

        return true;
    }

    /**
     * List active limit overrides for a store.
     */
    public function listLimitOverrides(string $storeId): Collection
    {
        return ProviderLimitOverride::where('store_id', $storeId)
            ->orderBy('limit_key')
            ->get();
    }

    // ─── Data Export ─────────────────────────────────────────────

    /**
     * Export store data as an array (for CSV/Excel generation).
     */
    public function exportStoreData(array $filters = []): array
    {
        $query = Store::with(['organization']);

        if (!empty($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (!empty($filters['business_type'])) {
            $query->where('business_type', $filters['business_type']);
        }

        $stores = $query->orderBy('created_at', 'desc')->get();

        return $stores->map(function ($store) {
            return [
                'id' => $store->id,
                'name' => $store->name,
                'organization' => $store->organization?->name,
                'business_type' => $store->business_type,
                'is_active' => $store->is_active ? 'Yes' : 'No',
                'currency' => $store->currency,
                'created_at' => $store->created_at?->toDateTimeString(),
            ];
        })->toArray();
    }

    // ─── Manual Onboarding ───────────────────────────────────────

    /**
     * Create a store via manual admin onboarding (with organization).
     */
    public function createStoreManually(
        array $orgData,
        array $storeData,
        string $adminUserId
    ): array {
        return DB::transaction(function () use ($orgData, $storeData, $adminUserId) {
            $org = Organization::create([
                'name' => $orgData['name'],
                'business_type' => $orgData['business_type'] ?? 'retail',
                'country' => $orgData['country'] ?? 'OM',
            ]);

            $store = Store::create([
                'organization_id' => $org->id,
                'name' => $storeData['name'],
                'business_type' => $storeData['business_type'] ?? $org->business_type ?? 'retail',
                'currency' => $storeData['currency'] ?? 'OMR',
                'is_active' => $storeData['is_active'] ?? true,
                'is_main_branch' => true,
            ]);

            $this->logActivity($adminUserId, 'store.create_manual', 'store', $store->id, [
                'organization_id' => $org->id,
                'store_name' => $store->name,
            ]);

            return [
                'organization' => $org,
                'store' => $store,
            ];
        });
    }

    // ─── Activity Logging ────────────────────────────────────────

    /**
     * Log an admin activity.
     */
    public function logActivity(
        string $adminUserId,
        string $action,
        ?string $entityType = null,
        ?string $entityId = null,
        ?array $details = null
    ): AdminActivityLog {
        return AdminActivityLog::create([
            'admin_user_id' => $adminUserId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details,
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
