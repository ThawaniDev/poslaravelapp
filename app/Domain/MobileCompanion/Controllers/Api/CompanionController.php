<?php

namespace App\Domain\MobileCompanion\Controllers\Api;

use App\Domain\MobileCompanion\Requests\LogAppEventRequest;
use App\Domain\MobileCompanion\Requests\RegisterSessionRequest;
use App\Domain\MobileCompanion\Requests\UpdateAppPreferencesRequest;
use App\Domain\MobileCompanion\Requests\UpdateQuickActionsRequest;
use App\Domain\MobileCompanion\Services\CompanionService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanionController extends BaseApiController
{
    public function __construct(
        private readonly CompanionService $companionService,
    ) {}

    public function quickStats(): JsonResponse
    {
        $storeId = auth()->user()->store_id;
        $result = $this->companionService->quickStats($storeId);

        return $this->success($result, __('companion.quick_stats_retrieved'));
    }

    public function dashboard(): JsonResponse
    {
        $storeId = auth()->user()->store_id;
        $result = $this->companionService->getDashboard($storeId);

        return $this->success($result, __('companion.dashboard_retrieved'));
    }

    public function branches(): JsonResponse
    {
        $storeId = auth()->user()->store_id;
        $result = $this->companionService->getBranches($storeId);

        return $this->success($result, __('companion.branches_retrieved'));
    }

    public function salesSummary(Request $request): JsonResponse
    {
        $storeId = auth()->user()->store_id;

        $from = $request->query('from');
        $to = $request->query('to');

        // Support period shorthand (today, week, month)
        if (! $from && $request->query('period')) {
            $period = $request->query('period');
            $to = now()->endOfDay()->toDateTimeString();
            $from = match ($period) {
                'week' => now()->startOfWeek()->toDateTimeString(),
                'month' => now()->startOfMonth()->toDateTimeString(),
                default => now()->startOfDay()->toDateTimeString(), // 'today'
            };
        }

        $result = $this->companionService->getSalesSummary($storeId, $from, $to);

        return $this->success($result, __('companion.sales_summary_retrieved'));
    }

    public function activeOrders(): JsonResponse
    {
        $storeId = auth()->user()->store_id;
        $result = $this->companionService->getActiveOrders($storeId);

        return $this->success($result, __('companion.active_orders_retrieved'));
    }

    public function inventoryAlerts(): JsonResponse
    {
        $storeId = auth()->user()->store_id;
        $result = $this->companionService->getInventoryAlerts($storeId);

        return $this->success($result, __('companion.inventory_alerts_retrieved'));
    }

    public function activeStaff(): JsonResponse
    {
        $storeId = auth()->user()->store_id;
        $result = $this->companionService->getActiveStaff($storeId);

        return $this->success($result, __('companion.active_staff_retrieved'));
    }

    public function toggleAvailability(Request $request): JsonResponse
    {
        $storeId = auth()->user()->store_id;
        $isActive = (bool) $request->input('is_active', true);
        $result = $this->companionService->toggleStoreAvailability($storeId, $isActive);

        return $this->success($result, __('companion.availability_updated'));
    }

    public function registerSession(RegisterSessionRequest $request): JsonResponse
    {
        $user = auth()->user();
        $result = $this->companionService->registerSession(
            $user->store_id,
            $user->id,
            $request->validated(),
        );

        return $this->created($result, __('companion.session_registered'));
    }

    public function endSession(string $sessionId): JsonResponse
    {
        $result = $this->companionService->endSession($sessionId);

        return $this->success($result, __('companion.session_ended'));
    }

    public function listSessions(): JsonResponse
    {
        $storeId = auth()->user()->store_id;
        $result = $this->companionService->listSessions($storeId);

        return $this->success($result, __('companion.sessions_retrieved'));
    }

    public function getPreferences(): JsonResponse
    {
        $userId = auth()->id();
        $result = $this->companionService->getAppPreferences($userId);

        return $this->success($result, __('companion.preferences_retrieved'));
    }

    public function updatePreferences(UpdateAppPreferencesRequest $request): JsonResponse
    {
        $userId = auth()->id();
        $result = $this->companionService->updateAppPreferences($userId, $request->validated());

        return $this->success($result, __('companion.preferences_updated'));
    }

    public function getQuickActions(): JsonResponse
    {
        $storeId = auth()->user()->store_id;
        $result = $this->companionService->getQuickActions($storeId);

        return $this->success($result, __('companion.quick_actions_retrieved'));
    }

    public function updateQuickActions(UpdateQuickActionsRequest $request): JsonResponse
    {
        $storeId = auth()->user()->store_id;
        $result = $this->companionService->updateQuickActions($storeId, $request->validated());

        return $this->success($result, __('companion.quick_actions_updated'));
    }

    public function mobileSummary(): JsonResponse
    {
        $storeId = auth()->user()->store_id;
        $result = $this->companionService->getMobileSummary($storeId);

        return $this->success($result, __('companion.summary_retrieved'));
    }

    public function logEvent(LogAppEventRequest $request): JsonResponse
    {
        $user = auth()->user();
        $result = $this->companionService->logAppEvent(
            $user->store_id,
            $user->id,
            $request->validated(),
        );

        return $this->created($result, __('companion.event_logged'));
    }
}
