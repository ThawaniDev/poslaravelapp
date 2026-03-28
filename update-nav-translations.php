<?php
/**
 * Script: update-nav-translations.php
 * Updates all Filament resources and pages to use __('nav.*') for
 * navigation group and label strings instead of hardcoded values.
 *
 * Run from poslaravelapp root:
 *   php update-nav-translations.php
 */

$groupMap = [
    'Core'                   => 'group_core',
    'Business'               => 'group_business',
    'People'                 => 'group_people',
    'Support'                => 'group_support',
    'Content'                => 'group_content',
    'Integrations'           => 'group_integrations',
    'Notifications'          => 'group_notifications',
    'Updates'                => 'group_updates',
    'Security'               => 'group_security',
    'Settings'               => 'group_settings',
    'Analytics'              => 'group_analytics',
    'Infrastructure'         => 'group_infrastructure',
    'UI Management'          => 'group_ui_management',
    'Website'                => 'group_website',
    'Subscription & Billing' => 'group_subscription_billing',
];

$labelMap = [
    'Active Sessions'          => 'active_sessions',
    'Admin Roles'              => 'admin_roles',
    'Admin Team'               => 'admin_team',
    'Audit Log'                => 'audit_log',
    'Business Types'           => 'business_types',
    'Consultation Requests'    => 'consultation_requests',
    'Contact Submissions'      => 'contact_submissions',
    'Delivery Orders'          => 'delivery_orders',
    'Delivery Platforms'       => 'delivery_platforms',
    'Discounts'                => 'discounts',
    'Hardware Quotes'          => 'hardware_quotes',
    'Hardware Sales'           => 'hardware_sales',
    'Implementation Fees'      => 'implementation_fees',
    'Invoices'                 => 'invoices',
    'IP Allowlist'             => 'ip_allowlist',
    'IP Blocklist'             => 'ip_blocklist',
    'Newsletter Subscribers'   => 'newsletter_subscribers',
    'Onboarding Steps'         => 'onboarding_steps',
    'Organizations'            => 'organizations',
    'Partnership Applications' => 'partnership_applications',
    'Payment Gateways'         => 'payment_gateways',
    'Plan Add-Ons'             => 'plan_add_ons',
    'Pricing Page'             => 'pricing_page',
    'Provider Permissions'     => 'provider_permissions',
    'Provider Users'           => 'provider_users',
    'Registrations'            => 'registrations',
    'Role Templates'           => 'role_templates',
    'Security Alerts'          => 'security_alerts',
    'Store Delivery Configs'   => 'store_delivery_configs',
    'Stores'                   => 'stores',
    'Subscription Plans'       => 'subscription_plans',
    'Subscriptions'            => 'subscriptions',
    'Templates'                => 'templates',
    'Trusted Devices'          => 'trusted_devices',
];

$files = array_merge(
    glob(__DIR__ . '/app/Filament/Resources/*.php'),
    glob(__DIR__ . '/app/Filament/Pages/*.php')
);

$updated = [];
$skipped = [];

foreach ($files as $file) {
    $content = file_get_contents($file);
    $original = $content;
    $changes = [];

    // ── Navigation GROUP ──────────────────────────────────────────────────────
    // Pattern: protected static ?string $navigationGroup = 'SomeName';
    if (preg_match("/    protected static \?string \\\$navigationGroup = '([^']+)';/", $content, $m)) {
        $groupValue = $m[1];

        if (isset($groupMap[$groupValue])) {
            $key = $groupMap[$groupValue];
            $hasExistingMethod = str_contains($content, 'getNavigationGroup()');

            if ($hasExistingMethod) {
                // Just null out the static property; existing method needs manual check
                $content = str_replace(
                    "protected static ?string \$navigationGroup = '$groupValue';",
                    "protected static ?string \$navigationGroup = null;",
                    $content
                );
                $changes[] = "group property → null (method exists, update it manually if needed)";
            } else {
                // Replace static property with null + insert new method
                $replacement = "protected static ?string \$navigationGroup = null;\n\n    public static function getNavigationGroup(): ?string\n    {\n        return __('nav.$key');\n    }";
                $content = str_replace(
                    "protected static ?string \$navigationGroup = '$groupValue';",
                    $replacement,
                    $content
                );
                $changes[] = "group → getNavigationGroup() returning __('nav.$key')";
            }
        } else {
            $changes[] = "WARNING: unknown group '$groupValue' – skipped";
        }
    }

    // ── Navigation LABEL ─────────────────────────────────────────────────────
    // Only replace when the value is a hardcoded string (not null)
    if (preg_match("/    protected static \?string \\\$navigationLabel = '([^']+)';/", $content, $m)) {
        $labelValue = $m[1];

        if (isset($labelMap[$labelValue])) {
            $key = $labelMap[$labelValue];
            $hasExistingMethod = str_contains($content, 'getNavigationLabel()');

            if ($hasExistingMethod) {
                // Null out the static property
                $content = str_replace(
                    "protected static ?string \$navigationLabel = '$labelValue';",
                    "protected static ?string \$navigationLabel = null;",
                    $content
                );
                $changes[] = "label property → null (method exists)";
            } else {
                // Replace static property with null + insert new method
                $replacement = "protected static ?string \$navigationLabel = null;\n\n    public static function getNavigationLabel(): string\n    {\n        return __('nav.$key');\n    }";
                $content = str_replace(
                    "protected static ?string \$navigationLabel = '$labelValue';",
                    $replacement,
                    $content
                );
                $changes[] = "label → getNavigationLabel() returning __('nav.$key')";
            }
        } else {
            $changes[] = "WARNING: unknown label '$labelValue' – skipped";
        }
    }

    if ($content !== $original) {
        file_put_contents($file, $content);
        $relPath = str_replace(__DIR__ . '/', '', $file);
        $updated[] = "  ✓ $relPath: " . implode(', ', $changes);
    } else {
        $relPath = str_replace(__DIR__ . '/', '', $file);
        $skipped[] = "  - $relPath (no hardcoded nav strings)";
    }
}

echo "\n=== NAV TRANSLATION UPDATE COMPLETE ===\n\n";
echo "Updated (" . count($updated) . " files):\n";
foreach ($updated as $line) {
    echo $line . "\n";
}
echo "\nSkipped (" . count($skipped) . " files with no matching strings)\n";
echo "\nDone. Remember to:\n";
echo "  1. Update AdminPanelProvider::navigationGroups() to use __('nav.group_*') keys\n";
echo "  2. Clear config/view caches: php artisan optimize:clear\n\n";
