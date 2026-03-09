<?php

namespace App\Domain\IndustryRestaurant\Models;

use App\Domain\Security\Enums\SessionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpenTab extends Model
{
    use HasUuids;

    protected $table = 'open_tabs';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'order_id',
        'customer_name',
        'table_id',
        'opened_at',
        'closed_at',
        'status',
    ];

    protected $casts = [
        'status' => SessionStatus::class,
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }
}
