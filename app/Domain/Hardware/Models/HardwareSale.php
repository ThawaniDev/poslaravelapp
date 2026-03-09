<?php

namespace App\Domain\Hardware\Models;

use App\Domain\Hardware\Enums\HardwareSaleItemType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HardwareSale extends Model
{
    use HasUuids;

    protected $table = 'hardware_sales';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'sold_by',
        'item_type',
        'item_description',
        'serial_number',
        'amount',
        'notes',
        'sold_at',
    ];

    protected $casts = [
        'item_type' => HardwareSaleItemType::class,
        'amount' => 'decimal:2',
        'sold_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function soldBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'sold_by');
    }
}
