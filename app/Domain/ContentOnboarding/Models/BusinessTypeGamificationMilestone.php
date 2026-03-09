<?php

namespace App\Domain\ContentOnboarding\Models;

use App\Domain\ContentOnboarding\Enums\MilestoneRewardType;
use App\Domain\ContentOnboarding\Enums\MilestoneType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessTypeGamificationMilestone extends Model
{
    use HasUuids;

    protected $table = 'business_type_gamification_milestones';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'business_type_id',
        'name',
        'name_ar',
        'milestone_type',
        'threshold_value',
        'reward_type',
        'reward_value',
        'sort_order',
    ];

    protected $casts = [
        'milestone_type' => MilestoneType::class,
        'reward_type' => MilestoneRewardType::class,
        'threshold_value' => 'decimal:2',
    ];

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }
}
