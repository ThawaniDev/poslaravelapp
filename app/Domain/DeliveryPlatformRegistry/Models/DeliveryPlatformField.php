<?php

namespace App\Domain\DeliveryPlatformRegistry\Models;

use App\Domain\DeliveryPlatformRegistry\Enums\DeliveryFieldType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryPlatformField extends Model
{
    use HasUuids;

    protected $table = 'delivery_platform_fields';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'delivery_platform_id',
        'field_label',
        'field_key',
        'field_type',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'field_type' => DeliveryFieldType::class,
        'is_required' => 'boolean',
    ];

    public function deliveryPlatform(): BelongsTo
    {
        return $this->belongsTo(DeliveryPlatform::class);
    }
}
