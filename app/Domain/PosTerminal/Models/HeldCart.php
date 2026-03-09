<?php

namespace App\Domain\PosTerminal\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeldCart extends Model
{
    use HasUuids;

    protected $table = 'held_carts';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'register_id',
        'cashier_id',
        'customer_id',
        'cart_data',
        'label',
        'held_at',
        'recalled_at',
        'recalled_by',
    ];

    protected $casts = [
        'cart_data' => 'array',
        'held_at' => 'datetime',
        'recalled_at' => 'datetime',
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
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
    public function recalledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recalled_by');
    }
}
