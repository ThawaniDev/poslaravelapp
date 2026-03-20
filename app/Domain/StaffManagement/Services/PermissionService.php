<?php

namespace App\Domain\StaffManagement\Services;

use App\Domain\StaffManagement\Models\Permission;
use Illuminate\Database\Eloquent\Collection;

class PermissionService
{
    /**
     * Get all permissions, optionally grouped by module.
     */
    public function all(): Collection
    {
        return Permission::orderBy('module')->orderBy('name')->get();
    }

    /**
     * Get permissions grouped by module.
     *
     * @return array<string, Collection>
     */
    public function groupedByModule(): array
    {
        return Permission::orderBy('name')
            ->get()
            ->groupBy('module')
            ->toArray();
    }

    /**
     * List distinct module names.
     */
    public function modules(): array
    {
        return Permission::distinct()->orderBy('module')->pluck('module')->toArray();
    }

    /**
     * Get permissions for a specific module.
     */
    public function forModule(string $module): Collection
    {
        return Permission::forModule($module)->orderBy('name')->get();
    }

    /**
     * Get all PIN-protected permissions.
     */
    public function pinProtected(): Collection
    {
        return Permission::pinProtected()->orderBy('module')->orderBy('name')->get();
    }

    /**
     * Find a permission by its code name.
     */
    public function findByName(string $name): ?Permission
    {
        return Permission::where('name', $name)->first();
    }

    /**
     * Seed all system permissions. Safe to re-run — uses updateOrCreate.
     */
    public function seedAll(): void
    {
        foreach (self::ALL_PERMISSIONS as $module => $perms) {
            foreach ($perms as $perm) {
                Permission::updateOrCreate(
                    ['name' => $perm['name']],
                    [
                        'display_name' => $perm['display_name'],
                        'module'       => $module,
                        'guard_name'   => 'staff',
                        'requires_pin' => $perm['requires_pin'] ?? false,
                    ],
                );
            }
        }
    }

