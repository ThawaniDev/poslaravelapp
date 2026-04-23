<?php

namespace App\Domain\LabelPrinting\Services;

use App\Domain\Auth\Models\User;
use App\Domain\LabelPrinting\Models\LabelPrintHistory;
use App\Domain\LabelPrinting\Models\LabelTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class LabelService
{
    public function listTemplates(string $orgId): Collection
    {
        return LabelTemplate::where('organization_id', $orgId)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function getPresets(?string $orgId = null): Collection
    {
        // Presets live in the organization's namespace. If the organization
        // has none yet, seed the spec-mandated defaults on first access.
        if ($orgId !== null) {
            $existing = LabelTemplate::where('organization_id', $orgId)
                ->where('is_preset', true)
                ->count();
            if ($existing === 0) {
                $this->ensureSystemPresets($orgId);
            }
            return LabelTemplate::where('organization_id', $orgId)
                ->where('is_preset', true)
                ->orderBy('name')
                ->get();
        }
        return LabelTemplate::where('is_preset', true)->orderBy('name')->get();
    }

    /**
     * Insert the three spec-mandated system presets for an organization
     * if they do not already exist:
     *   1. Standard Product (50×30 mm, Code128 barcode)
     *   2. Shelf Edge       (60×30 mm, EAN-13, large price)
     *   3. Weighable Item   (50×30 mm, EAN-13 with embedded weight prefix)
     */
    public function ensureSystemPresets(string $orgId): void
    {
        $defaults = [
            [
                'name' => 'Standard Product',
                'label_width_mm' => 50,
                'label_height_mm' => 30,
                'layout_json' => [
                    'preset_slug' => 'standard_product',
                    'elements' => [
                        ['type' => 'product_name', 'x' => 2.0, 'y' => 2.0, 'width' => 46.0, 'height' => 5.0, 'config' => ['font_size' => 10]],
                        ['type' => 'price', 'x' => 2.0, 'y' => 8.0, 'width' => 46.0, 'height' => 6.0, 'config' => ['font_size' => 14, 'show_currency' => true]],
                        ['type' => 'barcode', 'x' => 2.0, 'y' => 16.0, 'width' => 46.0, 'height' => 11.0, 'config' => ['format' => 'code128', 'show_text' => true]],
                    ],
                ],
            ],
            [
                'name' => 'Shelf Edge',
                'label_width_mm' => 60,
                'label_height_mm' => 30,
                'layout_json' => [
                    'preset_slug' => 'shelf_edge',
                    'elements' => [
                        ['type' => 'product_name', 'x' => 2.0, 'y' => 2.0, 'width' => 36.0, 'height' => 6.0, 'config' => ['font_size' => 9]],
                        ['type' => 'price', 'x' => 2.0, 'y' => 9.0, 'width' => 36.0, 'height' => 14.0, 'config' => ['font_size' => 22, 'show_currency' => true]],
                        ['type' => 'sku', 'x' => 2.0, 'y' => 24.0, 'width' => 36.0, 'height' => 4.0, 'config' => []],
                        ['type' => 'barcode', 'x' => 40.0, 'y' => 4.0, 'width' => 18.0, 'height' => 22.0, 'config' => ['format' => 'ean13', 'show_text' => true]],
                    ],
                ],
            ],
            [
                'name' => 'Weighable Item',
                'label_width_mm' => 50,
                'label_height_mm' => 30,
                'layout_json' => [
                    'preset_slug' => 'weighable_item',
                    'weighable' => true,
                    'elements' => [
                        ['type' => 'product_name', 'x' => 2.0, 'y' => 2.0, 'width' => 46.0, 'height' => 5.0, 'config' => ['font_size' => 10]],
                        ['type' => 'weight', 'x' => 2.0, 'y' => 8.0, 'width' => 22.0, 'height' => 5.0, 'config' => ['font_size' => 9]],
                        ['type' => 'price', 'x' => 24.0, 'y' => 8.0, 'width' => 24.0, 'height' => 5.0, 'config' => ['font_size' => 12, 'show_currency' => true]],
                        ['type' => 'barcode', 'x' => 2.0, 'y' => 14.0, 'width' => 46.0, 'height' => 13.0, 'config' => ['format' => 'ean13', 'show_text' => true]],
                    ],
                ],
            ],
        ];

        // Industry-specific add-ons (spec section 2 — Industry-Specific Workflows).
        // Looks up the org's business_type when available and appends matching presets.
        $businessType = \App\Domain\Core\Models\Organization::query()
            ->whereKey($orgId)
            ->value('business_type');

        if ($businessType !== null) {
            $type = strtolower(is_object($businessType) && property_exists($businessType, 'value')
                ? (string) $businessType->value
                : (string) $businessType);
            if (str_contains($type, 'pharm')) {
                $defaults[] = [
                    'name' => 'Pharmacy Label',
                    'label_width_mm' => 60,
                    'label_height_mm' => 40,
                    'layout_json' => [
                        'preset_slug' => 'pharmacy',
                        'industry' => 'pharmacy',
                        'elements' => [
                            ['type' => 'product_name', 'x' => 2.0, 'y' => 2.0, 'width' => 56.0, 'height' => 5.0, 'config' => ['font_size' => 10]],
                            ['type' => 'custom_text', 'x' => 2.0, 'y' => 8.0, 'width' => 56.0, 'height' => 4.0, 'config' => ['font_size' => 8, 'binding' => 'dosage']],
                            ['type' => 'expiry_date', 'x' => 2.0, 'y' => 13.0, 'width' => 28.0, 'height' => 4.0, 'config' => ['font_size' => 8]],
                            ['type' => 'custom_text', 'x' => 30.0, 'y' => 13.0, 'width' => 28.0, 'height' => 4.0, 'config' => ['font_size' => 8, 'binding' => 'batch']],
                            ['type' => 'barcode', 'x' => 2.0, 'y' => 18.0, 'width' => 40.0, 'height' => 18.0, 'config' => ['format' => 'code128', 'show_text' => true]],
                            ['type' => 'price', 'x' => 44.0, 'y' => 22.0, 'width' => 14.0, 'height' => 8.0, 'config' => ['font_size' => 14, 'show_currency' => true]],
                        ],
                    ],
                ];
            }
            if (str_contains($type, 'jewel') || str_contains($type, 'gold')) {
                $defaults[] = [
                    'name' => 'Jewelry Tag',
                    'label_width_mm' => 40,
                    'label_height_mm' => 20,
                    'layout_json' => [
                        'preset_slug' => 'jewelry',
                        'industry' => 'jewelry',
                        'elements' => [
                            ['type' => 'product_name', 'x' => 1.0, 'y' => 1.0, 'width' => 38.0, 'height' => 4.0, 'config' => ['font_size' => 8]],
                            ['type' => 'custom_text', 'x' => 1.0, 'y' => 5.0, 'width' => 18.0, 'height' => 3.0, 'config' => ['font_size' => 7, 'binding' => 'karat']],
                            ['type' => 'weight', 'x' => 20.0, 'y' => 5.0, 'width' => 19.0, 'height' => 3.0, 'config' => ['font_size' => 7]],
                            ['type' => 'barcode', 'x' => 1.0, 'y' => 9.0, 'width' => 38.0, 'height' => 8.0, 'config' => ['format' => 'code128', 'show_text' => false]],
                            ['type' => 'price', 'x' => 1.0, 'y' => 17.0, 'width' => 38.0, 'height' => 3.0, 'config' => ['font_size' => 9, 'show_currency' => true]],
                        ],
                    ],
                ];
            }
            if (str_contains($type, 'bake') || str_contains($type, 'restaurant') || str_contains($type, 'food')) {
                $defaults[] = [
                    'name' => 'Bakery / Food Label',
                    'label_width_mm' => 58,
                    'label_height_mm' => 40,
                    'layout_json' => [
                        'preset_slug' => 'bakery',
                        'industry' => 'bakery',
                        'elements' => [
                            ['type' => 'product_name', 'x' => 2.0, 'y' => 2.0, 'width' => 54.0, 'height' => 5.0, 'config' => ['font_size' => 10]],
                            ['type' => 'custom_text', 'x' => 2.0, 'y' => 8.0, 'width' => 27.0, 'height' => 4.0, 'config' => ['font_size' => 7, 'binding' => 'production_date', 'label' => 'Prod']],
                            ['type' => 'expiry_date', 'x' => 30.0, 'y' => 8.0, 'width' => 26.0, 'height' => 4.0, 'config' => ['font_size' => 7]],
                            ['type' => 'barcode', 'x' => 2.0, 'y' => 13.0, 'width' => 36.0, 'height' => 22.0, 'config' => ['format' => 'code128', 'show_text' => true]],
                            ['type' => 'price', 'x' => 40.0, 'y' => 18.0, 'width' => 16.0, 'height' => 14.0, 'config' => ['font_size' => 16, 'show_currency' => true]],
                        ],
                    ],
                ];
            }
        }

        foreach ($defaults as $def) {
            LabelTemplate::firstOrCreate(
                [
                    'organization_id' => $orgId,
                    'name' => $def['name'],
                    'is_preset' => true,
                ],
                [
                    'label_width_mm' => $def['label_width_mm'],
                    'label_height_mm' => $def['label_height_mm'],
                    'layout_json' => $def['layout_json'],
                    'is_default' => false,
                    'sync_version' => 1,
                ]
            );
        }
    }

    public function find(string $templateId): LabelTemplate
    {
        return LabelTemplate::findOrFail($templateId);
    }

    public function create(array $data, User $actor): LabelTemplate
    {
        $data['organization_id'] = $actor->organization_id;
        $data['created_by'] = $actor->id;
        $data['sync_version'] = 1;

        // Cannot create a preset via API
        $data['is_preset'] = false;

        // If setting as default, clear other defaults
        if (!empty($data['is_default'])) {
            LabelTemplate::where('organization_id', $actor->organization_id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        return LabelTemplate::create($data);
    }

    public function update(LabelTemplate $template, array $data): LabelTemplate
    {
        if ($template->is_preset) {
            throw new \RuntimeException('System presets cannot be modified. Duplicate to customise.');
        }

        // If setting as default, clear other defaults
        if (!empty($data['is_default'])) {
            LabelTemplate::where('organization_id', $template->organization_id)
                ->where('is_default', true)
                ->where('id', '!=', $template->id)
                ->update(['is_default' => false]);
        }

        $data['sync_version'] = ($template->sync_version ?? 0) + 1;
        $template->update($data);
        return $template->fresh();
    }

    public function delete(LabelTemplate $template): void
    {
        if ($template->is_preset) {
            throw new \RuntimeException('System presets cannot be deleted.');
        }
        $template->delete();
    }

    public function recordPrintHistory(array $data): LabelPrintHistory
    {
        $data['printed_at'] = now();
        return LabelPrintHistory::create($data);
    }

    public function getPrintHistory(string $storeId, int $perPage = 20): LengthAwarePaginator
    {
        return LabelPrintHistory::where('store_id', $storeId)
            ->orderByDesc('printed_at')
            ->paginate($perPage);
    }
}
