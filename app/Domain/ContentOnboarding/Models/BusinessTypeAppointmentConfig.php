<?php

namespace App\Domain\ContentOnboarding\Models;

use App\Domain\Order\Enums\CancellationFeeType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessTypeAppointmentConfig extends Model
{
    use HasUuids;

    protected $table = 'business_type_appointment_configs';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'business_type_id',
        'default_slot_duration_minutes',
        'min_advance_booking_hours',
        'max_advance_booking_days',
        'cancellation_window_hours',
        'cancellation_fee_type',
        'cancellation_fee_value',
        'allow_walkins',
        'overbooking_buffer_percentage',
        'require_deposit',
        'deposit_percentage',
    ];

    protected $casts = [
        'cancellation_fee_type' => CancellationFeeType::class,
        'allow_walkins' => 'boolean',
        'require_deposit' => 'boolean',
        'cancellation_fee_value' => 'decimal:2',
        'overbooking_buffer_percentage' => 'decimal:2',
        'deposit_percentage' => 'decimal:2',
    ];

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }
}
