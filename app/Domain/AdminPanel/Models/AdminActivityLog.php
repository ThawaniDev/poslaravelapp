<?php

namespace App\Domain\AdminPanel\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminActivityLog extends Model
{
    use HasUuids;

    protected $table = 'admin_activity_logs';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'admin_user_id',
        'action',
        'entity_type',
        'entity_id',
        'details',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    // ─── Relationships ───────────────────────────────────────

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class);
    }

    // ─── Scopes ──────────────────────────────────────────────

    public function scopeForAdmin(Builder $query, string $adminUserId): Builder
    {
        return $query->where('admin_user_id', $adminUserId);
    }

    public function scopeByAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    public function scopeByEntityType(Builder $query, string $entityType): Builder
    {
        return $query->where('entity_type', $entityType);
    }

    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // ─── Factory ─────────────────────────────────────────────

    public static function record(
        string $adminUserId,
        string $action,
        ?string $entityType = null,
        ?string $entityId = null,
        ?array $details = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): static {
        // entity_id is uuid in DB — pass null for non-UUID identifiers and store in details instead
        $isValidUuid = $entityId && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $entityId);

        if ($entityId && ! $isValidUuid) {
            $details = array_merge($details ?? [], ['entity_ref' => $entityId]);
            $entityId = null;
        }

        return static::create([
            'admin_user_id' => $adminUserId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details,
            'ip_address' => $ipAddress ?? request()?->ip() ?? '0.0.0.0',
            'user_agent' => $userAgent ?? request()?->userAgent() ?? '',
            'created_at' => now(),
        ]);
    }
}
