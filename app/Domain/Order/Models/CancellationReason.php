<?php

namespace App\Domain\Order\Models;

use App\Domain\Order\Enums\CancellationReasonCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CancellationReason extends Model
{
    use HasUuids;

    protected $table = 'cancellation_reasons';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_subscription_id',
        'reason_category',
        'reason_text',
        'cancelled_at',
    ];

    protected $casts = [
        'reason_category' => CancellationReasonCategory::class,
        'cancelled_at' => 'datetime',
    ];

    public function storeSubscription(): BelongsTo
    {
        return $this->belongsTo(StoreSubscription::class);
    }
}
