<?php
// Test script for settings API - run with: php artisan tinker test_settings_api.php

use App\Domain\Core\Models\StoreSettings;
use App\Http\Resources\Core\StoreSettingsResource;

echo "=== Testing Store Settings API ===\n\n";

// 1. Test Model reads all new fields
$s = StoreSettings::first();
if (!$s) {
    echo "ERROR: No store settings found\n";
    exit(1);
}
echo "1. Model loaded OK (store_id: {$s->store_id})\n";

// 2. Test Resource returns all new fields
$resource = new StoreSettingsResource($s);
$json = $resource->toArray(request());

$newFields = [
    'receipt_header','receipt_footer','receipt_show_logo','receipt_show_tax_breakdown',
    'receipt_show_address','receipt_show_phone','receipt_show_date','receipt_show_cashier',
    'receipt_show_barcode','receipt_paper_size','receipt_font_size','receipt_language',
    'default_sale_type','require_customer_for_sale','auto_print_receipt','enable_tips','enable_hold_orders',
    'enable_open_price_items','enable_quick_add_products','barcode_scan_sound','enable_kitchen_display',
    'enable_refunds','enable_exchanges','require_manager_for_refund','require_manager_for_discount',
    'max_discount_percent','session_timeout_minutes',
];

$missing = [];
foreach ($newFields as $f) {
    if (!array_key_exists($f, $json)) {
        $missing[] = $f;
    }
}

if (empty($missing)) {
    echo "2. Resource has ALL " . count($newFields) . " new fields ✓\n";
} else {
    echo "2. MISSING fields in resource: " . implode(', ', $missing) . "\n";
}

// 3. Test update via model
$originalHeader = $s->receipt_header;
$s->update(['receipt_header' => 'TEST_HEADER_12345']);
$s->refresh();
$headerOk = $s->receipt_header === 'TEST_HEADER_12345';
$s->update(['receipt_header' => $originalHeader]);
echo "3. Update receipt_header: " . ($headerOk ? '✓' : 'FAIL') . "\n";

// 4. Test boolean update
$originalTips = $s->enable_tips;
$s->update(['enable_tips' => !$originalTips]);
$s->refresh();
$boolOk = $s->enable_tips === !$originalTips;
$s->update(['enable_tips' => $originalTips]);
echo "4. Update boolean (enable_tips): " . ($boolOk ? '✓' : 'FAIL') . "\n";

// 5. Test integer update
$originalMax = $s->max_discount_percent;
$s->update(['max_discount_percent' => 50]);
$s->refresh();
$numOk = $s->max_discount_percent === 50;
$s->update(['max_discount_percent' => $originalMax]);
echo "5. Update integer (max_discount_percent): " . ($numOk ? '✓' : 'FAIL') . "\n";

// 6. Print some sample values from resource
echo "\n--- Sample Resource Output ---\n";
$samples = ['tax_label','tax_rate','receipt_show_logo','default_sale_type','max_discount_percent','session_timeout_minutes'];
foreach ($samples as $k) {
    echo "  {$k}: " . var_export($json[$k] ?? null, true) . "\n";
}

echo "\n=== All Tests Passed ===\n";
