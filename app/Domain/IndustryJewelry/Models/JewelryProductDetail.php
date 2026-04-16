<?php

namespace App\Domain\IndustryJewelry\Models;

use App\Domain\IndustryJewelry\Enums\MakingChargesType;
use App\Domain\IndustryJewelry\Enums\MetalType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JewelryProductDetail extends Model
{
    use HasUuids;

    protected $table = 'jewelry_product_details';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'metal_type',
        'karat',
        'gross_weight_g',
        'net_weight_g',
        'making_charges_type',
        'making_charges_value',
        'stone_type',
        'stone_weight_carat',
        'stone_count',
        'certificate_number',
        'certificate_url',
    ];

    protected $casts = [
        'metal_type' => MetalType::class,
        'making_charges_type' => MakingChargesType::class,
        'gross_weight_g' => 'decimal:2',
        'net_weight_g' => 'decimal:2',
        'making_charges_value' => 'decimal:2',
        'stone_weight_carat' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
