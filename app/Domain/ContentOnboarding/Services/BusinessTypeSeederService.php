<?php

namespace App\Domain\ContentOnboarding\Services;

use App\Domain\Catalog\Models\Category;
use App\Domain\ContentOnboarding\Models\BusinessType;
use App\Domain\Core\Models\Store;
use App\Domain\Customer\Models\CustomerGroup;
use App\Domain\Customer\Models\LoyaltyConfig;
use App\Domain\Promotion\Models\Promotion;
use App\Domain\StaffManagement\Models\CommissionRule;
use App\Domain\StaffManagement\Models\ShiftTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * BusinessTypeSeederService
 *
 * One-time seeder that copies all template rows from the selected BusinessType
 * into the newly created store/org. Called by StoreService::createStore()
 * immediately after store creation, only when business_type_id is set.
 *
 * Business Rule #8: Seeding is one-time. Subsequent template changes do NOT
 * propagate to already-seeded stores.
 *
 * Idempotency: each seed_X method first checks whether records already exist
 * for this store/org, and skips if they do.
 */
class BusinessTypeSeederService
{
    /**
     * Seed all available templates from the given BusinessType into the store.
     *
     * Returns a summary of what was seeded.
     */
    public function seed(Store $store, BusinessType $businessType): array
    {
        $seeded   = [];
        $skipped  = [];
        $orgId    = $store->organization_id;
        $storeId  = $store->id;
        $typeId   = $businessType->id;

        DB::transaction(function () use ($store, $businessType, $orgId, $storeId, $typeId, &$seeded, &$skipped) {
            // ── 1. Category Templates → categories ──────────────────────────
            $result = $this->seedCategories($orgId, $typeId);
            $result > 0 ? $seeded['categories'] = $result : $skipped[] = 'categories';

            // ── 2. Shift Templates → shift_templates ─────────────────────────
            $result = $this->seedShiftTemplates($storeId, $typeId);
            $result > 0 ? $seeded['shift_templates'] = $result : $skipped[] = 'shift_templates';

            // ── 3. Loyalty Config → loyalty_config ───────────────────────────
            $result = $this->seedLoyaltyConfig($orgId, $typeId);
            $result > 0 ? $seeded['loyalty_config'] = $result : $skipped[] = 'loyalty_config';

            // ── 4. Customer Group Templates → customer_groups ────────────────
            $result = $this->seedCustomerGroups($orgId, $typeId);
            $result > 0 ? $seeded['customer_groups'] = $result : $skipped[] = 'customer_groups';

            // ── 5. Promotion Templates → promotions (inactive) ───────────────
            $result = $this->seedPromotions($orgId, $typeId);
            $result > 0 ? $seeded['promotions'] = $result : $skipped[] = 'promotions';

            // ── 6. Commission Templates → commission_rules (inactive) ─────────
            $result = $this->seedCommissionRules($storeId, $typeId);
            $result > 0 ? $seeded['commission_rules'] = $result : $skipped[] = 'commission_rules';

            // ── 7. Receipt Template → stored in store settings JSON ──────────
            $result = $this->seedReceiptTemplate($store, $typeId);
            $result > 0 ? $seeded['receipt_template'] = $result : $skipped[] = 'receipt_template';

            // ── 8. Industry Config → stored in store settings JSON ───────────
            $result = $this->seedIndustryConfig($store, $typeId);
            $result > 0 ? $seeded['industry_config'] = $result : $skipped[] = 'industry_config';
        });

        Log::info('[BusinessTypeSeeder] Store seeded', [
            'store_id'         => $storeId,
            'business_type_id' => $typeId,
            'seeded'           => $seeded,
            'skipped'          => $skipped,
        ]);

        return ['seeded' => $seeded, 'skipped' => $skipped];
    }

    // ─── Individual seeders ────────────────────────────────────────────────────

    /**
     * Seed category templates as org-level product categories.
     * Skips if org already has categories (idempotency).
     */
    private function seedCategories(string $orgId, string $typeId): int
    {
        // Idempotency: skip if org already has categories
        if (Category::where('organization_id', $orgId)->exists()) {
            return 0;
        }

        $templates = DB::table('business_type_category_templates')
            ->where('business_type_id', $typeId)
            ->orderBy('sort_order')
            ->get();

        if ($templates->isEmpty()) {
            return 0;
        }

        $rows = $templates->map(fn ($t) => [
            'id'              => \Illuminate\Support\Str::uuid()->toString(),
            'organization_id' => $orgId,
            'name'            => $t->category_name,
            'name_ar'         => $t->category_name_ar,
            'sort_order'      => $t->sort_order,
            'is_active'       => true,
            'created_at'      => now(),
            'updated_at'      => now(),
        ])->toArray();

        DB::table('categories')->insert($rows);

        return count($rows);
    }

