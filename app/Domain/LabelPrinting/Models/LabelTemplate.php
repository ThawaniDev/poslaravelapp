<?php

namespace App\Domain\LabelPrinting\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabelTemplate extends Model
{
    use HasUuids;

    protected $table = 'label_templates';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'organization_id',
        'name',
        'label_width_mm',
        'label_height_mm',
        'layout_json',
        'is_preset',
        'is_default',
        'created_by',
        'sync_version',
    ];

    protected $casts = [
        'layout_json' => 'array',
        'is_preset' => 'boolean',
        'is_default' => 'boolean',
        'label_width_mm' => 'decimal:2',
        'label_height_mm' => 'decimal:2',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function labelPrintHistory(): HasMany
    {
        return $this->hasMany(LabelPrintHistory::class, 'template_id');
    }
}
