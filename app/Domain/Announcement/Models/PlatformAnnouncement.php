<?php

namespace App\Domain\Announcement\Models;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Announcement\Enums\AnnouncementType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformAnnouncement extends Model
{
    use HasUuids;

    protected $table = 'platform_announcements';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'type',
        'title',
        'title_ar',
        'body',
        'body_ar',
        'target_filter',
        'display_start_at',
        'display_end_at',
        'is_banner',
        'send_push',
        'send_email',
        'created_by',
    ];

    protected $casts = [
        'type' => AnnouncementType::class,
        'target_filter' => 'array',
        'is_banner' => 'boolean',
        'send_push' => 'boolean',
        'send_email' => 'boolean',
        'display_start_at' => 'datetime',
        'display_end_at' => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by');
    }
    public function platformAnnouncementDismissals(): HasMany
    {
        return $this->hasMany(PlatformAnnouncementDismissal::class, 'announcement_id');
    }
}
