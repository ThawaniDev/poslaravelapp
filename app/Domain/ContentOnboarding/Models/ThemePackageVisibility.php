<?php

namespace App\Domain\ContentOnboarding\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ThemePackageVisibility extends Pivot
{
    protected $table = 'theme_package_visibility';
    public $timestamps = false;

    protected $fillable = [
        'theme_id',
        'subscription_plan_id',
    ];

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }
    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }
}
