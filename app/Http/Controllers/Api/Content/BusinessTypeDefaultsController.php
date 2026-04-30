<?php

namespace App\Http\Controllers\Api\Content;

use App\Domain\ContentOnboarding\Models\BusinessType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Content\BusinessTypeApiResource;
use App\Http\Resources\Content\BusinessTypeDefaultsResource;
use Illuminate\Http\JsonResponse;

class BusinessTypeDefaultsController extends Controller
{
    /**
     * GET /api/v2/onboarding/business-types
     * List all active business types (light payload, for selection UI).
     */
    public function index(): JsonResponse
    {
        $types = BusinessType::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => BusinessTypeApiResource::collection($types),
        ]);
    }

    /**
     * GET /api/v2/onboarding/business-types/{slug}/defaults
     * Full defaults bundle for a business type.
     */
    public function defaults(string $slug): JsonResponse
    {
        $businessType = BusinessType::where('slug', $slug)
            ->where('is_active', true)
            ->with([
                'businessTypeCategoryTemplates',
                'businessTypeShiftTemplates',
                'businessTypeReceiptTemplate',
                'businessTypeIndustryConfig',
                'businessTypePromotionTemplates',
                'businessTypeCommissionTemplates',
                'businessTypeLoyaltyConfig',
                'businessTypeCustomerGroupTemplates',
                'businessTypeReturnPolicy',
                'businessTypeWasteReasonTemplates',
                'businessTypeAppointmentConfig',
                'businessTypeServiceCategoryTemplates',
                'businessTypeGiftRegistryTypes',
                'businessTypeGamificationBadges',
                'businessTypeGamificationChallenges',
                'businessTypeGamificationMilestones',
            ])
            ->firstOrFail();

        return response()->json([
            'data' => new BusinessTypeDefaultsResource($businessType),
        ]);
    }

    /**
     * GET /api/v2/onboarding/business-types/{slug}/category-templates
     */
    public function categoryTemplates(string $slug): JsonResponse
    {
        $businessType = $this->findActive($slug);

        return response()->json([
            'data' => $businessType->businessTypeCategoryTemplates()
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($c) => [
                    'id'               => $c->id,
                    'category_name'    => $c->category_name,
                    'category_name_ar' => $c->category_name_ar,
                    'sort_order'       => (int) $c->sort_order,
                ]),
        ]);
    }

    /**
     * GET /api/v2/onboarding/business-types/{slug}/shift-templates
     */
    public function shiftTemplates(string $slug): JsonResponse
    {
        $businessType = $this->findActive($slug);

        return response()->json([
            'data' => $businessType->businessTypeShiftTemplates()
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($s) => [
                    'id'                     => $s->id,
                    'name'                   => $s->name,
                    'name_ar'                => $s->name_ar,
                    'start_time'             => substr((string) $s->start_time, 0, 5),
                    'end_time'               => substr((string) $s->end_time, 0, 5),
                    'days_of_week'           => $s->days_of_week ?? [],
                    'break_duration_minutes' => (int) $s->break_duration_minutes,
                    'is_default'             => (bool) $s->is_default,
                    'sort_order'             => (int) $s->sort_order,
                ]),
        ]);
    }

    /**
     * GET /api/v2/onboarding/business-types/{slug}/receipt-template
     */
    public function receiptTemplate(string $slug): JsonResponse
    {
        $businessType = $this->findActive($slug);
        $template     = $businessType->businessTypeReceiptTemplate;

        if (! $template) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'paper_width'          => (int) $template->paper_width,
                'header_sections'      => $template->header_sections     ?? [],
                'body_sections'        => $template->body_sections        ?? [],
                'footer_sections'      => $template->footer_sections      ?? [],
                'zatca_qr_position'    => $template->zatca_qr_position,
                'show_bilingual'       => (bool) $template->show_bilingual,
                'font_size'            => $template->font_size,
                'custom_footer_text'   => $template->custom_footer_text,
                'custom_footer_text_ar' => $template->custom_footer_text_ar,
            ],
        ]);
    }

    /**
     * GET /api/v2/onboarding/business-types/{slug}/industry-config
     */
    public function industryConfig(string $slug): JsonResponse
    {
        $businessType = $this->findActive($slug);
        $config       = $businessType->businessTypeIndustryConfig;

        if (! $config) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'active_modules'          => $config->active_modules          ?? [],
                'default_settings'        => $config->default_settings        ?? [],
                'required_product_fields' => $config->required_product_fields ?? [],
            ],
        ]);
    }

    /**
     * GET /api/v2/onboarding/business-types/{slug}/loyalty-config
     */
    public function loyaltyConfig(string $slug): JsonResponse
    {
        $businessType = $this->findActive($slug);
        $config       = $businessType->businessTypeLoyaltyConfig;

        if (! $config) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'program_type'          => $config->program_type,
                'earning_rate'          => (float) $config->earning_rate,
                'redemption_value'      => (float) $config->redemption_value,
                'min_redemption_points' => (int) $config->min_redemption_points,
                'stamps_card_size'      => $config->stamps_card_size ? (int) $config->stamps_card_size : null,
                'cashback_percentage'   => $config->cashback_percentage ? (float) $config->cashback_percentage : null,
                'points_expiry_days'    => (int) $config->points_expiry_days,
                'enable_tiers'          => (bool) $config->enable_tiers,
                'tier_definitions'      => $config->tier_definitions ?? [],
                'is_active'             => (bool) $config->is_active,
            ],
        ]);
    }

    /**
     * GET /api/v2/onboarding/business-types/{slug}/customer-groups
     */
    public function customerGroups(string $slug): JsonResponse
    {
        $businessType = $this->findActive($slug);

        return response()->json([
            'data' => $businessType->businessTypeCustomerGroupTemplates()
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($g) => [
                    'id'                  => $g->id,
                    'name'                => $g->name,
                    'name_ar'             => $g->name_ar,
                    'description'         => $g->description,
                    'discount_percentage' => (float) $g->discount_percentage,
                    'credit_limit'        => (float) $g->credit_limit,
                    'payment_terms_days'  => (int) $g->payment_terms_days,
                    'is_default_group'    => (bool) $g->is_default_group,
                    'sort_order'          => (int) $g->sort_order,
                ]),
        ]);
    }

    /**
     * GET /api/v2/onboarding/business-types/{slug}/return-policy
     */
    public function returnPolicy(string $slug): JsonResponse
    {
        $businessType = $this->findActive($slug);
        $policy       = $businessType->businessTypeReturnPolicy;

        if (! $policy) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'return_window_days'          => (int) $policy->return_window_days,
                'refund_methods'              => $policy->refund_methods ?? [],
                'require_receipt'             => (bool) $policy->require_receipt,
                'restocking_fee_percentage'   => (float) $policy->restocking_fee_percentage,
                'void_grace_period_minutes'   => (int) $policy->void_grace_period_minutes,
                'require_manager_approval'    => (bool) $policy->require_manager_approval,
                'max_return_without_approval' => (float) $policy->max_return_without_approval,
                'return_reason_required'      => (bool) $policy->return_reason_required,
                'partial_return_allowed'      => (bool) $policy->partial_return_allowed,
            ],
        ]);
    }

    /**
     * GET /api/v2/onboarding/business-types/{slug}/waste-reasons
     */
    public function wasteReasons(string $slug): JsonResponse
    {
        $businessType = $this->findActive($slug);

        return response()->json([
            'data' => $businessType->businessTypeWasteReasonTemplates()
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($w) => [
                    'id'                     => $w->id,
                    'reason_code'            => $w->reason_code,
                    'name'                   => $w->name,
                    'name_ar'                => $w->name_ar,
                    'category'               => $w->category,
                    'description'            => $w->description,
                    'requires_approval'      => (bool) $w->requires_approval,
                    'affects_cost_reporting' => (bool) $w->affects_cost_reporting,
                    'sort_order'             => (int) $w->sort_order,
                ]),
        ]);
    }

    /**
     * GET /api/v2/onboarding/business-types/{slug}/appointment-config
     */
    public function appointmentConfig(string $slug): JsonResponse
    {
        $businessType = $this->findActive($slug);
        $config       = $businessType->businessTypeAppointmentConfig;

        if (! $config) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'default_slot_duration_minutes' => (int) $config->default_slot_duration_minutes,
                'min_advance_booking_hours'     => (int) $config->min_advance_booking_hours,
                'max_advance_booking_days'      => (int) $config->max_advance_booking_days,
                'cancellation_window_hours'     => (int) $config->cancellation_window_hours,
                'cancellation_fee_type'         => $config->cancellation_fee_type,
                'cancellation_fee_value'        => (float) $config->cancellation_fee_value,
                'allow_walkins'                 => (bool) $config->allow_walkins,
                'overbooking_buffer_percentage' => (float) $config->overbooking_buffer_percentage,
                'require_deposit'               => (bool) $config->require_deposit,
                'deposit_percentage'            => (float) $config->deposit_percentage,
                'service_category_templates'    => $businessType->businessTypeServiceCategoryTemplates()
                    ->orderBy('sort_order')
                    ->get()
                    ->map(fn ($sc) => [
                        'id'                       => $sc->id,
                        'name'                     => $sc->name,
                        'name_ar'                  => $sc->name_ar,
                        'default_duration_minutes' => (int) $sc->default_duration_minutes,
                        'default_price'            => $sc->default_price ? (float) $sc->default_price : null,
                        'sort_order'               => (int) $sc->sort_order,
                    ])->toArray(),
            ],
        ]);
    }

    /**
     * GET /api/v2/onboarding/business-types/{slug}/gift-registry-types
     */
    public function giftRegistryTypes(string $slug): JsonResponse
    {
        $businessType = $this->findActive($slug);

        return response()->json([
            'data' => $businessType->businessTypeGiftRegistryTypes()
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($g) => [
                    'id'                       => $g->id,
                    'name'                     => $g->name,
                    'name_ar'                  => $g->name_ar,
                    'description'              => $g->description,
                    'icon'                     => $g->icon,
                    'default_expiry_days'      => (int) $g->default_expiry_days,
                    'allow_public_sharing'     => (bool) $g->allow_public_sharing,
                    'allow_partial_fulfilment' => (bool) $g->allow_partial_fulfilment,
                    'require_minimum_items'    => (bool) $g->require_minimum_items,
                    'minimum_items_count'      => (int) $g->minimum_items_count,
                    'sort_order'               => (int) $g->sort_order,
                ]),
        ]);
    }

    /**
     * GET /api/v2/onboarding/business-types/{slug}/gamification-templates
     */
    public function gamificationTemplates(string $slug): JsonResponse
    {
        $businessType = $this->findActive($slug, [
            'businessTypeGamificationBadges',
            'businessTypeGamificationChallenges',
            'businessTypeGamificationMilestones',
        ]);

        return response()->json([
            'data' => [
                'badges' => $businessType->businessTypeGamificationBadges
                    ->map(fn ($b) => [
                        'id'                => $b->id,
                        'name'              => $b->name,
                        'name_ar'           => $b->name_ar,
                        'icon_url'          => $b->icon_url,
                        'trigger_type'      => $b->trigger_type,
                        'trigger_threshold' => (int) $b->trigger_threshold,
                        'points_reward'     => (int) $b->points_reward,
                        'description'       => $b->description,
                        'description_ar'    => $b->description_ar,
                    ])->values()->toArray(),
                'challenges' => $businessType->businessTypeGamificationChallenges
                    ->map(fn ($c) => [
                        'id'             => $c->id,
                        'name'           => $c->name,
                        'name_ar'        => $c->name_ar,
                        'challenge_type' => $c->challenge_type,
                        'target_value'   => (int) $c->target_value,
                        'reward_type'    => $c->reward_type,
                        'reward_value'   => $c->reward_value,
                        'duration_days'  => (int) $c->duration_days,
                        'is_recurring'   => (bool) $c->is_recurring,
                        'description'    => $c->description,
                        'description_ar' => $c->description_ar,
                    ])->values()->toArray(),
                'milestones' => $businessType->businessTypeGamificationMilestones
                    ->map(fn ($m) => [
                        'id'              => $m->id,
                        'name'            => $m->name,
                        'name_ar'         => $m->name_ar,
                        'milestone_type'  => $m->milestone_type,
                        'threshold_value' => (float) $m->threshold_value,
                        'reward_type'     => $m->reward_type,
                        'reward_value'    => $m->reward_value,
                    ])->values()->toArray(),
            ],
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function findActive(string $slug, array $with = []): BusinessType
    {
        return BusinessType::where('slug', $slug)
            ->where('is_active', true)
            ->with($with)
            ->firstOrFail();
    }
}
