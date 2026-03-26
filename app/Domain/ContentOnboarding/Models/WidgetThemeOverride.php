<?php

namespace App\Domain\ContentOnboarding\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WidgetThemeOverride extends Model
{
    use HasUuids;

    protected $table = 'widget_theme_overrides';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'layout_widget_placement_id', 'variable_key', 'value',
    ];

    public function placement(): BelongsTo
    {
        return $this->belongsTo(LayoutWidgetPlacement::class, 'layout_widget_placement_id');
    }
}
