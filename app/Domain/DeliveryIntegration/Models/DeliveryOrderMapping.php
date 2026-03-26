<?php

namespace App\Domain\DeliveryIntegration\Models;

use App\Domain\DeliveryIntegration\Enums\DeliveryConfigPlatform;
use App\Domain\DeliveryIntegration\Enums\DeliveryOrderStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryOrderMapping extends Model
{
    use HasUuids;

    protected $table = 'delivery_order_mappings';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'order_id',
        'platform',
        'external_order_id',
        'external_status',
        'delivery_status',
        'customer_name',
        'customer_phone',
        'delivery_address',
        'delivery_fee',
        'subtotal',
        'total_amount',
        'items_count',
        'commission_amount',
        'commission_percent',
        'raw_payload',
        'rejection_reason',
        'accepted_at',
        'ready_at',
        'dispatched_at',
        'delivered_at',
        'estimated_prep_minutes',
        'notes',
    ];

    protected $casts = [
        'platform' => DeliveryConfigPlatform::class,
        'delivery_status' => DeliveryOrderStatus::class,
        'raw_payload' => 'array',
        'commission_amount' => 'decimal:2',
        'commission_percent' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'accepted_at' => 'datetime',
        'ready_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────

    public function order(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Order\Models\Order::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Core\Models\Store::class);
    }

    public function statusPushLogs(): HasMany
    {
        return $this->hasMany(DeliveryStatusPushLog::class);
    }

    // ── Scopes ─────────────────────────────────────────────────

    public function scopeForStore($query, string $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function scopePending($query)
    {
        return $query->where('delivery_status', DeliveryOrderStatus::Pending);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('delivery_status', [
            DeliveryOrderStatus::Delivered->value,
            DeliveryOrderStatus::Rejected->value,
            DeliveryOrderStatus::Cancelled->value,
            DeliveryOrderStatus::Failed->value,
        ]);
    }

    // ── Helpers ─────────────────────────────────────────────────

    public function isTerminal(): bool
    {
        return $this->delivery_status?->isTerminal() ?? false;
    }

    public function canTransitionTo(DeliveryOrderStatus $status): bool
    {
        return $this->delivery_status?->canTransitionTo($status) ?? false;
    }
}
