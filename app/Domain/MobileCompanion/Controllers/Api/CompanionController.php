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

    /**
     * Resolve the current user's store ID. The companion app is store-specific,
     * so org-level users without an assigned store cannot use these endpoints.
     */
    private function requireStoreId(): string
    {
        $storeId = auth()->user()?->store_id;
        if (! $storeId) {
            abort(response()->json([
                'success' => false,
                'message' => 'A store assignment is required to use the companion app.',
            ], 400));
        }
        return $storeId;
    }

    public function quickStats(): JsonResponse
    {
        $result = $this->companionService->quickStats($this->requireStoreId());

        return $this->success($result, __('companion.quick_stats_retrieved'));
    }

    public function dashboard(): JsonResponse
    {
        $result = $this->companionService->getDashboard($this->requireStoreId());

        return $this->success($result, __('companion.dashboard_retrieved'));
    }

    public function branches(): JsonResponse
    {
        $result = $this->companionService->getBranches($this->requireStoreId());

        return $this->success($result, __('companion.branches_retrieved'));
    }

    public function salesSummary(Request $request): JsonResponse
    {
        $storeId = $this->requireStoreId();

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
        $result = $this->companionService->getActiveOrders($this->requireStoreId());

        return $this->success($result, __('companion.active_orders_retrieved'));
    }

    public function inventoryAlerts(): JsonResponse
    {
        $result = $this->companionService->getInventoryAlerts($this->requireStoreId());

        return $this->success($result, __('companion.inventory_alerts_retrieved'));
    }

    public function activeStaff(): JsonResponse
    {
        $result = $this->companionService->getActiveStaff($this->requireStoreId());

        return $this->success($result, __('companion.active_staff_retrieved'));
    }

    public function toggleAvailability(Request $request): JsonResponse
    {
        $isActive = (bool) $request->input('is_active', true);
        $result = $this->companionService->toggleStoreAvailability($this->requireStoreId(), $isActive);

        return $this->success($result, __('companion.availability_updated'));
    }

    public function registerSession(RegisterSessionRequest $request): JsonResponse
    {
        $user = auth()->user();
        $result = $this->companionService->registerSession(
            $this->requireStoreId(),
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
        $result = $this->companionService->listSessions($this->requireStoreId());

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
        $result = $this->companionService->getQuickActions($this->requireStoreId());

        return $this->success($result, __('companion.quick_actions_retrieved'));
    }

    public function updateQuickActions(UpdateQuickActionsRequest $request): JsonResponse
    {
        $result = $this->companionService->updateQuickActions($this->requireStoreId(), $request->validated());

        return $this->success($result, __('companion.quick_actions_updated'));
    }

    public function mobileSummary(): JsonResponse
    {
        $result = $this->companionService->getMobileSummary($this->requireStoreId());

        return $this->success($result, __('companion.summary_retrieved'));
    }

    public function logEvent(LogAppEventRequest $request): JsonResponse
    {
        $user = auth()->user();
        $result = $this->companionService->logAppEvent(
            $this->requireStoreId(),
            $user->id,
            $request->validated(),
        );

        return $this->created($result, __('companion.event_logged'));
    }
}
