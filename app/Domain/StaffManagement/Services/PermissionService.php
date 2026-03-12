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
    ];
}
