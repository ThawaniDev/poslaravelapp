<?php

namespace App\Domain\Core\Services;

use App\Domain\ContentOnboarding\Enums\OnboardingStep;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderRegistration\Models\OnboardingProgress;
use Illuminate\Support\Facades\DB;

class OnboardingService
{
    public function __construct(
        private readonly StoreService $storeService,
    ) {}

    /**
     * Onboarding step order (for navigation).
     */
    public const STEP_ORDER = [
        'welcome',
        'business_info',
        'business_type',
        'tax',
        'hardware',
        'products',
        'staff',
        'review',
    ];

    /**
     * Get or initialize onboarding progress for a store.
     */
    public function getProgress(string $storeId): OnboardingProgress
    {
        return OnboardingProgress::firstOrCreate(
            ['store_id' => $storeId],
            [
                'current_step' => OnboardingStep::Welcome,
                'completed_steps' => [],
                'checklist_items' => $this->defaultChecklist(),
                'is_wizard_completed' => false,
                'is_checklist_dismissed' => false,
                'started_at' => now(),
            ],
        );
    }

    /**
     * Advance to the next step in the wizard.
     */
    public function completeStep(string $storeId, string $step, array $stepData = []): OnboardingProgress
    {
        return DB::transaction(function () use ($storeId, $step, $stepData) {
            $progress = $this->getProgress($storeId);

            // Mark step as completed
            $completedSteps = $progress->completed_steps ?? [];
            if (!in_array($step, $completedSteps, true)) {
                $completedSteps[] = $step;
            }

            // Apply step data
            $this->applyStepData($storeId, $step, $stepData);

            // Move to next step
            $nextStep = $this->nextStep($step);

            $progress->update([
                'completed_steps' => $completedSteps,
                'current_step' => $nextStep ?? $step,
            ]);

            // Check if wizard is complete
            if ($this->isAllStepsCompleted($completedSteps)) {
                $progress->update([
                    'is_wizard_completed' => true,
                    'completed_at' => now(),
                ]);
            }

            return $progress->fresh();
        });
    }

    /**
     * Skip the rest of the wizard.
     */
    public function skipWizard(string $storeId): OnboardingProgress
    {
        $progress = $this->getProgress($storeId);
        $progress->update([
            'is_wizard_completed' => true,
            'completed_at' => now(),
        ]);
        return $progress;
    }

    /**
     * Update a checklist item (post-wizard tasks).
     */
    public function updateChecklistItem(string $storeId, string $itemKey, bool $completed): OnboardingProgress
    {
        $progress = $this->getProgress($storeId);
        $checklist = $progress->checklist_items ?? [];
        if (isset($checklist[$itemKey])) {
            $checklist[$itemKey]['completed'] = $completed;
            $checklist[$itemKey]['completed_at'] = $completed ? now()->toIso8601String() : null;
        }
        $progress->update(['checklist_items' => $checklist]);
        return $progress->fresh();
    }

    /**
     * Dismiss the onboarding checklist banner.
     */
    public function dismissChecklist(string $storeId): OnboardingProgress
    {
        $progress = $this->getProgress($storeId);
        $progress->update(['is_checklist_dismissed' => true]);
        return $progress;
    }

    /**
     * Reset onboarding (e.g., for re-setup).
     */
    public function resetOnboarding(string $storeId): OnboardingProgress
    {
        $progress = $this->getProgress($storeId);
        $progress->update([
            'current_step' => OnboardingStep::Welcome,
            'completed_steps' => [],
            'checklist_items' => $this->defaultChecklist(),
            'is_wizard_completed' => false,
            'is_checklist_dismissed' => false,
            'completed_at' => null,
        ]);
        return $progress->fresh();
    }

    // ─── Private Helpers ─────────────────────────────────────────

    private function applyStepData(string $storeId, string $step, array $data): void
    {
        if (empty($data)) return;

        $store = Store::findOrFail($storeId);

        switch ($step) {
            case 'business_info':
                // Update store & organization basic info
                $storeUpdates = array_intersect_key($data, array_flip([
                    'name', 'name_ar', 'address', 'city', 'phone', 'email',
                    'latitude', 'longitude', 'timezone', 'currency', 'locale',
                ]));
                if (!empty($storeUpdates)) {
                    $this->storeService->updateStore($store, $storeUpdates);
                }

                $orgUpdates = array_intersect_key($data, array_flip([
                    'cr_number', 'vat_number', 'country',
                ]));
                if (!empty($orgUpdates) && $store->organization) {
                    $store->organization->update($orgUpdates);
                }
                break;

            case 'business_type':
                if (isset($data['business_type'])) {
                    $this->storeService->applyBusinessType($store, $data['business_type']);
                }
                break;

            case 'tax':
                $taxData = array_intersect_key($data, array_flip([
                    'tax_label', 'tax_rate', 'prices_include_tax', 'tax_number',
                ]));
                if (!empty($taxData)) {
                    $this->storeService->updateSettings($storeId, $taxData);
                }
                break;

            case 'products':
                // Products step: import sample products from template
                // This is handled by the catalog feature (Phase 2)
                break;

            case 'staff':
                // Staff step: handled by the roles/permissions feature
                break;
        }
    }

    private function nextStep(string $currentStep): ?string
    {
        $index = array_search($currentStep, self::STEP_ORDER, true);
        if ($index === false || $index >= count(self::STEP_ORDER) - 1) {
            return null;
        }
        return self::STEP_ORDER[$index + 1];
    }

    private function isAllStepsCompleted(array $completedSteps): bool
    {
        // The review step being completed means the wizard is done
        return in_array('review', $completedSteps, true);
    }

    private function defaultChecklist(): array
    {
        return [
            'add_first_product' => [
                'label_en' => 'Add your first product',
                'label_ar' => 'أضف أول منتج',
                'completed' => false,
                'completed_at' => null,
            ],
            'configure_receipt' => [
                'label_en' => 'Customize your receipt',
                'label_ar' => 'خصص إيصالك',
                'completed' => false,
                'completed_at' => null,
            ],
            'invite_staff' => [
                'label_en' => 'Invite a team member',
                'label_ar' => 'إدعِ أحد أعضاء الفريقط',
                'completed' => false,
                'completed_at' => null,
            ],
            'make_first_sale' => [
                'label_en' => 'Make your first sale',
                'label_ar' => 'قم بأول عملية بيع',
                'completed' => false,
                'completed_at' => null,
            ],
            'set_working_hours' => [
                'label_en' => 'Set your working hours',
                'label_ar' => 'حدد ساعات العمل',
                'completed' => false,
                'completed_at' => null,
            ],
        ];
    }
}
