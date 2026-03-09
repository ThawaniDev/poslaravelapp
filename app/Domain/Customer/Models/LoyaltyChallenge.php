<?php

namespace App\Domain\Customer\Models;

use App\Domain\ContentOnboarding\Enums\ChallengeRewardType;
use App\Domain\ContentOnboarding\Enums\ChallengeType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyChallenge extends Model
{
    use HasUuids;

    protected $table = 'loyalty_challenges';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        'challenge_type',
        'target_value',
        'reward_type',
        'reward_value',
        'reward_badge_id',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'challenge_type' => ChallengeType::class,
        'reward_type' => ChallengeRewardType::class,
        'is_active' => 'boolean',
        'target_value' => 'decimal:2',
        'reward_value' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function customerChallengeProgress(): HasMany
    {
        return $this->hasMany(CustomerChallengeProgress::class, 'challenge_id');
    }
}
