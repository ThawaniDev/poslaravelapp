<?php

namespace App\Domain\IndustryElectronics\Models;

use App\Domain\Customer\Enums\ConditionGrade;
use App\Domain\IndustryElectronics\Enums\DeviceImeiStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceImeiRecord extends Model
{
    use HasUuids;

    protected $table = 'device_imei_records';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'store_id',
        'imei',
        'imei2',
        'serial_number',
        'condition_grade',
        'purchase_price',
        'status',
        'warranty_end_date',
        'store_warranty_end_date',
        'sold_order_id',
    ];

    protected $casts = [
        'condition_grade' => ConditionGrade::class,
        'status' => DeviceImeiStatus::class,
        'purchase_price' => 'decimal:2',
        'warranty_end_date' => 'date',
        'store_warranty_end_date' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function soldOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'sold_order_id');
    }
}
