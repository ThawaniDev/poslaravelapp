<?php

namespace App\Http\Resources\Content;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full defaults bundle for a business type.
 * Used by GET /api/v2/onboarding/business-types/{slug}/defaults
 */
class BusinessTypeDefaultsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var \App\Domain\ContentOnboarding\Models\BusinessType $this */
        return [
            'id'      => $this->id,
            'name'    => $this->name,
            'name_ar' => $this->name_ar,
            'slug'    => $this->slug,

            // ── Category Templates ────────────────────────────────────────
            'category_templates' => $this->businessTypeCategoryTemplates
                ->map(fn ($c) => [
                    'id'              => $c->id,
                    'category_name'   => $c->category_name,
                    'category_name_ar' => $c->category_name_ar,
                    'sort_order'      => (int) $c->sort_order,
                ])->values()->toArray(),

            // ── Shift Templates ───────────────────────────────────────────
            'shift_templates' => $this->businessTypeShiftTemplates
                ->map(fn ($s) => [
                    'id'                      => $s->id,
                    'name'                    => $s->name,
                    'name_ar'                 => $s->name_ar,
                    'start_time'              => substr((string) $s->start_time, 0, 5),
                    'end_time'                => substr((string) $s->end_time, 0, 5),
                    'days_of_week'            => $s->days_of_week ?? [],
                    'break_duration_minutes'  => (int) $s->break_duration_minutes,
                    'is_default'              => (bool) $s->is_default,
                    'sort_order'              => (int) $s->sort_order,
                ])->values()->toArray(),

            // ── Receipt Template ──────────────────────────────────────────
            'receipt_template' => $this->businessTypeReceiptTemplate ? [
                'paper_width'         => (int) $this->businessTypeReceiptTemplate->paper_width,
                'header_sections'     => $this->businessTypeReceiptTemplate->header_sections ?? [],
                'body_sections'       => $this->businessTypeReceiptTemplate->body_sections   ?? [],
                'footer_sections'     => $this->businessTypeReceiptTemplate->footer_sections ?? [],
                'zatca_qr_position'   => $this->businessTypeReceiptTemplate->zatca_qr_position,
                'show_bilingual'      => (bool) $this->businessTypeReceiptTemplate->show_bilingual,
                'font_size'           => $this->businessTypeReceiptTemplate->font_size,
                'custom_footer_text'  => $this->businessTypeReceiptTemplate->custom_footer_text,
                'custom_footer_text_ar' => $this->businessTypeReceiptTemplate->custom_footer_text_ar,
            ] : null,

            // ── Industry Config ───────────────────────────────────────────
            'industry_config' => $this->businessTypeIndustryConfig ? [
                'active_modules'          => $this->businessTypeIndustryConfig->active_modules          ?? [],
                'default_settings'        => $this->businessTypeIndustryConfig->default_settings        ?? [],
                'required_product_fields' => $this->businessTypeIndustryConfig->required_product_fields ?? [],
            ] : null,

            // ── Promotion Templates ───────────────────────────────────────
            'promotion_templates' => $this->businessTypePromotionTemplates
                ->map(fn ($p) => [
                    'id'             => $p->id,
                    'name'           => $p->name,
                    'name_ar'        => $p->name_ar,
                    'description'    => $p->description,
                    'promotion_type' => $p->promotion_type,
                    'discount_value' => $p->discount_value ? (float) $p->discount_value : null,
                    'applies_to'     => $p->applies_to,
                    'time_start'     => $p->time_start ? substr((string) $p->time_start, 0, 5) : null,
                    'time_end'       => $p->time_end   ? substr((string) $p->time_end, 0, 5)   : null,
                    'active_days'    => $p->active_days ?? [],
                    'minimum_order'  => (float) $p->minimum_order,
                ])->values()->toArray(),

            // ── Commission Templates ──────────────────────────────────────
            'commission_templates' => $this->businessTypeCommissionTemplates
                ->map(fn ($c) => [
                    'id'              => $c->id,
                    'name'            => $c->name,
                    'name_ar'         => $c->name_ar,
                    'commission_type' => $c->commission_type,
                    'value'           => $c->value ? (float) $c->value : null,
                    'applies_to'      => $c->applies_to,
                    'tier_thresholds' => $c->tier_thresholds ?? [],
                ])->values()->toArray(),

            // ── Loyalty Config ────────────────────────────────────────────
            'loyalty_config' => $this->businessTypeLoyaltyConfig ? [
                'program_type'          => $this->businessTypeLoyaltyConfig->program_type,
                'earning_rate'          => (float) $this->businessTypeLoyaltyConfig->earning_rate,
                'redemption_value'      => (float) $this->businessTypeLoyaltyConfig->redemption_value,
                'min_redemption_points' => (int) $this->businessTypeLoyaltyConfig->min_redemption_points,
                'stamps_card_size'      => $this->businessTypeLoyaltyConfig->stamps_card_size ? (int) $this->businessTypeLoyaltyConfig->stamps_card_size : null,
                'cashback_percentage'   => $this->businessTypeLoyaltyConfig->cashback_percentage ? (float) $this->businessTypeLoyaltyConfig->cashback_percentage : null,
                'points_expiry_days'    => (int) $this->businessTypeLoyaltyConfig->points_expiry_days,
                'enable_tiers'          => (bool) $this->businessTypeLoyaltyConfig->enable_tiers,
                'tier_definitions'      => $this->businessTypeLoyaltyConfig->tier_definitions ?? [],
                'is_active'             => (bool) $this->businessTypeLoyaltyConfig->is_active,
            ] : null,

            // ── Customer Group Templates ──────────────────────────────────
            'customer_group_templates' => $this->businessTypeCustomerGroupTemplates
                ->map(fn ($g) => [
                    'id'                 => $g->id,
                    'name'               => $g->name,
                    'name_ar'            => $g->name_ar,
                    'description'        => $g->description,
                    'discount_percentage' => (float) $g->discount_percentage,
                    'credit_limit'       => (float) $g->credit_limit,
                    'payment_terms_days' => (int) $g->payment_terms_days,
                    'is_default_group'   => (bool) $g->is_default_group,
                    'sort_order'         => (int) $g->sort_order,
                ])->values()->toArray(),

            // ── Return Policy ─────────────────────────────────────────────
            'return_policy' => $this->businessTypeReturnPolicy ? [
                'return_window_days'            => (int) $this->businessTypeReturnPolicy->return_window_days,
                'refund_methods'                => $this->businessTypeReturnPolicy->refund_methods ?? [],
                'require_receipt'               => (bool) $this->businessTypeReturnPolicy->require_receipt,
                'restocking_fee_percentage'     => (float) $this->businessTypeReturnPolicy->restocking_fee_percentage,
                'void_grace_period_minutes'     => (int) $this->businessTypeReturnPolicy->void_grace_period_minutes,
                'require_manager_approval'      => (bool) $this->businessTypeReturnPolicy->require_manager_approval,
                'max_return_without_approval'   => (float) $this->businessTypeReturnPolicy->max_return_without_approval,
                'return_reason_required'        => (bool) $this->businessTypeReturnPolicy->return_reason_required,
                'partial_return_allowed'        => (bool) $this->businessTypeReturnPolicy->partial_return_allowed,
            ] : null,

            // ── Waste Reason Templates ────────────────────────────────────
            'waste_reason_templates' => $this->businessTypeWasteReasonTemplates
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
                ])->values()->toArray(),

            // ── Appointment Config ────────────────────────────────────────
            'appointment_config' => $this->businessTypeAppointmentConfig ? [
                'default_slot_duration_minutes'    => (int) $this->businessTypeAppointmentConfig->default_slot_duration_minutes,
                'min_advance_booking_hours'        => (int) $this->businessTypeAppointmentConfig->min_advance_booking_hours,
                'max_advance_booking_days'         => (int) $this->businessTypeAppointmentConfig->max_advance_booking_days,
                'cancellation_window_hours'        => (int) $this->businessTypeAppointmentConfig->cancellation_window_hours,
                'cancellation_fee_type'            => $this->businessTypeAppointmentConfig->cancellation_fee_type,
                'cancellation_fee_value'           => (float) $this->businessTypeAppointmentConfig->cancellation_fee_value,
                'allow_walkins'                    => (bool) $this->businessTypeAppointmentConfig->allow_walkins,
                'overbooking_buffer_percentage'    => (float) $this->businessTypeAppointmentConfig->overbooking_buffer_percentage,
                'require_deposit'                  => (bool) $this->businessTypeAppointmentConfig->require_deposit,
                'deposit_percentage'               => (float) $this->businessTypeAppointmentConfig->deposit_percentage,
                'service_category_templates'       => $this->businessTypeServiceCategoryTemplates
                    ->map(fn ($sc) => [
                        'id'                       => $sc->id,
                        'name'                     => $sc->name,
                        'name_ar'                  => $sc->name_ar,
                        'default_duration_minutes' => (int) $sc->default_duration_minutes,
                        'default_price'            => $sc->default_price ? (float) $sc->default_price : null,
                        'sort_order'               => (int) $sc->sort_order,
                    ])->values()->toArray(),
            ] : null,

            // ── Gift Registry Types ───────────────────────────────────────
            'gift_registry_types' => $this->businessTypeGiftRegistryTypes
                ->map(fn ($g) => [
                    'id'                      => $g->id,
                    'name'                    => $g->name,
                    'name_ar'                 => $g->name_ar,
                    'description'             => $g->description,
                    'icon'                    => $g->icon,
                    'default_expiry_days'     => (int) $g->default_expiry_days,
                    'allow_public_sharing'    => (bool) $g->allow_public_sharing,
                    'allow_partial_fulfilment' => (bool) $g->allow_partial_fulfilment,
                    'require_minimum_items'   => (bool) $g->require_minimum_items,
                    'minimum_items_count'     => (int) $g->minimum_items_count,
                    'sort_order'              => (int) $g->sort_order,
                ])->values()->toArray(),

            // ── Gamification Templates ────────────────────────────────────
            'gamification_templates' => [
                'badges' => $this->businessTypeGamificationBadges
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
                'challenges' => $this->businessTypeGamificationChallenges
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
                'milestones' => $this->businessTypeGamificationMilestones
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
        ];
    }
}
