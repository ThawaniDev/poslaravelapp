<?php

namespace App\Domain\ContentOnboarding\Controllers\Api;

use App\Domain\ContentOnboarding\Enums\WidgetCategory;
use App\Domain\ContentOnboarding\Models\PosLayoutTemplate;
use App\Domain\ContentOnboarding\Services\LayoutBuilderService;
use App\Domain\Shared\Models\UserPreference;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LayoutBuilderController extends BaseApiController
{
    public function __construct(private readonly LayoutBuilderService $service) {}

    // ─── Widget Catalog ──────────────────────────────────

    public function widgetCatalog(Request $request): JsonResponse
    {
        $category = $request->input('category')
            ? WidgetCategory::tryFrom($request->input('category'))
            : null;

        $widgets = $this->service->getWidgetCatalog($category);

        return $this->success($widgets, __('ui.widgets_loaded'));
    }

    public function widget(string $id): JsonResponse
    {
        $widget = $this->service->getWidget($id);

        if (! $widget) {
            return $this->notFound(__('ui.widget_not_found'));
        }

        return $this->success($widget, __('ui.widget_loaded'));
    }

    // ─── Canvas Configuration ────────────────────────────

    public function canvasConfig(string $templateId): JsonResponse
    {
        $config = $this->service->getCanvasConfig($templateId);

        if (! $config) {
            return $this->notFound(__('ui.template_not_found'));
        }

        return $this->success($config, __('ui.canvas_config_loaded'));
    }

    public function updateCanvasConfig(Request $request, string $templateId): JsonResponse
    {
        $validated = $request->validate([
            'canvas_columns' => ['sometimes', 'integer', 'min:1', 'max:48'],
            'canvas_rows' => ['sometimes', 'integer', 'min:1', 'max:32'],
            'canvas_gap_px' => ['sometimes', 'integer', 'min:0', 'max:32'],
            'canvas_padding_px' => ['sometimes', 'integer', 'min:0', 'max:64'],
            'breakpoints' => ['sometimes', 'array'],
        ]);

        $template = $this->service->updateCanvasConfig($templateId, $validated);

        if (! $template) {
            return $this->error(__('ui.canvas_update_failed'), 422);
        }

        return $this->success(
            $this->service->getCanvasConfig($template->id),
            __('ui.canvas_config_updated'),
        );
    }

    // ─── Widget Placements ───────────────────────────────

    public function placements(string $templateId): JsonResponse
    {
        $placements = $this->service->getTemplatePlacements($templateId);

        return $this->success($placements, __('ui.placements_loaded'));
    }

    public function addPlacement(Request $request, string $templateId): JsonResponse
    {
        $validated = $request->validate([
            'widget_id' => ['required', 'uuid', 'exists:layout_widgets,id'],
            'instance_key' => ['sometimes', 'string', 'max:50'],
            'grid_x' => ['sometimes', 'integer', 'min:0'],
            'grid_y' => ['sometimes', 'integer', 'min:0'],
            'grid_w' => ['sometimes', 'integer', 'min:1'],
            'grid_h' => ['sometimes', 'integer', 'min:1'],
            'z_index' => ['sometimes', 'integer', 'min:0', 'max:999'],
            'properties' => ['sometimes', 'array'],
            'is_visible' => ['sometimes', 'boolean'],
        ]);

        $placement = $this->service->addWidgetToTemplate(
            $templateId,
            $validated['widget_id'],
            $validated,
        );

        if (! $placement) {
            return $this->error(__('ui.placement_add_failed'), 422);
        }

        return $this->created($placement->load(['widget', 'themeOverrides']), __('ui.placement_added'));
    }

    public function updatePlacement(Request $request, string $placementId): JsonResponse
    {
        $validated = $request->validate([
            'grid_x' => ['sometimes', 'integer', 'min:0'],
            'grid_y' => ['sometimes', 'integer', 'min:0'],
            'grid_w' => ['sometimes', 'integer', 'min:1'],
            'grid_h' => ['sometimes', 'integer', 'min:1'],
            'z_index' => ['sometimes', 'integer', 'min:0', 'max:999'],
            'properties' => ['sometimes', 'array'],
            'is_visible' => ['sometimes', 'boolean'],
        ]);

        $placement = $this->service->updatePlacement($placementId, $validated);

        if (! $placement) {
            return $this->error(__('ui.placement_update_failed'), 422);
        }

        return $this->success($placement, __('ui.placement_updated'));
    }

    public function removePlacement(string $placementId): JsonResponse
    {
        $removed = $this->service->removePlacement($placementId);

        if (! $removed) {
            return $this->error(__('ui.placement_remove_failed'), 422);
        }

        return $this->success(null, __('ui.placement_removed'));
    }

    public function batchUpdatePlacements(Request $request, string $templateId): JsonResponse
    {
        $validated = $request->validate([
            'placements' => ['required', 'array', 'min:1'],
            'placements.*.id' => ['required', 'uuid'],
            'placements.*.grid_x' => ['sometimes', 'integer', 'min:0'],
            'placements.*.grid_y' => ['sometimes', 'integer', 'min:0'],
            'placements.*.grid_w' => ['sometimes', 'integer', 'min:1'],
            'placements.*.grid_h' => ['sometimes', 'integer', 'min:1'],
            'placements.*.z_index' => ['sometimes', 'integer', 'min:0', 'max:999'],
            'placements.*.properties' => ['sometimes', 'array'],
            'placements.*.is_visible' => ['sometimes', 'boolean'],
        ]);

        $updated = $this->service->batchUpdatePlacements($templateId, $validated['placements']);

        return $this->success($updated, __('ui.placements_batch_updated'));
    }

    // ─── Widget Theme Overrides ──────────────────────────

    public function setThemeOverrides(Request $request, string $placementId): JsonResponse
    {
        $validated = $request->validate([
            'overrides' => ['required', 'array', 'min:1'],
            'overrides.*' => ['required', 'string', 'max:255'],
        ]);

        $overrides = $this->service->setWidgetThemeOverrides($placementId, $validated['overrides']);

        return $this->success($overrides, __('ui.theme_overrides_updated'));
    }

    public function removeThemeOverride(string $placementId, string $variableKey): JsonResponse
    {
        $removed = $this->service->removeWidgetThemeOverride($placementId, $variableKey);

        if (! $removed) {
            return $this->notFound(__('ui.theme_override_not_found'));
        }

        return $this->success(null, __('ui.theme_override_removed'));
    }

    // ─── Template Cloning ────────────────────────────────

    public function cloneTemplate(Request $request, string $templateId): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'name_ar' => ['sometimes', 'string', 'max:150'],
        ]);

        $clone = $this->service->cloneTemplate($templateId, $validated);

        if (! $clone) {
            return $this->error(__('ui.template_clone_failed'), 422);
        }

        return $this->created($clone, __('ui.template_cloned'));
    }

    // ─── Versioning ──────────────────────────────────────

    public function versions(string $templateId): JsonResponse
    {
        $versions = $this->service->getVersions($templateId);

        return $this->success($versions, __('ui.versions_loaded'));
    }

    public function createVersion(Request $request, string $templateId): JsonResponse
    {
        $validated = $request->validate([
            'version_number' => ['required', 'string', 'max:20'],
            'changelog' => ['nullable', 'string', 'max:2000'],
        ]);

        $version = $this->service->createVersion(
            $templateId,
            $validated['version_number'],
            $validated['changelog'] ?? null,
            $request->user()?->id,
        );

        if (! $version) {
            return $this->error(__('ui.version_create_failed'), 422);
        }

        return $this->created($version, __('ui.version_created'));
    }

    // ─── Full Layout ─────────────────────────────────────

    public function fullLayout(string $templateId): JsonResponse
    {
        $layout = $this->service->getFullLayout($templateId);

        if (! $layout) {
            return $this->notFound(__('ui.template_not_found'));
        }

        return $this->success($layout, __('ui.full_layout_loaded'));
    }

    // ─── Flat Convenience Routes (resolve active template) ───

    private function resolveActiveTemplateId(Request $request): ?string
    {
        $userId = $request->user()?->id;

        if ($userId) {
            $pref = UserPreference::where('user_id', $userId)->first();
            if ($pref?->pos_layout_id) {
                return $pref->pos_layout_id;
            }
        }

        // Fallback: first active default template
        return PosLayoutTemplate::where('is_active', true)
            ->where('is_default', true)
            ->first()?->id
            ?? PosLayoutTemplate::where('is_active', true)->first()?->id;
    }

    public function activeCanvasConfig(Request $request): JsonResponse
    {
        $templateId = $this->resolveActiveTemplateId($request);

        if (! $templateId) {
            return $this->notFound(__('ui.no_active_template'));
        }

        return $this->canvasConfig($templateId);
    }

    public function updateActiveCanvasConfig(Request $request): JsonResponse
    {
        $templateId = $this->resolveActiveTemplateId($request);

        if (! $templateId) {
            return $this->notFound(__('ui.no_active_template'));
        }

        return $this->updateCanvasConfig($request, $templateId);
    }

    public function activePlacements(Request $request): JsonResponse
    {
        $templateId = $this->resolveActiveTemplateId($request);

        if (! $templateId) {
            return $this->notFound(__('ui.no_active_template'));
        }

        return $this->placements($templateId);
    }

    public function addActivePlacement(Request $request): JsonResponse
    {
        $templateId = $this->resolveActiveTemplateId($request);

        if (! $templateId) {
            return $this->notFound(__('ui.no_active_template'));
        }

        return $this->addPlacement($request, $templateId);
    }

    public function activeVersions(Request $request): JsonResponse
    {
        $templateId = $this->resolveActiveTemplateId($request);

        if (! $templateId) {
            return $this->notFound(__('ui.no_active_template'));
        }

        return $this->versions($templateId);
    }

    public function createActiveVersion(Request $request): JsonResponse
    {
        $templateId = $this->resolveActiveTemplateId($request);

        if (! $templateId) {
            return $this->notFound(__('ui.no_active_template'));
        }

        return $this->createVersion($request, $templateId);
    }

    public function cloneActiveTemplate(Request $request): JsonResponse
    {
        $templateId = $this->resolveActiveTemplateId($request);

        if (! $templateId) {
            return $this->notFound(__('ui.no_active_template'));
        }

        return $this->cloneTemplate($request, $templateId);
    }

    public function activeFullLayout(Request $request): JsonResponse
    {
        $templateId = $this->resolveActiveTemplateId($request);

        if (! $templateId) {
            return $this->notFound(__('ui.no_active_template'));
        }

        return $this->fullLayout($templateId);
    }
}
