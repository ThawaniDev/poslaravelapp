<?php

namespace App\Domain\Website\Models;

use App\Domain\Website\Enums\NewsletterStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WebsiteNewsletterSubscriber extends Model
{
    use HasUuids;

    protected $table = 'website_newsletter_subscribers';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'email',
        'source_page',
        'status',
        'ip_address',
        'subscribed_at',
        'unsubscribed_at',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    protected $casts = [
        'status' => NewsletterStatus::class,
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    // ─── Boot ────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (self $record) {
            if (empty($record->subscribed_at)) {
                $record->subscribed_at = now();
            }
        });
    }

    // ─── Scopes ──────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', NewsletterStatus::Active);
    }
}
