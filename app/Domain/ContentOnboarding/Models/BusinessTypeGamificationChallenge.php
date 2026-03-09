<?php

namespace App\Domain\ContentOnboarding\Models;

use App\Domain\ContentOnboarding\Enums\GamificationChallengeType;
use App\Domain\ContentOnboarding\Enums\GamificationRewardType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessTypeGamificationChallenge extends Model
{
    use HasUuids;

    protected $table = 'business_type_gamification_challenges';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'business_type_id',
        'name',
        'name_ar',
        'challenge_type',
        'target_value',
        'reward_type',
        'reward_value',
        'duration_days',
        'is_recurring',
        'description',
        'description_ar',
        'sort_order',
    ];

    protected $casts = [
        'challenge_type' => GamificationChallengeType::class,
        'reward_type' => GamificationRewardType::class,
        'is_recurring' => 'boolean',
    ];

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }
}
