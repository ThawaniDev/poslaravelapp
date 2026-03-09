<?php

namespace App\Domain\ContentOnboarding\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingPageContent extends Model
{
    use HasUuids;

    protected $table = 'pricing_page_content';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'subscription_plan_id',
        'feature_bullet_list',
        'faq',
    ];

    protected $casts = [
        'feature_bullet_list' => 'array',
        'faq' => 'array',
    ];

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }
}
