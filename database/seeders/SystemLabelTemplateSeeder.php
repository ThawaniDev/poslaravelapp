<?php

namespace Database\Seeders;

use App\Domain\Core\Models\Organization;
use App\Domain\LabelPrinting\Services\LabelService;
use Illuminate\Database\Seeder;

/**
 * Seeds the three spec-mandated system label presets
 * (Standard Product, Shelf Edge, Weighable Item) for every organization.
 *
 * Idempotent — safe to re-run; LabelService::ensureSystemPresets uses
 * firstOrCreate so existing rows are not duplicated.
 */
class SystemLabelTemplateSeeder extends Seeder
{
    public function run(): void
    {
        /** @var LabelService $service */
        $service = app(LabelService::class);

        $count = 0;
        Organization::query()->select('id')->orderBy('id')->chunk(100, function ($orgs) use ($service, &$count) {
            foreach ($orgs as $org) {
                $service->ensureSystemPresets($org->id);
                $count++;
            }
        });

        $this->command?->info("  ✓ System label presets ensured for {$count} organisation(s)");
    }
}
