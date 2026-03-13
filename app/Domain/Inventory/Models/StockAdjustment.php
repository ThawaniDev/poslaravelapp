<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Store;
use App\Domain\Inventory\Enums\StockAdjustmentType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockAdjustment extends Model
{
    use HasUuids;

    protected $table = 'stock_adjustments';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'type',
        'reason_code',
        'notes',
        'adjusted_by',
    ];

    protected $casts = [
        'type' => StockAdjustmentType::class,
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }
    public function stockAdjustmentItems(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }
}
