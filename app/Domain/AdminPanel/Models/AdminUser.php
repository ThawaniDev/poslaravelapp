<?php

namespace App\Domain\AdminPanel\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class AdminUser extends Authenticatable implements FilamentUser
{
    use HasUuids;

    /**
     * Determine if the user can access the given Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
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
