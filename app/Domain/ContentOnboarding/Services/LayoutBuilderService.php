<?php

namespace App\Domain\ContentOnboarding\Services;

use App\Domain\ContentOnboarding\Enums\WidgetCategory;
use App\Domain\ContentOnboarding\Models\LayoutWidget;
use App\Domain\ContentOnboarding\Models\LayoutWidgetPlacement;
use App\Domain\ContentOnboarding\Models\PosLayoutTemplate;
use App\Domain\ContentOnboarding\Models\TemplateVersion;
use App\Domain\ContentOnboarding\Models\WidgetThemeOverride;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LayoutBuilderService
{
    // ─── Widget Registry ─────────────────────────────────

    public function getWidgetCatalog(?WidgetCategory $category = null): Collection
    {
        $query = LayoutWidget::where('is_active', true);

        if ($category) {
            $query->where('category', $category);
        }

        return $query->orderBy('sort_order')->orderBy('name')->get();
    }

    public function getWidget(string $widgetId): ?LayoutWidget
    {
        return LayoutWidget::find($widgetId);
    }

    public function getWidgetBySlug(string $slug): ?LayoutWidget
    {
        return LayoutWidget::where('slug', $slug)->first();
    }

    // ─── Canvas Configuration ────────────────────────────

    public function getCanvasConfig(string $templateId): ?array
    {
        $template = PosLayoutTemplate::find($templateId);
        if (! $template) {
            return null;
        }

        return [
            'id' => $template->id,
            'name' => $template->name,
            'canvas_columns' => $template->canvas_columns ?? 24,
            'canvas_rows' => $template->canvas_rows ?? 16,
            'canvas_gap_px' => $template->canvas_gap_px ?? 4,
            'canvas_padding_px' => $template->canvas_padding_px ?? 8,
            'breakpoints' => $template->breakpoints ?? [],
            'version' => $template->version ?? '1.0.0',
            'is_locked' => (bool) $template->is_locked,
        ];
    }

    public function updateCanvasConfig(string $templateId, array $data): ?PosLayoutTemplate
    {
        $template = PosLayoutTemplate::find($templateId);
        if (! $template || $template->is_locked) {
            return null;
        }

        $allowed = [
            'canvas_columns', 'canvas_rows', 'canvas_gap_px',
            'canvas_padding_px', 'breakpoints',
        ];

        $template->update(array_intersect_key($data, array_flip($allowed)));

        return $template->fresh();
    }

    // ─── Widget Placements ───────────────────────────────

    public function getTemplatePlacements(string $templateId): Collection
    {
        return LayoutWidgetPlacement::where('pos_layout_template_id', $templateId)
            ->with(['widget', 'themeOverrides'])
            ->orderBy('z_index')
            ->get();
    }

    public function addWidgetToTemplate(string $templateId, string $widgetId, array $placement): ?LayoutWidgetPlacement
    {
        $template = PosLayoutTemplate::find($templateId);
        if (! $template || $template->is_locked) {
            return null;
        }

        $widget = LayoutWidget::find($widgetId);
        if (! $widget || ! $widget->is_active) {
            return null;
        }

        $instanceKey = $placement['instance_key'] ?? $widget->slug . '_' . Str::random(6);

        $existing = LayoutWidgetPlacement::where('pos_layout_template_id', $templateId)
            ->where('instance_key', $instanceKey)
            ->exists();

        if ($existing) {
            return null;
        }

        return LayoutWidgetPlacement::create([
            'pos_layout_template_id' => $templateId,
            'layout_widget_id' => $widgetId,
            'instance_key' => $instanceKey,
            'grid_x' => $placement['grid_x'] ?? 0,
            'grid_y' => $placement['grid_y'] ?? 0,
            'grid_w' => $placement['grid_w'] ?? $widget->default_width,
            'grid_h' => $placement['grid_h'] ?? $widget->default_height,
            'z_index' => $placement['z_index'] ?? 0,
            'properties' => $placement['properties'] ?? $widget->default_properties ?? [],
            'is_visible' => $placement['is_visible'] ?? true,
        ]);
    }

    public function updatePlacement(string $placementId, array $data): ?LayoutWidgetPlacement
    {
        $placement = LayoutWidgetPlacement::with('widget')->find($placementId);
        if (! $placement) {
            return null;
        }

        $template = PosLayoutTemplate::find($placement->pos_layout_template_id);
        if ($template?->is_locked) {
            return null;
        }

        $widget = $placement->widget;
        $updateData = [];

        if (isset($data['grid_x'])) {
            $updateData['grid_x'] = $data['grid_x'];
        }
        if (isset($data['grid_y'])) {
            $updateData['grid_y'] = $data['grid_y'];
        }
        if (isset($data['grid_w'])) {
            $w = $data['grid_w'];
            if ($widget) {
                $w = max($widget->min_width, min($widget->max_width, $w));
            }
            $updateData['grid_w'] = $w;
        }
        if (isset($data['grid_h'])) {
            $h = $data['grid_h'];
            if ($widget) {
                $h = max($widget->min_height, min($widget->max_height, $h));
            }
            $updateData['grid_h'] = $h;
        }
        if (isset($data['z_index'])) {
            $updateData['z_index'] = $data['z_index'];
        }
        if (isset($data['properties'])) {
            $updateData['properties'] = $data['properties'];
        }
        if (isset($data['is_visible'])) {
            $updateData['is_visible'] = $data['is_visible'];
        }

        $placement->update($updateData);

        return $placement->fresh(['widget', 'themeOverrides']);
    }

    public function removePlacement(string $placementId): bool
    {
        $placement = LayoutWidgetPlacement::find($placementId);
        if (! $placement) {
            return false;
        }

        $template = PosLayoutTemplate::find($placement->pos_layout_template_id);
        if ($template?->is_locked) {
            return false;
        }

        return (bool) $placement->delete();
    }

    public function batchUpdatePlacements(string $templateId, array $placements): Collection
    {
        $template = PosLayoutTemplate::find($templateId);
        if (! $template || $template->is_locked) {
            return collect();
        }

        return DB::transaction(function () use ($templateId, $placements) {
            $updated = collect();

            foreach ($placements as $item) {
                if (! isset($item['id'])) {
                    continue;
                }

                $placement = LayoutWidgetPlacement::where('id', $item['id'])
                    ->where('pos_layout_template_id', $templateId)
                    ->first();

                if ($placement) {
                    $result = $this->updatePlacement($placement->id, $item);
                    if ($result) {
                        $updated->push($result);
                    }
                }
            }

            return $updated;
        });
    }

    // ─── Widget Theme Overrides ──────────────────────────

    public function setWidgetThemeOverrides(string $placementId, array $overrides): Collection
    {
        $placement = LayoutWidgetPlacement::find($placementId);
        if (! $placement) {
            return collect();
        }

        return DB::transaction(function () use ($placementId, $overrides) {
            $results = collect();

            foreach ($overrides as $key => $value) {
                $override = WidgetThemeOverride::updateOrCreate(
                    ['layout_widget_placement_id' => $placementId, 'variable_key' => $key],
                    ['value' => $value],
                );
                $results->push($override);
            }

            return $results;
        });
    }

    public function removeWidgetThemeOverride(string $placementId, string $variableKey): bool
    {
        return (bool) WidgetThemeOverride::where('layout_widget_placement_id', $placementId)
            ->where('variable_key', $variableKey)
            ->delete();
    }

    // ─── Template Cloning ────────────────────────────────

    public function cloneTemplate(string $sourceTemplateId, array $overrides = []): ?PosLayoutTemplate
    {
        $source = PosLayoutTemplate::find($sourceTemplateId);
        if (! $source) {
            return null;
        }

        return DB::transaction(function () use ($source, $overrides) {
            $clone = $source->replicate([
                'id', 'published_at', 'is_locked',
            ]);

            $clone->name = $overrides['name'] ?? $source->name . ' (Copy)';
            $clone->name_ar = $overrides['name_ar'] ?? $source->name_ar . ' (نسخة)';
            $clone->layout_key = $overrides['layout_key'] ?? $source->layout_key . '-copy-' . substr(md5((string) now()->timestamp . random_int(0, 999999)), 0, 8);
            $clone->is_default = false;
            $clone->is_locked = false;
            $clone->clone_source_id = $source->id;
            $clone->version = '1.0.0';
            $clone->published_at = null;
            $clone->save();

            // Clone widget placements
            $sourcePlacements = LayoutWidgetPlacement::where('pos_layout_template_id', $source->id)->get();

            foreach ($sourcePlacements as $sp) {
                $newPlacement = $sp->replicate(['id']);
                $newPlacement->pos_layout_template_id = $clone->id;
                $newPlacement->save();

                // Clone theme overrides for each placement
                $overridesData = WidgetThemeOverride::where('layout_widget_placement_id', $sp->id)->get();
                foreach ($overridesData as $override) {
                    $newOverride = $override->replicate(['id']);
                    $newOverride->layout_widget_placement_id = $newPlacement->id;
                    $newOverride->save();
                }
            }

            return $clone->fresh();
        });
    }

    // ─── Template Versioning ─────────────────────────────

    public function createVersion(string $templateId, string $versionNumber, ?string $changelog = null, ?string $publishedBy = null): ?TemplateVersion
    {
        $template = PosLayoutTemplate::find($templateId);
        if (! $template) {
            return null;
        }

        $placements = LayoutWidgetPlacement::where('pos_layout_template_id', $templateId)
            ->with('themeOverrides')
            ->get();

        $canvasSnapshot = [
            'canvas_columns' => $template->canvas_columns,
            'canvas_rows' => $template->canvas_rows,
            'canvas_gap_px' => $template->canvas_gap_px,
            'canvas_padding_px' => $template->canvas_padding_px,
            'breakpoints' => $template->breakpoints,
            'config' => $template->config,
        ];

        $widgetPlacementsSnapshot = $placements->map(fn ($p) => [
            'widget_slug' => $p->widget?->slug,
            'instance_key' => $p->instance_key,
            'grid_x' => $p->grid_x,
            'grid_y' => $p->grid_y,
            'grid_w' => $p->grid_w,
            'grid_h' => $p->grid_h,
            'z_index' => $p->z_index,
            'properties' => $p->properties,
            'is_visible' => $p->is_visible,
            'theme_overrides' => $p->themeOverrides->pluck('value', 'variable_key')->toArray(),
        ])->toArray();

        $version = TemplateVersion::create([
            'pos_layout_template_id' => $templateId,
            'version_number' => $versionNumber,
            'changelog' => $changelog,
            'canvas_snapshot' => $canvasSnapshot,
            'theme_snapshot' => null,
            'widget_placements_snapshot' => $widgetPlacementsSnapshot,
            'published_by' => $publishedBy,
            'published_at' => now(),
        ]);

        $template->update(['version' => $versionNumber]);

        return $version;
    }

    public function getVersions(string $templateId): Collection
    {
        return TemplateVersion::where('pos_layout_template_id', $templateId)
            ->orderByDesc('published_at')
            ->get();
    }

    public function getVersion(string $versionId): ?TemplateVersion
    {
        return TemplateVersion::find($versionId);
    }

    // ─── Full Layout Export ──────────────────────────────

    public function getFullLayout(string $templateId): ?array
    {
        $template = PosLayoutTemplate::find($templateId);
        if (! $template) {
            return null;
        }

        $placements = $this->getTemplatePlacements($templateId);

        return [
            'template' => $template,
            'canvas' => $this->getCanvasConfig($templateId),
            'placements' => $placements,
        ];
    }
}
