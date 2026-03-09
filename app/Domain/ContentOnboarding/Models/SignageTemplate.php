<?php

namespace App\Domain\ContentOnboarding\Models;

use App\Domain\ContentOnboarding\Enums\SignageTemplateType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SignageTemplate extends Model
{
    use HasUuids;

    protected $table = 'signage_templates';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'name_ar',
        'slug',
        'template_type',
        'layout_config',
        'placeholder_content',
        'background_color',
        'text_color',
        'font_family',
        'transition_style',
        'preview_image_url',
        'is_active',
    ];

    protected $casts = [
        'template_type' => SignageTemplateType::class,
        'layout_config' => 'array',
        'placeholder_content' => 'array',
        'is_active' => 'boolean',
    ];

    public function signageTemplateBusinessTypes(): HasMany
    {
        return $this->hasMany(SignageTemplateBusinessType::class);
    }
    public function signageTemplatePackageVisibility(): HasMany
    {
        return $this->hasMany(SignageTemplatePackageVisibility::class);
    }
}
