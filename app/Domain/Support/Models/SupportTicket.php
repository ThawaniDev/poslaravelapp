<?php

namespace App\Domain\Support\Models;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Support\Enums\TicketCategory;
use App\Domain\Support\Enums\TicketPriority;
use App\Domain\Support\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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

    // ─── Boot ────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (self $ticket) {
            if (empty($ticket->ticket_number)) {
                $ticket->ticket_number = 'TKT-' . strtoupper(Str::random(8));
            }
            if (empty($ticket->sla_deadline_at) && $ticket->priority) {
                $priority = $ticket->priority instanceof TicketPriority
                    ? $ticket->priority
                    : TicketPriority::from($ticket->priority);
                $ticket->sla_deadline_at = now()->addMinutes($priority->slaResolutionMinutes());
            }
        });
    }

    // ─── Relationships ───────────────────────────────────────

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

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class)->orderBy('sent_at');
    }

    public function supportTicketMessages(): HasMany
    {
        return $this->messages();
    }

    // ─── Scopes ──────────────────────────────────────────────

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', TicketStatus::Open);
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', TicketStatus::InProgress);
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereIn('status', [TicketStatus::Open, TicketStatus::InProgress]);
    }

    public function scopeSlaBreach(Builder $query): Builder
    {
        return $query->unresolved()
            ->whereNotNull('sla_deadline_at')
            ->where('sla_deadline_at', '<', now());
    }

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    // ─── Helpers ─────────────────────────────────────────────

    public function isOpen(): bool
    {
        return $this->status === TicketStatus::Open;
    }

    public function isClosed(): bool
    {
        return $this->status === TicketStatus::Closed;
    }

    public function isSlaBreached(): bool
    {
        return $this->sla_deadline_at
            && $this->status !== TicketStatus::Resolved
            && $this->status !== TicketStatus::Closed
            && now()->isAfter($this->sla_deadline_at);
    }

    public function getSlaBadgeAttribute(): string
    {
        if (!$this->sla_deadline_at) {
            return 'none';
        }
        if ($this->status === TicketStatus::Resolved || $this->status === TicketStatus::Closed) {
            return 'met';
        }
        return now()->isAfter($this->sla_deadline_at) ? 'breached' : 'on_track';
    }
}
