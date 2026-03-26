<?php

namespace App\Domain\Support\Models;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Auth\Models\User;
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

    // ─── Relationships ───────────────────────────────────────

    public function supportTicket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class);
    }

    public function sender(): BelongsTo
    {
        return $this->sender_type === TicketSenderType::Admin
            ? $this->belongsTo(AdminUser::class, 'sender_id')
            : $this->belongsTo(User::class, 'sender_id');
    }

    // ─── Helpers ─────────────────────────────────────────────

    public function isAdminMessage(): bool
    {
        return $this->sender_type === TicketSenderType::Admin;
    }

    public function isInternalNote(): bool
    {
        return (bool) $this->is_internal_note;
    }

    public function getSenderNameAttribute(): string
    {
        if ($this->sender_type === TicketSenderType::Admin) {
            return AdminUser::find($this->sender_id)?->name ?? __('support.sender_admin');
        }

        return User::find($this->sender_id)?->name ?? __('support.sender_provider');
    }
}
