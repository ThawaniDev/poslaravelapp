<?php

namespace App\Domain\SystemConfig\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AgeRestrictedCategory extends Model
{
    use HasUuids;

    protected $table = 'age_restricted_categories';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'category_slug',
        'min_age',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

}
