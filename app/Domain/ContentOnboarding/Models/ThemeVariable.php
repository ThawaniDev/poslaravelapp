<?php

namespace App\Domain\ContentOnboarding\Models;

use App\Domain\ContentOnboarding\Enums\ThemeVariableCategory;
use App\Domain\ContentOnboarding\Enums\ThemeVariableType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThemeVariable extends Model
{
    use HasUuids;

    protected $table = 'theme_variables';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'theme_id', 'variable_key', 'variable_value',
        'variable_type', 'category',
    ];

    protected $casts = [
        'variable_type' => ThemeVariableType::class,
        'category' => ThemeVariableCategory::class,
    ];

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class, 'theme_id');
    }
}
