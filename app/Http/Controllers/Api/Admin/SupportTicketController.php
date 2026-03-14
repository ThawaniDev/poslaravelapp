<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Support\Models\CannedResponse;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Models\SupportTicketMessage;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SupportTicketController extends BaseApiController
{
    // ─── Tickets ─────────────────────────────────────────────

    public function listTickets(Request $request): JsonResponse
    {
        $query = SupportTicket::query()->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }
        if ($request->filled('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('subject', 'like', "%{$s}%")
                  ->orWhere('ticket_number', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%");
            });
        }

        $tickets = $query->paginate($request->integer('per_page', 20));

        return $this->success($tickets, 'Support tickets retrieved');
    }

    public function createTicket(Request $request): JsonResponse
    {
        $request->validate([
            'organization_id' => 'required|uuid',
            'subject'         => 'required|string|max:255',
            'description'     => 'required|string',
            'category'        => 'required|string|in:billing,technical,zatca,feature_request,general',
            'priority'        => 'sometimes|string|in:low,medium,high,critical',
            'store_id'        => 'sometimes|uuid',
            'user_id'         => 'sometimes|uuid',
        ]);

        $ticket = SupportTicket::create([
            'ticket_number'   => 'TKT-' . strtoupper(Str::random(8)),
            'organization_id' => $request->organization_id,
            'store_id'        => $request->input('store_id'),
            'user_id'         => $request->input('user_id'),
            'assigned_to'     => $request->user()->id,
            'category'        => $request->category,
            'priority'        => $request->input('priority', 'medium'),
            'status'          => 'open',
            'subject'         => $request->subject,
            'description'     => $request->description,
        ]);

        return $this->created($ticket, 'Support ticket created');
    }

    public function showTicket(string $id): JsonResponse
    {
        $ticket = SupportTicket::with('supportTicketMessages')->find($id);

        if (!$ticket) {
            return $this->notFound('Support ticket not found');
        }

        return $this->success($ticket, 'Support ticket details');
    }

    public function updateTicket(Request $request, string $id): JsonResponse
    {
        $ticket = SupportTicket::find($id);

        if (!$ticket) {
            return $this->notFound('Support ticket not found');
        }

        $request->validate([
            'subject'     => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'category'    => 'sometimes|string|in:billing,technical,zatca,feature_request,general',
            'priority'    => 'sometimes|string|in:low,medium,high,critical',
        ]);

        $ticket->update($request->only(['subject', 'description', 'category', 'priority']));

        return $this->success($ticket->fresh(), 'Support ticket updated');
    }

    public function assignTicket(Request $request, string $id): JsonResponse
    {
        $ticket = SupportTicket::find($id);

        if (!$ticket) {
            return $this->notFound('Support ticket not found');
        }

        $request->validate([
            'assigned_to' => 'required|uuid',
        ]);

        $ticket->update(['assigned_to' => $request->assigned_to]);

        return $this->success($ticket->fresh(), 'Ticket assigned');
    }

    public function changeStatus(Request $request, string $id): JsonResponse
    {
        $ticket = SupportTicket::find($id);

        if (!$ticket) {
            return $this->notFound('Support ticket not found');
        }

        $request->validate([
            'status' => 'required|string|in:open,in_progress,resolved,closed',
        ]);

        $newStatus = $request->status;
        $updates = ['status' => $newStatus];

        if ($newStatus === 'resolved') {
            $updates['resolved_at'] = now();
        }
        if ($newStatus === 'closed') {
            $updates['closed_at'] = now();
        }

        $ticket->update($updates);

        return $this->success($ticket->fresh(), 'Ticket status updated');
    }

    // ─── Messages ────────────────────────────────────────────

    public function listMessages(string $ticketId): JsonResponse
    {
        $ticket = SupportTicket::find($ticketId);

        if (!$ticket) {
            return $this->notFound('Support ticket not found');
        }

        $messages = SupportTicketMessage::where('support_ticket_id', $ticketId)
            ->orderBy('sent_at')
            ->get();

        return $this->success($messages, 'Ticket messages retrieved');
    }

    public function addMessage(Request $request, string $ticketId): JsonResponse
    {
        $ticket = SupportTicket::find($ticketId);

        if (!$ticket) {
            return $this->notFound('Support ticket not found');
        }

        $request->validate([
            'message_text'     => 'required|string',
            'is_internal_note' => 'sometimes|boolean',
            'attachments'      => 'sometimes|array',
        ]);

        $message = SupportTicketMessage::create([
            'support_ticket_id' => $ticketId,
            'sender_type'       => 'admin',
            'sender_id'         => $request->user()->id,
            'message_text'      => $request->message_text,
            'attachments'       => $request->input('attachments'),
            'is_internal_note'  => $request->boolean('is_internal_note', false),
            'sent_at'           => now(),
        ]);

        // Set first_response_at if not yet set
        if (!$ticket->first_response_at) {
            $ticket->update(['first_response_at' => now()]);
        }

        return $this->created($message, 'Message added');
    }

    // ─── Canned Responses ────────────────────────────────────

    public function listCannedResponses(Request $request): JsonResponse
    {
        $query = CannedResponse::query()->orderBy('title');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                  ->orWhere('shortcut', 'like', "%{$s}%")
                  ->orWhere('body', 'like', "%{$s}%");
            });
        }

        return $this->success($query->get(), 'Canned responses retrieved');
    }

    public function createCannedResponse(Request $request): JsonResponse
    {
        $request->validate([
            'title'    => 'required|string|max:255',
            'body'     => 'required|string',
            'body_ar'  => 'required|string',
            'shortcut' => 'sometimes|string|max:50',
            'category' => 'sometimes|string|in:billing,technical,zatca,feature_request,general',
        ]);

        $response = CannedResponse::forceCreate([
            'title'      => $request->title,
            'shortcut'   => $request->input('shortcut'),
            'body'       => $request->body,
            'body_ar'    => $request->body_ar,
            'category'   => $request->input('category'),
            'is_active'  => true,
            'created_by' => $request->user()->id,
            'created_at' => now(),
        ]);

        return $this->created($response, 'Canned response created');
    }

    public function showCannedResponse(string $id): JsonResponse
    {
        $response = CannedResponse::find($id);

        if (!$response) {
            return $this->notFound('Canned response not found');
        }

        return $this->success($response, 'Canned response details');
    }

    public function updateCannedResponse(Request $request, string $id): JsonResponse
    {
        $response = CannedResponse::find($id);

        if (!$response) {
            return $this->notFound('Canned response not found');
        }

        $request->validate([
            'title'    => 'sometimes|string|max:255',
            'body'     => 'sometimes|string',
            'body_ar'  => 'sometimes|string',
            'shortcut' => 'sometimes|string|max:50',
            'category' => 'sometimes|string|in:billing,technical,zatca,feature_request,general',
        ]);

        $response->update($request->only(['title', 'body', 'body_ar', 'shortcut', 'category']));

        return $this->success($response->fresh(), 'Canned response updated');
    }

    public function destroyCannedResponse(string $id): JsonResponse
    {
        $response = CannedResponse::find($id);

        if (!$response) {
            return $this->notFound('Canned response not found');
        }

        $response->delete();

        return $this->success(null, 'Canned response deleted');
    }

    public function toggleCannedResponse(string $id): JsonResponse
    {
        $response = CannedResponse::find($id);

        if (!$response) {
            return $this->notFound('Canned response not found');
        }

        $response->update(['is_active' => !$response->is_active]);

        return $this->success($response->fresh(), 'Canned response toggled');
    }
}
