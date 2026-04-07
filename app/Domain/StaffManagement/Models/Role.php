<?php

namespace App\Domain\StaffManagement\Models;

use App\Domain\Core\Models\Store;
use App\Domain\Security\Models\RoleAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $table = 'roles';
    protected $guard_name = 'staff';

    protected $fillable = [
        'store_id',
        'name',
        'display_name',
        'display_name_ar',
        'guard_name',
        'is_predefined',
        'scope',
        'description',
        'description_ar',
    ];

    protected $casts = [
        'is_predefined' => 'boolean',
    ];

    /**
     * Whether this role is scoped to the organization (cross-branch).
     */
    public function isOrganizationScoped(): bool
    {
        return ($this->scope ?? 'branch') === 'organization';
    }

    /**
     * Whether this role is scoped to a single branch.
     */
    public function isBranchScoped(): bool
    {
        return ($this->scope ?? 'branch') === 'branch';
    }

    // ─── Relationships ───────────────────────────────────────────

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'role_has_permissions',
            'role_id',
            'permission_id',
        );
    }

    public function roleHasPermissions(): HasMany
    {
        return $this->hasMany(RoleHasPermission::class);
    }

    public function modelHasRoles(): HasMany
    {
        return $this->hasMany(ModelHasRole::class);
    }

    public function roleAuditLog(): HasMany
    {
        return $this->hasMany(RoleAuditLog::class);
    }

    public function staffBranchAssignments(): HasMany
    {
        return $this->hasMany(StaffBranchAssignment::class);
    }

    // ─── Scopes ──────────────────────────────────────────────────

    public function scopeForStore($query, string $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function scopePredefined($query)
    {
        return $query->where('is_predefined', true);
    }

    public function scopeCustom($query)
    {
        return $query->where('is_predefined', false);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function hasPermission(string $permissionName): bool
    {
        return $this->permissions()->where('name', $permissionName)->exists();
    }

    public function isPredefined(): bool
    {
        return $this->is_predefined === true;
    }
}
