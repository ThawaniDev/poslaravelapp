<?php

namespace App\Domain\Support\Models;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Support\Enums\TicketCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CannedResponse extends Model
{
    use HasUuids;

    protected $table = 'canned_responses';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'title',
        'shortcut',
        'body',
        'body_ar',
        'category',
        'is_active',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'category' => TicketCategory::class,
        'is_active' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by');
    }
}
