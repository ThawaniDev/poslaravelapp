<?php

namespace App\Domain\Customer\Models;

use App\Domain\Customer\Enums\AppointmentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use HasUuids;

    protected $table = 'appointments';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'customer_id',
        'staff_id',
        'service_product_id',
        'appointment_date',
        'start_time',
        'end_time',
        'status',
        'notes',
        'reminder_sent',
    ];

    protected $casts = [
        'status' => AppointmentStatus::class,
        'reminder_sent' => 'boolean',
        'appointment_date' => 'date',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
    public function staff(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'staff_id');
    }
    public function serviceProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'service_product_id');
    }
}
