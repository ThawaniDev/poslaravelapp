<?php

namespace App\Domain\Notification\Controllers\Api;

use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Jobs\DispatchNotificationJob;
use App\Domain\Notification\Models\NotificationTemplate;
use App\Domain\Notification\Resources\NotificationTemplateResource;
use App\Domain\Notification\Services\NotificationTemplateService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationTemplateController extends BaseApiController
{
    public function __construct(
        private readonly NotificationTemplateService $templateService,
    ) {}

    /**
     * GET /api/v2/notification-templates
     * List active templates, optionally filtered by event_key or channel.
     */
    public function index(Request $request): JsonResponse
    {
        $query = NotificationTemplate::query();

        if ($request->has('event_key')) {
            $query->where('event_key', $request->input('event_key'));
        }

        if ($request->has('channel')) {
            $query->where('channel', $request->input('channel'));
        }

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        $templates = $query->orderBy('event_key')->orderBy('channel')->get();

        return $this->success(NotificationTemplateResource::collection($templates));
    }

    /**
     * GET /api/v2/notification-templates/{id}
     * Show a single template.
     */
    public function show(string $id): JsonResponse
    {
        $template = NotificationTemplate::find($id);

        if (!$template) {
            return $this->notFound(__('notifications.template_not_found'));
        }

        return $this->success(new NotificationTemplateResource($template));
    }

    /**
     * POST /api/v2/notification-templates/render
     * Render a template with provided variables, for client preview.
     */
    public function render(Request $request): JsonResponse
    {
        $request->validate([
            'event_key' => 'required|string|max:255',
            'channel' => 'required|string|max:50',
            'variables' => 'sometimes|array',
            'locale' => 'sometimes|string|in:en,ar',
        ]);

        $channel = NotificationChannel::tryFrom($request->input('channel'));
        if (!$channel) {
            return $this->error(__('notifications.invalid_channel'), 422);
        }

        $result = $this->templateService->render(
            $request->input('event_key'),
            $channel,
            $request->input('variables', []),
            $request->input('locale', 'en'),
        );

        if (!$result) {
            return $this->notFound(__('notifications.template_not_found'));
        }

        return $this->success($result);
    }

    /**
     * POST /api/v2/notification-templates/dispatch
     * Dispatch a notification through the queue.
     */
    public function dispatch(Request $request): JsonResponse
    {
        $request->validate([
            'event_key' => 'required|string|max:255',
            'channel' => 'required|string|max:50',
            'recipient' => 'required|string|max:500',
            'variables' => 'sometimes|array',
            'locale' => 'sometimes|string|in:en,ar',
        ]);

        $channel = NotificationChannel::tryFrom($request->input('channel'));
        if (!$channel) {
            return $this->error(__('notifications.invalid_channel'), 422);
        }

        DispatchNotificationJob::dispatch(
            eventKey: $request->input('event_key'),
            channel: $channel,
            recipient: $request->input('recipient'),
            variables: $request->input('variables', []),
            locale: $request->input('locale', 'en'),
        );

        return $this->success(null, __('notifications.dispatch_queued'));
    }

    /**
     * GET /api/v2/notification-templates/events
     * Get the full event catalog.
     */
    public function events(): JsonResponse
    {
        return $this->success(NotificationTemplateService::eventCatalog());
    }

    /**
     * GET /api/v2/notification-templates/events/{eventKey}/variables
     * Get available variables for a specific event.
     */
    public function eventVariables(string $eventKey): JsonResponse
    {
        $events = NotificationTemplateService::allEvents();

        if (!isset($events[$eventKey])) {
            return $this->notFound(__('notifications.event_not_found'));
        }

        return $this->success([
            'event_key' => $eventKey,
            'variables' => $events[$eventKey]['variables'],
            'description' => $events[$eventKey]['description'],
        ]);
    }
}
