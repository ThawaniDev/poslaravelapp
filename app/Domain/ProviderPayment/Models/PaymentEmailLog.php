<?php

namespace App\Domain\ProviderPayment\Models;

use App\Domain\ProviderPayment\Enums\PaymentEmailType;
use App\Domain\ProviderSubscription\Models\Invoice;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentEmailLog extends Model
{
    use HasUuids;

    protected $table = 'payment_email_logs';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'provider_payment_id',
        'invoice_id',
        'email_type',
        'recipient_email',
        'subject',
        'status',
        'error_message',
        'mailtrap_message_id',
    ];

    protected $casts = [
        'email_type' => PaymentEmailType::class,
    ];

    public function providerPayment(): BelongsTo
    {
        return $this->belongsTo(ProviderPayment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