    /**
     * Master list of all system permissions by module.
     */
    public const ALL_PERMISSIONS = [
        'pos' => [
            ['name' => 'pos.open_session',      'display_name' => 'Open POS Session'],
            ['name' => 'pos.close_session',      'display_name' => 'Close POS Session'],
            ['name' => 'pos.sell',               'display_name' => 'Process Sales'],
            ['name' => 'pos.apply_discount',     'display_name' => 'Apply Discount',          'requires_pin' => true],
            ['name' => 'pos.void_transaction',   'display_name' => 'Void Transaction',        'requires_pin' => true],
            ['name' => 'pos.hold_recall',        'display_name' => 'Hold / Recall Cart'],
            ['name' => 'pos.refund',             'display_name' => 'Process Refund',           'requires_pin' => true],
            ['name' => 'pos.reprint_receipt',    'display_name' => 'Reprint Receipt'],
            ['name' => 'pos.view_sessions',      'display_name' => 'View POS Sessions'],
            ['name' => 'pos.cash_in_out',        'display_name' => 'Cash In/Out',              'requires_pin' => true],
            ['name' => 'pos.price_override',     'display_name' => 'Override Item Price',      'requires_pin' => true],
            ['name' => 'pos.no_sale',            'display_name' => 'Open Cash Drawer (No Sale)', 'requires_pin' => true],
        ],
        'orders' => [
            ['name' => 'orders.view',            'display_name' => 'View Orders'],
            ['name' => 'orders.create',          'display_name' => 'Create Orders'],
            ['name' => 'orders.update_status',   'display_name' => 'Update Order Status'],
            ['name' => 'orders.cancel',          'display_name' => 'Cancel Orders',            'requires_pin' => true],
            ['name' => 'orders.edit',            'display_name' => 'Edit Orders'],
            ['name' => 'orders.delete',          'display_name' => 'Delete Orders',            'requires_pin' => true],
        ],
        'inventory' => [
            ['name' => 'inventory.view',         'display_name' => 'View Inventory'],
            ['name' => 'inventory.adjust',       'display_name' => 'Adjust Stock'],
            ['name' => 'inventory.receive',      'display_name' => 'Receive Stock'],
            ['name' => 'inventory.transfer',     'display_name' => 'Transfer Stock'],
            ['name' => 'inventory.count',        'display_name' => 'Stock Count'],
            ['name' => 'inventory.write_off',    'display_name' => 'Write Off Stock',          'requires_pin' => true],
        ],
        'catalog' => [
            ['name' => 'catalog.view',            'display_name' => 'View Products'],
            ['name' => 'catalog.create',          'display_name' => 'Create Products'],
            ['name' => 'catalog.update',          'display_name' => 'Update Products'],
            ['name' => 'catalog.delete',          'display_name' => 'Delete Products',          'requires_pin' => true],
            ['name' => 'catalog.manage_categories', 'display_name' => 'Manage Categories'],
            ['name' => 'catalog.import_export',   'display_name' => 'Import/Export Products'],
            ['name' => 'catalog.manage_pricing',  'display_name' => 'Manage Pricing'],
        ],
        'customers' => [
            ['name' => 'customers.view',          'display_name' => 'View Customers'],
            ['name' => 'customers.create',        'display_name' => 'Create Customers'],
            ['name' => 'customers.update',        'display_name' => 'Update Customers'],
            ['name' => 'customers.delete',        'display_name' => 'Delete Customers',        'requires_pin' => true],
            ['name' => 'customers.manage_loyalty', 'display_name' => 'Manage Loyalty Points'],
            ['name' => 'customers.manage_credit', 'display_name' => 'Manage Store Credit'],
        ],
        'reports' => [
            ['name' => 'reports.view_sales',      'display_name' => 'View Sales Reports'],
            ['name' => 'reports.view_inventory',  'display_name' => 'View Inventory Reports'],
            ['name' => 'reports.view_financial',  'display_name' => 'View Financial Reports'],
            ['name' => 'reports.view_staff',      'display_name' => 'View Staff Reports'],
            ['name' => 'reports.export',          'display_name' => 'Export Reports'],
        ],
        'staff' => [
            ['name' => 'staff.view',              'display_name' => 'View Staff'],
            ['name' => 'staff.create',            'display_name' => 'Add Staff Members'],
            ['name' => 'staff.update',            'display_name' => 'Update Staff'],
            ['name' => 'staff.deactivate',        'display_name' => 'Deactivate Staff',        'requires_pin' => true],
            ['name' => 'staff.assign_role',       'display_name' => 'Assign Roles'],
            ['name' => 'staff.manage_roles',      'display_name' => 'Manage Custom Roles'],
        ],
        'settings' => [
            ['name' => 'settings.view',           'display_name' => 'View Settings'],
            ['name' => 'settings.update',         'display_name' => 'Update Settings'],
            ['name' => 'settings.manage_payment', 'display_name' => 'Manage Payment Methods'],
            ['name' => 'settings.manage_tax',     'display_name' => 'Manage Tax Settings'],
            ['name' => 'settings.manage_receipt', 'display_name' => 'Manage Receipt Templates'],
        ],
        'accounting' => [
            ['name' => 'accounting.view',          'display_name' => 'View Accounting'],
            ['name' => 'accounting.manage_expenses', 'display_name' => 'Manage Expenses'],
            ['name' => 'accounting.manage_cash',   'display_name' => 'Manage Cash Sessions'],
        ],
        'kitchen' => [
            ['name' => 'kitchen.view',             'display_name' => 'View Kitchen Orders'],
            ['name' => 'kitchen.update_status',    'display_name' => 'Update Kitchen Status'],
        ],
        'promotions' => [
            ['name' => 'promotions.view',          'display_name' => 'View Promotions'],
            ['name' => 'promotions.create',        'display_name' => 'Create Promotions'],
            ['name' => 'promotions.update',        'display_name' => 'Update Promotions'],
            ['name' => 'promotions.delete',        'display_name' => 'Delete Promotions'],
        ],
        'delivery' => [
            ['name' => 'delivery.view',            'display_name' => 'View Delivery Integrations'],
            ['name' => 'delivery.manage',          'display_name' => 'Manage Delivery Configs'],
            ['name' => 'delivery.sync_menu',       'display_name' => 'Sync Menu to Platforms'],
            ['name' => 'delivery.manage_orders',   'display_name' => 'Manage Delivery Orders'],
        ],
        'thawani' => [
            ['name' => 'thawani.view',             'display_name' => 'View Thawani Integration'],
            ['name' => 'thawani.manage',           'display_name' => 'Manage Thawani Config'],
            ['name' => 'thawani.sync',             'display_name' => 'Sync Products to Thawani'],
            ['name' => 'thawani.settlements',      'display_name' => 'View Settlements'],
        ],
        'security' => [
            ['name' => 'security.view_audit',      'display_name' => 'View Security Audit Log'],
            ['name' => 'security.manage_devices',  'display_name' => 'Manage Registered Devices'],
            ['name' => 'security.manage_policies', 'display_name' => 'Manage Security Policies',  'requires_pin' => true],
            ['name' => 'security.remote_wipe',     'display_name' => 'Remote Wipe Device',        'requires_pin' => true],
        ],
        'zatca' => [
            ['name' => 'zatca.view',               'display_name' => 'View ZATCA Invoices'],
            ['name' => 'zatca.submit',             'display_name' => 'Submit ZATCA Invoice'],
            ['name' => 'zatca.enroll',             'display_name' => 'Enroll ZATCA Certificate',   'requires_pin' => true],
        ],
        'hardware' => [
            ['name' => 'hardware.view',            'display_name' => 'View Hardware Configs'],
            ['name' => 'hardware.manage',          'display_name' => 'Manage Hardware Devices'],
        ],
        'backup' => [
            ['name' => 'backup.view',              'display_name' => 'View Backup History'],
            ['name' => 'backup.create',            'display_name' => 'Create Backup'],
            ['name' => 'backup.restore',           'display_name' => 'Restore Backup',             'requires_pin' => true],
            ['name' => 'backup.delete',            'display_name' => 'Delete Backup',              'requires_pin' => true],
        ],
        'pos_customization' => [
            ['name' => 'pos_customization.view',   'display_name' => 'View POS Customization'],
            ['name' => 'pos_customization.update', 'display_name' => 'Update POS Customization'],
        ],
        'support' => [
            ['name' => 'support.view',             'display_name' => 'View Support Tickets'],
            ['name' => 'support.create',           'display_name' => 'Create Support Ticket'],
            ['name' => 'support.reply',            'display_name' => 'Reply to Ticket'],
        ],
        'labels' => [
            ['name' => 'labels.view',              'display_name' => 'View Label Templates'],
            ['name' => 'labels.manage',            'display_name' => 'Manage Label Templates'],
            ['name' => 'labels.print',             'display_name' => 'Print Labels'],
        ],
        'notifications' => [
            ['name' => 'notifications.view',              'display_name' => 'View Notifications'],
            ['name' => 'notifications.manage_templates',  'display_name' => 'Manage Notification Templates'],
            ['name' => 'notifications.manage_preferences', 'display_name' => 'Manage Notification Preferences'],
        ],
        'bakery' => [
            ['name' => 'bakery.view',              'display_name' => 'View Bakery Module'],
            ['name' => 'bakery.recipes',           'display_name' => 'Manage Bakery Recipes'],
            ['name' => 'bakery.orders',            'display_name' => 'Manage Custom Cake Orders'],
            ['name' => 'bakery.production',        'display_name' => 'Manage Production Schedule'],
        ],
        'electronics' => [
            ['name' => 'electronics.view',         'display_name' => 'View Electronics Module'],
            ['name' => 'electronics.imei',         'display_name' => 'Manage IMEI Records'],
            ['name' => 'electronics.repairs',      'display_name' => 'Manage Repair Jobs'],
            ['name' => 'electronics.trade_in',     'display_name' => 'Manage Trade-Ins'],
        ],
        'florist' => [
            ['name' => 'florist.view',             'display_name' => 'View Florist Module'],
            ['name' => 'florist.manage',           'display_name' => 'Manage Arrangements'],
            ['name' => 'florist.subscriptions',    'display_name' => 'Manage Flower Subscriptions'],
            ['name' => 'florist.freshness',        'display_name' => 'Manage Freshness Tracking'],
        ],
        'jewelry' => [
            ['name' => 'jewelry.view',             'display_name' => 'View Jewelry Module'],
            ['name' => 'jewelry.manage',           'display_name' => 'Manage Jewelry Details'],
            ['name' => 'jewelry.rates',            'display_name' => 'Manage Metal Rates'],
            ['name' => 'jewelry.buyback',          'display_name' => 'Process Buyback',            'requires_pin' => true],
        ],
        'pharmacy' => [
            ['name' => 'pharmacy.view',            'display_name' => 'View Pharmacy Module'],
            ['name' => 'pharmacy.manage',          'display_name' => 'Manage Drug Schedules'],
            ['name' => 'pharmacy.prescriptions',   'display_name' => 'Manage Prescriptions'],
        ],
        'restaurant' => [
            ['name' => 'restaurant.view',          'display_name' => 'View Restaurant Module'],
            ['name' => 'restaurant.tables',        'display_name' => 'Manage Tables'],
            ['name' => 'restaurant.reservations',  'display_name' => 'Manage Reservations'],
            ['name' => 'restaurant.kitchen',       'display_name' => 'Manage Kitchen Tickets'],
            ['name' => 'restaurant.tabs',          'display_name' => 'Manage Open Tabs'],
        ],
        'accounting_integration' => [
            ['name' => 'accounting.connect',       'display_name' => 'Connect Accounting Provider', 'requires_pin' => true],
            ['name' => 'accounting.export',        'display_name' => 'Export to Accounting'],
            ['name' => 'accounting.mappings',      'display_name' => 'Manage Account Mappings'],
        ],
    ];
}
