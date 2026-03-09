<?php

namespace App\Domain\ContentOnboarding\Models;

use App\Domain\ContentOnboarding\Enums\BadgeTriggerType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessTypeGamificationBadge extends Model
{
    use HasUuids;

    protected $table = 'business_type_gamification_badges';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'business_type_id',
        'name',
        'name_ar',
        'icon_url',
        'trigger_type',
        'trigger_threshold',
        'points_reward',
        'description',
        'description_ar',
        'sort_order',
    ];

    protected $casts = [
        'trigger_type' => BadgeTriggerType::class,
    ];

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }
}
