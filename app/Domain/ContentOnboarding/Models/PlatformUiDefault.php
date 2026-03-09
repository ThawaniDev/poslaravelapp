<?php

namespace App\Domain\ContentOnboarding\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformUiDefault extends Model
{
    protected $table = 'platform_ui_defaults';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'key',
        'value',
    ];

}
