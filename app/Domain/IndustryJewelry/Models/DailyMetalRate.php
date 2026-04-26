<?php

namespace App\Domain\IndustryJewelry\Models;

use App\Domain\Core\Models\Store;
use App\Domain\IndustryJewelry\Enums\MetalType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyMetalRate extends Model
{
    use HasUuids;

    protected $table = 'daily_metal_rates';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'metal_type',
        'karat',
        'rate_per_gram',
        'buyback_rate_per_gram',
        'effective_date',
    ];

    protected $casts = [
        'metal_type' => MetalType::class,
        'rate_per_gram' => 'decimal:2',
        'buyback_rate_per_gram' => 'decimal:2',
        'effective_date' => 'date',
        'created_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
