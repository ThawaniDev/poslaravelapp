<?php

namespace App\Domain\Shared\Models;

use App\Domain\ContentOnboarding\Enums\FontSize;
use App\Domain\ContentOnboarding\Enums\Handedness;
use App\Domain\Auth\Enums\UserTheme;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    use HasUuids;

    protected $table = 'user_preferences';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'pos_handedness',
        'font_size',
        'theme',
        'pos_layout_id',
        'accessibility_json',
    ];

    protected $casts = [
        'pos_handedness' => Handedness::class,
        'font_size' => FontSize::class,
        'theme' => UserTheme::class,
        'accessibility_json' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function posLayout(): BelongsTo
    {
        return $this->belongsTo(PosLayoutTemplate::class, 'pos_layout_id');
    }
}
