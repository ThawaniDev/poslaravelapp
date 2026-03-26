<?php

namespace App\Domain\ContentOnboarding\Controllers\Api;

use App\Domain\ContentOnboarding\Services\PosLayoutService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UiController extends BaseApiController
{
    public function __construct(private readonly PosLayoutService $service) {}

    // ─── Layouts ─────────────────────────────────────────

    public function layouts(Request $request): JsonResponse
    {
        $request->validate([
            'business_type' => ['required', 'string', 'max:50'],
        ]);

        $planId = $request->user()->store?->activeSubscription?->subscription_plan_id ?? null;

        $layouts = $this->service->getAvailableLayouts(
            $request->input('business_type'),
            $planId,
        );

        return $this->success($layouts, __('ui.layouts_loaded'));
    }

    // ─── Themes ──────────────────────────────────────────

    public function themes(Request $request): JsonResponse
    {
        $planId = $request->user()->store?->activeSubscription?->subscription_plan_id ?? null;

        $themes = $this->service->getAvailableThemes($planId);

        return $this->success($themes, __('ui.themes_loaded'));
    }

    // ─── Platform Defaults ───────────────────────────────

    public function defaults(): JsonResponse
    {
        $defaults = $this->service->getPlatformDefaults();

        return $this->success($defaults, __('ui.defaults_loaded'));
    }

    // ─── Resolved Preferences (cascade) ──────────────────

    public function preferences(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $storeId = $request->user()->store_id ?? null;

        $preferences = $this->service->resolvePreferences($userId, $storeId);

        return $this->success($preferences, __('ui.preferences_loaded'));
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pos_handedness' => ['nullable', 'string', 'in:left,right,center'],
            'font_size' => ['nullable', 'string', 'in:small,medium,large,extra-large'],
            'theme' => ['nullable', 'string', 'max:50'],
            'pos_layout_id' => ['nullable', 'uuid', 'exists:pos_layout_templates,id'],
        ]);

        $pref = $this->service->updateUserPreferences(
            $request->user()->id,
            $validated,
        );

        return $this->success($pref, __('ui.preferences_updated'));
    }

    // ─── Store Defaults ──────────────────────────────────

    public function updateStoreDefaults(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'theme' => ['nullable', 'string', 'in:light,dark,custom'],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'font_scale' => ['nullable', 'numeric', 'min:0.5', 'max:2.0'],
            'handedness' => ['nullable', 'string', 'in:left,right,center'],
            'grid_columns' => ['nullable', 'integer', 'min:2', 'max:8'],
            'show_product_images' => ['nullable', 'boolean'],
            'show_price_on_grid' => ['nullable', 'boolean'],
            'cart_display_mode' => ['nullable', 'string', 'in:compact,detailed'],
            'layout_direction' => ['nullable', 'string', 'in:ltr,rtl,auto'],
        ]);

        $storeId = $request->user()->store_id;
        if (! $storeId) {
            return $this->error(__('ui.no_store_associated'), 403);
        }

        $setting = $this->service->updateStoreDefaults($storeId, $validated);

        return $this->success($setting, __('ui.store_defaults_updated'));
    }

    // ─── Receipt Layout Templates ────────────────────────

    public function receiptTemplates(Request $request): JsonResponse
    {
        $planId = $request->user()->store?->activeSubscription?->subscription_plan_id ?? null;

        $templates = $this->service->getAvailableReceiptTemplates($planId);

        return $this->success($templates, __('ui.receipt_templates_loaded'));
    }

    public function receiptTemplateBySlug(string $slug): JsonResponse
    {
        $template = $this->service->getReceiptTemplateBySlug($slug);

        if (! $template) {
            return $this->notFound(__('ui.receipt_template_not_found'));
        }

        return $this->success($template, __('ui.receipt_template_loaded'));
    }

    // ─── CFD Themes ──────────────────────────────────────

    public function cfdThemes(Request $request): JsonResponse
    {
        $planId = $request->user()->store?->activeSubscription?->subscription_plan_id ?? null;

        $themes = $this->service->getAvailableCfdThemes($planId);

        return $this->success($themes, __('ui.cfd_themes_loaded'));
    }

    public function cfdThemeBySlug(string $slug): JsonResponse
    {
        $theme = $this->service->getCfdThemeBySlug($slug);

        if (! $theme) {
            return $this->notFound(__('ui.cfd_theme_not_found'));
        }

        return $this->success($theme, __('ui.cfd_theme_loaded'));
    }

    // ─── Signage Templates ───────────────────────────────

    public function signageTemplates(Request $request): JsonResponse
    {
        $request->validate([
            'business_type' => ['required', 'string', 'max:50'],
        ]);

        $planId = $request->user()->store?->activeSubscription?->subscription_plan_id ?? null;

        $templates = $this->service->getAvailableSignageTemplates(
            $request->input('business_type'),
            $planId,
        );

        return $this->success($templates, __('ui.signage_templates_loaded'));
    }

    public function signageTemplateBySlug(string $slug): JsonResponse
    {
        $template = $this->service->getSignageTemplateBySlug($slug);

        if (! $template) {
            return $this->notFound(__('ui.signage_template_not_found'));
        }

        return $this->success($template, __('ui.signage_template_loaded'));
    }

    // ─── Label Templates ─────────────────────────────────

    public function labelTemplates(Request $request): JsonResponse
    {
        $request->validate([
            'business_type' => ['required', 'string', 'max:50'],
        ]);

        $planId = $request->user()->store?->activeSubscription?->subscription_plan_id ?? null;

        $templates = $this->service->getAvailableLabelTemplates(
            $request->input('business_type'),
            $planId,
        );

        return $this->success($templates, __('ui.label_templates_loaded'));
    }

    public function labelTemplateBySlug(string $slug): JsonResponse
    {
        $template = $this->service->getLabelTemplateBySlug($slug);

        if (! $template) {
            return $this->notFound(__('ui.label_template_not_found'));
        }

        return $this->success($template, __('ui.label_template_loaded'));
    }
}
