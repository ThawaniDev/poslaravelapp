<?php

namespace App\Domain\IndustryRestaurant\Models;

use App\Domain\IndustryRestaurant\Enums\TableReservationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TableReservation extends Model
{
    use HasUuids;

    protected $table = 'table_reservations';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'table_id',
        'customer_name',
        'customer_phone',
        'party_size',
        'reservation_date',
        'reservation_time',
        'duration_minutes',
        'status',
        'notes',
    ];

    protected $casts = [
        'status' => TableReservationStatus::class,
        'reservation_date' => 'date',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }
}
