<?php

namespace App\Domain\CashierGamification\Controllers\Api;

use App\Domain\CashierGamification\Models\CashierAnomaly;
use App\Domain\CashierGamification\Models\CashierBadge;
use App\Domain\CashierGamification\Models\CashierGamificationSetting;
use App\Domain\CashierGamification\Models\CashierPerformanceSnapshot;
use App\Domain\CashierGamification\Models\CashierShiftReport;
use App\Domain\CashierGamification\Requests\ManageBadgeRequest;
use App\Domain\CashierGamification\Requests\ReviewAnomalyRequest;
use App\Domain\CashierGamification\Requests\UpdateGamificationSettingsRequest;
use App\Domain\CashierGamification\Resources\CashierAnomalyResource;
use App\Domain\CashierGamification\Resources\CashierBadgeAwardResource;
use App\Domain\CashierGamification\Resources\CashierBadgeResource;
use App\Domain\CashierGamification\Resources\CashierGamificationSettingResource;
use App\Domain\CashierGamification\Resources\CashierPerformanceSnapshotResource;
use App\Domain\CashierGamification\Resources\CashierShiftReportResource;
use App\Domain\CashierGamification\Services\CashierAnomalyService;
use App\Domain\CashierGamification\Services\CashierBadgeService;
use App\Domain\CashierGamification\Services\CashierPerformanceService;
use App\Domain\CashierGamification\Services\CashierShiftReportService;
use App\Domain\PosTerminal\Models\PosSession;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashierGamificationController extends BaseApiController
{
    public function __construct(
        private readonly CashierPerformanceService $performanceService,
        private readonly CashierAnomalyService $anomalyService,
        private readonly CashierBadgeService $badgeService,
        private readonly CashierShiftReportService $shiftReportService,
    ) {}

    // ─── Leaderboard ─────────────────────────────────────────

    public function leaderboard(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $paginator = $this->performanceService->getLeaderboard(
            $storeId,
            $request->only(['date', 'period_type', 'sort_by', 'sort_dir']),
            (int) $request->get('per_page', 20),
        );

        $result = $paginator->toArray();
        $result['data'] = CashierPerformanceSnapshotResource::collection($paginator->items())->resolve();
        return $this->success($result);
    }

    public function cashierHistory(Request $request, string $cashierId): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $paginator = $this->performanceService->getCashierHistory(
            $storeId,
            $cashierId,
            $request->only(['period_type', 'date_from', 'date_to']),
            (int) $request->get('per_page', 20),
        );

        $result = $paginator->toArray();
        $result['data'] = CashierPerformanceSnapshotResource::collection($paginator->items())->resolve();
        return $this->success($result);
    }

    // ─── Generate Snapshot (manual trigger or session-close hook) ─

    public function generateSnapshot(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $sessionId = $request->input('pos_session_id');

        if (!$sessionId) {
            return $this->error('pos_session_id is required.', 422);
        }

        $session = PosSession::where('store_id', $storeId)->find($sessionId);
        if (!$session) {
            return $this->notFound('POS session not found.');
        }

        try {
            // Generate shift snapshot
            $snapshot = $this->performanceService->calculateShiftSnapshot($storeId, $session);

            // Generate daily aggregate
            $this->performanceService->calculateDailySnapshot($storeId, $snapshot->cashier_id, $snapshot->date);

            // Detect anomalies
            $anomalies = $this->anomalyService->detectAnomalies($storeId, $snapshot);

            // Populate anomaly_flags on the snapshot
            if (!empty($anomalies)) {
                $snapshot->update([
                    'anomaly_flags' => array_map(fn ($a) => $a->anomaly_type, $anomalies),
                ]);
            }

            // Evaluate badges
            $dailySnapshot = CashierPerformanceSnapshot::where('store_id', $storeId)
                ->where('cashier_id', $snapshot->cashier_id)
                ->where('date', $snapshot->date)
                ->where('period_type', 'daily')
                ->first();

            $badges = [];
            if ($dailySnapshot) {
                $badges = $this->badgeService->evaluateAndAward($storeId, $dailySnapshot);
            }

            // Generate shift report
            $report = $this->shiftReportService->generateReport($storeId, $snapshot);

            return $this->created([
                'snapshot' => new CashierPerformanceSnapshotResource($snapshot),
                'anomalies_detected' => count($anomalies),
                'badges_awarded' => count($badges),
                'shift_report' => new CashierShiftReportResource($report),
            ]);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    // ─── Badges ──────────────────────────────────────────────

    public function badgeDefinitions(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $badges = $this->badgeService->listBadges($storeId);
        return $this->success(CashierBadgeResource::collection($badges)->resolve());
    }

    public function badgeAwards(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $paginator = $this->badgeService->listAwards(
            $storeId,
            $request->only(['cashier_id', 'badge_id']),
            (int) $request->get('per_page', 20),
        );

        $result = $paginator->toArray();
        $result['data'] = CashierBadgeAwardResource::collection($paginator->items())->resolve();
        return $this->success($result);
    }

    public function createBadge(ManageBadgeRequest $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $badge = $this->badgeService->createBadge($storeId, $request->validated());
        return $this->created(new CashierBadgeResource($badge));
    }

    public function updateBadge(ManageBadgeRequest $request, string $badgeId): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $badge = CashierBadge::where('store_id', $storeId)->findOrFail($badgeId);
        $updated = $this->badgeService->updateBadge($badge, $request->validated());
        return $this->success(new CashierBadgeResource($updated));
    }

    public function deleteBadge(Request $request, string $badgeId): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $badge = CashierBadge::where('store_id', $storeId)->findOrFail($badgeId);
        $this->badgeService->deleteBadge($badge);
        return $this->success(null, 'Badge deleted.');
    }

    public function seedBadges(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $count = $this->badgeService->seedDefaultBadges($storeId);
        return $this->created(['badges_seeded' => $count]);
    }

    // ─── Anomalies ───────────────────────────────────────────

    public function anomalies(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $paginator = $this->anomalyService->list(
            $storeId,
            $request->only(['cashier_id', 'severity', 'anomaly_type', 'is_reviewed', 'date_from', 'date_to']),
            (int) $request->get('per_page', 20),
        );

        $result = $paginator->toArray();
        $result['data'] = CashierAnomalyResource::collection($paginator->items())->resolve();
        return $this->success($result);
    }

    public function reviewAnomaly(ReviewAnomalyRequest $request, string $anomalyId): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $anomaly = CashierAnomaly::where('store_id', $storeId)->findOrFail($anomalyId);
        $reviewed = $this->anomalyService->review($anomaly, $request->user()->id, $request->input('review_notes'));
        return $this->success(new CashierAnomalyResource($reviewed));
    }

    // ─── Shift Reports ───────────────────────────────────────

    public function shiftReports(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $paginator = $this->shiftReportService->list(
            $storeId,
            $request->only(['cashier_id', 'risk_level', 'date_from', 'date_to']),
            (int) $request->get('per_page', 20),
        );

        $result = $paginator->toArray();
        $result['data'] = CashierShiftReportResource::collection($paginator->items())->resolve();
        return $this->success($result);
    }

    public function showShiftReport(Request $request, string $reportId): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $report = CashierShiftReport::where('store_id', $storeId)->findOrFail($reportId);
        return $this->success(new CashierShiftReportResource($report->load('cashier:id,name,email')));
    }

    public function markShiftReportSent(Request $request, string $reportId): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $report = CashierShiftReport::where('store_id', $storeId)->findOrFail($reportId);
        $updated = $this->shiftReportService->markSent($report);
        return $this->success(new CashierShiftReportResource($updated));
    }

    // ─── Settings ────────────────────────────────────────────

    public function settings(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $settings = CashierGamificationSetting::firstOrCreate(
            ['store_id' => $storeId],
            [
                'leaderboard_enabled' => true,
                'badges_enabled' => true,
                'anomaly_detection_enabled' => true,
                'shift_reports_enabled' => true,
                'auto_generate_on_session_close' => true,
                'anomaly_z_score_threshold' => 2.0,
                'risk_score_void_weight' => 30,
                'risk_score_no_sale_weight' => 25,
                'risk_score_discount_weight' => 25,
                'risk_score_price_override_weight' => 20,
            ]
        );
        return $this->success(new CashierGamificationSettingResource($settings));
    }

    public function updateSettings(UpdateGamificationSettingsRequest $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $settings = CashierGamificationSetting::firstOrCreate(
            ['store_id' => $storeId],
            ['leaderboard_enabled' => true]
        );
        $settings->update($request->validated());
        return $this->success(new CashierGamificationSettingResource($settings->fresh()));
    }
}
