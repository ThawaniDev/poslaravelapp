<?php

namespace App\Domain\Customer\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignagePlaylist extends Model
{
    use HasUuids;

    protected $table = 'signage_playlists';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'name',
        'slides',
        'schedule',
        'is_active',
    ];

    protected $casts = [
        'slides' => 'array',
        'schedule' => 'array',
        'is_active' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
