<?php

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternalBarcodeSequence extends Model
{
    use HasUuids;

    protected $table = 'internal_barcode_sequence';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'last_sequence',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
