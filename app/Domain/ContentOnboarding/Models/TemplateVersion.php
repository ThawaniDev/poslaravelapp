<?php

namespace App\Domain\ContentOnboarding\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateVersion extends Model
{
    use HasUuids;

    protected $table = 'template_versions';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'pos_layout_template_id', 'version_number', 'changelog',
        'canvas_snapshot', 'theme_snapshot', 'widget_placements_snapshot',
        'published_by', 'published_at',
    ];

    protected $casts = [
        'canvas_snapshot' => 'array',
        'theme_snapshot' => 'array',
        'widget_placements_snapshot' => 'array',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->created_at = $model->created_at ?? now();
        });
    }

    public function layoutTemplate(): BelongsTo
    {
        return $this->belongsTo(PosLayoutTemplate::class, 'pos_layout_template_id');
    }
}