    /**
     * Seed shift templates into the store's shift_templates.
     * Skips if the store already has shift templates (idempotency).
     */
    private function seedShiftTemplates(string $storeId, string $typeId): int
    {
        if (ShiftTemplate::where('store_id', $storeId)->exists()) {
            return 0;
        }

        $templates = DB::table('business_type_shift_templates')
            ->where('business_type_id', $typeId)
            ->orderBy('sort_order')
            ->get();

        if ($templates->isEmpty()) {
            return 0;
        }

        $rows = $templates->map(fn ($t) => [
            'id'                     => \Illuminate\Support\Str::uuid()->toString(),
            'store_id'               => $storeId,
            'name'                   => $t->name,
            'start_time'             => $t->start_time,
            'end_time'               => $t->end_time,
            'break_duration_minutes' => $t->break_duration_minutes ?? 30,
            'is_active'              => true,
            'created_at'             => now(),
        ])->toArray();

        DB::table('shift_templates')->insert($rows);

        return count($rows);
    }

    /**
     * Seed loyalty config (always seeded as inactive per Business Rule #9).
     * Skips if org already has loyalty config or if program_type = 'none'.
     */
    private function seedLoyaltyConfig(string $orgId, string $typeId): int
    {
        if (LoyaltyConfig::where('organization_id', $orgId)->exists()) {
            return 0;
        }

        $template = DB::table('business_type_loyalty_configs')
            ->where('business_type_id', $typeId)
            ->first();

        if (! $template || $template->program_type === 'none') {
            return 0;
        }

        // Map from template to store-side loyalty_config table
        // The store-side table uses different field names
        DB::table('loyalty_config')->insert([
            'id'                    => \Illuminate\Support\Str::uuid()->toString(),
            'organization_id'       => $orgId,
            'points_per_sar'        => $template->earning_rate      ?? 1.00,
            'sar_per_point'         => $template->redemption_value  ?? 0.01,
            'min_redemption_points' => $template->min_redemption_points ?? 100,
            'is_active'             => false, // always inactive until provider enables
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        return 1;
    }

    /**
     * Seed customer group templates into customer_groups (org-level).
     * Skips if org already has groups.
     */
    private function seedCustomerGroups(string $orgId, string $typeId): int
    {
        if (CustomerGroup::where('organization_id', $orgId)->exists()) {
            return 0;
        }

        $templates = DB::table('business_type_customer_group_templates')
            ->where('business_type_id', $typeId)
            ->orderBy('sort_order')
            ->get();

        if ($templates->isEmpty()) {
            return 0;
        }

        $rows = $templates->map(fn ($t) => [
            'id'              => \Illuminate\Support\Str::uuid()->toString(),
            'organization_id' => $orgId,
            'name'            => $t->name,
            'discount_percent' => $t->discount_percentage ?? 0,
            'created_at'      => now(),
        ])->toArray();

        DB::table('customer_groups')->insert($rows);

        return count($rows);
    }

    /**
     * Seed promotion templates as inactive promotions (Business Rule #6).
     * Skips if org already has any promotions seeded from these templates.
     */
    private function seedPromotions(string $orgId, string $typeId): int
    {
        $templates = DB::table('business_type_promotion_templates')
            ->where('business_type_id', $typeId)
            ->get();

        if ($templates->isEmpty()) {
            return 0;
        }

        // Idempotency: skip if org already has promotions matching any template name
        $templateNames = $templates->pluck('name')->toArray();
        $alreadySeeded = DB::table('promotions')
            ->where('organization_id', $orgId)
            ->whereIn('name', $templateNames)
            ->exists();

        if ($alreadySeeded) {
            return 0;
        }

        $rows = [];
        foreach ($templates as $t) {
            // Map business promotion types to store Promotion types
            $type = $this->mapPromotionType($t->promotion_type);
            if (! $type) {
                continue;
            }

            $rows[] = [
                'id'              => \Illuminate\Support\Str::uuid()->toString(),
                'organization_id' => $orgId,
                'name'            => $t->name,
                'type'            => $type,
                'discount_value'  => $t->discount_value ?? 0,
                'min_order_total' => $t->minimum_order  ?? 0,
                'is_active'       => false, // inactive per Business Rule #6
                'is_stackable'    => false,
                'is_coupon'       => false,
                'usage_count'     => 0,
                'created_at'      => now(),
            ];
        }

        if (empty($rows)) {
            return 0;
        }

        DB::table('promotions')->insert($rows);

        return count($rows);
    }

    /**
     * Seed commission templates as inactive commission rules (Business Rule #7).
     */
    private function seedCommissionRules(string $storeId, string $typeId): int
    {
        if (CommissionRule::where('store_id', $storeId)->exists()) {
            return 0;
        }

        $templates = DB::table('business_type_commission_templates')
            ->where('business_type_id', $typeId)
            ->get();

        if ($templates->isEmpty()) {
            return 0;
        }

        $rows = [];
        foreach ($templates as $t) {
            $rows[] = [
                'id'         => \Illuminate\Support\Str::uuid()->toString(),
                'store_id'   => $storeId,
                'type'       => $this->mapCommissionType($t->commission_type),
                'percentage' => $t->value ?? 0,
                'tiers_json' => $t->tier_thresholds ?? '[]',
                'is_active'  => false, // inactive per Business Rule #7
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (empty($rows)) {
            return 0;
        }

        DB::table('commission_rules')->insert($rows);

        return count($rows);
    }

    /**
     * Seed receipt template into store_settings receipt columns.
     */
    private function seedReceiptTemplate(Store $store, string $typeId): int
    {
        $template = DB::table('business_type_receipt_templates')
            ->where('business_type_id', $typeId)
            ->first();

        if (! $template) {
            return 0;
        }

        $settings = DB::table('store_settings')
            ->where('store_id', $store->id)
            ->first();

        if (! $settings) {
            return 0;
        }

        // Only seed if store still has default paper size (not customized yet)
        if (! empty($settings->receipt_paper_size)) {
            return 0;
        }

        $fontSize = match ($template->font_size ?? 'medium') {
            'small'  => 'small',
            'large'  => 'large',
            default  => 'medium',
        };

        $paperSize = $template->paper_width >= 80 ? '80mm' : '58mm';

        DB::table('store_settings')
            ->where('store_id', $store->id)
            ->update([
                'receipt_paper_size'  => $paperSize,
                'receipt_font_size'   => $fontSize,
                'receipt_show_logo'   => true,
                'receipt_show_date'   => true,
                'receipt_show_cashier' => true,
                'updated_at'          => now(),
            ]);

        return 1;
    }

    /**
     * Seed industry config (active_modules) into store_settings feature flags.
     * Maps known module keys to store_settings boolean columns.
     */
    private function seedIndustryConfig(Store $store, string $typeId): int
    {
        $config = DB::table('business_type_industry_configs')
            ->where('business_type_id', $typeId)
            ->first();

        if (! $config) {
            return 0;
        }

        $settings = DB::table('store_settings')
            ->where('store_id', $store->id)
            ->first();

        if (! $settings) {
            return 0;
        }

        $activeModules = json_decode($config->active_modules ?? '[]', true);

        $updates = [];

        if (in_array('loyalty', $activeModules, true)) {
            $updates['enable_loyalty_points'] = true;
        }
        if (in_array('kitchen_display', $activeModules, true)) {
            $updates['enable_kitchen_display'] = true;
        }
        if (in_array('customer_display', $activeModules, true)) {
            $updates['enable_customer_display'] = true;
        }

        if (empty($updates)) {
            return 0;
        }

        $updates['updated_at'] = now();

        DB::table('store_settings')
            ->where('store_id', $store->id)
            ->update($updates);

        return 1;
    }

    // ─── Type mapping helpers ──────────────────────────────────────────────────

    /**
     * Map business_type_promotion_templates.promotion_type → promotions.type enum.
     * Returns null if there's no valid mapping (skip that row).
     */
    private function mapPromotionType(string $templateType): ?string
    {
        return match ($templateType) {
            'percentage_discount', 'percentage' => 'percentage',
            'fixed_discount', 'fixed'           => 'fixed',
            'buy_x_get_y'                       => 'buy_x_get_y',
            'bundle'                            => 'bundle',
            'free_item'                         => 'free_item',
            'bogo'                              => 'buy_x_get_y',
            'happy_hour'                        => 'percentage',
            default                             => null,
        };
    }

    /**
     * Map business_type_commission_templates.commission_type → commission_rules.type enum.
     */
    private function mapCommissionType(string $templateType): string
    {
        return match ($templateType) {
            'percentage_of_sale'   => 'percentage',
            'flat_per_transaction' => 'flat',
            'tiered'               => 'tiered',
            default                => 'percentage',
        };
    }
}
