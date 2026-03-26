<?php

namespace App\Domain\Announcement\Models;

use App\Domain\Announcement\Enums\ReminderChannel;
use App\Domain\Announcement\Enums\ReminderType;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReminder extends Model
{
    use HasUuids;

    protected $table = 'payment_reminders';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_subscription_id',
        'reminder_type',
        'channel',
        'sent_at',
    ];

    protected $casts = [
        'reminder_type' => ReminderType::class,
        'channel' => ReminderChannel::class,
        'sent_at' => 'datetime',
    ];

    public function storeSubscription(): BelongsTo
    {
        return $this->belongsTo(StoreSubscription::class);
    }
}
