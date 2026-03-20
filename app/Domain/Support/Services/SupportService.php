<?php

namespace App\Domain\Support\Services;

use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Models\SupportTicketMessage;
use App\Domain\Support\Enums\TicketStatus;
use Illuminate\Support\Str;

class SupportService
{
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
            ->with('supportTicketMessages')
            ->first();
    }

    public function createTicket(string $userId, string $storeId, ?string $organizationId, array $data): SupportTicket
    {
        return SupportTicket::create([
            'ticket_number' => 'TKT-' . strtoupper(Str::random(8)),
            'user_id' => $userId,
            'store_id' => $storeId,
            'organization_id' => $organizationId,
            'category' => $data['category'],
            'priority' => $data['priority'] ?? 'medium',
            'status' => TicketStatus::Open,
            'subject' => $data['subject'],
            'description' => $data['description'],
        ]);
    }

    public function addMessage(string $ticketId, string $userId, string $storeId, array $data): ?SupportTicketMessage
    {
        $ticket = SupportTicket::where('id', $ticketId)
            ->where(fn ($q) => $q->where('user_id', $userId)->orWhere('store_id', $storeId))
            ->first();

        if (!$ticket) {
            return null;
        }

        return SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'provider',
            'sender_id' => $userId,
            'message_text' => $data['message'],
            'attachments' => $data['attachments'] ?? null,
            'is_internal_note' => false,
            'sent_at' => now(),
        ]);
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
            'status' => TicketStatus::Closed,
            'closed_at' => now(),
        ]);

        return true;
    }

    public function getStats(string $userId, string $storeId): array
    {
        $base = SupportTicket::where(fn ($q) => $q->where('user_id', $userId)->orWhere('store_id', $storeId));

        return [
            'total' => (clone $base)->count(),
            'open' => (clone $base)->where('status', TicketStatus::Open)->count(),
            'in_progress' => (clone $base)->where('status', TicketStatus::InProgress)->count(),
            'resolved' => (clone $base)->where('status', TicketStatus::Resolved)->count(),
            'closed' => (clone $base)->where('status', TicketStatus::Closed)->count(),
        ];
    }
}
