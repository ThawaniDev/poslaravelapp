<?php

namespace App\Domain\DeliveryIntegration\Models;

use App\Domain\DeliveryIntegration\Enums\DeliveryConfigPlatform;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryPlatformConfig extends Model
{
    use HasUuids;

    protected $table = 'delivery_platform_configs';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'platform',
        'api_key',
        'merchant_id',
        'webhook_secret',
        'branch_id_on_platform',
        'is_enabled',
        'auto_accept',
        'throttle_limit',
        'max_daily_orders',
        'last_menu_sync_at',
        'operating_hours_synced',
        'last_order_received_at',
        'daily_order_count',
        'sync_menu_on_product_change',
        'menu_sync_interval_hours',
        'webhook_url',
        'status',
    ];

    protected $casts = [
        'platform' => DeliveryConfigPlatform::class,
        'is_enabled' => 'boolean',
        'auto_accept' => 'boolean',
        'operating_hours_synced' => 'boolean',
        'sync_menu_on_product_change' => 'boolean',
        'last_menu_sync_at' => 'datetime',
        'last_order_received_at' => 'datetime',
    ];

    protected $hidden = ['api_key', 'webhook_secret'];

    // ── Relationships ──────────────────────────────────────────

    public function store(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Core\Models\Store::class);
    }

    public function menuSyncLogs(): HasMany
    {
        return $this->hasMany(DeliveryMenuSyncLog::class, 'store_id', 'store_id')
            ->where('platform', $this->platform);
    }

    // ── Scopes ─────────────────────────────────────────────────

    public function scopeForStore($query, string $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    // ── Helpers ─────────────────────────────────────────────────

    public function isThrottled(): bool
    {
        if (! $this->throttle_limit) {
            return false;
        }

        $recentCount = DeliveryOrderMapping::where('store_id', $this->store_id)
            ->where('platform', $this->platform->value)
            ->where('created_at', '>=', now()->subMinutes(15))
            ->count();

        return $recentCount >= $this->throttle_limit;
    }

    public function isDailyLimitReached(): bool
    {
        if (! $this->max_daily_orders) {
            return false;
        }

        return $this->daily_order_count >= $this->max_daily_orders;
    }

    public function incrementDailyOrderCount(): void
    {
        $this->increment('daily_order_count');
        $this->update(['last_order_received_at' => now()]);
    }
}
