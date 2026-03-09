<?php

namespace App\Domain\AccountingIntegration\Models;

use App\Domain\AccountingIntegration\Enums\AccountingExportStatus;
use App\Domain\AccountingIntegration\Enums\ExportTriggeredBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingExport extends Model
{
    use HasUuids;

    protected $table = 'accounting_exports';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'provider',
        'start_date',
        'end_date',
        'export_types',
        'status',
        'entries_count',
        'error_message',
        'journal_entry_ids',
        'csv_url',
        'triggered_by',
        'completed_at',
    ];

    protected $casts = [
        'status' => AccountingExportStatus::class,
        'triggered_by' => ExportTriggeredBy::class,
        'export_types' => 'array',
        'journal_entry_ids' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
