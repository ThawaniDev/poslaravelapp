<?php

namespace App\Domain\Payment\Models;

use App\Domain\Payment\Enums\CashEventType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashEvent extends Model
{
    use HasUuids;

    protected $table = 'cash_events';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'cash_session_id',
        'type',
        'amount',
        'reason',
        'notes',
        'performed_by',
    ];

    protected $casts = [
        'type' => CashEventType::class,
        'amount' => 'decimal:2',
    ];

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
