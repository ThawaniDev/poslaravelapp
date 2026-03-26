<?php

namespace App\Domain\ContentOnboarding\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LayoutWidgetPlacement extends Model
{
    use HasUuids;

    protected $table = 'layout_widget_placements';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'pos_layout_template_id', 'layout_widget_id', 'instance_key',
        'grid_x', 'grid_y', 'grid_w', 'grid_h', 'z_index',
        'properties', 'is_visible',
    ];

    protected $casts = [
        'properties' => 'array',
        'is_visible' => 'boolean',
    ];

    public function layoutTemplate(): BelongsTo
    {
        return $this->belongsTo(PosLayoutTemplate::class, 'pos_layout_template_id');
    }

    public function widget(): BelongsTo
    {
        return $this->belongsTo(LayoutWidget::class, 'layout_widget_id');
    }

    public function themeOverrides(): HasMany
    {
        return $this->hasMany(WidgetThemeOverride::class, 'layout_widget_placement_id');
    }
}
