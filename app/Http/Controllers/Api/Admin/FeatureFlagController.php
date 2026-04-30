<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\SystemConfig\Models\ABTest;
use App\Domain\SystemConfig\Models\ABTestEvent;
use App\Domain\SystemConfig\Models\ABTestVariant;
use App\Domain\SystemConfig\Models\FeatureFlag;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeatureFlagController extends BaseApiController
{
    // ═══════════════════════════════════════════════════════════
    //  Feature Flag Stats
    // ═══════════════════════════════════════════════════════════

    /**
     * GET /admin/feature-flags/stats
     */
    public function flagStats(): JsonResponse
    {
        $totalFlags = FeatureFlag::count();
        $enabledFlags = FeatureFlag::where('is_enabled', true)->count();
        $disabledFlags = $totalFlags - $enabledFlags;
        $withTargeting = FeatureFlag::where(function ($q) {
            $q->whereNotNull('target_plan_ids')
              ->orWhereNotNull('target_store_ids');
        })->count();
        $partialRollout = FeatureFlag::where('is_enabled', true)
            ->where('rollout_percentage', '<', 100)
            ->count();

        // A/B tests
        $totalTests = ABTest::count();
        $runningTests = ABTest::where('status', 'running')->count();
        $completedTests = ABTest::where('status', 'completed')->count();
        $totalEvents = ABTestEvent::count();

        return $this->success([
            'flags' => [
                'total' => $totalFlags,
                'enabled' => $enabledFlags,
                'disabled' => $disabledFlags,
                'with_targeting' => $withTargeting,
                'partial_rollout' => $partialRollout,
            ],
            'ab_tests' => [
                'total' => $totalTests,
                'running' => $runningTests,
                'completed' => $completedTests,
                'total_events' => $totalEvents,
            ],
        ], 'Feature flag stats retrieved');
    }

    // ═══════════════════════════════════════════════════════════
    //  Feature Flags CRUD
    // ═══════════════════════════════════════════════════════════

    /**
     * GET /admin/feature-flags
     */
    public function index(Request $request): JsonResponse
    {
        $query = FeatureFlag::query()->orderBy('flag_key');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('flag_key', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_enabled')) {
            $query->where('is_enabled', filter_var($request->input('is_enabled'), FILTER_VALIDATE_BOOLEAN));
        }

        $flags = $query->get();

        return $this->success([
            'flags' => $flags,
            'total' => $flags->count(),
        ], 'Feature flags retrieved');
    }

    /**
     * POST /admin/feature-flags
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'flag_key' => 'required|string|max:50|unique:feature_flags,flag_key',
            'description' => 'nullable|string|max:255',
            'is_enabled' => 'boolean',
            'rollout_percentage' => 'integer|min:0|max:100',
            'target_plan_ids' => 'nullable|array',
            'target_store_ids' => 'nullable|array',
        ]);

        $flag = FeatureFlag::create([
            'flag_key' => $request->input('flag_key'),
            'description' => $request->input('description'),
            'is_enabled' => $request->boolean('is_enabled', false),
            'rollout_percentage' => $request->input('rollout_percentage', 100),
            'target_plan_ids' => $request->input('target_plan_ids'),
            'target_store_ids' => $request->input('target_store_ids'),
        ]);

        return $this->created($flag, 'Feature flag created');
    }

    /**
     * GET /admin/feature-flags/{id}
     */
    public function show(string $id): JsonResponse
    {
        $flag = FeatureFlag::with('abTests.variants')->find($id);

        if (!$flag) {
            return $this->notFound('Feature flag not found');
        }

        return $this->success($flag, 'Feature flag details');
    }

    /**
     * PUT /admin/feature-flags/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $flag = FeatureFlag::find($id);

        if (!$flag) {
            return $this->notFound('Feature flag not found');
        }

        $request->validate([
            'flag_key' => "sometimes|string|max:50|unique:feature_flags,flag_key,{$id}",
            'description' => 'nullable|string|max:255',
            'is_enabled' => 'boolean',
            'rollout_percentage' => 'integer|min:0|max:100',
            'target_plan_ids' => 'nullable|array',
            'target_store_ids' => 'nullable|array',
        ]);

        $flag->update($request->only([
            'flag_key', 'description', 'is_enabled',
            'rollout_percentage', 'target_plan_ids', 'target_store_ids',
        ]));

        return $this->success($flag->fresh(), 'Feature flag updated');
    }

    /**
     * DELETE /admin/feature-flags/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $flag = FeatureFlag::find($id);

        if (!$flag) {
            return $this->notFound('Feature flag not found');
        }

        // FK SET NULL is bypassed in tests (session_replication_role=replica); apply manually.
        \App\Domain\SystemConfig\Models\ABTest::where('feature_flag_id', $flag->id)->update(['feature_flag_id' => null]);
        $flag->delete();

        return $this->success(null, 'Feature flag deleted');
    }

    /**
     * POST /admin/feature-flags/{id}/toggle
     */
    public function toggle(string $id): JsonResponse
    {
        $flag = FeatureFlag::find($id);

        if (!$flag) {
            return $this->notFound('Feature flag not found');
        }

        $flag->update(['is_enabled' => !$flag->is_enabled]);

        return $this->success($flag->fresh(), 'Feature flag toggled');
    }

    // ═══════════════════════════════════════════════════════════
    //  A/B Tests CRUD
    // ═══════════════════════════════════════════════════════════

    /**
     * GET /admin/ab-tests
     */
    public function listTests(Request $request): JsonResponse
    {
        $query = ABTest::with('variants', 'featureFlag')->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('feature_flag_id')) {
            $query->where('feature_flag_id', $request->input('feature_flag_id'));
        }

        $tests = $query->paginate($request->integer('per_page', 15));

        return $this->success([
            'tests' => $tests->items(),
            'total' => $tests->total(),
            'current_page' => $tests->currentPage(),
            'last_page' => $tests->lastPage(),
        ], 'A/B tests retrieved');
    }

    /**
     * POST /admin/ab-tests
     */
    public function createTest(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'feature_flag_id' => 'nullable|uuid|exists:feature_flags,id',
            'metric_key' => 'nullable|string|max:100',
            'traffic_percentage' => 'integer|min:1|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'variants' => 'sometimes|array|min:2',
            'variants.*.variant_key' => 'required_with:variants|string|max:50',
            'variants.*.variant_label' => 'nullable|string|max:150',
            'variants.*.weight' => 'integer|min:0|max:100',
            'variants.*.is_control' => 'boolean',
            'variants.*.metadata' => 'nullable|array',
        ]);

        $test = ABTest::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'feature_flag_id' => $request->input('feature_flag_id'),
            'metric_key' => $request->input('metric_key'),
            'traffic_percentage' => $request->input('traffic_percentage', 100),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'status' => 'draft',
        ]);

        if ($request->has('variants')) {
            foreach ($request->input('variants') as $v) {
                $test->variants()->create([
                    'variant_key' => $v['variant_key'],
                    'variant_label' => $v['variant_label'] ?? null,
                    'weight' => $v['weight'] ?? 50,
                    'is_control' => $v['is_control'] ?? false,
                    'metadata' => $v['metadata'] ?? null,
                ]);
            }
        }

        return $this->created(
            $test->load('variants', 'featureFlag'),
            'A/B test created'
        );
    }

    /**
     * GET /admin/ab-tests/{id}
     */
    public function showTest(string $id): JsonResponse
    {
        $test = ABTest::with('variants', 'featureFlag')->find($id);

        if (!$test) {
            return $this->notFound('A/B test not found');
        }

        return $this->success($test, 'A/B test details');
    }

    /**
     * PUT /admin/ab-tests/{id}
     */
    public function updateTest(Request $request, string $id): JsonResponse
    {
        $test = ABTest::find($id);

        if (!$test) {
            return $this->notFound('A/B test not found');
        }

        if ($test->status === 'running') {
            return $this->error('Cannot update a running test. Stop it first.', 422);
        }

        $request->validate([
            'name' => 'sometimes|string|max:150',
            'description' => 'nullable|string',
            'feature_flag_id' => 'nullable|uuid|exists:feature_flags,id',
            'metric_key' => 'nullable|string|max:100',
            'traffic_percentage' => 'integer|min:1|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $test->update($request->only([
            'name', 'description', 'feature_flag_id',
            'metric_key', 'traffic_percentage', 'start_date', 'end_date',
        ]));

        return $this->success($test->fresh()->load('variants', 'featureFlag'), 'A/B test updated');
    }

    /**
     * DELETE /admin/ab-tests/{id}
     */
    public function destroyTest(string $id): JsonResponse
    {
        $test = ABTest::find($id);

        if (!$test) {
            return $this->notFound('A/B test not found');
        }

        if ($test->status === 'running') {
            return $this->error('Cannot delete a running test. Stop it first.', 422);
        }

        // FK cascade is bypassed in tests (session_replication_role=replica); delete children explicitly.
        $test->variants()->delete();
        $test->delete();

        return $this->success(null, 'A/B test deleted');
    }

    /**
     * POST /admin/ab-tests/{id}/start
     */
    public function startTest(string $id): JsonResponse
    {
        $test = ABTest::with('variants')->find($id);

        if (!$test) {
            return $this->notFound('A/B test not found');
        }

        if ($test->status === 'running') {
            return $this->error('Test is already running', 422);
        }

        if ($test->variants->count() < 2) {
            return $this->error('A/B test requires at least 2 variants to start', 422);
        }

        $test->update([
            'status' => 'running',
            'start_date' => $test->start_date ?? now()->toDateString(),
        ]);

        return $this->success($test->fresh()->load('variants'), 'A/B test started');
    }

    /**
     * POST /admin/ab-tests/{id}/stop
     */
    public function stopTest(string $id): JsonResponse
    {
        $test = ABTest::find($id);

        if (!$test) {
            return $this->notFound('A/B test not found');
        }

        if ($test->status !== 'running') {
            return $this->error('Only running tests can be stopped', 422);
        }

        $test->update([
            'status' => 'completed',
            'end_date' => now()->toDateString(),
        ]);

        return $this->success($test->fresh()->load('variants'), 'A/B test stopped');
    }

    /**
     * GET /admin/ab-tests/{id}/results
     */
    public function testResults(string $id): JsonResponse
    {
        $test = ABTest::with('variants', 'featureFlag')->find($id);

        if (!$test) {
            return $this->notFound('A/B test not found');
        }

        // Aggregate real impressions and conversions per variant
        $eventCounts = ABTestEvent::where('ab_test_id', $test->id)
            ->selectRaw("
                variant_id,
                SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as impressions,
                SUM(CASE WHEN event_type = 'conversion' THEN 1 ELSE 0 END) as conversions
            ")
            ->groupBy('variant_id')
            ->get()
            ->keyBy('variant_id');

        $variantResults = $test->variants->map(function ($v) use ($eventCounts) {
            $counts = $eventCounts->get($v->id);
            $impressions = (int) ($counts->impressions ?? 0);
            $conversions = (int) ($counts->conversions ?? 0);

            return [
                'variant_key' => $v->variant_key,
                'variant_label' => $v->variant_label,
                'is_control' => $v->is_control,
                'weight' => $v->weight,
                'impressions' => $impressions,
                'conversions' => $conversions,
                'conversion_rate' => $impressions > 0
                    ? round(($conversions / $impressions) * 100, 2)
                    : 0.0,
            ];
        });

        // Determine winner: variant with highest conversion rate (if enough data)
        $winner = null;
        $confidence = 0.0;
        $controlResult = $variantResults->firstWhere('is_control', true);
        $bestNonControl = $variantResults->where('is_control', false)
            ->sortByDesc('conversion_rate')
            ->first();

        if ($controlResult && $bestNonControl
            && $controlResult['impressions'] >= 30 && $bestNonControl['impressions'] >= 30
        ) {
            if ($bestNonControl['conversion_rate'] > $controlResult['conversion_rate']) {
                $winner = $bestNonControl['variant_key'];
                // Simplified confidence based on sample size
                $totalImpressions = $controlResult['impressions'] + $bestNonControl['impressions'];
                $confidence = min(99.0, round(50 + ($totalImpressions / 20), 1));
            }
        }

        return $this->success([
            'test' => $test,
            'results' => $variantResults->values(),
            'winner' => $winner,
            'confidence' => $confidence,
        ], 'A/B test results');
    }

    // ═══════════════════════════════════════════════════════════
    //  A/B Test Variants
    // ═══════════════════════════════════════════════════════════

    /**
     * POST /admin/ab-tests/{testId}/variants
     */
    public function addVariant(Request $request, string $testId): JsonResponse
    {
        $test = ABTest::find($testId);

        if (!$test) {
            return $this->notFound('A/B test not found');
        }

        if ($test->status === 'running') {
            return $this->error('Cannot add variants to a running test', 422);
        }

        $request->validate([
            'variant_key' => 'required|string|max:50',
            'variant_label' => 'nullable|string|max:150',
            'weight' => 'integer|min:0|max:100',
            'is_control' => 'boolean',
            'metadata' => 'nullable|array',
        ]);

        // Check for duplicate key
        $exists = $test->variants()->where('variant_key', $request->input('variant_key'))->exists();
        if ($exists) {
            return $this->error('Variant key already exists in this test', 422);
        }

        $variant = $test->variants()->create([
            'variant_key' => $request->input('variant_key'),
            'variant_label' => $request->input('variant_label'),
            'weight' => $request->input('weight', 50),
            'is_control' => $request->boolean('is_control', false),
            'metadata' => $request->input('metadata'),
        ]);

        return $this->created($variant, 'Variant added');
    }

    /**
     * DELETE /admin/ab-tests/{testId}/variants/{variantId}
     */
    public function removeVariant(string $testId, string $variantId): JsonResponse
    {
        $test = ABTest::find($testId);

        if (!$test) {
            return $this->notFound('A/B test not found');
        }

        if ($test->status === 'running') {
            return $this->error('Cannot remove variants from a running test', 422);
        }

        $variant = $test->variants()->find($variantId);

        if (!$variant) {
            return $this->notFound('Variant not found');
        }

        $variant->delete();

        return $this->success(null, 'Variant removed');
    }
}
