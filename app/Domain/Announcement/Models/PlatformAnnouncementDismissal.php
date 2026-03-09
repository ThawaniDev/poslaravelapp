<?php

namespace App\Domain\Announcement\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAnnouncementDismissal extends Model
{
    use HasUuids;

    protected $table = 'platform_announcement_dismissals';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'announcement_id',
        'store_id',
        'dismissed_at',
    ];

    protected $casts = [
        'dismissed_at' => 'datetime',
    ];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(PlatformAnnouncement::class, 'announcement_id');
    }
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
