<?php

namespace App\Domain\WameedAI\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AIStoreBillingConfig extends Model
{
    use HasUuids;

    protected $table = 'ai_store_billing_configs';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'organization_id',
        'is_ai_enabled',
        'monthly_limit_usd',
        'custom_margin_percentage',
        'disabled_reason',
        'disabled_at',
        'enabled_at',
        'notes',
    ];

    protected $casts = [
        'is_ai_enabled' => 'boolean',
        'monthly_limit_usd' => 'decimal:3',
        'custom_margin_percentage' => 'decimal:3',
        'disabled_at' => 'datetime',
        'enabled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Core\Models\Store::class, 'store_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Core\Models\Organization::class, 'organization_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(AIBillingInvoice::class, 'store_id', 'store_id');
    }

    public function getEffectiveMarginPercentage(): float
    {
        if ($this->custom_margin_percentage !== null) {
            return (float) $this->custom_margin_percentage;
        }
        return AIBillingSetting::getFloat('margin_percentage', 20.0);
    }

    public function isWithinMonthlyLimit(float $currentCostUsd): bool
    {
        $storeLimit = (float) $this->monthly_limit_usd;
        if ($storeLimit <= 0) {
            $globalLimit = AIBillingSetting::getFloat('global_monthly_limit_usd', 0);
            if ($globalLimit <= 0) return true;
            return $currentCostUsd < $globalLimit;
        }
        return $currentCostUsd < $storeLimit;
    }

    public static function getOrCreateForStore(string $storeId, string $organizationId): self
    {
        return static::firstOrCreate(
            ['store_id' => $storeId],
            [
                'organization_id' => $organizationId,
                'is_ai_enabled' => true,
                'monthly_limit_usd' => 0,
            ],
        );
    }
}
