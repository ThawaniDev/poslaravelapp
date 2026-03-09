<?php

namespace App\Domain\SystemConfig\Models;

use App\Domain\SystemConfig\Enums\SystemSettingsGroup;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemSetting extends Model
{
    use HasUuids;

    protected $table = 'system_settings';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'key',
        'value',
        'group',
        'description',
        'updated_by',
    ];

    protected $casts = [
        'group' => SystemSettingsGroup::class,
        'value' => 'array',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'updated_by');
    }
}
