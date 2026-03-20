<?php

namespace App\Domain\Support\Controllers\Api;

use App\Domain\Support\Services\SupportService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportController extends BaseApiController
{
    public function __construct(
        private readonly SupportService $supportService,
    ) {}

    /**
     * GET /api/v2/support/tickets
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|string|in:open,in_progress,resolved,closed',
            'category' => 'nullable|string|in:billing,technical,zatca,feature_request,general',
            'priority' => 'nullable|string|in:low,medium,high,critical',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $tickets = $this->supportService->listTickets(
            $request->user()->id,
            $request->user()->store_id,
            $request->only(['status', 'category', 'priority', 'per_page']),
        );

        return $this->success($tickets, __('support.tickets_retrieved'));
    }

    /**
     * GET /api/v2/support/tickets/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $ticket = $this->supportService->getTicket(
            $id,
            $request->user()->id,
            $request->user()->store_id,
        );

        if (!$ticket) {
            return $this->notFound(__('support.ticket_not_found'));
        }

        return $this->success($ticket, __('support.ticket_retrieved'));
    }

    /**
     * POST /api/v2/support/tickets
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => 'required|string|in:billing,technical,zatca,feature_request,general',
            'priority' => 'nullable|string|in:low,medium,high,critical',
            'subject' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
        ]);

        $ticket = $this->supportService->createTicket(
            $request->user()->id,
            $request->user()->store_id,
            $request->user()->organization_id ?? null,
            $validated,
        );

        return $this->created($ticket, __('support.ticket_created'));
    }

    /**
     * POST /api/v2/support/tickets/{id}/messages
     */
    public function addMessage(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:5000',
            'attachments' => 'nullable|array',
        ]);

        $message = $this->supportService->addMessage(
            $id,
            $request->user()->id,
            $request->user()->store_id,
            $validated,
        );

        if (!$message) {
            return $this->notFound(__('support.ticket_not_found'));
        }

        return $this->created($message, __('support.message_sent'));
    }

    /**
     * PUT /api/v2/support/tickets/{id}/close
     */
    public function close(Request $request, string $id): JsonResponse
    {
        $closed = $this->supportService->closeTicket(
            $id,
            $request->user()->id,
            $request->user()->store_id,
        );

        if (!$closed) {
            return $this->notFound(__('support.ticket_not_found'));
        }

        return $this->success(null, __('support.ticket_closed'));
    }

    /**
     * GET /api/v2/support/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $stats = $this->supportService->getStats(
            $request->user()->id,
            $request->user()->store_id,
        );

        return $this->success($stats, __('support.stats_retrieved'));
    }
}
