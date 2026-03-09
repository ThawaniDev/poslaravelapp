<?php

namespace App\Domain\ContentOnboarding\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosLayoutTemplate extends Model
{
    use HasUuids;

    protected $table = 'pos_layout_templates';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'business_type_id',
        'layout_key',
        'name',
        'name_ar',
        'description',
        'preview_image_url',
        'config',
        'is_default',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'config' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }
    public function layoutPackageVisibility(): HasMany
    {
        return $this->hasMany(LayoutPackageVisibility::class);
    }
    public function userPreferences(): HasMany
    {
        return $this->hasMany(UserPreference::class, 'pos_layout_id');
    }
}
