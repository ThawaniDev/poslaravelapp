<?php

namespace App\Http\Controllers\Api\Core;

use App\Domain\ContentOnboarding\Models\OnboardingStep;
use App\Domain\Core\Services\OnboardingService;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Core\OnboardingStepRequest;
use App\Http\Resources\Core\OnboardingProgressResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends BaseApiController
{
    public function __construct(
        private readonly OnboardingService $onboardingService,
    ) {}

    /**
     * GET /core/onboarding/progress?store_id=xxx — Get onboarding progress.
     */
    public function progress(Request $request): JsonResponse
    {
        $storeId = $request->query('store_id', $request->user()?->store_id);
        if (!$storeId) {
            return $this->error('store_id is required.', 422);
        }

        $progress = $this->onboardingService->getProgress($storeId);
        return $this->success(new OnboardingProgressResource($progress));
    }

    /**
     * POST /core/onboarding/complete-step — Complete one onboarding step.
     */
    public function completeStep(OnboardingStepRequest $request): JsonResponse
    {
        $progress = $this->onboardingService->completeStep(
            $request->validated('store_id'),
            $request->validated('step'),
            $request->validated('data', []),
        );

        return $this->success(
            new OnboardingProgressResource($progress),
            'Step completed.',
        );
    }

    /**
     * POST /core/onboarding/skip — Skip the wizard.
     */
    public function skip(Request $request): JsonResponse
    {
        $request->validate(['store_id' => 'required|uuid|exists:stores,id']);

        $progress = $this->onboardingService->skipWizard(
            $request->input('store_id'),
        );

        return $this->success(
            new OnboardingProgressResource($progress),
            'Wizard skipped.',
        );
    }

    /**
     * POST /core/onboarding/checklist — Update a checklist item.
     */
    public function updateChecklist(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => 'required|uuid|exists:stores,id',
            'item_key' => 'required|string',
            'completed' => 'required|boolean',
        ]);

        $progress = $this->onboardingService->updateChecklistItem(
            $request->input('store_id'),
            $request->input('item_key'),
            $request->boolean('completed'),
        );

        return $this->success(
            new OnboardingProgressResource($progress),
            'Checklist updated.',
        );
    }

    /**
     * POST /core/onboarding/dismiss-checklist — Dismiss the checklist banner.
     */
    public function dismissChecklist(Request $request): JsonResponse
    {
        $request->validate(['store_id' => 'required|uuid|exists:stores,id']);

        $progress = $this->onboardingService->dismissChecklist(
            $request->input('store_id'),
        );

        return $this->success(
            new OnboardingProgressResource($progress),
            'Checklist dismissed.',
        );
    }

    /**
     * POST /core/onboarding/reset — Reset onboarding for re-setup.
     */
    public function reset(Request $request): JsonResponse
    {
        $request->validate(['store_id' => 'required|uuid|exists:stores,id']);

        $progress = $this->onboardingService->resetOnboarding(
            $request->input('store_id'),
        );

        return $this->success(
            new OnboardingProgressResource($progress),
            'Onboarding reset.',
        );
    }

    /**
     * GET /core/onboarding/steps — List all onboarding steps (metadata from DB,
     * falling back to the hardcoded STEP_ORDER from OnboardingService).
     */
    public function steps(): JsonResponse
    {
        $dbSteps = OnboardingStep::orderBy('sort_order')
            ->orderBy('step_number')
            ->get();

        if ($dbSteps->isNotEmpty()) {
            $steps = $dbSteps->map(fn ($step) => [
                'key'            => \Illuminate\Support\Str::slug($step->title, '_'),
                'order'          => (int) $step->sort_order,
                'step_number'    => (int) $step->step_number,
                'label_en'       => $step->title,
                'label_ar'       => $step->title_ar,
                'description'    => $step->description,
                'description_ar' => $step->description_ar,
                'is_required'    => (bool) $step->is_required,
            ]);
        } else {
            // Fallback: use hardcoded STEP_ORDER if no DB records
            $steps = collect(OnboardingService::STEP_ORDER)->map(fn ($step, $i) => [
                'key'            => $step,
                'order'          => $i,
                'step_number'    => $i + 1,
                'label_en'       => $this->stepLabel($step, 'en'),
                'label_ar'       => $this->stepLabel($step, 'ar'),
                'description'    => null,
                'description_ar' => null,
                'is_required'    => true,
            ]);
        }

        return $this->success($steps->values());
    }

    private function stepLabel(string $step, string $locale): string
    {
        $labels = [
            'welcome' => ['en' => 'Welcome', 'ar' => 'مرحباً'],
            'business_info' => ['en' => 'Business Information', 'ar' => 'معلومات النشاط التجاري'],
            'business_type' => ['en' => 'Business Type', 'ar' => 'نوع النشاط'],
            'tax' => ['en' => 'Tax Configuration', 'ar' => 'إعدادات الضريبة'],
            'hardware' => ['en' => 'Hardware Setup', 'ar' => 'إعداد الأجهزة'],
            'products' => ['en' => 'Products', 'ar' => 'المنتجات'],
            'staff' => ['en' => 'Staff & Roles', 'ar' => 'الموظفين والأدوار'],
            'review' => ['en' => 'Review & Complete', 'ar' => 'مراجعة وإكمال'],
        ];

        return $labels[$step][$locale] ?? $step;
    }
}
