<?php

namespace App\Domain\ContentOnboarding\Models;

use App\Domain\ContentOnboarding\Enums\WidgetCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LayoutWidget extends Model
{
    use HasUuids;

    protected $table = 'layout_widgets';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'slug', 'name', 'name_ar', 'description', 'description_ar',
        'category', 'icon', 'default_width', 'default_height',
        'min_width', 'min_height', 'max_width', 'max_height',
        'is_resizable', 'is_required', 'properties_schema',
        'default_properties', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'category' => WidgetCategory::class,
        'properties_schema' => 'array',
        'default_properties' => 'array',
        'is_resizable' => 'boolean',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function placements(): HasMany
    {
        return $this->hasMany(LayoutWidgetPlacement::class);
    }
}
