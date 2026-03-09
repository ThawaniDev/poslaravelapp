<?php

namespace App\Domain\Customer\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CfdConfiguration extends Model
{
    use HasUuids;

    protected $table = 'cfd_configurations';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'is_enabled',
        'target_monitor',
        'theme_config',
        'idle_content',
        'idle_rotation_seconds',
    ];

    protected $casts = [
        'theme_config' => 'array',
        'idle_content' => 'array',
        'is_enabled' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
