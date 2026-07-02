<?php

use App\Domain\ThawaniIntegration\Models\ThawaniColumnMapping;
use Illuminate\Database\Migrations\Migration;

/**
 * Seed the default Thawani ↔ Wameed column mappings.
 *
 * These are the built-in field translations used when pulling products and
 * categories from the Thawani API. Without them the service falls back to
 * returning the raw Thawani payload — which contains fields that do not exist
 * on the Wameed models (e.g. thawani_product_id, store_category_id) and
 * causes a MassAssignmentException.
 *
 * Uses firstOrCreate so it is safe to run multiple times.
 */
return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            // ─── Product mappings ───────────────────────────────────
            // Only fields that exist on the Wameed products table and are
            // actually sent by the Thawani API are mapped here.
            // Intentionally excluded: thawani_product_id, store_category_id,
            // has_offer, wameed_product_id, sync_status, last_synced_at
            // (Thawani-internal / handled by mapping tables), and quantity
            // (no products.quantity column — inventory is separate).
            ['entity_type' => 'product', 'thawani_field' => 'name',             'wameed_field' => 'name',          'transform_type' => 'direct'],
            ['entity_type' => 'product', 'thawani_field' => 'name_ar',          'wameed_field' => 'name_ar',        'transform_type' => 'direct'],
            ['entity_type' => 'product', 'thawani_field' => 'description',      'wameed_field' => 'description',    'transform_type' => 'direct'],
            ['entity_type' => 'product', 'thawani_field' => 'description_ar',   'wameed_field' => 'description_ar', 'transform_type' => 'direct'],
            ['entity_type' => 'product', 'thawani_field' => 'price',            'wameed_field' => 'sell_price',     'transform_type' => 'direct'],
            ['entity_type' => 'product', 'thawani_field' => 'offer_price',      'wameed_field' => 'offer_price',    'transform_type' => 'direct'],
            // Thawani sends offer_start_date / offer_end_date;
            // Wameed products table uses offer_start / offer_end.
            ['entity_type' => 'product', 'thawani_field' => 'offer_start_date', 'wameed_field' => 'offer_start',    'transform_type' => 'direct'],
            ['entity_type' => 'product', 'thawani_field' => 'offer_end_date',   'wameed_field' => 'offer_end',      'transform_type' => 'direct'],
            ['entity_type' => 'product', 'thawani_field' => 'image',            'wameed_field' => 'image_url',      'transform_type' => 'direct'],
            ['entity_type' => 'product', 'thawani_field' => 'barcode',          'wameed_field' => 'barcode',        'transform_type' => 'direct'],
            ['entity_type' => 'product', 'thawani_field' => 'is_active',        'wameed_field' => 'is_active',      'transform_type' => 'direct'],

            // ─── Category mappings ──────────────────────────────────
            ['entity_type' => 'category', 'thawani_field' => 'name',    'wameed_field' => 'name',      'transform_type' => 'direct'],
            ['entity_type' => 'category', 'thawani_field' => 'name_ar', 'wameed_field' => 'name_ar',   'transform_type' => 'direct'],
            ['entity_type' => 'category', 'thawani_field' => 'image',   'wameed_field' => 'image_url', 'transform_type' => 'direct'],
        ];

        foreach ($defaults as $mapping) {
            ThawaniColumnMapping::firstOrCreate(
                [
                    'entity_type'   => $mapping['entity_type'],
                    'thawani_field' => $mapping['thawani_field'],
                    'wameed_field'  => $mapping['wameed_field'],
                ],
                [
                    'transform_type'   => $mapping['transform_type'],
                    'transform_config' => null,
                ]
            );
        }
    }

    public function down(): void
    {
        // Only remove rows that were not modified by the user.
        // A simple truncate would destroy custom mappings.
        ThawaniColumnMapping::whereIn('entity_type', ['product', 'category'])
            ->whereNull('transform_config')
            ->where('transform_type', 'direct')
            ->delete();
    }
};
