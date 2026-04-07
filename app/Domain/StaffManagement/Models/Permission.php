<?php

namespace App\Domain\StaffManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Permission extends Model
{
    protected $table = 'permissions';

    protected $fillable = [
        'name',
        'display_name',
        'display_name_ar',
        'module',
        'guard_name',
        'requires_pin',
        'description',
        'description_ar',
        'sort_order',
    ];

    protected $casts = [
        'requires_pin' => 'boolean',
    ];

    // ─── Relationships ───────────────────────────────────────────

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'role_has_permissions',
            'permission_id',
            'role_id',
        );
    }

    public function roleHasPermissions(): HasMany
    {
        return $this->hasMany(RoleHasPermission::class);
    }

    // ─── Scopes ──────────────────────────────────────────────────

    public function scopeForModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    public function scopePinProtected($query)
    {
        return $query->where('requires_pin', true);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function requiresPin(): bool
    {
        return $this->requires_pin === true;
    }
}
