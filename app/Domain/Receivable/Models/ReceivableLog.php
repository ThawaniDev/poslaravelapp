<?php

namespace App\Domain\Receivable\Models;

use App\Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivableLog extends Model
{
    use HasUuids;

    protected $table = 'receivable_logs';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'receivable_id',
        'event',
        'from_value',
        'to_value',
        'amount',
        'note',
        'meta',
        'actor_id',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function receivable(): BelongsTo
    {
        return $this->belongsTo(Receivable::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
