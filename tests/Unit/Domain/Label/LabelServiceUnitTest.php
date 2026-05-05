<?php

namespace Tests\Unit\Domain\Label;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\LabelPrinting\Models\LabelPrintHistory;
use App\Domain\LabelPrinting\Models\LabelTemplate;
use App\Domain\LabelPrinting\Services\LabelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for LabelService business logic.
 *
 * Covers all service methods exhaustively:
 *   listTemplates, getPresets, ensureSystemPresets, find, findForOrg,
 *   create, update, delete, duplicate, setDefault,
 *   recordPrintHistory, getPrintHistory, printHistoryStats
 */
class LabelServiceUnitTest extends TestCase
{
    use RefreshDatabase;

    private LabelService $service;
    private Organization $org;
    private Store $store;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LabelService();

        $this->org = Organization::create([
            'name'          => 'Test Org',
            'business_type' => 'grocery',
            'country'       => 'SA',
        ]);
        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name'            => 'Test Store',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
            'is_active'       => true,
            'is_main_branch'  => true,
        ]);
        $this->user = User::create([
            'name'            => 'Owner',
            'email'           => 'owner@unit-label.com',
            'password_hash'   => bcrypt('pw'),
            'store_id'        => $this->store->id,
            'organization_id' => $this->org->id,
            'role'            => 'owner',
            'is_active'       => true,
        ]);
    }

    // ════════════════════════════════════════════════════════════════
    // listTemplates
    // ════════════════════════════════════════════════════════════════

    public function test_listTemplates_returns_all_org_templates(): void
    {
        $this->makeTemplate(['name' => 'Alpha', 'is_default' => false]);
        $this->makeTemplate(['name' => 'Beta', 'is_default' => false]);

        $result = $this->service->listTemplates($this->org->id);

        $this->assertCount(2, $result);
    }

    public function test_listTemplates_does_not_return_other_org_templates(): void
    {
        $otherOrg = Organization::create(['name' => 'Other', 'business_type' => 'fashion', 'country' => 'SA']);
        $this->makeTemplate(['name' => 'Mine']);
        LabelTemplate::create([
            'organization_id' => $otherOrg->id,
            'name'            => 'NotMine',
            'label_width_mm'  => 50,
            'label_height_mm' => 30,
            'layout_json'     => ['fields' => []],
            'sync_version'    => 1,
        ]);

        $result = $this->service->listTemplates($this->org->id);

        $this->assertCount(1, $result);
        $this->assertEquals('Mine', $result->first()->name);
    }

    public function test_listTemplates_orders_default_first(): void
    {
        $this->makeTemplate(['name' => 'Zzz', 'is_default' => false]);
        $this->makeTemplate(['name' => 'Aaa', 'is_default' => true]);
        $this->makeTemplate(['name' => 'Mmm', 'is_default' => false]);

        $result = $this->service->listTemplates($this->org->id);

        $this->assertEquals('Aaa', $result->first()->name);
    }

    public function test_listTemplates_returns_empty_when_org_has_no_templates(): void
    {
        $result = $this->service->listTemplates($this->org->id);
        $this->assertEmpty($result);
    }

    // ════════════════════════════════════════════════════════════════
    // getPresets / ensureSystemPresets
    // ════════════════════════════════════════════════════════════════

    public function test_getPresets_seeds_standard_presets_on_first_access(): void
    {
        $this->assertSame(0, LabelTemplate::where('organization_id', $this->org->id)->where('is_preset', true)->count());

        $presets = $this->service->getPresets($this->org->id);

        $names = $presets->pluck('name')->all();
        $this->assertContains('Standard Product', $names);
        $this->assertContains('Shelf Edge', $names);
        $this->assertContains('Weighable Item', $names);
    }

    public function test_getPresets_does_not_duplicate_on_repeated_calls(): void
    {
        $this->service->getPresets($this->org->id);
        $this->service->getPresets($this->org->id);

        $count = LabelTemplate::where('organization_id', $this->org->id)
            ->where('is_preset', true)
            ->where('name', 'Standard Product')
            ->count();
        $this->assertSame(1, $count);
    }

    public function test_getPresets_seeds_pharmacy_preset_for_pharmacy_business(): void
    {
        $pharmaOrg = Organization::create([
            'name'          => 'Pharma',
            'business_type' => 'pharmacy',
            'country'       => 'SA',
        ]);

        $presets = $this->service->getPresets($pharmaOrg->id);

        $names = $presets->pluck('name')->all();
        $this->assertContains('Pharmacy Label', $names);
    }

    public function test_getPresets_seeds_jewelry_preset_for_jewelry_business(): void
    {
        $jewelOrg = Organization::create([
            'name'          => 'Jewels',
            'business_type' => 'jewelry',
            'country'       => 'SA',
        ]);

        $presets = $this->service->getPresets($jewelOrg->id);

        $names = $presets->pluck('name')->all();
        $this->assertContains('Jewelry Tag', $names);
    }

    public function test_getPresets_returns_only_presets_not_custom_templates(): void
    {
        $this->makeTemplate(['name' => 'My Custom', 'is_preset' => false]);
        $this->service->getPresets($this->org->id); // seeds presets

        $presets = $this->service->getPresets($this->org->id);

        $this->assertTrue($presets->every(fn ($t) => $t->is_preset === true));
        $names = $presets->pluck('name')->all();
        $this->assertNotContains('My Custom', $names);
    }

    public function test_getPresets_are_scoped_per_org(): void
    {
        $otherOrg = Organization::create(['name' => 'Other', 'business_type' => 'grocery', 'country' => 'SA']);

        $this->service->getPresets($this->org->id);
        $this->service->getPresets($otherOrg->id);

        $myCount    = LabelTemplate::where('organization_id', $this->org->id)->where('is_preset', true)->count();
        $otherCount = LabelTemplate::where('organization_id', $otherOrg->id)->where('is_preset', true)->count();

        $this->assertGreaterThan(0, $myCount);
        $this->assertGreaterThan(0, $otherCount);
        $this->assertSame($myCount, $otherCount, 'Both orgs get the same preset set');
    }

    // ════════════════════════════════════════════════════════════════
    // find / findForOrg
    // ════════════════════════════════════════════════════════════════

    public function test_find_returns_template_by_id(): void
    {
        $t = $this->makeTemplate(['name' => 'FindMe']);
        $found = $this->service->find($t->id, $this->org->id);
        $this->assertEquals($t->id, $found->id);
    }

    public function test_find_throws_when_not_found(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->service->find('00000000-0000-0000-0000-000000000000', $this->org->id);
    }

    public function test_findForOrg_throws_for_other_org_template(): void
    {
        $otherOrg = Organization::create(['name' => 'Other', 'business_type' => 'grocery', 'country' => 'SA']);
        $foreign  = LabelTemplate::create([
            'organization_id' => $otherOrg->id,
            'name'            => 'Foreign',
            'label_width_mm'  => 50,
            'label_height_mm' => 30,
            'layout_json'     => ['fields' => []],
            'sync_version'    => 1,
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->service->findForOrg($foreign->id, $this->org->id);
    }

    // ════════════════════════════════════════════════════════════════
    // create
    // ════════════════════════════════════════════════════════════════

    public function test_create_assigns_org_from_actor(): void
    {
        $t = $this->service->create($this->makeTemplateData(), $this->user);
        $this->assertEquals($this->org->id, $t->organization_id);
    }

    public function test_create_assigns_created_by_from_actor(): void
    {
        $t = $this->service->create($this->makeTemplateData(), $this->user);
        $this->assertEquals($this->user->id, $t->created_by);
    }

    public function test_create_marks_as_non_preset(): void
    {
        $t = $this->service->create($this->makeTemplateData(['is_preset' => true]), $this->user);
        $this->assertFalse((bool) $t->is_preset);
    }

    public function test_create_sets_sync_version_to_1(): void
    {
        $t = $this->service->create($this->makeTemplateData(), $this->user);
        $this->assertSame(1, $t->sync_version);
    }

    public function test_create_clears_previous_default_when_is_default_true(): void
    {
        $existing = $this->makeTemplate(['is_default' => true]);

        $this->service->create($this->makeTemplateData(['is_default' => true]), $this->user);

        $existing->refresh();
        $this->assertFalse((bool) $existing->is_default);
    }

    public function test_create_does_not_touch_defaults_when_is_default_false(): void
    {
        $existing = $this->makeTemplate(['is_default' => true]);

        $this->service->create($this->makeTemplateData(['is_default' => false]), $this->user);

        $existing->refresh();
        $this->assertTrue((bool) $existing->is_default);
    }

    // ════════════════════════════════════════════════════════════════
    // update
    // ════════════════════════════════════════════════════════════════

    public function test_update_changes_template_name(): void
    {
        $t = $this->makeTemplate(['name' => 'Old']);
        $updated = $this->service->update($t, ['name' => 'New']);
        $this->assertEquals('New', $updated->name);
    }

    public function test_update_increments_sync_version(): void
    {
        $t = $this->makeTemplate(['sync_version' => 3]);
        $updated = $this->service->update($t, ['name' => 'Changed']);
        $this->assertSame(4, $updated->sync_version);
    }

    public function test_update_throws_for_preset(): void
    {
        $t = $this->makeTemplate(['is_preset' => true]);

        $this->expectException(\RuntimeException::class);
        $this->service->update($t, ['name' => 'Hacked']);
    }

    public function test_update_clears_other_defaults_when_setting_as_default(): void
    {
        $existing = $this->makeTemplate(['name' => 'Old Default', 'is_default' => true]);
        $newT     = $this->makeTemplate(['name' => 'New Template', 'is_default' => false]);

        $this->service->update($newT, ['is_default' => true]);

        $existing->refresh();
        $this->assertFalse((bool) $existing->is_default);
    }

    public function test_update_does_not_clear_defaults_when_is_default_false(): void
    {
        $existing = $this->makeTemplate(['name' => 'Default', 'is_default' => true]);
        $newT     = $this->makeTemplate(['name' => 'Other', 'is_default' => false]);

        $this->service->update($newT, ['name' => 'Other Updated']);

        $existing->refresh();
        $this->assertTrue((bool) $existing->is_default);
    }

    // ════════════════════════════════════════════════════════════════
    // delete
    // ════════════════════════════════════════════════════════════════

    public function test_delete_removes_custom_template(): void
    {
        $t = $this->makeTemplate(['is_preset' => false]);
        $this->service->delete($t);
        $this->assertDatabaseMissing('label_templates', ['id' => $t->id]);
    }

    public function test_delete_throws_for_preset(): void
    {
        $t = $this->makeTemplate(['is_preset' => true]);

        $this->expectException(\RuntimeException::class);
        $this->service->delete($t);
    }

    // ════════════════════════════════════════════════════════════════
    // duplicate
    // ════════════════════════════════════════════════════════════════

    public function test_duplicate_creates_a_copy_with_copy_suffix(): void
    {
        $t    = $this->makeTemplate(['name' => 'My Template']);
        $copy = $this->service->duplicate($t, $this->user);

        $this->assertEquals('My Template (Copy)', $copy->name);
        $this->assertNotEquals($t->id, $copy->id);
    }

    public function test_duplicate_preserves_dimensions_and_layout(): void
    {
        $layout = ['elements' => [['type' => 'barcode', 'x' => 2, 'y' => 2]]];
        $t = $this->makeTemplate(['label_width_mm' => 60, 'label_height_mm' => 40, 'layout_json' => $layout]);

        $copy = $this->service->duplicate($t, $this->user);

        $this->assertEquals(60.0, (float) $copy->label_width_mm);
        $this->assertEquals(40.0, (float) $copy->label_height_mm);
        $this->assertEquals($layout, $copy->layout_json);
    }

    public function test_duplicate_marks_copy_as_non_preset_and_non_default(): void
    {
        $t = $this->makeTemplate(['is_preset' => true, 'is_default' => true]);
        $copy = $this->service->duplicate($t, $this->user);

        $this->assertFalse((bool) $copy->is_preset);
        $this->assertFalse((bool) $copy->is_default);
    }

    public function test_duplicate_assigns_actor_as_created_by(): void
    {
        $t    = $this->makeTemplate();
        $copy = $this->service->duplicate($t, $this->user);

        $this->assertEquals($this->user->id, $copy->created_by);
    }

    // ════════════════════════════════════════════════════════════════
    // setDefault
    // ════════════════════════════════════════════════════════════════

    public function test_setDefault_marks_template_as_default(): void
    {
        $t = $this->makeTemplate(['is_default' => false]);
        $updated = $this->service->setDefault($t, $this->org->id);
        $this->assertTrue((bool) $updated->is_default);
    }

    public function test_setDefault_clears_previous_org_default(): void
    {
        $old = $this->makeTemplate(['name' => 'Old Default', 'is_default' => true]);
        $new = $this->makeTemplate(['name' => 'New Template', 'is_default' => false]);

        $this->service->setDefault($new, $this->org->id);

        $old->refresh();
        $this->assertFalse((bool) $old->is_default);
    }

    public function test_setDefault_does_not_clear_other_org_default(): void
    {
        $otherOrg  = Organization::create(['name' => 'Other', 'business_type' => 'grocery', 'country' => 'SA']);
        $otherDefault = LabelTemplate::create([
            'organization_id' => $otherOrg->id,
            'name'            => 'Other Org Default',
            'label_width_mm'  => 50,
            'label_height_mm' => 30,
            'layout_json'     => ['fields' => []],
            'is_default'      => true,
            'sync_version'    => 1,
        ]);

        $mine = $this->makeTemplate(['is_default' => false]);
        $this->service->setDefault($mine, $this->org->id);

        $otherDefault->refresh();
        $this->assertTrue((bool) $otherDefault->is_default, 'Other org default must not be cleared');
    }

    // ════════════════════════════════════════════════════════════════
    // recordPrintHistory
    // ════════════════════════════════════════════════════════════════

    public function test_recordPrintHistory_creates_record(): void
    {
        $this->service->recordPrintHistory([
            'store_id'      => $this->store->id,
            'printed_by'    => $this->user->id,
            'product_count' => 3,
            'total_labels'  => 6,
        ]);

        $this->assertDatabaseHas('label_print_history', [
            'store_id'      => $this->store->id,
            'product_count' => 3,
            'total_labels'  => 6,
        ]);
    }

    public function test_recordPrintHistory_sets_printed_at_to_now(): void
    {
        $before = now()->subSecond();
        $record = $this->service->recordPrintHistory([
            'store_id'      => $this->store->id,
            'printed_by'    => $this->user->id,
            'product_count' => 1,
            'total_labels'  => 1,
        ]);
        $after = now()->addSecond();

        $this->assertNotNull($record->printed_at);
        $this->assertTrue($record->printed_at->between($before, $after));
    }

    public function test_recordPrintHistory_stores_printer_language(): void
    {
        $record = $this->service->recordPrintHistory([
            'store_id'         => $this->store->id,
            'printed_by'       => $this->user->id,
            'product_count'    => 2,
            'total_labels'     => 4,
            'printer_language' => 'zpl',
        ]);

        $this->assertEquals('zpl', $record->printer_language);
    }

    public function test_recordPrintHistory_stores_duration_ms(): void
    {
        $record = $this->service->recordPrintHistory([
            'store_id'      => $this->store->id,
            'printed_by'    => $this->user->id,
            'product_count' => 1,
            'total_labels'  => 5,
            'duration_ms'   => 1500,
        ]);

        $this->assertSame(1500, (int) $record->duration_ms);
    }

    public function test_recordPrintHistory_allows_null_template_id(): void
    {
        $record = $this->service->recordPrintHistory([
            'store_id'      => $this->store->id,
            'printed_by'    => $this->user->id,
            'product_count' => 1,
            'total_labels'  => 1,
            'template_id'   => null,
        ]);

        $this->assertNull($record->template_id);
    }

    // ════════════════════════════════════════════════════════════════
    // getPrintHistory
    // ════════════════════════════════════════════════════════════════

    public function test_getPrintHistory_returns_records_for_store(): void
    {
        $this->makePrintHistory(['store_id' => $this->store->id, 'product_count' => 2]);

        $paginator = $this->service->getPrintHistory($this->store->id);

        $this->assertSame(1, $paginator->total());
    }

    public function test_getPrintHistory_does_not_return_other_store_records(): void
    {
        $otherStore = Store::create([
            'organization_id' => $this->org->id,
            'name'            => 'Other Store',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
            'is_active'       => true,
            'is_main_branch'  => false,
        ]);

        $this->makePrintHistory(['store_id' => $this->store->id, 'product_count' => 1]);
        $this->makePrintHistory(['store_id' => $otherStore->id, 'product_count' => 99]);

        $paginator = $this->service->getPrintHistory($this->store->id);

        $this->assertSame(1, $paginator->total());
        $this->assertSame(1, (int) $paginator->items()[0]->product_count);
    }

    public function test_getPrintHistory_orders_newest_first(): void
    {
        $this->makePrintHistory(['printed_at' => now()->subDays(2)]);
        $this->makePrintHistory(['printed_at' => now()]);
        $this->makePrintHistory(['printed_at' => now()->subDay()]);

        $paginator = $this->service->getPrintHistory($this->store->id);
        $timestamps = collect($paginator->items())->pluck('printed_at')->map(fn ($d) => $d->timestamp)->all();

        $sorted = $timestamps;
        rsort($sorted);
        $this->assertEquals($sorted, $timestamps, 'Results must be ordered newest → oldest');
    }

    public function test_getPrintHistory_filters_by_date_from(): void
    {
        $this->makePrintHistory(['printed_at' => now()->subDays(10)]);
        $this->makePrintHistory(['printed_at' => now()->subDay()]);

        $paginator = $this->service->getPrintHistory(
            $this->store->id,
            filters: ['from' => now()->subDays(3)->toDateString()]
        );

        $this->assertSame(1, $paginator->total());
    }

    public function test_getPrintHistory_filters_by_date_to(): void
    {
        $this->makePrintHistory(['printed_at' => now()->subDays(10)]);
        $this->makePrintHistory(['printed_at' => now()->subDay()]);

        $paginator = $this->service->getPrintHistory(
            $this->store->id,
            filters: ['to' => now()->subDays(5)->toDateString()]
        );

        $this->assertSame(1, $paginator->total());
    }

    public function test_getPrintHistory_filters_by_template_id(): void
    {
        $template = $this->makeTemplate(['name' => 'Specific Template']);
        $this->makePrintHistory(['template_id' => $template->id]);
        $this->makePrintHistory(['template_id' => null]);

        $paginator = $this->service->getPrintHistory(
            $this->store->id,
            filters: ['template_id' => $template->id]
        );

        $this->assertSame(1, $paginator->total());
    }

    public function test_getPrintHistory_paginates_with_per_page(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->makePrintHistory();
        }

        $paginator = $this->service->getPrintHistory($this->store->id, perPage: 2);

        $this->assertSame(5, $paginator->total());
        $this->assertSame(2, $paginator->perPage());
        $this->assertCount(2, $paginator->items());
    }

    // ════════════════════════════════════════════════════════════════
    // printHistoryStats
    // ════════════════════════════════════════════════════════════════

    public function test_printHistoryStats_returns_zeros_for_empty_history(): void
    {
        $stats = $this->service->printHistoryStats($this->store->id);

        $this->assertSame(0, $stats['jobs_last_30_days']);
        $this->assertSame(0, $stats['products_last_30_days']);
        $this->assertSame(0, $stats['labels_last_30_days']);
    }

    public function test_printHistoryStats_aggregates_recent_jobs(): void
    {
        $this->makePrintHistory(['product_count' => 3, 'total_labels' => 6, 'printed_at' => now()->subDays(5)]);
        $this->makePrintHistory(['product_count' => 2, 'total_labels' => 4, 'printed_at' => now()->subDays(10)]);

        $stats = $this->service->printHistoryStats($this->store->id);

        $this->assertSame(2, $stats['jobs_last_30_days']);
        $this->assertSame(5, $stats['products_last_30_days']);
        $this->assertSame(10, $stats['labels_last_30_days']);
    }

    public function test_printHistoryStats_excludes_jobs_older_than_30_days(): void
    {
        $this->makePrintHistory(['product_count' => 99, 'total_labels' => 99, 'printed_at' => now()->subDays(31)]);

        $stats = $this->service->printHistoryStats($this->store->id);

        $this->assertSame(0, $stats['jobs_last_30_days']);
    }

    public function test_printHistoryStats_scoped_to_store(): void
    {
        $otherStore = Store::create([
            'organization_id' => $this->org->id,
            'name'            => 'Branch',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
            'is_active'       => true,
            'is_main_branch'  => false,
        ]);

        $this->makePrintHistory(['store_id' => $otherStore->id, 'product_count' => 50, 'total_labels' => 100]);

        $stats = $this->service->printHistoryStats($this->store->id);
        $this->assertSame(0, $stats['jobs_last_30_days']);
    }

    // ════════════════════════════════════════════════════════════════
    // Helpers
    // ════════════════════════════════════════════════════════════════

    private function makeTemplate(array $overrides = []): LabelTemplate
    {
        return LabelTemplate::create(array_merge([
            'organization_id' => $this->org->id,
            'name'            => 'Test Template',
            'label_width_mm'  => 50,
            'label_height_mm' => 30,
            'layout_json'     => ['elements' => []],
            'is_preset'       => false,
            'is_default'      => false,
            'created_by'      => $this->user->id,
            'sync_version'    => 1,
        ], $overrides));
    }

    private function makeTemplateData(array $overrides = []): array
    {
        return array_merge([
            'name'            => 'My Template',
            'label_width_mm'  => 50,
            'label_height_mm' => 30,
            'layout_json'     => ['elements' => []],
        ], $overrides);
    }

    private function makePrintHistory(array $overrides = []): LabelPrintHistory
    {
        return LabelPrintHistory::create(array_merge([
            'store_id'      => $this->store->id,
            'printed_by'    => $this->user->id,
            'product_count' => 1,
            'total_labels'  => 1,
            'printed_at'    => now(),
        ], $overrides));
    }
}
