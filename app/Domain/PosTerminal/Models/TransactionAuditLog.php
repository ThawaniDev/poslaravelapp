<?php

namespace App\Domain\PosTerminal\Models;

use App\Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionAuditLog extends Model
{
    use HasUuids;

    protected $table = 'transaction_audit_logs';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'transaction_id',
        'actor_id',
        'action',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public static function record(string $transactionId, ?string $actorId, string $action, array $payload = []): self
    {
        return self::create([
            'transaction_id' => $transactionId,
            'actor_id' => $actorId,
            'action' => $action,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }
}
