<?php

namespace App\Domain\BackupSync\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UpdateRollout extends Model
{
    use HasUuids;

    protected $table = 'update_rollouts';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'version',
        'rollout_percentage',
        'is_critical',
        'target_stores',
        'pinned_stores',
        'release_notes',
        'released_at',
    ];

    protected $casts = [
        'target_stores' => 'array',
        'pinned_stores' => 'array',
        'is_critical' => 'boolean',
        'released_at' => 'datetime',
    ];

}
