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
        return Permission::orderBy('module')
            ->orderBy('sort_order')
            ->orderBy('name')
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
    public function seedAll(): int
    {
        $count = 0;

        foreach (self::ALL_PERMISSIONS as $module => $perms) {
            $sortOrder = 0;
            foreach ($perms as $perm) {
                $sortOrder++;
                Permission::updateOrCreate(
                    ['name' => $perm['name']],
                    [
                        'display_name'    => $perm['display_name'],
                        'display_name_ar' => $perm['display_name_ar'] ?? null,
                        'module'          => $module,
                        'guard_name'      => 'staff',
                        'requires_pin'    => $perm['requires_pin'] ?? false,
                        'description'     => $perm['description'] ?? null,
                        'description_ar'  => $perm['description_ar'] ?? null,
                        'sort_order'      => $sortOrder,
                    ],
                );
                $count++;
            }
        }

        return $count;
    }

    /**
     * Master list of all 167 system permissions by module.
     * Single source of truth — matches Flutter permission_constants.dart exactly.
     */
    public const ALL_PERMISSIONS = [
        // ─── POS Terminal ────────────────────────────────────────
        'pos' => [
            ['name' => 'pos.shift_open',       'display_name' => 'Open Shift',                  'display_name_ar' => 'فتح وردية',                   'requires_pin' => false, 'description' => 'Start a new POS shift/session',            'description_ar' => 'بدء وردية/جلسة نقطة بيع جديدة'],
            ['name' => 'pos.shift_close',      'display_name' => 'Close Shift',                 'display_name_ar' => 'إغلاق وردية',                 'requires_pin' => false, 'description' => 'End and reconcile a POS shift',            'description_ar' => 'إنهاء ومطابقة وردية نقطة البيع'],
            ['name' => 'pos.sell',             'display_name' => 'Process Sales',               'display_name_ar' => 'إجراء المبيعات',               'requires_pin' => false, 'description' => 'Ring up and complete sales transactions',   'description_ar' => 'تسجيل وإتمام معاملات البيع'],
            ['name' => 'pos.discount',         'display_name' => 'Apply Discount',              'display_name_ar' => 'تطبيق خصم',                   'requires_pin' => true,  'description' => 'Apply discounts to cart items',             'description_ar' => 'تطبيق خصومات على عناصر السلة'],
            ['name' => 'pos.approve_discount', 'display_name' => 'Approve Discount',            'display_name_ar' => 'الموافقة على الخصم',           'requires_pin' => true,  'description' => 'Approve discount requests from other staff','description_ar' => 'الموافقة على طلبات الخصم من موظفين آخرين'],
            ['name' => 'pos.void',             'display_name' => 'Void Line Item',              'display_name_ar' => 'إلغاء عنصر',                  'requires_pin' => true,  'description' => 'Remove a line item from the cart',         'description_ar' => 'إزالة عنصر من السلة'],
            ['name' => 'pos.void_transaction', 'display_name' => 'Void Transaction',            'display_name_ar' => 'إلغاء معاملة',                'requires_pin' => true,  'description' => 'Void an entire completed transaction',     'description_ar' => 'إلغاء معاملة مكتملة بالكامل'],
            ['name' => 'pos.refund',           'display_name' => 'Process Refund',              'display_name_ar' => 'إجراء استرجاع',               'requires_pin' => true,  'description' => 'Process customer refunds',                 'description_ar' => 'معالجة استرجاع العملاء'],
            ['name' => 'pos.return',           'display_name' => 'Process Return',              'display_name_ar' => 'إجراء إرجاع',                 'requires_pin' => false, 'description' => 'Accept product returns from customers',    'description_ar' => 'قبول إرجاع المنتجات من العملاء'],
            ['name' => 'pos.tax_exempt',       'display_name' => 'Tax-Exempt Sales',            'display_name_ar' => 'مبيعات معفاة من الضريبة',      'requires_pin' => true,  'description' => 'Process sales without tax',                'description_ar' => 'إجراء مبيعات بدون ضريبة'],
            ['name' => 'pos.hold_recall',      'display_name' => 'Hold / Recall Cart',          'display_name_ar' => 'تعليق / استرجاع السلة',        'requires_pin' => false, 'description' => 'Hold and recall carts',                    'description_ar' => 'تعليق واسترجاع السلات'],
            ['name' => 'pos.reprint_receipt',  'display_name' => 'Reprint Receipt',             'display_name_ar' => 'إعادة طباعة الإيصال',          'requires_pin' => false, 'description' => 'Reprint transaction receipts',             'description_ar' => 'إعادة طباعة إيصالات المعاملات'],
            ['name' => 'pos.price_override',   'display_name' => 'Override Item Price',         'display_name_ar' => 'تغيير سعر العنصر',             'requires_pin' => true,  'description' => 'Change the price of items at POS',         'description_ar' => 'تغيير سعر العناصر في نقطة البيع'],
            ['name' => 'pos.no_sale',          'display_name' => 'Open Cash Drawer (No Sale)',  'display_name_ar' => 'فتح درج النقد (بدون بيع)',      'requires_pin' => true,  'description' => 'Open the cash drawer without a sale',      'description_ar' => 'فتح درج النقد بدون إجراء بيع'],
            ['name' => 'pos.view_sessions',    'display_name' => 'View POS Sessions',           'display_name_ar' => 'عرض جلسات نقطة البيع',         'requires_pin' => false, 'description' => 'View POS session history',                 'description_ar' => 'عرض سجل جلسات نقطة البيع'],
            ['name' => 'pos.manage_terminals', 'display_name' => 'Manage POS Terminals',        'display_name_ar' => 'إدارة أجهزة نقطة البيع',       'requires_pin' => false, 'description' => 'Add, edit, delete POS terminals',          'description_ar' => 'إضافة وتعديل وحذف أجهزة نقطة البيع'],
        ],

        // ─── Orders ──────────────────────────────────────────────
        'orders' => [
            ['name' => 'orders.view',          'display_name' => 'View Orders',                 'display_name_ar' => 'عرض الطلبات',                  'requires_pin' => false, 'description' => 'View order list and details',              'description_ar' => 'عرض قائمة وتفاصيل الطلبات'],
            ['name' => 'orders.manage',        'display_name' => 'Manage Orders',               'display_name_ar' => 'إدارة الطلبات',                'requires_pin' => false, 'description' => 'Create and edit orders',                   'description_ar' => 'إنشاء وتعديل الطلبات'],
            ['name' => 'orders.return',        'display_name' => 'Process Returns & Exchanges', 'display_name_ar' => 'معالجة المرتجعات والاستبدال',   'requires_pin' => false, 'description' => 'Process order returns and exchanges',      'description_ar' => 'معالجة مرتجعات واستبدال الطلبات'],
            ['name' => 'orders.void',          'display_name' => 'Void Orders',                 'display_name_ar' => 'إلغاء الطلبات',                'requires_pin' => true,  'description' => 'Void completed orders',                    'description_ar' => 'إلغاء الطلبات المكتملة'],
            ['name' => 'orders.update_status', 'display_name' => 'Update Order Status',         'display_name_ar' => 'تحديث حالة الطلب',             'requires_pin' => false, 'description' => 'Change order status',                      'description_ar' => 'تغيير حالة الطلب'],
        ],

        // ─── Transactions ────────────────────────────────────────
        'transactions' => [
            ['name' => 'transactions.view',    'display_name' => 'View Transactions',           'display_name_ar' => 'عرض المعاملات',                'requires_pin' => false, 'description' => 'View transaction explorer and details',    'description_ar' => 'عرض مستكشف المعاملات والتفاصيل'],
            ['name' => 'transactions.export',  'display_name' => 'Export Transactions',         'display_name_ar' => 'تصدير المعاملات',              'requires_pin' => false, 'description' => 'Export transaction data to CSV/PDF',       'description_ar' => 'تصدير بيانات المعاملات إلى CSV/PDF'],
            ['name' => 'transactions.void',    'display_name' => 'Void Transactions',           'display_name_ar' => 'إلغاء المعاملات',              'requires_pin' => true,  'description' => 'Void completed transactions',              'description_ar' => 'إلغاء المعاملات المكتملة'],
        ],

        // ─── Products / Catalog ──────────────────────────────────
        'products' => [
            ['name' => 'products.view',              'display_name' => 'View Products',                'display_name_ar' => 'عرض المنتجات',                'requires_pin' => false, 'description' => 'View product list and details',            'description_ar' => 'عرض قائمة وتفاصيل المنتجات'],
            ['name' => 'products.manage',            'display_name' => 'Manage Products',              'display_name_ar' => 'إدارة المنتجات',              'requires_pin' => false, 'description' => 'Create, edit, delete products',            'description_ar' => 'إنشاء وتعديل وحذف المنتجات'],
            ['name' => 'products.manage_categories', 'display_name' => 'Manage Categories',            'display_name_ar' => 'إدارة الفئات',                'requires_pin' => false, 'description' => 'Create and manage product categories',     'description_ar' => 'إنشاء وإدارة فئات المنتجات'],
            ['name' => 'products.manage_suppliers',  'display_name' => 'Manage Suppliers',             'display_name_ar' => 'إدارة الموردين',              'requires_pin' => false, 'description' => 'Create and manage suppliers',              'description_ar' => 'إنشاء وإدارة الموردين'],
            ['name' => 'products.import_export',     'display_name' => 'Import/Export Products',       'display_name_ar' => 'استيراد/تصدير المنتجات',       'requires_pin' => false, 'description' => 'Bulk import and export products',          'description_ar' => 'استيراد وتصدير المنتجات بالجملة'],
            ['name' => 'products.manage_pricing',    'display_name' => 'Manage Pricing',               'display_name_ar' => 'إدارة التسعير',               'requires_pin' => false, 'description' => 'Update product pricing',                   'description_ar' => 'تحديث أسعار المنتجات'],
            ['name' => 'products.use_predefined',    'display_name' => 'Use Predefined Catalog',       'display_name_ar' => 'استخدام الكتالوج المعد مسبقاً','requires_pin' => false, 'description' => 'Import from predefined product catalog',   'description_ar' => 'استيراد من كتالوج المنتجات المعد مسبقاً'],
        ],

        // ─── Inventory ───────────────────────────────────────────
        'inventory' => [
            ['name' => 'inventory.view',             'display_name' => 'View Inventory',               'display_name_ar' => 'عرض المخزون',                 'requires_pin' => false, 'description' => 'View stock levels and movements',         'description_ar' => 'عرض مستويات وحركات المخزون'],
            ['name' => 'inventory.manage',           'display_name' => 'Manage Inventory',             'display_name_ar' => 'إدارة المخزون',               'requires_pin' => false, 'description' => 'Full inventory management',               'description_ar' => 'إدارة المخزون الكاملة'],
            ['name' => 'inventory.adjust',           'display_name' => 'Adjust Stock',                 'display_name_ar' => 'تعديل المخزون',               'requires_pin' => false, 'description' => 'Make stock adjustments',                  'description_ar' => 'إجراء تعديلات على المخزون'],
            ['name' => 'inventory.transfer',         'display_name' => 'Transfer Stock',               'display_name_ar' => 'نقل المخزون',                 'requires_pin' => false, 'description' => 'Transfer stock between branches',         'description_ar' => 'نقل المخزون بين الفروع'],
            ['name' => 'inventory.stocktake',        'display_name' => 'Stocktake',                    'display_name_ar' => 'جرد المخزون',                 'requires_pin' => false, 'description' => 'Perform stock counts',                    'description_ar' => 'إجراء جرد المخزون'],
            ['name' => 'inventory.receive',          'display_name' => 'Receive Goods',                'display_name_ar' => 'استلام البضائع',               'requires_pin' => false, 'description' => 'Process goods receipts',                  'description_ar' => 'معالجة استلام البضائع'],
            ['name' => 'inventory.purchase_orders',  'display_name' => 'Manage Purchase Orders',       'display_name_ar' => 'إدارة أوامر الشراء',           'requires_pin' => false, 'description' => 'Create and manage purchase orders',       'description_ar' => 'إنشاء وإدارة أوامر الشراء'],
            ['name' => 'inventory.supplier_returns', 'display_name' => 'Manage Supplier Returns',      'display_name_ar' => 'إدارة مرتجعات الموردين',       'requires_pin' => false, 'description' => 'Process returns to suppliers',            'description_ar' => 'معالجة المرتجعات للموردين'],
            ['name' => 'inventory.recipes',          'display_name' => 'Manage Recipes',               'display_name_ar' => 'إدارة الوصفات',               'requires_pin' => false, 'description' => 'Create composite product recipes',        'description_ar' => 'إنشاء وصفات المنتجات المركبة'],
            ['name' => 'inventory.write_off',        'display_name' => 'Write Off Stock',              'display_name_ar' => 'شطب المخزون',                 'requires_pin' => true,  'description' => 'Write off damaged or expired stock',      'description_ar' => 'شطب المخزون التالف أو المنتهي'],
        ],

        // ─── Customers ───────────────────────────────────────────
        'customers' => [
            ['name' => 'customers.view',            'display_name' => 'View Customers',               'display_name_ar' => 'عرض العملاء',                 'requires_pin' => false, 'description' => 'View customer list and profiles',         'description_ar' => 'عرض قائمة وملفات العملاء'],
            ['name' => 'customers.manage',          'display_name' => 'Manage Customers',             'display_name_ar' => 'إدارة العملاء',               'requires_pin' => false, 'description' => 'Create, edit, delete customers',          'description_ar' => 'إنشاء وتعديل وحذف العملاء'],
            ['name' => 'customers.manage_loyalty',  'display_name' => 'Manage Loyalty Points',        'display_name_ar' => 'إدارة نقاط الولاء',            'requires_pin' => false, 'description' => 'Manage customer loyalty programs',        'description_ar' => 'إدارة برامج ولاء العملاء'],
            ['name' => 'customers.manage_credit',   'display_name' => 'Manage Store Credit',          'display_name_ar' => 'إدارة رصيد المتجر',            'requires_pin' => true,  'description' => 'Manage customer store credit',            'description_ar' => 'إدارة رصيد العملاء في المتجر'],
            ['name' => 'customers.manage_debits',   'display_name' => 'Manage Customer Debits',       'display_name_ar' => 'إدارة ديون العملاء',            'requires_pin' => false, 'description' => 'Manage customer debits and dues',         'description_ar' => 'إدارة ديون ومستحقات العملاء'],
        ],

        // ─── Payments & Cash ─────────────────────────────────────
        'payments' => [
            ['name' => 'payments.process',          'display_name' => 'Process Payments',             'display_name_ar' => 'معالجة المدفوعات',             'requires_pin' => false, 'description' => 'Accept and process payments',             'description_ar' => 'قبول ومعالجة المدفوعات'],
            ['name' => 'payments.refund',           'display_name' => 'Process Refunds',              'display_name_ar' => 'معالجة المبالغ المستردة',       'requires_pin' => true,  'description' => 'Issue payment refunds',                   'description_ar' => 'إصدار المبالغ المستردة'],
        ],
        'installments' => [
            ['name' => 'installments.configure',    'display_name' => 'Configure Installments',       'display_name_ar' => 'إعداد التقسيط',                'requires_pin' => false, 'description' => 'Configure installment provider credentials and settings', 'description_ar' => 'إعداد بيانات اعتماد مزودي التقسيط والإعدادات'],
            ['name' => 'installments.use',          'display_name' => 'Use Installment Payments',     'display_name_ar' => 'استخدام الدفع بالتقسيط',       'requires_pin' => false, 'description' => 'Initiate installment payments at POS checkout', 'description_ar' => 'بدء مدفوعات التقسيط عند نقطة البيع'],
            ['name' => 'installments.view_history', 'display_name' => 'View Installment History',     'display_name_ar' => 'عرض سجل التقسيط',              'requires_pin' => false, 'description' => 'View installment payment history and details', 'description_ar' => 'عرض سجل مدفوعات التقسيط وتفاصيلها'],
        ],
        'cash' => [
            ['name' => 'cash.manage',              'display_name' => 'Manage Cash Drawer',           'display_name_ar' => 'إدارة درج النقود',             'requires_pin' => false, 'description' => 'Cash in/out and drawer management',       'description_ar' => 'إدارة النقود والدرج'],
            ['name' => 'cash.view_sessions',       'display_name' => 'View Cash Sessions',           'display_name_ar' => 'عرض جلسات النقد',              'requires_pin' => false, 'description' => 'View cash session history',               'description_ar' => 'عرض سجل جلسات النقد'],
            ['name' => 'cash.view_daily_summary',  'display_name' => 'View Daily Summary',           'display_name_ar' => 'عرض الملخص اليومي',            'requires_pin' => false, 'description' => 'View daily sales summary',                'description_ar' => 'عرض ملخص المبيعات اليومي'],
            ['name' => 'cash.reconciliation',      'display_name' => 'Financial Reconciliation',     'display_name_ar' => 'المطابقة المالية',              'requires_pin' => false, 'description' => 'Perform financial reconciliation',        'description_ar' => 'إجراء المطابقة المالية'],
        ],

        // ─── Finance ─────────────────────────────────────────────
        'finance' => [
            ['name' => 'finance.commissions',      'display_name' => 'View Commissions',             'display_name_ar' => 'عرض العمولات',                 'requires_pin' => false, 'description' => 'View commission reports',                 'description_ar' => 'عرض تقارير العمولات'],
            ['name' => 'finance.settlements',      'display_name' => 'View Settlements',             'display_name_ar' => 'عرض التسويات',                 'requires_pin' => false, 'description' => 'View payment settlements',                'description_ar' => 'عرض تسويات المدفوعات'],
            ['name' => 'finance.expenses',         'display_name' => 'Manage Expenses',              'display_name_ar' => 'إدارة المصروفات',              'requires_pin' => false, 'description' => 'Record and manage expenses',              'description_ar' => 'تسجيل وإدارة المصروفات'],
            ['name' => 'finance.gift_cards',       'display_name' => 'Manage Gift Cards',            'display_name_ar' => 'إدارة بطاقات الهدايا',          'requires_pin' => false, 'description' => 'Create and manage gift cards',            'description_ar' => 'إنشاء وإدارة بطاقات الهدايا'],
        ],

        // ─── Reports ─────────────────────────────────────────────
        'reports' => [
            ['name' => 'reports.view',             'display_name' => 'View Reports Dashboard',       'display_name_ar' => 'عرض لوحة التقارير',            'requires_pin' => false, 'description' => 'Access reports dashboard',                'description_ar' => 'الوصول إلى لوحة التقارير'],
            ['name' => 'reports.view_financial',   'display_name' => 'View Financial Reports',       'display_name_ar' => 'عرض التقارير المالية',          'requires_pin' => false, 'description' => 'Access financial reports',                'description_ar' => 'الوصول إلى التقارير المالية'],
            ['name' => 'reports.view_margin',      'display_name' => 'View Margins',                 'display_name_ar' => 'عرض هوامش الربح',              'requires_pin' => false, 'description' => 'View profit margin reports',              'description_ar' => 'عرض تقارير هوامش الربح'],
            ['name' => 'reports.attendance',       'display_name' => 'View Attendance Reports',      'display_name_ar' => 'عرض تقارير الحضور',            'requires_pin' => false, 'description' => 'View staff attendance reports',           'description_ar' => 'عرض تقارير حضور الموظفين'],
            ['name' => 'reports.sales',            'display_name' => 'View Sales Reports',           'display_name_ar' => 'عرض تقارير المبيعات',          'requires_pin' => false, 'description' => 'View sales and revenue reports',          'description_ar' => 'عرض تقارير المبيعات والإيرادات'],
            ['name' => 'reports.inventory',        'display_name' => 'View Inventory Reports',       'display_name_ar' => 'عرض تقارير المخزون',           'requires_pin' => false, 'description' => 'View inventory and stock reports',        'description_ar' => 'عرض تقارير المخزون'],
            ['name' => 'reports.customers',        'display_name' => 'View Customer Reports',        'display_name_ar' => 'عرض تقارير العملاء',           'requires_pin' => false, 'description' => 'View customer analytics',                 'description_ar' => 'عرض تحليلات العملاء'],
            ['name' => 'reports.staff',            'display_name' => 'View Staff Reports',           'display_name_ar' => 'عرض تقارير الموظفين',          'requires_pin' => false, 'description' => 'View staff performance reports',          'description_ar' => 'عرض تقارير أداء الموظفين'],
            ['name' => 'reports.export',           'display_name' => 'Export Reports',               'display_name_ar' => 'تصدير التقارير',               'requires_pin' => false, 'description' => 'Export reports to CSV/PDF',               'description_ar' => 'تصدير التقارير إلى CSV/PDF'],
        ],

        // ─── Staff Management ────────────────────────────────────
        'staff' => [
            ['name' => 'staff.view',               'display_name' => 'View Staff',                   'display_name_ar' => 'عرض الموظفين',                 'requires_pin' => false, 'description' => 'View staff list and profiles',            'description_ar' => 'عرض قائمة وملفات الموظفين'],
            ['name' => 'staff.create',             'display_name' => 'Create Staff',                 'display_name_ar' => 'إنشاء موظف',                  'requires_pin' => false, 'description' => 'Add new staff members',                   'description_ar' => 'إضافة موظفين جدد'],
            ['name' => 'staff.edit',               'display_name' => 'Edit Staff',                   'display_name_ar' => 'تعديل الموظفين',              'requires_pin' => false, 'description' => 'Edit staff profiles and settings',        'description_ar' => 'تعديل ملفات وإعدادات الموظفين'],
            ['name' => 'staff.delete',             'display_name' => 'Delete Staff',                 'display_name_ar' => 'حذف الموظفين',                'requires_pin' => true,  'description' => 'Delete staff members',                    'description_ar' => 'حذف الموظفين'],
            ['name' => 'staff.manage',             'display_name' => 'Manage Staff',                 'display_name_ar' => 'إدارة الموظفين',              'requires_pin' => false, 'description' => 'Full staff management access',            'description_ar' => 'صلاحية إدارة الموظفين الكاملة'],
            ['name' => 'staff.manage_pin',         'display_name' => 'Manage PINs',                  'display_name_ar' => 'إدارة أرقام PIN',             'requires_pin' => true,  'description' => 'Set and reset staff PINs',                'description_ar' => 'تعيين وإعادة تعيين أرقام PIN'],
            ['name' => 'staff.manage_shifts',      'display_name' => 'Manage Shifts',                'display_name_ar' => 'إدارة الورديات',              'requires_pin' => false, 'description' => 'Create and manage shift schedules',       'description_ar' => 'إنشاء وإدارة جداول الورديات'],
            ['name' => 'staff.training_mode',      'display_name' => 'Training Mode',                'display_name_ar' => 'وضع التدريب',                 'requires_pin' => false, 'description' => 'Enable training mode for staff',          'description_ar' => 'تفعيل وضع التدريب للموظفين'],
        ],

        // ─── Roles & Permissions ─────────────────────────────────
        'roles' => [
            ['name' => 'roles.view',               'display_name' => 'View Roles',                   'display_name_ar' => 'عرض الأدوار',                  'requires_pin' => false, 'description' => 'View roles and their permissions',        'description_ar' => 'عرض الأدوار وصلاحياتها'],
            ['name' => 'roles.create',             'display_name' => 'Create Roles',                 'display_name_ar' => 'إنشاء الأدوار',               'requires_pin' => false, 'description' => 'Create new custom roles',                 'description_ar' => 'إنشاء أدوار مخصصة جديدة'],
            ['name' => 'roles.edit',               'display_name' => 'Edit Roles',                   'display_name_ar' => 'تعديل الأدوار',               'requires_pin' => false, 'description' => 'Edit existing custom roles',              'description_ar' => 'تعديل الأدوار المخصصة الحالية'],
            ['name' => 'roles.delete',             'display_name' => 'Delete Roles',                 'display_name_ar' => 'حذف الأدوار',                 'requires_pin' => true,  'description' => 'Delete custom roles',                     'description_ar' => 'حذف الأدوار المخصصة'],
            ['name' => 'roles.audit',              'display_name' => 'Audit Roles',                  'display_name_ar' => 'تدقيق الأدوار',               'requires_pin' => false, 'description' => 'View role change audit log',              'description_ar' => 'عرض سجل تدقيق تغييرات الأدوار'],
            ['name' => 'roles.assign',             'display_name' => 'Assign Roles to Staff',        'display_name_ar' => 'تعيين الأدوار للموظفين',       'requires_pin' => false, 'description' => 'Assign/unassign roles to staff',          'description_ar' => 'تعيين/إلغاء تعيين الأدوار للموظفين'],
        ],

        // ─── Labels ──────────────────────────────────────────────
        'labels' => [
            ['name' => 'labels.view',              'display_name' => 'View Labels',                  'display_name_ar' => 'عرض الملصقات',                 'requires_pin' => false, 'description' => 'View label templates and history',        'description_ar' => 'عرض قوالب الملصقات والسجل'],
            ['name' => 'labels.manage',            'display_name' => 'Manage Labels',                'display_name_ar' => 'إدارة الملصقات',               'requires_pin' => false, 'description' => 'Create and manage label templates',       'description_ar' => 'إنشاء وإدارة قوالب الملصقات'],
            ['name' => 'labels.print',             'display_name' => 'Print Labels',                 'display_name_ar' => 'طباعة الملصقات',               'requires_pin' => false, 'description' => 'Print product labels',                    'description_ar' => 'طباعة ملصقات المنتجات'],
        ],

        // ─── Promotions ──────────────────────────────────────────
        'promotions' => [
            ['name' => 'promotions.manage',        'display_name' => 'Manage Promotions',            'display_name_ar' => 'إدارة العروض',                 'requires_pin' => false, 'description' => 'Create and manage promotions',            'description_ar' => 'إنشاء وإدارة العروض الترويجية'],
            ['name' => 'promotions.apply_manual',  'display_name' => 'Apply Promotions Manually',    'display_name_ar' => 'تطبيق العروض يدوياً',           'requires_pin' => false, 'description' => 'Manually apply promotions at POS',        'description_ar' => 'تطبيق العروض يدوياً في نقطة البيع'],
            ['name' => 'promotions.view_analytics', 'display_name' => 'View Promotion Analytics',    'display_name_ar' => 'عرض تحليلات العروض',           'requires_pin' => false, 'description' => 'View promotion performance',              'description_ar' => 'عرض أداء العروض الترويجية'],
        ],

        // ─── Settings ────────────────────────────────────────────
        'settings' => [
            ['name' => 'settings.view',            'display_name' => 'View Settings',                'display_name_ar' => 'عرض الإعدادات',                'requires_pin' => false, 'description' => 'View store settings',                     'description_ar' => 'عرض إعدادات المتجر'],
            ['name' => 'settings.manage',          'display_name' => 'Manage Settings',              'display_name_ar' => 'إدارة الإعدادات',              'requires_pin' => false, 'description' => 'Update store settings',                   'description_ar' => 'تحديث إعدادات المتجر'],
            ['name' => 'settings.hardware',        'display_name' => 'Configure Hardware',           'display_name_ar' => 'إعداد الأجهزة',               'requires_pin' => false, 'description' => 'Configure hardware devices',              'description_ar' => 'إعداد أجهزة المتجر'],
            ['name' => 'settings.sync',            'display_name' => 'Manage Sync',                  'display_name_ar' => 'إدارة المزامنة',              'requires_pin' => false, 'description' => 'Manage data sync settings',               'description_ar' => 'إدارة إعدادات مزامنة البيانات'],
            ['name' => 'settings.thawani',         'display_name' => 'Configure Thawani',            'display_name_ar' => 'إعداد ثواني',                 'requires_pin' => false, 'description' => 'Configure Thawani integration',           'description_ar' => 'إعداد تكامل ثواني'],
            ['name' => 'settings.updates',         'display_name' => 'Manage Updates',               'display_name_ar' => 'إدارة التحديثات',             'requires_pin' => false, 'description' => 'Manage app updates',                      'description_ar' => 'إدارة تحديثات التطبيق'],
            ['name' => 'settings.backup',          'display_name' => 'Manage Backups',               'display_name_ar' => 'إدارة النسخ الاحتياطي',        'requires_pin' => false, 'description' => 'Create and restore backups',              'description_ar' => 'إنشاء واستعادة النسخ الاحتياطي'],
            ['name' => 'settings.tax',             'display_name' => 'Manage Tax Settings',          'display_name_ar' => 'إدارة إعدادات الضريبة',        'requires_pin' => false, 'description' => 'Configure tax rates and rules',           'description_ar' => 'إعداد معدلات وقواعد الضريبة'],
            ['name' => 'settings.receipt',         'display_name' => 'Manage Receipt Settings',      'display_name_ar' => 'إدارة إعدادات الإيصال',        'requires_pin' => false, 'description' => 'Configure receipt templates',             'description_ar' => 'إعداد قوالب الإيصالات'],
            ['name' => 'settings.store_profile',   'display_name' => 'Manage Store Profile',         'display_name_ar' => 'إدارة ملف المتجر',             'requires_pin' => false, 'description' => 'Update store profile information',        'description_ar' => 'تحديث معلومات ملف المتجر'],
            ['name' => 'settings.localization',    'display_name' => 'Manage Localization',          'display_name_ar' => 'إدارة الترجمة',               'requires_pin' => false, 'description' => 'Configure language and locale',           'description_ar' => 'إعداد اللغة والمنطقة'],
            ['name' => 'settings.pos_behavior',    'display_name' => 'POS Behavior Settings',        'display_name_ar' => 'إعدادات سلوك نقطة البيع',      'requires_pin' => false, 'description' => 'Configure POS behavior settings',         'description_ar' => 'إعداد سلوك نقطة البيع'],
        ],

        // ─── Accounting Integration ──────────────────────────────
        'accounting' => [
            ['name' => 'accounting.configure',      'display_name' => 'Configure Accounting',         'display_name_ar' => 'إعداد المحاسبة',              'requires_pin' => false, 'description' => 'Configure accounting integration',        'description_ar' => 'إعداد تكامل المحاسبة'],
            ['name' => 'accounting.connect',        'display_name' => 'Connect Accounting',           'display_name_ar' => 'ربط المحاسبة',                'requires_pin' => true,  'description' => 'Connect to accounting provider',          'description_ar' => 'الربط بمزود المحاسبة'],
            ['name' => 'accounting.export',         'display_name' => 'Export to Accounting',         'display_name_ar' => 'التصدير للمحاسبة',             'requires_pin' => false, 'description' => 'Export transactions to accounting',       'description_ar' => 'تصدير المعاملات للمحاسبة'],
            ['name' => 'accounting.view_history',   'display_name' => 'View Export History',          'display_name_ar' => 'عرض سجل التصدير',             'requires_pin' => false, 'description' => 'View accounting export history',          'description_ar' => 'عرض سجل تصدير المحاسبة'],
            ['name' => 'accounting.manage_mappings', 'display_name' => 'Manage Account Mappings',     'display_name_ar' => 'إدارة تعيينات الحسابات',       'requires_pin' => false, 'description' => 'Map POS accounts to accounting',          'description_ar' => 'تعيين حسابات نقطة البيع للمحاسبة'],
        ],

        // ─── Thawani Integration ─────────────────────────────────
        'thawani' => [
            ['name' => 'thawani.menu',             'display_name' => 'Manage Thawani Menu',          'display_name_ar' => 'إدارة قائمة ثواني',            'requires_pin' => false, 'description' => 'Manage Thawani online menu',              'description_ar' => 'إدارة قائمة ثواني عبر الإنترنت'],
            ['name' => 'thawani.view_dashboard',   'display_name' => 'View Thawani Dashboard',       'display_name_ar' => 'عرض لوحة ثواني',              'requires_pin' => false, 'description' => 'View Thawani integration dashboard',      'description_ar' => 'عرض لوحة تكامل ثواني'],
            ['name' => 'thawani.manage_config',    'display_name' => 'Manage Thawani Config',        'display_name_ar' => 'إدارة إعدادات ثواني',          'requires_pin' => false, 'description' => 'Configure Thawani integration',           'description_ar' => 'إعداد تكامل ثواني'],
        ],

        // ─── Delivery ────────────────────────────────────────────
        'delivery' => [
            ['name' => 'delivery.manage',          'display_name' => 'Manage Delivery',              'display_name_ar' => 'إدارة التوصيل',               'requires_pin' => false, 'description' => 'Manage delivery integration',             'description_ar' => 'إدارة تكامل التوصيل'],
            ['name' => 'delivery.view_dashboard',  'display_name' => 'View Delivery Dashboard',      'display_name_ar' => 'عرض لوحة التوصيل',            'requires_pin' => false, 'description' => 'View delivery orders dashboard',          'description_ar' => 'عرض لوحة طلبات التوصيل'],
            ['name' => 'delivery.manage_config',   'display_name' => 'Manage Delivery Config',       'display_name_ar' => 'إدارة إعدادات التوصيل',        'requires_pin' => false, 'description' => 'Configure delivery platforms',            'description_ar' => 'إعداد منصات التوصيل'],
            ['name' => 'delivery.sync_menu',       'display_name' => 'Sync Menu to Delivery',        'display_name_ar' => 'مزامنة القائمة للتوصيل',       'requires_pin' => false, 'description' => 'Sync products to delivery platforms',     'description_ar' => 'مزامنة المنتجات لمنصات التوصيل'],
            ['name' => 'delivery.view_logs',       'display_name' => 'View Delivery Logs',           'display_name_ar' => 'عرض سجلات التوصيل',           'requires_pin' => false, 'description' => 'View webhook and status logs',            'description_ar' => 'عرض سجلات الويب هوك والحالة'],
        ],

        // ─── Notifications ───────────────────────────────────────
        'notifications' => [
            ['name' => 'notifications.view',       'display_name' => 'View Notifications',           'display_name_ar' => 'عرض الإشعارات',               'requires_pin' => false, 'description' => 'View notification list',                  'description_ar' => 'عرض قائمة الإشعارات'],
            ['name' => 'notifications.manage',     'display_name' => 'Manage Notifications',         'display_name_ar' => 'إدارة الإشعارات',             'requires_pin' => false, 'description' => 'Configure notification preferences',      'description_ar' => 'إعداد تفضيلات الإشعارات'],
            ['name' => 'notifications.schedules',  'display_name' => 'Manage Schedules',             'display_name_ar' => 'إدارة الجداول',               'requires_pin' => false, 'description' => 'Manage notification schedules',           'description_ar' => 'إدارة جداول الإشعارات'],
        ],

        // ─── Branches ────────────────────────────────────────────
        'branches' => [
            ['name' => 'branches.view',            'display_name' => 'View Branches',                'display_name_ar' => 'عرض الفروع',                  'requires_pin' => false, 'description' => 'View branch list and details',            'description_ar' => 'عرض قائمة وتفاصيل الفروع'],
            ['name' => 'branches.manage',          'display_name' => 'Manage Branches',              'display_name_ar' => 'إدارة الفروع',                'requires_pin' => false, 'description' => 'Create, edit, delete branches',           'description_ar' => 'إنشاء وتعديل وحذف الفروع'],
        ],

        // ─── Subscription ────────────────────────────────────────
        'subscription' => [
            ['name' => 'subscription.view',        'display_name' => 'View Subscription',            'display_name_ar' => 'عرض الاشتراك',                'requires_pin' => false, 'description' => 'View subscription status',                'description_ar' => 'عرض حالة الاشتراك'],
            ['name' => 'subscription.manage',      'display_name' => 'Manage Subscription',          'display_name_ar' => 'إدارة الاشتراك',              'requires_pin' => true,  'description' => 'Change plans and manage billing',         'description_ar' => 'تغيير الخطط وإدارة الفواتير'],
        ],

        // ─── Support ─────────────────────────────────────────────
        'support' => [
            ['name' => 'support.view',             'display_name' => 'View Support',                 'display_name_ar' => 'عرض الدعم',                   'requires_pin' => false, 'description' => 'Access support dashboard and KB',         'description_ar' => 'الوصول إلى لوحة الدعم وقاعدة المعرفة'],
            ['name' => 'support.create_ticket',    'display_name' => 'Create Support Ticket',        'display_name_ar' => 'إنشاء تذكرة دعم',             'requires_pin' => false, 'description' => 'Submit support tickets',                  'description_ar' => 'تقديم تذاكر الدعم'],
        ],

        // ─── Security ────────────────────────────────────────────
        'security' => [
            ['name' => 'security.view_dashboard',  'display_name' => 'View Security Dashboard',      'display_name_ar' => 'عرض لوحة الأمان',             'requires_pin' => false, 'description' => 'View security overview',                  'description_ar' => 'عرض نظرة عامة على الأمان'],
            ['name' => 'security.manage_policies', 'display_name' => 'Manage Security Policies',     'display_name_ar' => 'إدارة سياسات الأمان',          'requires_pin' => true,  'description' => 'Configure security policies',             'description_ar' => 'إعداد سياسات الأمان'],
            ['name' => 'security.view_audit',      'display_name' => 'View Audit Log',               'display_name_ar' => 'عرض سجل التدقيق',             'requires_pin' => false, 'description' => 'View security audit trail',               'description_ar' => 'عرض مسار التدقيق الأمني'],
        ],

        // ─── ZATCA ───────────────────────────────────────────────
        'zatca' => [
            ['name' => 'zatca.view',               'display_name' => 'View ZATCA Dashboard',         'display_name_ar' => 'عرض لوحة زاتكا',              'requires_pin' => false, 'description' => 'View ZATCA compliance dashboard',         'description_ar' => 'عرض لوحة الامتثال لزاتكا'],
            ['name' => 'zatca.manage',             'display_name' => 'Manage ZATCA',                 'display_name_ar' => 'إدارة زاتكا',                 'requires_pin' => false, 'description' => 'Submit invoices and manage enrollment',   'description_ar' => 'تقديم الفواتير وإدارة التسجيل'],
        ],

        // ─── Sync ────────────────────────────────────────────────
        'sync' => [
            ['name' => 'sync.view',                'display_name' => 'View Sync Dashboard',          'display_name_ar' => 'عرض لوحة المزامنة',           'requires_pin' => false, 'description' => 'View data sync status',                   'description_ar' => 'عرض حالة مزامنة البيانات'],
            ['name' => 'sync.manage',              'display_name' => 'Manage Sync',                  'display_name_ar' => 'إدارة المزامنة',              'requires_pin' => false, 'description' => 'Trigger sync and resolve conflicts',      'description_ar' => 'تشغيل المزامنة وحل التعارضات'],
        ],

        // ─── Hardware ────────────────────────────────────────────
        'hardware' => [
            ['name' => 'hardware.view',            'display_name' => 'View Hardware',                'display_name_ar' => 'عرض الأجهزة',                 'requires_pin' => false, 'description' => 'View connected hardware',                 'description_ar' => 'عرض الأجهزة المتصلة'],
            ['name' => 'hardware.manage',          'display_name' => 'Manage Hardware',              'display_name_ar' => 'إدارة الأجهزة',               'requires_pin' => false, 'description' => 'Configure printers, scanners, etc.',      'description_ar' => 'إعداد الطابعات والماسحات وغيرها'],
        ],

        // ─── Backup ──────────────────────────────────────────────
        'backup' => [
            ['name' => 'backup.view',              'display_name' => 'View Backups',                 'display_name_ar' => 'عرض النسخ الاحتياطي',          'requires_pin' => false, 'description' => 'View backup history',                     'description_ar' => 'عرض سجل النسخ الاحتياطي'],
            ['name' => 'backup.manage',            'display_name' => 'Manage Backups',               'display_name_ar' => 'إدارة النسخ الاحتياطي',        'requires_pin' => false, 'description' => 'Create and restore backups',              'description_ar' => 'إنشاء واستعادة النسخ الاحتياطي'],
        ],

        // ─── Dashboard ───────────────────────────────────────────
        'dashboard' => [
            ['name' => 'dashboard.view',           'display_name' => 'View Dashboard',               'display_name_ar' => 'عرض لوحة المعلومات',          'requires_pin' => false, 'description' => 'Access the owner dashboard',              'description_ar' => 'الوصول إلى لوحة معلومات المالك'],
        ],

        // ─── Companion App ───────────────────────────────────────
        'companion' => [
            ['name' => 'companion.view',           'display_name' => 'View Companion Dashboard',     'display_name_ar' => 'عرض لوحة التطبيق المصاحب',     'requires_pin' => false, 'description' => 'Access companion mobile app',             'description_ar' => 'الوصول إلى التطبيق المصاحب'],
        ],

        // ─── POS Customization ───────────────────────────────────
        'pos_customization' => [
            ['name' => 'pos_customization.view',   'display_name' => 'View POS Customization',       'display_name_ar' => 'عرض تخصيص نقطة البيع',        'requires_pin' => false, 'description' => 'View POS themes and templates',           'description_ar' => 'عرض سمات وقوالب نقطة البيع'],
            ['name' => 'pos_customization.manage', 'display_name' => 'Manage POS Customization',     'display_name_ar' => 'إدارة تخصيص نقطة البيع',       'requires_pin' => false, 'description' => 'Configure POS layout and themes',         'description_ar' => 'إعداد تخطيط وسمات نقطة البيع'],
        ],

        // ─── Layout Builder ──────────────────────────────────────
        'layout_builder' => [
            ['name' => 'layout_builder.view',      'display_name' => 'View Layout Builder',          'display_name_ar' => 'عرض منشئ التخطيط',            'requires_pin' => false, 'description' => 'View layout templates',                   'description_ar' => 'عرض قوالب التخطيط'],
            ['name' => 'layout_builder.manage',    'display_name' => 'Manage Layout Builder',        'display_name_ar' => 'إدارة منشئ التخطيط',           'requires_pin' => false, 'description' => 'Create and edit layouts',                 'description_ar' => 'إنشاء وتعديل التخطيطات'],
        ],

        // ─── Marketplace ─────────────────────────────────────────
        'marketplace' => [
            ['name' => 'marketplace.view',         'display_name' => 'View Marketplace',             'display_name_ar' => 'عرض السوق',                   'requires_pin' => false, 'description' => 'Browse the marketplace',                  'description_ar' => 'تصفح السوق'],
            ['name' => 'marketplace.purchase',     'display_name' => 'Purchase from Marketplace',    'display_name_ar' => 'الشراء من السوق',              'requires_pin' => false, 'description' => 'Purchase templates and add-ons',          'description_ar' => 'شراء القوالب والإضافات'],
        ],

        // ─── Accessibility ───────────────────────────────────────
        'accessibility' => [
            ['name' => 'accessibility.manage',     'display_name' => 'Manage Accessibility',         'display_name_ar' => 'إدارة إمكانية الوصول',         'requires_pin' => false, 'description' => 'Configure accessibility settings',        'description_ar' => 'إعداد إعدادات إمكانية الوصول'],
        ],

        // ─── Nice to Have / Gamification ─────────────────────────
        'nice_to_have' => [
            ['name' => 'nice_to_have.view',        'display_name' => 'View Nice to Have',            'display_name_ar' => 'عرض الميزات الإضافية',         'requires_pin' => false, 'description' => 'Access gamification and extras',          'description_ar' => 'الوصول إلى التلعيب والإضافات'],
            ['name' => 'nice_to_have.manage',      'display_name' => 'Manage Nice to Have',          'display_name_ar' => 'إدارة الميزات الإضافية',       'requires_pin' => false, 'description' => 'Configure gamification features',         'description_ar' => 'إعداد ميزات التلعيب'],
        ],

        // ─── Onboarding ──────────────────────────────────────────
        'onboarding' => [
            ['name' => 'onboarding.manage',        'display_name' => 'Manage Onboarding',            'display_name_ar' => 'إدارة الإعداد الأولي',         'requires_pin' => false, 'description' => 'Manage store onboarding wizard',          'description_ar' => 'إدارة معالج إعداد المتجر'],
        ],

        // ─── Auto Update ─────────────────────────────────────────
        'auto_update' => [
            ['name' => 'auto_update.view',         'display_name' => 'View Auto Update',             'display_name_ar' => 'عرض التحديث التلقائي',         'requires_pin' => false, 'description' => 'View update status',                      'description_ar' => 'عرض حالة التحديث'],
            ['name' => 'auto_update.manage',       'display_name' => 'Manage Auto Update',           'display_name_ar' => 'إدارة التحديث التلقائي',       'requires_pin' => false, 'description' => 'Configure auto-update settings',          'description_ar' => 'إعداد إعدادات التحديث التلقائي'],
        ],

        // ─── Industry: Bakery ────────────────────────────────────
        'bakery' => [
            ['name' => 'bakery.view',              'display_name' => 'View Bakery Dashboard',        'display_name_ar' => 'عرض لوحة المخبز',             'requires_pin' => false, 'description' => 'View bakery dashboard',                   'description_ar' => 'عرض لوحة المخبز'],
            ['name' => 'bakery.recipes',           'display_name' => 'Manage Recipes',               'display_name_ar' => 'إدارة الوصفات',               'requires_pin' => false, 'description' => 'Create and manage bakery recipes',        'description_ar' => 'إنشاء وإدارة وصفات المخبز'],
            ['name' => 'bakery.custom_orders',     'display_name' => 'Manage Custom Orders',         'display_name_ar' => 'إدارة الطلبات المخصصة',        'requires_pin' => false, 'description' => 'Manage custom cake orders',               'description_ar' => 'إدارة طلبات الكيك المخصصة'],
            ['name' => 'bakery.production',        'display_name' => 'Manage Production',            'display_name_ar' => 'إدارة الإنتاج',               'requires_pin' => false, 'description' => 'Manage production schedule',              'description_ar' => 'إدارة جدول الإنتاج'],
        ],

        // ─── Industry: Electronics ───────────────────────────────
        'mobile' => [
            ['name' => 'mobile.repairs',           'display_name' => 'Manage Repairs',               'display_name_ar' => 'إدارة الإصلاحات',             'requires_pin' => false, 'description' => 'Manage device repair jobs',               'description_ar' => 'إدارة أعمال إصلاح الأجهزة'],
            ['name' => 'mobile.trade_in',          'display_name' => 'Process Trade-Ins',            'display_name_ar' => 'معالجة المقايضات',             'requires_pin' => false, 'description' => 'Process device trade-ins',                'description_ar' => 'معالجة مقايضات الأجهزة'],
            ['name' => 'mobile.imei',              'display_name' => 'Track IMEI',                   'display_name_ar' => 'تتبع IMEI',                   'requires_pin' => false, 'description' => 'Track device IMEI records',               'description_ar' => 'تتبع سجلات IMEI للأجهزة'],
            ['name' => 'mobile.view',              'display_name' => 'View Electronics Dashboard',   'display_name_ar' => 'عرض لوحة الإلكترونيات',        'requires_pin' => false, 'description' => 'View electronics dashboard',              'description_ar' => 'عرض لوحة الإلكترونيات'],
        ],

        // ─── Industry: Florist ───────────────────────────────────
        'flowers' => [
            ['name' => 'flowers.arrangements',     'display_name' => 'Manage Arrangements',          'display_name_ar' => 'إدارة التنسيقات',             'requires_pin' => false, 'description' => 'Manage flower arrangements',              'description_ar' => 'إدارة تنسيقات الزهور'],
            ['name' => 'flowers.subscriptions',    'display_name' => 'Manage Subscriptions',         'display_name_ar' => 'إدارة الاشتراكات',            'requires_pin' => false, 'description' => 'Manage flower subscriptions',             'description_ar' => 'إدارة اشتراكات الزهور'],
            ['name' => 'flowers.freshness',        'display_name' => 'Manage Freshness Tracking',    'display_name_ar' => 'إدارة تتبع النضارة',           'requires_pin' => false, 'description' => 'Track flower freshness',                  'description_ar' => 'تتبع نضارة الزهور'],
            ['name' => 'flowers.view',             'display_name' => 'View Florist Dashboard',       'display_name_ar' => 'عرض لوحة بائع الزهور',        'requires_pin' => false, 'description' => 'View florist dashboard',                  'description_ar' => 'عرض لوحة بائع الزهور'],
        ],

        // ─── Industry: Jewelry ───────────────────────────────────
        'jewelry' => [
            ['name' => 'jewelry.manage_rates',     'display_name' => 'Manage Metal Rates',           'display_name_ar' => 'إدارة أسعار المعادن',          'requires_pin' => false, 'description' => 'Update gold/silver rates',                'description_ar' => 'تحديث أسعار الذهب/الفضة'],
            ['name' => 'jewelry.buyback',          'display_name' => 'Process Buyback',              'display_name_ar' => 'معالجة إعادة الشراء',          'requires_pin' => true,  'description' => 'Process jewelry buyback',                 'description_ar' => 'معالجة إعادة شراء المجوهرات'],
            ['name' => 'jewelry.view',             'display_name' => 'View Jewelry Dashboard',       'display_name_ar' => 'عرض لوحة المجوهرات',          'requires_pin' => false, 'description' => 'View jewelry dashboard',                  'description_ar' => 'عرض لوحة المجوهرات'],
            ['name' => 'jewelry.manage_details',   'display_name' => 'Manage Jewelry Details',       'display_name_ar' => 'إدارة تفاصيل المجوهرات',       'requires_pin' => false, 'description' => 'Manage jewelry product details',          'description_ar' => 'إدارة تفاصيل منتجات المجوهرات'],
        ],

        // ─── Industry: Pharmacy ──────────────────────────────────
        'pharmacy' => [
            ['name' => 'pharmacy.prescriptions',    'display_name' => 'Manage Prescriptions',        'display_name_ar' => 'إدارة الوصفات الطبية',         'requires_pin' => false, 'description' => 'Manage pharmacy prescriptions',           'description_ar' => 'إدارة الوصفات الطبية'],
            ['name' => 'pharmacy.controlled_substances', 'display_name' => 'Sell Controlled Substances', 'display_name_ar' => 'بيع المواد الخاضعة للرقابة', 'requires_pin' => true, 'description' => 'Sell controlled/scheduled substances', 'description_ar' => 'بيع المواد الخاضعة للرقابة والجدول'],
            ['name' => 'pharmacy.view',             'display_name' => 'View Pharmacy Dashboard',     'display_name_ar' => 'عرض لوحة الصيدلية',           'requires_pin' => false, 'description' => 'View pharmacy dashboard',                 'description_ar' => 'عرض لوحة الصيدلية'],
            ['name' => 'pharmacy.drug_schedules',   'display_name' => 'Manage Drug Schedules',       'display_name_ar' => 'إدارة جداول الأدوية',          'requires_pin' => false, 'description' => 'Manage drug schedule classifications',    'description_ar' => 'إدارة تصنيفات جداول الأدوية'],
        ],

        // ─── Industry: Restaurant ────────────────────────────────
        'restaurant' => [
            ['name' => 'restaurant.tables',         'display_name' => 'Manage Tables',               'display_name_ar' => 'إدارة الطاولات',              'requires_pin' => false, 'description' => 'Manage restaurant tables',                'description_ar' => 'إدارة طاولات المطعم'],
            ['name' => 'restaurant.kds',            'display_name' => 'Kitchen Display',              'display_name_ar' => 'شاشة المطبخ',                 'requires_pin' => false, 'description' => 'Access kitchen display system',           'description_ar' => 'الوصول إلى نظام عرض المطبخ'],
            ['name' => 'restaurant.reservations',   'display_name' => 'Manage Reservations',         'display_name_ar' => 'إدارة الحجوزات',              'requires_pin' => false, 'description' => 'Manage table reservations',               'description_ar' => 'إدارة حجوزات الطاولات'],
            ['name' => 'restaurant.tabs',           'display_name' => 'Manage Tabs',                 'display_name_ar' => 'إدارة الحسابات المفتوحة',      'requires_pin' => false, 'description' => 'Manage open tabs',                        'description_ar' => 'إدارة الحسابات المفتوحة'],
            ['name' => 'restaurant.split_bill',     'display_name' => 'Split Bills',                 'display_name_ar' => 'تقسيم الفواتير',              'requires_pin' => false, 'description' => 'Split bills between customers',           'description_ar' => 'تقسيم الفواتير بين العملاء'],
            ['name' => 'restaurant.view',           'display_name' => 'View Restaurant Dashboard',   'display_name_ar' => 'عرض لوحة المطعم',             'requires_pin' => false, 'description' => 'View restaurant dashboard',               'description_ar' => 'عرض لوحة المطعم'],
        ],

        // ─── Wameed AI ───────────────────────────────────────────
        'wameed_ai' => [
            ['name' => 'wameed_ai.view',    'display_name' => 'View AI Features',       'display_name_ar' => 'عرض ميزات الذكاء الاصطناعي',       'requires_pin' => false, 'description' => 'View AI suggestions, usage logs, and feature list',       'description_ar' => 'عرض اقتراحات الذكاء الاصطناعي وسجلات الاستخدام وقائمة الميزات'],
            ['name' => 'wameed_ai.use',     'display_name' => 'Use AI Features',        'display_name_ar' => 'استخدام ميزات الذكاء الاصطناعي',   'requires_pin' => false, 'description' => 'Invoke AI features (reorder, OCR, analytics, etc.)',      'description_ar' => 'استخدام ميزات الذكاء الاصطناعي (إعادة الطلب، المسح الضوئي، التحليلات، إلخ)'],
            ['name' => 'wameed_ai.manage',  'display_name' => 'Manage AI Settings',     'display_name_ar' => 'إدارة إعدادات الذكاء الاصطناعي',   'requires_pin' => false, 'description' => 'Configure AI features, providers, and store settings',    'description_ar' => 'تكوين ميزات الذكاء الاصطناعي والمزودين وإعدادات المتجر'],
        ],

        // ─── Cashier Gamification & Theft Deterrence ─────────────
        'cashier_performance' => [
            ['name' => 'cashier_performance.view_leaderboard', 'display_name' => 'View Cashier Leaderboard',   'display_name_ar' => 'عرض لوحة متصدري الصرافين',      'requires_pin' => false, 'description' => 'View cashier performance leaderboard and history',      'description_ar' => 'عرض لوحة متصدري أداء الصرافين والسجل'],
            ['name' => 'cashier_performance.view_badges',      'display_name' => 'View Cashier Badges',        'display_name_ar' => 'عرض شارات الصرافين',             'requires_pin' => false, 'description' => 'View cashier badge definitions and awards',            'description_ar' => 'عرض تعريفات شارات الصرافين والجوائز'],
            ['name' => 'cashier_performance.manage_badges',    'display_name' => 'Manage Cashier Badges',      'display_name_ar' => 'إدارة شارات الصرافين',           'requires_pin' => false, 'description' => 'Create, edit, delete cashier badge definitions',       'description_ar' => 'إنشاء وتعديل وحذف تعريفات شارات الصرافين'],
            ['name' => 'cashier_performance.view_anomalies',   'display_name' => 'View Cashier Anomalies',     'display_name_ar' => 'عرض الحالات الشاذة للصرافين',     'requires_pin' => false, 'description' => 'View anomaly alerts and risk scores for cashiers',     'description_ar' => 'عرض تنبيهات الحالات الشاذة ودرجات المخاطر للصرافين'],
            ['name' => 'cashier_performance.view_reports',     'display_name' => 'View Shift Reports',         'display_name_ar' => 'عرض تقارير الورديات',            'requires_pin' => false, 'description' => 'View cashier shift-end report cards',                  'description_ar' => 'عرض بطاقات تقارير نهاية وردية الصراف'],
            ['name' => 'cashier_performance.manage_settings',  'display_name' => 'Manage Gamification Settings','display_name_ar' => 'إدارة إعدادات التحفيز',          'requires_pin' => false, 'description' => 'Configure gamification settings, weights, and thresholds', 'description_ar' => 'تكوين إعدادات التحفيز والأوزان والحدود'],
        ],
    ];
}
