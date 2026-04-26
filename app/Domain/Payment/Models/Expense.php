<?php

namespace App\Domain\Payment\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Store;
use App\Domain\Payment\Enums\ExpenseCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasUuids;

    protected $table = 'expenses';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = true;

    protected $fillable = [
        'store_id',
        'cash_session_id',
        'amount',
        'category',
        'description',
        'receipt_image_url',
        'recorded_by',
        'expense_date',
    ];

    protected $casts = [
        'category' => ExpenseCategory::class,
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
