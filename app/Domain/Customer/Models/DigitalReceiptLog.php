<?php

namespace App\Domain\Customer\Models;

use App\Domain\Customer\Enums\DigitalReceiptChannel;
use App\Domain\Customer\Enums\DigitalReceiptStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DigitalReceiptLog extends Model
{
    use HasUuids;

    protected $table = 'digital_receipt_log';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'customer_id',
        'channel',
        'destination',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'channel' => DigitalReceiptChannel::class,
        'status' => DigitalReceiptStatus::class,
        'sent_at' => 'datetime',
    ];

}
