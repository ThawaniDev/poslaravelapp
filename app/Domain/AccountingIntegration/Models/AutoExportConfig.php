<?php

namespace App\Domain\AccountingIntegration\Models;

use App\Domain\AccountingIntegration\Enums\ExportFrequency;
use App\Domain\Core\Models\Store;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoExportConfig extends Model
{
    use HasUuids;

    protected $table = 'auto_export_configs';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'enabled',
        'frequency',
        'day_of_week',
        'day_of_month',
        'time',
        'export_types',
        'notify_email',
        'retry_on_failure',
        'last_run_at',
        'next_run_at',
    ];

    protected $casts = [
        'frequency' => ExportFrequency::class,
        'export_types' => 'array',
        'enabled' => 'boolean',
        'retry_on_failure' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
