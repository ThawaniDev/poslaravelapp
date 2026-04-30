<?php

namespace App\Domain\SystemConfig\Models;

use App\Domain\AdminPanel\Models\AdminUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranslationVersion extends Model
{
    use HasUuids;

    protected $table = 'translation_versions';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'version_hash',
        'published_at',
        'published_by',
        'notes',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'published_by');
    }
}
