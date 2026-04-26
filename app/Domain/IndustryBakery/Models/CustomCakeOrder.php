<?php

namespace App\Domain\IndustryBakery\Models;

use App\Domain\Core\Models\Store;
use App\Domain\Customer\Models\Customer;
use App\Domain\IndustryBakery\Enums\CustomCakeOrderStatus;
use App\Domain\Order\Models\Order;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomCakeOrder extends Model
{
    use HasUuids;

    protected $table = 'custom_cake_orders';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'customer_id',
        'order_id',
        'description',
        'size',
        'flavor',
        'decoration_notes',
        'delivery_date',
        'delivery_time',
        'price',
        'deposit_paid',
        'status',
        'reference_image_url',
    ];

    protected $casts = [
        'status' => CustomCakeOrderStatus::class,
        'price' => 'decimal:2',
        'deposit_paid' => 'decimal:2',
        'delivery_date' => 'date',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
