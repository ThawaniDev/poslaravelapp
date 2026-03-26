<?php

namespace App\Domain\ContentOnboarding\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateReview extends Model
{
    use HasUuids;

    protected $table = 'template_reviews';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'marketplace_listing_id', 'store_id', 'user_id', 'rating',
        'title', 'body', 'is_verified_purchase', 'is_published',
        'admin_response', 'admin_responded_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_verified_purchase' => 'boolean',
        'is_published' => 'boolean',
        'admin_responded_at' => 'datetime',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(TemplateMarketplaceListing::class, 'marketplace_listing_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Store\Models\Store::class, 'store_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class, 'user_id');
    }
}
