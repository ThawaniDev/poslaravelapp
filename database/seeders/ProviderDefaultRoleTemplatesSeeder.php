<?php

namespace Database\Seeders;

use App\Domain\StaffManagement\Services\RoleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Syncs `default_role_templates` (+ permissions pivot) from
 * RoleService::DEFAULT_ROLE_TEMPLATES so the platform admin
 * `/admin/default-role-templates` page reflects the canonical
 * 16-role / 187-permission model.
 *
 * - Slug = template `name`.
 * - Owner ('*') gets every active provider_permission.
 * - Permissions resolved against `provider_permissions.name`; unknown names skipped.
 * - Idempotent: upserts templates, diffs pivot rows.
 *
 * Run: php artisan db:seed --class=ProviderDefaultRoleTemplatesSeeder --force
 */
class ProviderDefaultRoleTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        // Build provider_permission name → id lookup (active only).
        $permMap = DB::table('provider_permissions')
            ->where('is_active', true)
            ->pluck('id', 'name')
            ->all();
        $allPermIds = array_values($permMap);
        $this->command->info('Provider permissions available: ' . count($permMap));

        $templatesUpserted = 0;
        $linksAdded = 0;
        $linksRemoved = 0;
        $missingNames = [];

        foreach (RoleService::DEFAULT_ROLE_TEMPLATES as $tpl) {
            $slug = $tpl['name'];

            $existing = DB::table('default_role_templates')->where('slug', $slug)->first();

            $payload = [
                'name'           => $tpl['display_name'],
                'name_ar'        => $tpl['display_name_ar'] ?? $tpl['display_name'],
                'description'    => $tpl['description'] ?? null,
                'description_ar' => $tpl['description_ar'] ?? null,
                'updated_at'     => now(),
            ];

            if ($existing) {
                DB::table('default_role_templates')->where('id', $existing->id)->update($payload);
                $templateId = $existing->id;
            } else {
                $templateId = (string) Str::uuid();
                DB::table('default_role_templates')->insert(array_merge($payload, [
                    'id'         => $templateId,
                    'slug'       => $slug,
                    'created_at' => now(),
                ]));
            }
            $templatesUpserted++;

            // Resolve target permission ids.
            if (in_array('*', $tpl['permissions'], true)) {
                $targetIds = $allPermIds;
            } else {
                $targetIds = [];
                foreach ($tpl['permissions'] as $name) {
                    if (isset($permMap[$name])) {
                        $targetIds[] = $permMap[$name];
                    } else {
                        $missingNames[$name] = true;
                    }
                }
            }

            $currentIds = DB::table('default_role_template_permissions')
                ->where('default_role_template_id', $templateId)
                ->pluck('provider_permission_id')
                ->all();

            $toAdd    = array_values(array_diff($targetIds, $currentIds));
            $toRemove = array_values(array_diff($currentIds, $targetIds));

            if ($toAdd) {
                $rows = array_map(fn ($pid) => [
                    'id'                       => (string) Str::uuid(),
                    'default_role_template_id' => $templateId,
                    'provider_permission_id'   => $pid,
                ], $toAdd);
                foreach (array_chunk($rows, 200) as $chunk) {
                    DB::table('default_role_template_permissions')->insert($chunk);
                }
                $linksAdded += count($toAdd);
            }
            if ($toRemove) {
                DB::table('default_role_template_permissions')
                    ->where('default_role_template_id', $templateId)
                    ->whereIn('provider_permission_id', $toRemove)
                    ->delete();
                $linksRemoved += count($toRemove);
            }
        }

        $this->command->info("✓ Templates upserted: {$templatesUpserted}");
        $this->command->info("✓ Pivot rows: +{$linksAdded} added, -{$linksRemoved} removed");

        if ($missingNames) {
            $this->command->warn('Permissions referenced by templates but missing in provider_permissions:');
            foreach (array_keys($missingNames) as $n) {
                $this->command->warn('  - ' . $n);
            }
        }

        // Report orphan templates not in the canonical list.
        $canonicalSlugs = array_column(RoleService::DEFAULT_ROLE_TEMPLATES, 'name');
        $orphans = DB::table('default_role_templates')
            ->whereNotIn('slug', $canonicalSlugs)
            ->pluck('slug')
            ->all();
        if ($orphans) {
            $this->command->warn('Orphan templates (not in canonical list, left untouched): ' . implode(', ', $orphans));
        }
    }
}
