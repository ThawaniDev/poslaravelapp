<?php

namespace App\Domain\SystemConfig\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TaxExemptionType extends Model
{
    use HasUuids;

    protected $table = 'tax_exemption_types';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'name_ar',
        'required_documents',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

}
