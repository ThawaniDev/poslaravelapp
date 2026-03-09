<?php

namespace App\Domain\SystemConfig\Models;

use App\Domain\SystemConfig\Enums\CalendarSystem;
use App\Domain\SystemConfig\Enums\LocaleDirection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SupportedLocale extends Model
{
    use HasUuids;

    protected $table = 'supported_locales';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'locale_code',
        'language_name',
        'language_name_native',
        'direction',
        'date_format',
        'number_format',
        'calendar_system',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'direction' => LocaleDirection::class,
        'calendar_system' => CalendarSystem::class,
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

}
