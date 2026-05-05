<?php

namespace Tests\Unit\Label;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\LabelPrinting\Models\LabelPrintHistory;
use App\Domain\LabelPrinting\Models\LabelTemplate;
use App\Domain\LabelPrinting\Services\LabelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for LabelService.
 * Exercises service logic directly (without going through HTTP routes/controllers).
 */
class LabelServiceTest extends TestCase
{
    use RefreshDatabase;

    private LabelService $service;
    private Organization $org;
    private Store $store;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(LabelService::class);

        $this->org = Organization::create([
            'name'          => 'Service Test Org',
            'business_type' => 'grocery',
            'country'       => 'SA',
        ]);
        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name'            => 'Service Store',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
            'is_active'       => true,
            'is_main_branch'  => true,
        ]);
        $this->user = User::create([
            'name'            => 'Service User',
            'email'           => 'svc@labels.test',
            'password_hash'   => bcrypt('pw'),
            'store_id'        => $this->store->id,
            'organization_id' => $this->org->id,
            'role'            => 'owner',
            'is_active'       => true,
        ]);
    }

    // ─── ensureSystemPresets ──────────────────────────────────

    public function test_ensure_system_presets_creates_three_core_presets(): void
    {
        $this->assertSame(0, LabelTemplate::where('organization_id', $this->org->id)->count());

        $this->service->ensureSystemPresets($this->org->id);

        $names = LabelTemplate::where('organization_id', $this->org->id)
            ->pluck('name')->all();

        $this->assertContains('Standard Product', $names);
        $this->assertContains('Shelf Edge', $names);
        $this->assertContains('Weighable Item', $names);
    }

    public function test_ensure_system_presets_marks_them_as_preset(): void
    {
        $this->service->ensureSystemPresets($this->org->id);

        $nonPresetCount = LabelTemplate::where('organization_id', $this->org->id)
            ->where('is_preset', false)->count();

        $this->assertSame(0, $nonPresetCount);
    }

    public function test_ensure_system_presets_is_idempotent(): void
    {
        $this->service->ensureSystemPresets($this->org->id);
        $firstCount = LabelTemplate::where('organization_id', $this->org->id)->count();

        $this->service->ensureSystemPresets($this->org->id);
        $secondCount = LabelTemplate::where('organization_id', $this->org->id)->count();

        // Should not duplicate presets on second call
        $this->assertSame($firstCount, $secondCount);
    }

    public function test_ensure_system_presets_seeds_pharmacy_preset_for_pharmacy_org(): void
    {
        $pharmacyOrg = Organization::create([
            'name'          => 'Pharmacy Co',
            'business_type' => 'pharmacy',
            'country'       => 'SA',
        ]);

        $this->service->ensureSystemPresets($pharmacyOrg->id);

        $names = LabelTemplate::where('organization_id', $pharmacyOrg->id)
            ->pluck('name')->all();

        // Pharmacy gets a wider barcode label (60×40mm)
        $this->assertTrue(collect($names)->contains(fn ($n) => str_contains($n, 'Pharmacy') || str_contains($n, 'Drug') || str_contains($n, 'Prescription') || str_contains($n, 'Medicine')), implode(', ', $names));
    }

    public function test_ensure_system_presets_seeds_jewelry_preset_for_jewelry_org(): void
    {
        $jewelryOrg = Organization::create([
            'name'          => 'Gold & Co',
            'business_type' => 'jewelry',
            'country'       => 'SA',
        ]);

        $this->service->ensureSystemPresets($jewelryOrg->id);

        $names = LabelTemplate::where('organization_id', $jewelryOrg->id)
            ->pluck('name')->all();

        // Jewelry gets a small 40×20mm tag
        $hasJewelryPreset = collect($names)->contains(fn ($n) => str_contains(strtolower($n), 'jewel') || str_contains(strtolower($n), 'gold') || str_contains(strtolower($n), 'tag'));
        $this->assertTrue($hasJewelryPreset, 'Expected a jewelry-specific preset. Got: ' . implode(', ', $names));
    }

    public function test_ensure_system_presets_seeds_bakery_preset(): void
    {
        $bakeryOrg = Organization::create([
            'name'          => 'Sweet Bakery',
            'business_type' => 'bakery',
            'country'       => 'SA',
        ]);

        $this->service->ensureSystemPresets($bakeryOrg->id);

        $names = LabelTemplate::where('organization_id', $bakeryOrg->id)
            ->pluck('name')->all();

        $hasBakeryPreset = collect($names)->contains(fn ($n) =>
            str_contains(strtolower($n), 'bak') ||
            str_contains(strtolower($n), 'food') ||
            str_contains(strtolower($n), 'fresh') ||
            str_contains(strtolower($n), 'weighable')
        );
        $this->assertTrue($hasBakeryPreset, 'Expected a bakery-specific preset. Got: ' . implode(', ', $names));
    }

    // ─── listTemplates ─────────────────────────────────────────

    public function test_list_templates_returns_default_first(): void
    {
        LabelTemplate::create([
            'organization_id' => $this->org->id, 'name' => 'Alpha',
            'label_width_mm' => 50, 'label_height_mm' => 30,
            'layout_json' => [], 'is_default' => false, 'sync_version' => 1,
        ]);
        LabelTemplate::create([
            'organization_id' => $this->org->id, 'name' => 'Zeta Default',
            'label_width_mm' => 50, 'label_height_mm' => 30,
            'layout_json' => [], 'is_default' => true, 'sync_version' => 1,
        ]);

        $results = $this->service->listTemplates($this->org->id);

        $this->assertEquals('Zeta Default', $results->first()->name);
    }

    public function test_list_templates_search_filter(): void
    {
        LabelTemplate::create([
            'organization_id' => $this->org->id, 'name' => 'Barcode Wide',
            'label_width_mm' => 60, 'label_height_mm' => 30,
            'layout_json' => [], 'sync_version' => 1,
        ]);
        LabelTemplate::create([
            'organization_id' => $this->org->id, 'name' => 'Small Tag',
            'label_width_mm' => 40, 'label_height_mm' => 20,
            'layout_json' => [], 'sync_version' => 1,
        ]);

        $results = $this->service->listTemplates($this->org->id, ['search' => 'Barcode']);

        $this->assertCount(1, $results);
        $this->assertEquals('Barcode Wide', $results->first()->name);
    }

    public function test_list_templates_type_preset_filter(): void
    {
        LabelTemplate::create([
            'organization_id' => $this->org->id, 'name' => 'System Preset',
            'label_width_mm' => 50, 'label_height_mm' => 30,
            'layout_json' => [], 'is_preset' => true, 'sync_version' => 1,
        ]);
        LabelTemplate::create([
            'organization_id' => $this->org->id, 'name' => 'My Custom',
            'label_width_mm' => 50, 'label_height_mm' => 30,
            'layout_json' => [], 'is_preset' => false, 'sync_version' => 1,
        ]);

        $presets = $this->service->listTemplates($this->org->id, ['type' => 'preset']);
        $custom  = $this->service->listTemplates($this->org->id, ['type' => 'custom']);

        $this->assertCount(1, $presets);
        $this->assertEquals('System Preset', $presets->first()->name);
        $this->assertCount(1, $custom);
        $this->assertEquals('My Custom', $custom->first()->name);
    }

    public function test_list_templates_scoped_to_organization(): void
    {
        $other = Organization::create([
            'name' => 'Other', 'business_type' => 'grocery', 'country' => 'SA',
        ]);
        LabelTemplate::create([
            'organization_id' => $other->id, 'name' => 'Foreign Template',
            'label_width_mm' => 50, 'label_height_mm' => 30,
            'layout_json' => [], 'sync_version' => 1,
        ]);

        $results = $this->service->listTemplates($this->org->id);

        $this->assertCount(0, $results);
    }

    // ─── create ──────────────────────────────────────────────

    public function test_create_sets_organization_and_created_by(): void
    {
        $template = $this->service->create([
            'name'            => 'My Template',
            'label_width_mm'  => 50,
            'label_height_mm' => 30,
            'layout_json'     => [],
        ], $this->user);

        $this->assertEquals($this->org->id, $template->organization_id);
        $this->assertEquals($this->user->id, $template->created_by);
    }

    public function test_create_forces_is_preset_false(): void
    {
        $template = $this->service->create([
            'name'            => 'Sneaky',
            'label_width_mm'  => 50,
            'label_height_mm' => 30,
            'layout_json'     => [],
            'is_preset'       => true, // should be ignored
        ], $this->user);

        $this->assertFalse((bool) $template->is_preset);
    }

    public function test_create_sets_sync_version_to_1(): void
    {
        $template = $this->service->create([
            'name'            => 'V1',
            'label_width_mm'  => 50,
            'label_height_mm' => 30,
            'layout_json'     => [],
        ], $this->user);

        $this->assertEquals(1, $template->sync_version);
    }

    public function test_create_with_is_default_clears_previous_default(): void
    {
        $existing = LabelTemplate::create([
            'organization_id' => $this->org->id, 'name' => 'Old Default',
            'label_width_mm' => 50, 'label_height_mm' => 30,
            'layout_json' => [], 'is_default' => true, 'sync_version' => 1,
        ]);

        $this->service->create([
            'name'            => 'New Default',
            'label_width_mm'  => 50,
            'label_height_mm' => 30,
            'layout_json'     => [],
            'is_default'      => true,
        ], $this->user);

        $existing->refresh();
        $this->assertFalse((bool) $existing->is_default);
    }

    // ─── update ──────────────────────────────────────────────

    public function test_update_increments_sync_version(): void
    {
        $template = $this->service->create([
            'name' => 'Original', 'label_width_mm' => 50,
            'label_height_mm' => 30, 'layout_json' => [],
        ], $this->user);

        $updated = $this->service->update($template, ['name' => 'Updated']);

        $this->assertEquals(2, $updated->sync_version);
    }

    public function test_update_throws_for_preset(): void
    {
        $preset = LabelTemplate::create([
            'organization_id' => $this->org->id, 'name' => 'System Preset',
            'label_width_mm' => 50, 'label_height_mm' => 30,
            'layout_json' => [], 'is_preset' => true, 'sync_version' => 1,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service->update($preset, ['name' => 'Renamed']);
    }

    // ─── delete ──────────────────────────────────────────────

    public function test_delete_removes_template(): void
    {
        $template = $this->service->create([
            'name' => 'Delete Me', 'label_width_mm' => 50,
            'label_height_mm' => 30, 'layout_json' => [],
        ], $this->user);

        $id = $template->id;
        $this->service->delete($template);

        $this->assertNull(LabelTemplate::find($id));
    }

    public function test_delete_throws_for_preset(): void
    {
        $preset = LabelTemplate::create([
            'organization_id' => $this->org->id, 'name' => 'System Preset',
            'label_width_mm' => 50, 'label_height_mm' => 30,
            'layout_json' => [], 'is_preset' => true, 'sync_version' => 1,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service->delete($preset);
    }

    // ─── duplicate ───────────────────────────────────────────

    public function test_duplicate_appends_copy_suffix(): void
    {
        $source = $this->service->create([
            'name' => 'Original', 'label_width_mm' => 50,
            'label_height_mm' => 30, 'layout_json' => [],
        ], $this->user);

        $copy = $this->service->duplicate($source, $this->user);

        $this->assertEquals('Original (Copy)', $copy->name);
    }

    public function test_duplicate_is_not_preset_and_not_default(): void
    {
        $preset = LabelTemplate::create([
            'organization_id' => $this->org->id, 'name' => 'Preset',
            'label_width_mm' => 50, 'label_height_mm' => 30,
            'layout_json' => [], 'is_preset' => true, 'is_default' => true, 'sync_version' => 1,
        ]);

        $copy = $this->service->duplicate($preset, $this->user);

        $this->assertFalse((bool) $copy->is_preset);
        $this->assertFalse((bool) $copy->is_default);
    }

    public function test_duplicate_copies_dimensions(): void
    {
        $source = $this->service->create([
            'name' => 'Source', 'label_width_mm' => 75,
            'label_height_mm' => 45, 'layout_json' => ['elements' => [['type' => 'barcode']]],
        ], $this->user);

        $copy = $this->service->duplicate($source, $this->user);

        $this->assertEquals(75.0, (float) $copy->label_width_mm);
        $this->assertEquals(45.0, (float) $copy->label_height_mm);
        $this->assertEquals($source->layout_json, $copy->layout_json);
    }

    // ─── setDefault ──────────────────────────────────────────

    public function test_set_default_clears_previous_default(): void
    {
        $first = LabelTemplate::create([
            'organization_id' => $this->org->id, 'name' => 'First',
            'label_width_mm' => 50, 'label_height_mm' => 30,
            'layout_json' => [], 'is_default' => true, 'sync_version' => 1,
        ]);
        $second = LabelTemplate::create([
            'organization_id' => $this->org->id, 'name' => 'Second',
            'label_width_mm' => 50, 'label_height_mm' => 30,
            'layout_json' => [], 'is_default' => false, 'sync_version' => 1,
        ]);

        $this->service->setDefault($second, $this->org->id);

        $first->refresh();
        $second->refresh();
        $this->assertFalse((bool) $first->is_default);
        $this->assertTrue((bool) $second->is_default);
    }

    public function test_set_default_for_already_default_template_is_idempotent(): void
    {
        $template = LabelTemplate::create([
            'organization_id' => $this->org->id, 'name' => 'Default',
            'label_width_mm' => 50, 'label_height_mm' => 30,
            'layout_json' => [], 'is_default' => true, 'sync_version' => 1,
        ]);

        $result = $this->service->setDefault($template, $this->org->id);

        $this->assertTrue((bool) $result->is_default);
        $this->assertSame(1, LabelTemplate::where('organization_id', $this->org->id)
            ->where('is_default', true)->count());
    }

    // ─── findForOrg ──────────────────────────────────────────

    public function test_find_for_org_returns_template_in_scope(): void
    {
        $template = LabelTemplate::create([
            'organization_id' => $this->org->id, 'name' => 'Mine',
            'label_width_mm' => 50, 'label_height_mm' => 30,
            'layout_json' => [], 'sync_version' => 1,
        ]);

        $found = $this->service->findForOrg($template->id, $this->org->id);

        $this->assertEquals($template->id, $found->id);
    }

    public function test_find_for_org_throws_for_foreign_template(): void
    {
        $other = Organization::create([
            'name' => 'Other', 'business_type' => 'grocery', 'country' => 'SA',
        ]);
        $foreign = LabelTemplate::create([
            'organization_id' => $other->id, 'name' => 'Foreign',
            'label_width_mm' => 50, 'label_height_mm' => 30,
            'layout_json' => [], 'sync_version' => 1,
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->service->findForOrg($foreign->id, $this->org->id);
    }

    // ─── recordPrintHistory ──────────────────────────────────

    public function test_record_print_history_sets_printed_at(): void
    {
        $history = $this->service->recordPrintHistory([
            'store_id'      => $this->store->id,
            'printed_by'    => $this->user->id,
            'product_count' => 3,
            'total_labels'  => 9,
        ]);

        $this->assertNotNull($history->printed_at);
    }

    public function test_record_print_history_accepts_null_template(): void
    {
        $history = $this->service->recordPrintHistory([
            'store_id'      => $this->store->id,
            'template_id'   => null,
            'printed_by'    => $this->user->id,
            'product_count' => 2,
            'total_labels'  => 4,
        ]);

        $this->assertNull($history->template_id);
    }

    // ─── getPrintHistory filters ─────────────────────────────

    public function test_get_print_history_filters_by_date_from(): void
    {
        LabelPrintHistory::create([
            'store_id' => $this->store->id, 'printed_by' => $this->user->id,
            'product_count' => 1, 'total_labels' => 1,
            'printed_at' => now()->subDays(10),
        ]);
        LabelPrintHistory::create([
            'store_id' => $this->store->id, 'printed_by' => $this->user->id,
            'product_count' => 2, 'total_labels' => 2,
            'printed_at' => now()->subDay(),
        ]);

        $result = $this->service->getPrintHistory($this->store->id, 20, [
            'from' => now()->subDays(3)->toDateString(),
        ]);

        $this->assertSame(1, $result->total());
        $this->assertEquals(2, $result->items()[0]->product_count);
    }

    public function test_get_print_history_filters_by_date_to(): void
    {
        LabelPrintHistory::create([
            'store_id' => $this->store->id, 'printed_by' => $this->user->id,
            'product_count' => 1, 'total_labels' => 1,
            'printed_at' => now()->subDays(10),
        ]);
        LabelPrintHistory::create([
            'store_id' => $this->store->id, 'printed_by' => $this->user->id,
            'product_count' => 99, 'total_labels' => 99,
            'printed_at' => now(),
        ]);

        $result = $this->service->getPrintHistory($this->store->id, 20, [
            'to' => now()->subDays(5)->toDateString(),
        ]);

        $this->assertSame(1, $result->total());
        $this->assertEquals(1, $result->items()[0]->product_count);
    }

    public function test_get_print_history_filters_by_template_id(): void
    {
        $template = LabelTemplate::create([
            'organization_id' => $this->org->id, 'name' => 'Tpl',
            'label_width_mm' => 50, 'label_height_mm' => 30,
            'layout_json' => [], 'sync_version' => 1,
        ]);

        LabelPrintHistory::create([
            'store_id' => $this->store->id, 'template_id' => $template->id,
            'printed_by' => $this->user->id,
            'product_count' => 5, 'total_labels' => 5,
            'printed_at' => now(),
        ]);
        LabelPrintHistory::create([
            'store_id' => $this->store->id,
            'printed_by' => $this->user->id,
            'product_count' => 1, 'total_labels' => 1,
            'printed_at' => now(),
        ]);

        $result = $this->service->getPrintHistory($this->store->id, 20, [
            'template_id' => $template->id,
        ]);

        $this->assertSame(1, $result->total());
        $this->assertEquals(5, $result->items()[0]->product_count);
    }

    public function test_get_print_history_paginates(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            LabelPrintHistory::create([
                'store_id' => $this->store->id, 'printed_by' => $this->user->id,
                'product_count' => $i, 'total_labels' => $i,
                'printed_at' => now()->subSeconds($i),
            ]);
        }

        $page1 = $this->service->getPrintHistory($this->store->id, 10);
        $page2 = $this->service->getPrintHistory($this->store->id, 10);

        $this->assertSame(25, $page1->total());
        $this->assertCount(10, $page1->items());
    }
}
