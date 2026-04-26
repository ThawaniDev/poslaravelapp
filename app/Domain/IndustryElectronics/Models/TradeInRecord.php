<?php

namespace App\Domain\IndustryElectronics\Models;

use App\Domain\Core\Models\Store;
use App\Domain\Customer\Models\Customer;
use App\Domain\Order\Models\Order;
use App\Domain\StaffManagement\Models\StaffUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradeInRecord extends Model
{
    use HasUuids;

    protected $table = 'trade_in_records';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'customer_id',
        'device_description',
        'imei',
        'condition_grade',
        'assessed_value',
        'applied_to_order_id',
        'staff_user_id',
    ];

    protected $casts = [
        'assessed_value' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
    public function appliedToOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'applied_to_order_id');
    }
    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class);
    }
}
