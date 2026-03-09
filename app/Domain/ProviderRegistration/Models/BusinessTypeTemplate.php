<?php

namespace App\Domain\ProviderRegistration\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class BusinessTypeTemplate extends Model
{
    use HasUuids;

    protected $table = 'business_type_templates';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        'icon',
        'template_json',
        'sample_products_json',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'template_json' => 'array',
        'sample_products_json' => 'array',
        'is_active' => 'boolean',
    ];

}
