<?php

namespace App\Domain\LabelPrinting\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Store;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabelPrintHistory extends Model
{
    use HasUuids;

    protected $table = 'label_print_history';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'template_id',
        'printed_by',
        'product_count',
        'total_labels',
        'printer_name',
        'printer_language',
        'job_pages',
        'duration_ms',
        'printed_at',
    ];

    protected $casts = [
        'printed_at'   => 'datetime',
        'product_count' => 'integer',
        'total_labels'  => 'integer',
        'job_pages'     => 'integer',
        'duration_ms'   => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function template(): BelongsTo
    {
        return $this->belongsTo(LabelTemplate::class, 'template_id');
    }
    public function printedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by');
    }
}
