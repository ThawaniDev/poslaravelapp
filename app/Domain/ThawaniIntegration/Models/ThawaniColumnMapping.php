<?php

namespace App\Domain\ThawaniIntegration\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ThawaniColumnMapping extends Model
{
    use HasUuids;

    protected $table = 'thawani_column_mappings';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'entity_type',
        'thawani_field',
        'wameed_field',
        'transform_type',
        'transform_config',
    ];

    protected $casts = [
        'transform_config' => 'array',
    ];

    public static function getMappingsForEntity(string $entityType): array
    {
        return static::where('entity_type', $entityType)
            ->get()
            ->mapWithKeys(fn ($m) => [$m->thawani_field => [
                'wameed_field' => $m->wameed_field,
                'transform_type' => $m->transform_type,
                'transform_config' => $m->transform_config,
            ]])
            ->toArray();
    }
}
