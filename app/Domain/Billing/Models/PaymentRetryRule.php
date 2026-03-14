<?php

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PaymentRetryRule extends Model
{
    use HasUuids;

    protected $table = 'payment_retry_rules';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'max_retries',
        'retry_interval_hours',
        'grace_period_after_failure_days',
        'updated_at',
    ];

    protected $casts = [
        'max_retries' => 'integer',
        'retry_interval_hours' => 'integer',
        'grace_period_after_failure_days' => 'integer',
        'updated_at' => 'datetime',
    ];
}
