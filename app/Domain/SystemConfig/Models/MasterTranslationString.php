<?php

namespace App\Domain\SystemConfig\Models;

use App\Domain\SystemConfig\Enums\TranslationCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MasterTranslationString extends Model
{
    use HasUuids;

    protected $table = 'master_translation_strings';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'string_key',
        'category',
        'value_en',
        'value_ar',
        'description',
        'is_overridable',
    ];

    protected $casts = [
        'category' => TranslationCategory::class,
        'is_overridable' => 'boolean',
    ];

}
