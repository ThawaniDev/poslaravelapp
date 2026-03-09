<?php

namespace App\Domain\IndustryRestaurant\Models;

use App\Domain\IndustryRestaurant\Enums\RestaurantTableStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantTable extends Model
{
    use HasUuids;

    protected $table = 'restaurant_tables';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'table_number',
        'display_name',
        'seats',
        'zone',
        'position_x',
        'position_y',
        'status',
        'current_order_id',
        'is_active',
    ];

    protected $casts = [
        'status' => RestaurantTableStatus::class,
        'is_active' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function currentOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'current_order_id');
    }
    public function kitchenTickets(): HasMany
    {
        return $this->hasMany(KitchenTicket::class, 'table_id');
    }
    public function tableReservations(): HasMany
    {
        return $this->hasMany(TableReservation::class, 'table_id');
    }
    public function openTabs(): HasMany
    {
        return $this->hasMany(OpenTab::class, 'table_id');
    }
}
