<?php

namespace App\Domain\Support\Models;

use App\Domain\Support\Enums\TicketCategory;
use App\Domain\Support\Enums\TicketPriority;
use App\Domain\Support\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    use HasUuids;

    protected $table = 'support_tickets';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'ticket_number',
        'organization_id',
        'store_id',
        'user_id',
        'assigned_to',
        'category',
        'priority',
        'status',
        'subject',
        'description',
        'sla_deadline_at',
        'first_response_at',
        'resolved_at',
        'closed_at',
    ];

    protected $casts = [
        'category' => TicketCategory::class,
        'priority' => TicketPriority::class,
        'status' => TicketStatus::class,
        'sla_deadline_at' => 'datetime',
        'first_response_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'assigned_to');
    }
    public function supportTicketMessages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class);
    }
}
