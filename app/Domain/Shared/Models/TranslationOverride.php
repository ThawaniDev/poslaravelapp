<?php

namespace App\Domain\Shared\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranslationOverride extends Model
{
    use HasUuids;

    protected $table = 'translation_overrides';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'string_key',
        'locale',
        'custom_value',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
