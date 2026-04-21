<?php

namespace App\Domain\Announcement\Controllers\Api;

use App\Domain\Announcement\Models\PaymentReminder;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderPaymentReminderController extends BaseApiController
{
    /**
     * GET /api/v2/payment-reminders
     *
     * Lists payment reminders for the authenticated user's organization
     * (most recent first). Optional filters: ?type=upcoming|overdue,
     * ?channel=email|sms|push, ?per_page=25.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->organization_id) {
            return $this->error('Organization context required', 400);
        }

        $subscriptionIds = StoreSubscription::where('organization_id', $user->organization_id)
            ->pluck('id');

        if ($subscriptionIds->isEmpty()) {
            return $this->success([
                'reminders' => [],
                'summary' => [
                    'total' => 0,
                    'upcoming' => 0,
                    'overdue' => 0,
                ],
            ]);
        }

        $query = PaymentReminder::query()
            ->whereIn('store_subscription_id', $subscriptionIds)
            ->with('storeSubscription.subscriptionPlan');

        if ($type = $request->query('type')) {
            $query->where('reminder_type', $type);
        }

        if ($channel = $request->query('channel')) {
            $query->where('channel', $channel);
        }

        $perPage = (int) $request->query('per_page', 25);
        $perPage = max(1, min(100, $perPage));

        $reminders = $query->orderByDesc('sent_at')->paginate($perPage);

        $summary = [
            'total' => PaymentReminder::whereIn('store_subscription_id', $subscriptionIds)->count(),
            'upcoming' => PaymentReminder::whereIn('store_subscription_id', $subscriptionIds)
                ->where('reminder_type', 'upcoming')->count(),
            'overdue' => PaymentReminder::whereIn('store_subscription_id', $subscriptionIds)
                ->where('reminder_type', 'overdue')->count(),
        ];

        return $this->success([
            'reminders' => $reminders->getCollection()->map(fn (PaymentReminder $r) => [
                'id' => $r->id,
                'reminder_type' => $r->reminder_type->value,
                'channel' => $r->channel->value,
                'sent_at' => $r->sent_at?->toIso8601String(),
                'subscription' => $r->storeSubscription ? [
                    'id' => $r->storeSubscription->id,
                    'status' => $r->storeSubscription->status?->value,
                    'plan_name' => $r->storeSubscription->subscriptionPlan?->name,
                    'current_period_end' => $r->storeSubscription->current_period_end?->toIso8601String(),
                ] : null,
            ])->all(),
            'meta' => [
                'current_page' => $reminders->currentPage(),
                'last_page' => $reminders->lastPage(),
                'per_page' => $reminders->perPage(),
                'total' => $reminders->total(),
            ],
            'summary' => $summary,
        ]);
    }
}
