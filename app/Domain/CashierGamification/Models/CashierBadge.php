<?php

namespace App\Domain\CashierGamification\Models;

use App\Domain\CashierGamification\Enums\CashierBadgeTrigger;
use App\Domain\CashierGamification\Enums\PerformancePeriod;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashierBadge extends Model
{
    use HasUuids;

    protected $table = 'cashier_badges';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'slug',
        'name_en',
        'name_ar',
        'description_en',
        'description_ar',
        'icon',
        'color',
        'trigger_type',
        'trigger_threshold',
        'period',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'trigger_type' => CashierBadgeTrigger::class,
        'period' => PerformancePeriod::class,
        'trigger_threshold' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Core\Models\Store::class);
    }

    public function awards(): HasMany
    {
        return $this->hasMany(CashierBadgeAward::class, 'badge_id');
    }
}
