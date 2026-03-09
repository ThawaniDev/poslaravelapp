<?php

namespace App\Domain\ContentOnboarding\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CfdThemePackageVisibility extends Pivot
{
    protected $table = 'cfd_theme_package_visibility';
    public $timestamps = false;

    protected $fillable = [
        'cfd_theme_id',
        'subscription_plan_id',
    ];

    public function cfdTheme(): BelongsTo
    {
        return $this->belongsTo(CfdTheme::class);
    }
    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }
}
