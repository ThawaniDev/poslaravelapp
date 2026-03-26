<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\ContentOnboarding\Models\KnowledgeBaseArticle;
use App\Domain\Support\Enums\TicketStatus;
use App\Domain\Support\Models\CannedResponse;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Models\SupportTicketMessage;
use App\Domain\Support\Services\SupportService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class SupportTicketController extends BaseApiController
{
    public function __construct(
        private readonly SupportService $supportService,
    ) {}

    // ─── Tickets ─────────────────────────────────────────────

    public function listTickets(Request $request): JsonResponse
    {
        $query = SupportTicket::query()
            ->with(['organization:id,name', 'assignedTo:id,name'])
            ->withCount('messages')
            ->orderByDesc('created_at');

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
        if ($request->boolean('sla_breached')) {
            $query->whereNotNull('sla_deadline_at')->where('sla_deadline_at', '<', now());
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

        return $this->success($tickets, __('support.tickets_retrieved'));
    }

    public function createTicket(Request $request): JsonResponse
    {
        $request->validate([
            'organization_id' => 'required|uuid',
            'subject'         => 'required|string|max:255',
            'description'     => 'required|string',
            'category'        => 'required|string|in:billing,technical,zatca,feature_request,general,hardware',
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

        Log::channel('daily')->info('Admin created support ticket', [
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'admin_id' => $request->user()->id,
        ]);

        return $this->created($ticket, __('support.ticket_created'));
    }

    public function showTicket(string $id): JsonResponse
    {
        $ticket = SupportTicket::with([
            'supportTicketMessages' => fn ($q) => $q->orderBy('sent_at'),
            'organization:id,name',
            'store:id,name',
            'assignedTo:id,name',
        ])->find($id);

        if (!$ticket) {
            return $this->notFound(__('support.ticket_not_found'));
        }

        return $this->success($ticket, __('support.ticket_retrieved'));
    }

    public function updateTicket(Request $request, string $id): JsonResponse
    {
        $ticket = SupportTicket::find($id);

        if (!$ticket) {
            return $this->notFound(__('support.ticket_not_found'));
        }

        $request->validate([
            'subject'     => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'category'    => 'sometimes|string|in:billing,technical,zatca,feature_request,general,hardware',
            'priority'    => 'sometimes|string|in:low,medium,high,critical',
        ]);

        $ticket->update($request->only(['subject', 'description', 'category', 'priority']));

        Log::channel('daily')->info('Admin updated support ticket', [
            'ticket_id' => $id,
            'admin_id' => $request->user()->id,
            'changes' => $request->only(['subject', 'description', 'category', 'priority']),
        ]);

        return $this->success($ticket->fresh(), __('support.ticket_updated'));
    }

    public function assignTicket(Request $request, string $id): JsonResponse
    {
        $ticket = SupportTicket::find($id);

        if (!$ticket) {
            return $this->notFound(__('support.ticket_not_found'));
        }

        $request->validate([
            'assigned_to' => 'required|uuid',
        ]);

        $this->supportService->assignTicket($ticket, $request->assigned_to, $request->user());

        return $this->success($ticket->fresh(), __('support.ticket_assigned'));
    }

    public function changeStatus(Request $request, string $id): JsonResponse
    {
        $ticket = SupportTicket::find($id);

        if (!$ticket) {
            return $this->notFound(__('support.ticket_not_found'));
        }

        $request->validate([
            'status' => 'required|string|in:open,in_progress,resolved,closed',
        ]);

        $this->supportService->changeStatus($ticket, TicketStatus::from($request->status), $request->user()->id);

        return $this->success($ticket->fresh(), __('support.status_changed'));
    }

    // ─── Messages ────────────────────────────────────────────

    public function listMessages(string $ticketId): JsonResponse
    {
        $ticket = SupportTicket::find($ticketId);

        if (!$ticket) {
            return $this->notFound(__('support.ticket_not_found'));
        }

        $messages = SupportTicketMessage::where('support_ticket_id', $ticketId)
            ->orderBy('sent_at')
            ->get();

        return $this->success($messages, __('support.messages_retrieved'));
    }

    public function addMessage(Request $request, string $ticketId): JsonResponse
    {
        $ticket = SupportTicket::find($ticketId);

        if (!$ticket) {
            return $this->notFound(__('support.ticket_not_found'));
        }

        $request->validate([
            'message_text'     => 'required|string',
            'is_internal_note' => 'sometimes|boolean',
            'attachments'      => 'sometimes|array',
        ]);

        $message = $this->supportService->adminAddMessage(
            $ticket,
            $request->user(),
            $request->message_text,
            $request->boolean('is_internal_note', false),
            $request->input('attachments'),
        );

        return $this->created($message, __('support.message_sent'));
    }

    // ─── Admin Stats ─────────────────────────────────────────

    public function stats(): JsonResponse
    {
        $stats = $this->supportService->getAdminStats();

        return $this->success($stats, __('support.stats_retrieved'));
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

        return $this->success($query->get(), __('support.canned_responses_retrieved'));
    }

    public function createCannedResponse(Request $request): JsonResponse
    {
        $request->validate([
            'title'    => 'required|string|max:255',
            'body'     => 'required|string',
            'body_ar'  => 'required|string',
            'shortcut' => 'sometimes|string|max:50',
            'category' => 'sometimes|string|in:billing,technical,zatca,feature_request,general,hardware',
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

        return $this->created($response, __('support.canned_response_created'));
    }

    public function showCannedResponse(string $id): JsonResponse
    {
        $response = CannedResponse::find($id);

        if (!$response) {
            return $this->notFound(__('support.canned_response_not_found'));
        }

        return $this->success($response, __('support.canned_response_retrieved'));
    }

    public function updateCannedResponse(Request $request, string $id): JsonResponse
    {
        $response = CannedResponse::find($id);

        if (!$response) {
            return $this->notFound(__('support.canned_response_not_found'));
        }

        $request->validate([
            'title'    => 'sometimes|string|max:255',
            'body'     => 'sometimes|string',
            'body_ar'  => 'sometimes|string',
            'shortcut' => 'sometimes|string|max:50',
            'category' => 'sometimes|string|in:billing,technical,zatca,feature_request,general,hardware',
        ]);

        $response->update($request->only(['title', 'body', 'body_ar', 'shortcut', 'category']));

        return $this->success($response->fresh(), __('support.canned_response_updated'));
    }

    public function destroyCannedResponse(string $id): JsonResponse
    {
        $response = CannedResponse::find($id);

        if (!$response) {
            return $this->notFound(__('support.canned_response_not_found'));
        }

        $response->delete();

        return $this->success(null, __('support.canned_response_deleted'));
    }

    public function toggleCannedResponse(string $id): JsonResponse
    {
        $response = CannedResponse::find($id);

        if (!$response) {
            return $this->notFound(__('support.canned_response_not_found'));
        }

        $response->update(['is_active' => !$response->is_active]);

        return $this->success($response->fresh(), __('support.canned_response_toggled'));
    }

    // ─── Knowledge Base Articles (admin CRUD) ────────────────

    public function listKbArticles(Request $request): JsonResponse
    {
        $query = KnowledgeBaseArticle::query()->orderBy('sort_order');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('is_published')) {
            $query->where('is_published', filter_var($request->is_published, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                  ->orWhere('title_ar', 'like', "%{$s}%");
            });
        }

        return $this->success($query->get(), __('support.kb_articles_retrieved'));
    }

    public function createKbArticle(Request $request): JsonResponse
    {
        $request->validate([
            'title'    => 'required|string|max:255',
            'title_ar' => 'required|string|max:255',
            'slug'     => 'required|string|max:100|unique:knowledge_base_articles,slug',
            'body'     => 'required|string',
            'body_ar'  => 'required|string',
            'category' => 'required|string|in:getting_started,pos_usage,inventory,delivery,billing,troubleshooting',
            'is_published' => 'sometimes|boolean',
            'sort_order'   => 'sometimes|integer|min:0',
        ]);

        $article = KnowledgeBaseArticle::create($request->only([
            'title', 'title_ar', 'slug', 'body', 'body_ar', 'category', 'is_published', 'sort_order',
        ]));

        Log::channel('daily')->info('Admin created KB article', [
            'article_id' => $article->id,
            'admin_id' => $request->user()->id,
        ]);

        return $this->created($article, __('support.kb_article_created'));
    }

    public function showKbArticle(string $id): JsonResponse
    {
        $article = KnowledgeBaseArticle::find($id);

        if (!$article) {
            return $this->notFound(__('support.kb_article_not_found'));
        }

        return $this->success($article, __('support.kb_article_retrieved'));
    }

    public function updateKbArticle(Request $request, string $id): JsonResponse
    {
        $article = KnowledgeBaseArticle::find($id);

        if (!$article) {
            return $this->notFound(__('support.kb_article_not_found'));
        }

        $request->validate([
            'title'    => 'sometimes|string|max:255',
            'title_ar' => 'sometimes|string|max:255',
            'slug'     => 'sometimes|string|max:100|unique:knowledge_base_articles,slug,' . $id,
            'body'     => 'sometimes|string',
            'body_ar'  => 'sometimes|string',
            'category' => 'sometimes|string|in:getting_started,pos_usage,inventory,delivery,billing,troubleshooting',
            'is_published' => 'sometimes|boolean',
            'sort_order'   => 'sometimes|integer|min:0',
        ]);

        $article->update($request->only([
            'title', 'title_ar', 'slug', 'body', 'body_ar', 'category', 'is_published', 'sort_order',
        ]));

        Log::channel('daily')->info('Admin updated KB article', [
            'article_id' => $id,
            'admin_id' => $request->user()->id,
        ]);

        return $this->success($article->fresh(), __('support.kb_article_updated'));
    }

    public function destroyKbArticle(string $id): JsonResponse
    {
        $article = KnowledgeBaseArticle::find($id);

        if (!$article) {
            return $this->notFound(__('support.kb_article_not_found'));
        }

        $article->delete();

        Log::channel('daily')->info('Admin deleted KB article', [
            'article_id' => $id,
            'admin_id' => request()->user()->id,
        ]);

        return $this->success(null, __('support.kb_article_deleted'));
    }
}
