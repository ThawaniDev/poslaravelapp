<?php

namespace App\Domain\ProviderRegistration\Services;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Auth\Enums\UserRole;
use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Register;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderRegistration\Enums\ProviderRegistrationStatus;
use App\Domain\ProviderRegistration\Models\ImpersonationSession;
use App\Domain\ProviderRegistration\Models\ProviderNote;
use App\Domain\ProviderRegistration\Models\ProviderRegistration;
use App\Domain\ProviderSubscription\Models\ProviderLimitOverride;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%")
                    ->orWhereHas('organization', function ($oq) use ($search) {
                        $oq->where('name', 'ilike', "%{$search}%")
                            ->orWhere('cr_number', 'ilike', "%{$search}%")
                            ->orWhere('vat_number', 'ilike', "%{$search}%");
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
        return Store::with(['organization', 'registers'])
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

        $orgId = $store->organization_id;

        $subscription = StoreSubscription::with('subscriptionPlan')
            ->where('organization_id', $orgId)->first();
        $overrides = ProviderLimitOverride::where('organization_id', $orgId)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })->get();
        $notesCount = ProviderNote::where('organization_id', $orgId)->count();

        $storeIds = Store::where('organization_id', $orgId)->pluck('id');
        $staffCount = User::whereIn('store_id', $storeIds)->count();
        $productsCount = DB::table('products')->where('organization_id', $orgId)->count();
        $recentOrders = DB::table('orders')
            ->whereIn('store_id', $storeIds)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        $registers = Register::whereIn('store_id', $storeIds)
            ->select(['id', 'store_id', 'name', 'device_id', 'app_version', 'platform', 'last_sync_at', 'is_online', 'is_active'])
            ->orderBy('last_sync_at', 'desc')->get();
        $deliveryPlatforms = DB::table('store_delivery_platforms')
            ->whereIn('store_id', $storeIds)
            ->select(['id', 'store_id', 'delivery_platform_id', 'is_enabled', 'last_sync_at'])
            ->get();
        $branches = Store::where('organization_id', $orgId)
            ->select(['id', 'name', 'is_active', 'is_main_branch', 'city', 'created_at'])->get();

        return [
            'store_id'   => $storeId,
            'store_name' => $store->name,
            'is_active'  => $store->is_active,
            'organization' => [
                'id'         => $store->organization?->id,
                'name'       => $store->organization?->name,
                'cr_number'  => $store->organization?->cr_number,
                'vat_number' => $store->organization?->vat_number,
            ],
            'subscription' => $subscription ? [
                'plan_id'              => $subscription->subscription_plan_id,
                'plan_name'            => $subscription->subscriptionPlan?->name,
                'status'               => $subscription->status instanceof \BackedEnum ? $subscription->status->value : $subscription->status,
                'billing_cycle'        => $subscription->billing_cycle instanceof \BackedEnum ? $subscription->billing_cycle->value : $subscription->billing_cycle,
                'current_period_start' => $subscription->current_period_start?->toIso8601String(),
                'current_period_end'   => $subscription->current_period_end?->toIso8601String(),
                'trial_ends_at'        => $subscription->trial_ends_at?->toIso8601String(),
                'payment_method'       => $subscription->payment_method instanceof \BackedEnum ? $subscription->payment_method->value : $subscription->payment_method,
                'cancelled_at'         => $subscription->cancelled_at?->toIso8601String(),
            ] : null,
            'active_overrides'     => $overrides->count(),
            'limit_overrides'      => $overrides->map(fn ($o) => [
                'limit_key'      => $o->limit_key,
                'override_value' => $o->override_value,
                'expires_at'     => $o->expires_at?->toIso8601String(),
            ])->values(),
            'internal_notes_count' => $notesCount,
            'usage' => [
                'staff_count'              => $staffCount,
                'products_count'           => $productsCount,
                'registers_count'          => $registers->count(),
                'branches_count'           => $branches->count(),
                'delivery_platforms_count' => $deliveryPlatforms->count(),
                'recent_orders_30d'        => $recentOrders,
            ],
            'registers' => $registers->map(fn ($r) => [
                'id'           => $r->id,
                'name'         => $r->name,
                'device_id'    => $r->device_id,
                'app_version'  => $r->app_version,
                'platform'     => $r->platform,
                'last_sync_at' => $r->last_sync_at,
                'is_online'    => $r->is_online,
                'is_active'    => $r->is_active,
            ])->values(),
            'delivery_platforms' => $deliveryPlatforms->map(fn ($dp) => [
                'id'                   => $dp->id,
                'delivery_platform_id' => $dp->delivery_platform_id,
                'is_enabled'           => $dp->is_enabled,
                'last_sync_at'         => $dp->last_sync_at,
            ])->values(),
            'branches' => $branches->map(fn ($b) => [
                'id'             => $b->id,
                'name'           => $b->name,
                'is_active'      => $b->is_active,
                'is_main_branch' => $b->is_main_branch,
                'city'           => $b->city,
                'created_at'     => $b->created_at?->toIso8601String(),
            ])->values(),
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

        return $store->refresh()->load('organization');
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

        return $store->refresh()->load('organization');
    }

    // ─── Impersonation ───────────────────────────────────────────

    public function startImpersonation(string $storeId, string $adminUserId, string $ip, string $userAgent): array
    {
        $store = Store::with(['organization'])->findOrFail($storeId);
        $ownerUser = User::where('store_id', $storeId)->where('role', UserRole::Owner->value)->first();
        if (!$ownerUser) {
            $ownerUser = User::where('organization_id', $store->organization_id)->where('role', UserRole::Owner->value)->first();
        }
        if (!$ownerUser) {
            throw new \RuntimeException('No owner user found for this store.');
        }
        // End any active sessions for this admin/store pair
        ImpersonationSession::where('admin_user_id', $adminUserId)
            ->where('store_id', $storeId)
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);
        $session = ImpersonationSession::create([
            'admin_user_id'  => $adminUserId,
            'target_user_id' => $ownerUser->id,
            'store_id'       => $storeId,
            'token'          => ImpersonationSession::generateToken(),
            'ip_address'     => $ip,
            'user_agent'     => $userAgent,
            'started_at'     => now(),
            'expires_at'     => now()->addMinutes(30),
            'created_at'     => now(),
        ]);
        $this->logActivity($adminUserId, 'store.impersonate.start', 'store', $storeId, [
            'target_user_id' => $ownerUser->id,
            'target_email'   => $ownerUser->email,
            'session_id'     => $session->id,
        ]);
        return [
            'session_id'        => $session->id,
            'token'             => $session->token,
            'expires_at'        => $session->expires_at->toIso8601String(),
            'target_user'       => [
                'id'    => $ownerUser->id,
                'name'  => $ownerUser->name,
                'email' => $ownerUser->email,
                'role'  => $ownerUser->role instanceof \BackedEnum ? $ownerUser->role->value : $ownerUser->role,
            ],
            'store_name'        => $store->name,
            'organization_name' => $store->organization?->name,
        ];
    }

    public function endImpersonation(string $token, string $adminUserId): bool
    {
        $session = ImpersonationSession::where('token', $token)
            ->where('admin_user_id', $adminUserId)
            ->whereNull('ended_at')
            ->first();
        if (!$session) return false;
        $session->update(['ended_at' => now()]);
        $this->logActivity($adminUserId, 'store.impersonate.end', 'store', $session->store_id, [
            'session_id'       => $session->id,
            'target_user_id'   => $session->target_user_id,
            'duration_seconds' => $session->started_at->diffInSeconds(now()),
        ]);
        return true;
    }

    public function extendImpersonation(string $token, string $adminUserId): ?ImpersonationSession
    {
        $session = ImpersonationSession::where('token', $token)
            ->where('admin_user_id', $adminUserId)
            ->whereNull('ended_at')
            ->where('expires_at', '>', now())
            ->first();
        if (!$session) return null;
        $session->update(['expires_at' => now()->addMinutes(30)]);
        $this->logActivity($adminUserId, 'store.impersonate.extend', 'store', $session->store_id, [
            'session_id' => $session->id,
        ]);
        return $session;
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
    public function approveRegistration(string $registrationId, string $adminUserId): array
    {
        $registration = ProviderRegistration::findOrFail($registrationId);

        if ($registration->status !== ProviderRegistrationStatus::Pending) {
            throw new \InvalidArgumentException('Only pending registrations can be approved.');
        }

        return DB::transaction(function () use ($registration, $adminUserId) {
            $org = Organization::create([
                'name'          => $registration->organization_name,
                'name_ar'       => $registration->organization_name_ar ?? $registration->organization_name,
                'slug'          => Str::slug($registration->organization_name) . '-' . Str::random(6),
                'cr_number'     => $registration->cr_number,
                'vat_number'    => $registration->vat_number,
                'business_type' => $registration->business_type_id ?? 'grocery',
                'is_active'     => true,
            ]);
            $store = Store::create([
                'organization_id' => $org->id,
                'name'            => $registration->organization_name,
                'name_ar'         => $registration->organization_name_ar ?? $registration->organization_name,
                'slug'            => Str::slug($registration->organization_name) . '-store-' . Str::random(6),
                'business_type'   => $registration->business_type_id ?? 'grocery',
                'currency'        => 'SAR',
                'is_active'       => true,
                'is_main_branch'  => true,
                'phone'           => $registration->owner_phone,
            ]);
            $tempPassword = Str::random(12);
            $user = User::create([
                'store_id'             => $store->id,
                'organization_id'      => $org->id,
                'name'                 => $registration->owner_name,
                'email'                => $registration->owner_email,
                'phone'                => $registration->owner_phone,
                'password_hash'        => Hash::make($tempPassword),
                'role'                 => UserRole::Owner->value,
                'is_active'            => true,
                'must_change_password' => true,
            ]);
            $registration->update([
                'status'      => ProviderRegistrationStatus::Approved,
                'reviewed_by' => $adminUserId,
                'reviewed_at' => now(),
            ]);
            $this->logActivity($adminUserId, 'registration.approve', 'provider_registration', $registration->id, [
                'organization_name' => $registration->organization_name,
                'organization_id'   => $org->id,
                'store_id'          => $store->id,
                'user_id'           => $user->id,
            ]);
            return [
                'registration'  => $registration->refresh(),
                'organization'  => $org,
                'store'         => $store,
                'user'          => $user,
                'temp_password' => $tempPassword,
            ];
        });
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

        return $note->load('adminUser');
    }

    /**
     * List notes for an organization.
     */
    public function listNotes(string $organizationId): Collection
    {
        return ProviderNote::with('adminUser')
            ->where('organization_id', $organizationId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    // ─── Limit Overrides ─────────────────────────────────────────

    /**
     * Set or update a limit override for an organization.
     */
    public function setLimitOverride(
        string $organizationId,
        string $limitKey,
        int $overrideValue,
        string $adminUserId,
        ?string $reason = null,
        ?string $expiresAt = null
    ): ProviderLimitOverride {
        $override = ProviderLimitOverride::updateOrCreate(
            ['organization_id' => $organizationId, 'limit_key' => $limitKey],
            [
                'override_value' => $overrideValue,
                'reason' => $reason,
                'set_by' => $adminUserId,
                'expires_at' => $expiresAt,
                'created_at' => now(),
            ]
        );

        $this->logActivity($adminUserId, 'limit_override.set', 'provider_limit_override', $override->id, [
            'organization_id' => $organizationId,
            'limit_key' => $limitKey,
            'override_value' => $overrideValue,
        ]);

        return $override;
    }

    /**
     * Remove a limit override for an organization.
     */
    public function removeLimitOverride(string $organizationId, string $limitKey, string $adminUserId): bool
    {
        $override = ProviderLimitOverride::where('organization_id', $organizationId)
            ->where('limit_key', $limitKey)
            ->first();

        if (!$override) {
            return false;
        }

        $this->logActivity($adminUserId, 'limit_override.remove', 'provider_limit_override', $override->id, [
            'organization_id' => $organizationId,
            'limit_key' => $limitKey,
        ]);

        $override->delete();

        return true;
    }

    /**
     * List active limit overrides for an organization.
     */
    public function listLimitOverrides(string $organizationId): Collection
    {
        return ProviderLimitOverride::with('setBy')
            ->where('organization_id', $organizationId)
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
                'name'          => $orgData['name'],
                'name_ar'       => $orgData['name_ar'] ?? $orgData['name'],
                'slug'          => Str::slug($orgData['name']) . '-' . Str::random(6),
                'cr_number'     => $orgData['cr_number'] ?? null,
                'vat_number'    => $orgData['vat_number'] ?? null,
                'business_type' => $orgData['business_type'] ?? 'grocery',
                'country'       => $orgData['country'] ?? 'SA',
                'is_active'     => true,
            ]);

            $store = Store::create([
                'organization_id' => $org->id,
                'name'            => $storeData['name'],
                'name_ar'         => $storeData['name_ar'] ?? $storeData['name'],
                'slug'            => Str::slug($storeData['name']) . '-' . Str::random(6),
                'business_type'   => $storeData['business_type'] ?? $org->business_type,
                'currency'        => $storeData['currency'] ?? 'SAR',
                'is_active'       => $storeData['is_active'] ?? true,
                'is_main_branch'  => true,
                'phone'           => $storeData['phone'] ?? null,
                'email'           => $storeData['email'] ?? null,
            ]);

            $user = null;
            $tempPassword = null;
            if (!empty($storeData['owner_name']) && !empty($storeData['owner_email'])) {
                $tempPassword = Str::random(12);
                $user = User::create([
                    'store_id'             => $store->id,
                    'organization_id'      => $org->id,
                    'name'                 => $storeData['owner_name'],
                    'email'                => $storeData['owner_email'],
                    'phone'                => $storeData['owner_phone'] ?? null,
                    'password_hash'        => Hash::make($tempPassword),
                    'role'                 => UserRole::Owner->value,
                    'is_active'            => true,
                    'must_change_password' => true,
                ]);
            }

            $this->logActivity($adminUserId, 'store.create_manual', 'store', $store->id, [
                'organization_id' => $org->id,
                'store_name'      => $store->name,
                'owner_email'     => $user?->email,
            ]);

            return [
                'organization'  => $org,
                'store'         => $store,
                'user'          => $user,
                'temp_password' => $tempPassword,
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
