<?php

namespace App\Domain\AdminPanel\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class AdminUser extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasUuids;

    /** @var bool|null Cached super-admin flag for this request */
    protected ?bool $cachedIsSuperAdmin = null;

    /** @var \Illuminate\Support\Collection|null Cached permission names for this request */
    protected ?\Illuminate\Support\Collection $cachedPermissions = null;

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }

    // ── Permission helpers ───────────────────────────────────────────

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            AdminRole::class,
            'admin_user_roles',
            'admin_user_id',
            'admin_role_id',
        );
    }

    public function isSuperAdmin(): bool
    {
        if ($this->cachedIsSuperAdmin === null) {
            $this->cachedIsSuperAdmin = Cache::remember(
                "admin_user:{$this->id}:is_super_admin",
                300, // 5 minutes
                fn () => $this->roles()->where('slug', 'super_admin')->exists(),
            );
        }

        return $this->cachedIsSuperAdmin;
    }

    /**
     * Load all permission names for this user (cached 5 minutes).
     */
    protected function loadPermissions(): \Illuminate\Support\Collection
    {
        if ($this->cachedPermissions === null) {
            $permissions = Cache::remember(
                "admin_user:{$this->id}:permissions",
                300, // 5 minutes
                function () {
                    $roleIds = $this->roles()->pluck('admin_roles.id');

                    return DB::table('admin_role_permissions')
                        ->join('admin_permissions', 'admin_role_permissions.admin_permission_id', '=', 'admin_permissions.id')
                        ->whereIn('admin_role_permissions.admin_role_id', $roleIds)
                        ->pluck('admin_permissions.name')
                        ->all();
                },
            );

            $this->cachedPermissions = collect($permissions);
        }

        return $this->cachedPermissions;
    }

    public function hasPermission(string $permissionName): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->loadPermissions()->contains($permissionName);
    }

    /**
     * Alias for hasPermission() to match the Spatie/Filament conventional name
     * used throughout the admin panel resources.
     */
    public function hasPermissionTo(string $permissionName, ?string $guardName = null): bool
    {
        return $this->hasPermission($permissionName);
    }

    public function hasAnyPermission(array $permissionNames): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->loadPermissions()->intersect($permissionNames)->isNotEmpty();
    }

    protected $table = 'admin_users';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'email',
        'password_hash',
        'phone',
        'avatar_url',
        'is_active',
        'two_factor_secret',
        'two_factor_enabled',
        'two_factor_confirmed_at',
        'last_login_at',
        'last_login_ip',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'two_factor_enabled' => 'boolean',
        'two_factor_confirmed_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    protected $hidden = [
        'password_hash',
        'two_factor_secret',
    ];

    /**
     * Override the default 'password' column for Laravel auth.
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function adminUserRoles(): HasMany
    {
        return $this->hasMany(AdminUserRole::class);
    }
    public function adminUserRolesViaAssignedBy(): HasMany
    {
        return $this->hasMany(AdminUserRole::class, 'assigned_by');
    }
    public function adminActivityLogs(): HasMany
    {
        return $this->hasMany(AdminActivityLog::class);
    }
    public function systemSettings(): HasMany
    {
        return $this->hasMany(SystemSetting::class, 'updated_by');
    }
    public function translationVersions(): HasMany
    {
        return $this->hasMany(TranslationVersion::class, 'published_by');
    }
    public function adminIpAllowlist(): HasMany
    {
        return $this->hasMany(AdminIpAllowlist::class, 'added_by');
    }
    public function adminIpBlocklist(): HasMany
    {
        return $this->hasMany(AdminIpBlocklist::class, 'blocked_by');
    }
    public function adminTrustedDevices(): HasMany
    {
        return $this->hasMany(AdminTrustedDevice::class);
    }
    public function adminSessions(): HasMany
    {
        return $this->hasMany(AdminSession::class);
    }
    public function securityAlerts(): HasMany
    {
        return $this->hasMany(SecurityAlert::class);
    }
    public function securityAlertsViaResolvedBy(): HasMany
    {
        return $this->hasMany(SecurityAlert::class, 'resolved_by');
    }
    public function subscriptionCredits(): HasMany
    {
        return $this->hasMany(SubscriptionCredit::class, 'applied_by');
    }
    public function providerRegistrations(): HasMany
    {
        return $this->hasMany(ProviderRegistration::class, 'reviewed_by');
    }
    public function providerNotes(): HasMany
    {
        return $this->hasMany(ProviderNote::class);
    }
    public function providerLimitOverrides(): HasMany
    {
        return $this->hasMany(ProviderLimitOverride::class, 'set_by');
    }
    public function platformAnnouncements(): HasMany
    {
        return $this->hasMany(PlatformAnnouncement::class, 'created_by');
    }
    public function hardwareSales(): HasMany
    {
        return $this->hasMany(HardwareSale::class, 'sold_by');
    }
    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'assigned_to');
    }
    public function cannedResponses(): HasMany
    {
        return $this->hasMany(CannedResponse::class, 'created_by');
    }
}
