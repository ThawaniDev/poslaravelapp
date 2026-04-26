<?php

namespace App\Domain\IndustryPharmacy\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\IndustryPharmacy\Enums\DrugScheduleType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DrugSchedule extends Model
{
    use HasUuids;

    protected $table = 'drug_schedules';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'schedule_type',
        'active_ingredient',
        'dosage_form',
        'strength',
        'manufacturer',
        'requires_prescription',
    ];

    protected $casts = [
        'schedule_type' => DrugScheduleType::class,
        'requires_prescription' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
