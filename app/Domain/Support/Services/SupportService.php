<?php

namespace App\Domain\Support\Services;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\Support\Enums\TicketPriority;
use App\Domain\Support\Enums\TicketSenderType;
use App\Domain\Support\Enums\TicketStatus;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Models\SupportTicketMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SupportService
{
    // ═══════════════════════════════════════════════════════════
    //  PROVIDER-FACING
    // ═══════════════════════════════════════════════════════════

    public function listTickets(string $userId, string $storeId, array $filters = []): array
    {
        $query = SupportTicket::where('user_id', $userId)
            ->orWhere('store_id', $storeId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        return $query->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 15)
            ->toArray();
    }

    public function getTicket(string $ticketId, string $userId, string $storeId): ?SupportTicket
    {
        return SupportTicket::where('id', $ticketId)
            ->where(fn ($q) => $q->where('user_id', $userId)->orWhere('store_id', $storeId))
            ->with(['messages' => fn ($q) => $q->where('is_internal_note', false)])
            ->first();
    }

    public function createTicket(string $userId, string $storeId, ?string $organizationId, array $data): SupportTicket
    {
        return DB::transaction(function () use ($userId, $storeId, $organizationId, $data) {
            $priority = TicketPriority::tryFrom($data['priority'] ?? 'medium') ?? TicketPriority::Medium;

            $ticket = SupportTicket::create([
                'ticket_number'   => 'TKT-' . strtoupper(Str::random(8)),
                'user_id'         => $userId,
                'store_id'        => $storeId,
                'organization_id' => $organizationId,
                'category'        => $data['category'],
                'priority'        => $priority,
                'status'          => TicketStatus::Open,
                'subject'         => $data['subject'],
                'description'     => $data['description'],
            ]);

            Log::channel('daily')->info('Support ticket created by provider', [
                'ticket_id'     => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'user_id'       => $userId,
                'store_id'      => $storeId,
                'category'      => $data['category'],
                'priority'      => $priority->value,
            ]);

            return $ticket;
        });
    }

    public function addMessage(string $ticketId, string $userId, string $storeId, array $data): ?SupportTicketMessage
    {
        $ticket = SupportTicket::where('id', $ticketId)
            ->where(fn ($q) => $q->where('user_id', $userId)->orWhere('store_id', $storeId))
            ->first();

        if (!$ticket) {
            return null;
        }

        $message = SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type'       => TicketSenderType::Provider,
            'sender_id'         => $userId,
            'message_text'      => $data['message'],
            'attachments'       => $data['attachments'] ?? null,
            'is_internal_note'  => false,
            'sent_at'           => now(),
        ]);

        // Re-open ticket if it was resolved
        if ($ticket->status === TicketStatus::Resolved) {
            $ticket->update(['status' => TicketStatus::Open, 'resolved_at' => null]);
        }

        Log::channel('daily')->info('Provider replied to support ticket', [
            'ticket_id'  => $ticket->id,
            'message_id' => $message->id,
            'user_id'    => $userId,
        ]);

        return $message;
    }

    public function closeTicket(string $ticketId, string $userId, string $storeId): bool
    {
        $ticket = SupportTicket::where('id', $ticketId)
            ->where(fn ($q) => $q->where('user_id', $userId)->orWhere('store_id', $storeId))
            ->first();

        if (!$ticket) {
            return false;
        }

        $ticket->update([
            'status'    => TicketStatus::Closed,
            'closed_at' => now(),
        ]);

        Log::channel('daily')->info('Provider closed support ticket', [
            'ticket_id' => $ticket->id,
            'user_id'   => $userId,
        ]);

        return true;
    }

    public function getStats(string $userId, string $storeId): array
    {
        $base = SupportTicket::where(fn ($q) => $q->where('user_id', $userId)->orWhere('store_id', $storeId));

        return [
            'total'       => (clone $base)->count(),
            'open'        => (clone $base)->where('status', TicketStatus::Open)->count(),
            'in_progress' => (clone $base)->where('status', TicketStatus::InProgress)->count(),
            'resolved'    => (clone $base)->where('status', TicketStatus::Resolved)->count(),
            'closed'      => (clone $base)->where('status', TicketStatus::Closed)->count(),
        ];
    }

    // ═══════════════════════════════════════════════════════════
    //  ADMIN-FACING
    // ═══════════════════════════════════════════════════════════

    public function adminAddMessage(
        SupportTicket $ticket,
        string $adminId,
        string $messageText,
        bool $isInternalNote = false,
        ?array $attachments = null,
    ): SupportTicketMessage {
        $message = SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type'       => TicketSenderType::Admin,
            'sender_id'         => $adminId,
            'message_text'      => $messageText,
            'attachments'       => $attachments,
            'is_internal_note'  => $isInternalNote,
            'sent_at'           => now(),
        ]);

        // Track first response (only non-internal-note replies count)
        if (!$isInternalNote && !$ticket->first_response_at) {
            $ticket->update(['first_response_at' => now()]);
        }

        // Auto-transition to in_progress if currently open
        if (!$isInternalNote && $ticket->status === TicketStatus::Open) {
            $ticket->update(['status' => TicketStatus::InProgress]);
        }

        Log::channel('daily')->info('Admin replied to support ticket', [
            'ticket_id'      => $ticket->id,
            'message_id'     => $message->id,
            'admin_id'       => $adminId,
            'is_internal'    => $isInternalNote,
        ]);

        AdminActivityLog::record(
            adminUserId: $adminId,
            action: $isInternalNote ? 'add_internal_note' : 'reply_to_ticket',
            entityType: 'support_ticket',
            entityId: $ticket->id,
            details: ['message_id' => $message->id],
        );

        return $message;
    }

    public function assignTicket(SupportTicket $ticket, string $assigneeId, string $adminId): SupportTicket
    {
        $ticket->update(['assigned_to' => $assigneeId]);

        Log::channel('daily')->info('Support ticket assigned', [
            'ticket_id'   => $ticket->id,
            'assigned_to' => $assigneeId,
            'assigned_by' => $adminId,
        ]);

        AdminActivityLog::record(
            adminUserId: $adminId,
            action: 'assign_ticket',
            entityType: 'support_ticket',
            entityId: $ticket->id,
            details: ['assigned_to' => $assigneeId],
        );

        return $ticket->fresh();
    }

    public function changeStatus(SupportTicket $ticket, TicketStatus $newStatus, string $adminId): SupportTicket
    {
        $updates = ['status' => $newStatus];

        if ($newStatus === TicketStatus::Resolved) {
            $updates['resolved_at'] = now();
        }
        if ($newStatus === TicketStatus::Closed) {
            $updates['closed_at'] = now();
        }

        $ticket->update($updates);

        Log::channel('daily')->info('Support ticket status changed', [
            'ticket_id'  => $ticket->id,
            'new_status' => $newStatus->value,
            'changed_by' => $adminId,
        ]);

        AdminActivityLog::record(
            adminUserId: $adminId,
            action: 'change_ticket_status',
            entityType: 'support_ticket',
            entityId: $ticket->id,
            details: ['new_status' => $newStatus->value],
        );

        return $ticket->fresh();
    }

    public function getAdminStats(): array
    {
        return [
            'total'            => SupportTicket::count(),
            'open'             => SupportTicket::open()->count(),
            'in_progress'      => SupportTicket::inProgress()->count(),
            'unresolved'       => SupportTicket::unresolved()->count(),
            'sla_breached'     => SupportTicket::slaBreach()->count(),
            'resolved_today'   => SupportTicket::where('status', TicketStatus::Resolved)
                ->whereDate('resolved_at', today())->count(),
            'avg_response_min' => (int) SupportTicket::whereNotNull('first_response_at')
                ->selectRaw(
                    DB::getDriverName() === 'sqlite'
                        ? 'AVG((julianday(first_response_at) - julianday(created_at)) * 24 * 60) as avg'
                        : 'AVG(EXTRACT(EPOCH FROM (first_response_at - created_at)) / 60) as avg'
                )
                ->value('avg'),
        ];
    }
}
