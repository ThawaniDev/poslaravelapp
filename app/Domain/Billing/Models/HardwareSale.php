<?php

namespace App\Domain\Billing\Models;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Core\Models\Store;
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
        'amount' => 'decimal:2',
        'sold_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function soldByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'sold_by');
    }
}
