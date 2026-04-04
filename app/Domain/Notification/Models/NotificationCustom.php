<?php

namespace App\Domain\Notification\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NotificationCustom extends Model
{
    use HasUuids;

    protected $table = 'notifications_custom';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'store_id',
        'category',
        'title',
        'message',
        'action_url',
        'reference_type',
        'reference_id',
        'is_read',
        'priority',
        'expires_at',
        'metadata',
        'channel',
        'read_at',
        'created_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'expires_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    /**
     * Scope to a specific user.
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to unread notifications.
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope to a specific category.
     */
    public function scopeOfCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to non-expired notifications.
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope by priority.
     */
    public function scopeOfPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope for a store.
     */
    public function scopeForStore($query, string $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    /**
     * Get delivery logs for this notification.
     */
    public function deliveryLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NotificationDeliveryLog::class, 'notification_id');
    }

    /**
     * Get read receipts.
     */
    public function readReceipts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NotificationReadReceipt::class, 'notification_id');
    }
}
