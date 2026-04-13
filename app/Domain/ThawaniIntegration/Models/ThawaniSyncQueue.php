<?php

namespace App\Domain\ThawaniIntegration\Models;

use App\Domain\Core\Models\Store;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThawaniSyncQueue extends Model
{
    use HasUuids;

    protected $table = 'thawani_sync_queue';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'entity_type',
        'entity_id',
        'action',
        'payload',
        'status',
        'attempts',
        'max_attempts',
        'error_message',
        'scheduled_at',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'scheduled_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending')
            ->where('scheduled_at', '<=', now())
            ->whereColumn('attempts', '<', 'max_attempts');
    }

    public function scopeForStore($query, string $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function markProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    public function markCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->increment('attempts');
        $this->update([
            'status' => $this->attempts >= $this->max_attempts ? 'failed' : 'pending',
            'error_message' => $error,
            'scheduled_at' => now()->addMinutes(min(pow(2, $this->attempts), 60)),
        ]);
    }
}
