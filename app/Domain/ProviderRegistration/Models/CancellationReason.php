<?php

namespace App\Domain\ProviderRegistration\Models;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
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
        'recorded_by',
        'cancelled_at',
    ];

    protected $casts = [
        'cancelled_at' => 'datetime',
    ];

    /** Valid reason categories */
    public const CATEGORIES = ['price', 'features', 'competitor', 'support', 'other'];

    public function storeSubscription(): BelongsTo
    {
        return $this->belongsTo(StoreSubscription::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'recorded_by');
    }
}
