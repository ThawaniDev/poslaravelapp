<?php

namespace App\Domain\IndustryRestaurant\Models;

use App\Domain\Core\Models\Store;
use App\Domain\IndustryRestaurant\Enums\KitchenTicketStatus;
use App\Domain\Order\Models\Order;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenTicket extends Model
{
    use HasUuids;

    protected $table = 'kitchen_tickets';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'order_id',
        'table_id',
        'ticket_number',
        'items_json',
        'station',
        'status',
        'course_number',
        'fire_at',
        'completed_at',
    ];

    protected $casts = [
        'status' => KitchenTicketStatus::class,
        'items_json' => 'array',
        'fire_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
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
