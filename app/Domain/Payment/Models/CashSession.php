<?php

namespace App\Domain\Payment\Models;

use App\Domain\Security\Enums\SessionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends Model
{
    use HasUuids;

    protected $table = 'cash_sessions';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'terminal_id',
        'opened_by',
        'closed_by',
        'opening_float',
        'expected_cash',
        'actual_cash',
        'variance',
        'status',
        'opened_at',
        'closed_at',
        'close_notes',
    ];

    protected $casts = [
        'status' => SessionStatus::class,
        'opening_float' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'actual_cash' => 'decimal:2',
        'variance' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
    public function cashEvents(): HasMany
    {
        return $this->hasMany(CashEvent::class);
    }
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
