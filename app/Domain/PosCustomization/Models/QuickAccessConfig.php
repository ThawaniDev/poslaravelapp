<?php

namespace App\Domain\PosCustomization\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuickAccessConfig extends Model
{
    use HasUuids;

    protected $table = 'quick_access_configs';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'grid_rows',
        'grid_cols',
        'buttons_json',
        'sync_version',
    ];

    protected $casts = [
        'buttons_json' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
