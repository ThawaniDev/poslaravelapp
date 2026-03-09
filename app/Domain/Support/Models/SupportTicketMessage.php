<?php

namespace App\Domain\Support\Models;

use App\Domain\Support\Enums\TicketSenderType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketMessage extends Model
{
    use HasUuids;

    protected $table = 'support_ticket_messages';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'support_ticket_id',
        'sender_type',
        'sender_id',
        'message_text',
        'attachments',
        'is_internal_note',
        'sent_at',
    ];

    protected $casts = [
        'sender_type' => TicketSenderType::class,
        'attachments' => 'array',
        'is_internal_note' => 'boolean',
        'sent_at' => 'datetime',
    ];

    public function supportTicket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class);
    }
}
