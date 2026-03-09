<?php

namespace App\Domain\Customer\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerChallengeProgress extends Model
{
    use HasUuids;

    protected $table = 'customer_challenge_progress';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'customer_id',
        'challenge_id',
        'current_value',
        'is_completed',
        'completed_at',
        'reward_claimed',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'reward_claimed' => 'boolean',
        'current_value' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
    public function challenge(): BelongsTo
    {
        return $this->belongsTo(LoyaltyChallenge::class, 'challenge_id');
    }
}
