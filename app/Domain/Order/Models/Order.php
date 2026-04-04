<?php

namespace App\Domain\Order\Models;

use App\Domain\Order\Enums\OrderSource;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Store;
use App\Domain\Customer\Models\Customer;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasUuids;

    protected $table = 'orders';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'transaction_id',
        'customer_id',
        'order_number',
        'source',
        'status',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total',
        'notes',
        'customer_notes',
        'external_order_id',
        'delivery_address',
        'created_by',
    ];

    protected $casts = [
        'source' => OrderSource::class,
        'status' => OrderStatus::class,
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
    public function orderStatusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }
    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }
    public function exchanges(): HasMany
    {
        return $this->hasMany(Exchange::class, 'original_order_id');
    }
    public function exchangesViaNewOrder(): HasMany
    {
        return $this->hasMany(Exchange::class, 'new_order_id');
    }
    public function orderDeliveryInfo(): HasOne
    {
        return $this->hasOne(OrderDeliveryInfo::class);
    }
    public function deliveryOrderMappings(): HasMany
    {
        return $this->hasMany(DeliveryOrderMapping::class);
    }
    public function thawaniOrderMappings(): HasMany
    {
        return $this->hasMany(ThawaniOrderMapping::class);
    }
    public function zatcaInvoices(): HasMany
    {
        return $this->hasMany(ZatcaInvoice::class);
    }
    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }
    public function deviceImeiRecords(): HasMany
    {
        return $this->hasMany(DeviceImeiRecord::class, 'sold_order_id');
    }
    public function tradeInRecords(): HasMany
    {
        return $this->hasMany(TradeInRecord::class, 'applied_to_order_id');
    }
    public function customCakeOrders(): HasMany
    {
        return $this->hasMany(CustomCakeOrder::class);
    }
    public function restaurantTables(): HasMany
    {
        return $this->hasMany(RestaurantTable::class, 'current_order_id');
    }
    public function kitchenTickets(): HasMany
    {
        return $this->hasMany(KitchenTicket::class);
    }
    public function openTabs(): HasMany
    {
        return $this->hasMany(OpenTab::class);
    }
}
