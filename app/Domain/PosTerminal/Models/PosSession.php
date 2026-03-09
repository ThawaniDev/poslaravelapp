<?php

namespace App\Domain\PosTerminal\Models;

use App\Domain\Security\Enums\SessionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSession extends Model
{
    use HasUuids;

    protected $table = 'pos_sessions';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'register_id',
        'cashier_id',
        'status',
        'opening_cash',
        'closing_cash',
        'expected_cash',
        'cash_difference',
        'total_cash_sales',
        'total_card_sales',
        'total_other_sales',
        'total_refunds',
        'total_voids',
        'transaction_count',
        'opened_at',
        'closed_at',
        'z_report_printed',
    ];

    protected $casts = [
        'status' => SessionStatus::class,
        'z_report_printed' => 'boolean',
        'opening_cash' => 'decimal:2',
        'closing_cash' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'cash_difference' => 'decimal:2',
        'total_cash_sales' => 'decimal:2',
        'total_card_sales' => 'decimal:2',
        'total_other_sales' => 'decimal:2',
        'total_refunds' => 'decimal:2',
        'total_voids' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
