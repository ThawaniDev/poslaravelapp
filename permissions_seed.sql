-- =====================================================================
-- POS System — Permissions & Roles Seed Data
-- =====================================================================
-- Target     : Supabase PostgreSQL (project xyunpamaxzomfpwtggvl)
-- Run once   : After database_schema.sql has been applied
-- Idempotent : Uses ON CONFLICT DO NOTHING so safe to re-run
-- =====================================================================
-- Contents:
--   §1  Platform (Admin) Permissions          → admin_permissions        (59 rows)
--   §2  Platform (Admin) Roles                → admin_roles              ( 7 rows)
--   §3  Platform Role ↔ Permission Mapping    → admin_role_permissions
--   §4  Provider Permissions                  → provider_permissions     (76 rows)
--   §5  Provider Default Role Templates       → default_role_templates   ( 8 rows)
--   §6  Template ↔ Permission Mapping         → default_role_template_permissions
--   §7  Spatie Runtime Permissions            → permissions              (76 rows)
--   §8  Verification Queries
-- =====================================================================

BEGIN;

-- =====================================================================
-- §1  PLATFORM (ADMIN) PERMISSIONS
-- =====================================================================
-- Table: admin_permissions (id UUID PK, name UNIQUE, "group", description)
-- Source: platform_roles_feature.md master catalogue + extensions from
--         individual platform feature docs
-- Naming: {group}.{action}
-- =====================================================================

INSERT INTO admin_permissions (name, "group", description) VALUES

-- ── Stores ───────────────────────────────────────────────────────────
('stores.view',            'stores',         'View store list and details'),
('stores.edit',            'stores',         'Edit store settings and status'),
('stores.create',          'stores',         'Manual store onboarding'),
('stores.suspend',         'stores',         'Suspend or reactivate a store'),
('stores.delete',          'stores',         'Permanently delete a store'),
('stores.impersonate',     'stores',         'Login as store owner for support'),
('stores.export',          'stores',         'Export store data'),

-- ── Billing ──────────────────────────────────────────────────────────
('billing.view',           'billing',        'View subscriptions and invoices'),
('billing.edit',           'billing',        'Modify subscriptions and apply discounts/credits'),
('billing.refund',         'billing',        'Process billing refunds'),
('billing.invoices',       'billing',        'Generate and manage invoices'),
('billing.plans',          'billing',        'Create and edit subscription plans'),

-- ── Tickets ──────────────────────────────────────────────────────────
('tickets.view',           'tickets',        'View support tickets'),
('tickets.respond',        'tickets',        'Reply to support tickets'),
('tickets.assign',         'tickets',        'Assign tickets to agents'),
('tickets.manage',         'tickets',        'Create, close, and delete tickets'),

-- ── Integrations ─────────────────────────────────────────────────────
('integrations.view',      'integrations',   'View delivery platform configurations'),
('integrations.manage',    'integrations',   'Create, edit, and delete platform configurations'),
('integrations.test',      'integrations',   'Test platform connectivity'),

-- ── Settings ─────────────────────────────────────────────────────────
('settings.view',          'settings',       'View system configuration'),
('settings.edit',          'settings',       'Modify system settings'),
('settings.feature_flags', 'settings',       'Manage feature flags'),
('settings.maintenance',   'settings',       'Toggle maintenance mode'),
('settings.credentials',   'settings',       'Manage encrypted third-party API credentials'),
('settings.hardware_catalog', 'settings',    'Manage certified POS hardware compatibility catalog'),
('settings.translations',  'settings',       'Manage master ARB translation strings and locales'),
('settings.payment_methods','settings',       'Manage the payment method registry'),
('settings.security_policies','settings',     'Manage platform-wide security policy defaults'),

-- ── Analytics ────────────────────────────────────────────────────────
('analytics.view',         'analytics',      'View platform dashboards and reports'),
('analytics.export',       'analytics',      'Export analytics data'),

-- ── Announcements ────────────────────────────────────────────────────
('announcements.view',     'announcements',  'View announcements'),
('announcements.create',   'announcements',  'Create announcements'),
('announcements.manage',   'announcements',  'Create, edit, and delete announcements'),
('announcements.send',     'announcements',  'Send and dispatch announcement notifications'),

-- ── Users ────────────────────────────────────────────────────────────
('users.view',             'users',          'View cross-store user list'),
('users.manage',           'users',          'Reset passwords and disable accounts'),
('users.edit',             'users',          'Enable or disable individual user accounts'),
('users.reset_password',   'users',          'Reset provider user passwords'),

-- ── App Updates ──────────────────────────────────────────────────────
('app_updates.view',       'app_updates',    'View app release versions and adoption stats'),
('app_updates.manage',     'app_updates',    'Publish new versions and adjust rollout'),
('app_updates.rollback',   'app_updates',    'Deactivate a release and trigger rollback'),

-- ── Admin Team ───────────────────────────────────────────────────────
('admin_team.view',        'admin_team',     'View admin users'),
('admin_team.manage',      'admin_team',     'Create, edit, and deactivate admin accounts'),
('admin_team.roles',       'admin_team',     'Manage custom admin roles and permissions'),

-- ── Provider Roles ───────────────────────────────────────────────────
('provider_roles.view',    'provider_roles', 'View provider permission master list'),
('provider_roles.manage',  'provider_roles', 'Edit default provider role templates'),

-- ── Infrastructure ───────────────────────────────────────────────────
('infrastructure.view',    'infrastructure', 'View infrastructure dashboard and server health'),
('infrastructure.manage',  'infrastructure', 'Queue management, failed job retry, cache flush'),
('infrastructure.backups', 'infrastructure', 'View, trigger, and download database backups'),

-- ── Content ──────────────────────────────────────────────────────────
('content.view',           'content',        'View business types and onboarding configuration'),
('content.manage',         'content',        'Manage business types, onboarding, and templates'),
('content.pricing',        'content',        'Manage public pricing page content'),

-- ── Notifications ────────────────────────────────────────────────────
('notifications.manage',   'notifications',  'Manage notification templates for all channels'),

-- ── UI / Layout ──────────────────────────────────────────────────────
('ui.manage',              'ui',             'Manage POS layout templates, themes, and receipt layouts'),

-- ── Security ─────────────────────────────────────────────────────────
('security.view',          'security',       'View security dashboard and audit logs'),
('security.manage_ips',    'security',       'Manage IP allowlist and blocklist'),
('security.manage_sessions','security',       'View and revoke admin sessions'),
('security.manage_alerts', 'security',       'Investigate and resolve security alerts'),

-- ── Knowledge Base ───────────────────────────────────────────────────
('kb.manage',              'kb',             'Manage canned responses and knowledge base articles')

ON CONFLICT (name) DO NOTHING;


-- =====================================================================
-- §2  PLATFORM (ADMIN) ROLES
-- =====================================================================
-- Table: admin_roles (id UUID PK, name, slug UNIQUE, description, is_system)
-- Source: platform_roles_feature.md §1
-- =====================================================================

INSERT INTO admin_roles (name, slug, description, is_system) VALUES
('Super Admin',          'super_admin',          'Full unrestricted access to every platform function',            TRUE),
('Platform Manager',     'platform_manager',     'Day-to-day platform operations, store and content management',   TRUE),
('Support Agent',        'support_agent',        'Handle support tickets and view store info for troubleshooting', TRUE),
('Finance Admin',        'finance_admin',        'Billing, invoicing, refunds, and financial analytics',           TRUE),
('Integration Manager',  'integration_manager',  'Manage third-party integrations and app updates',                TRUE),
('Sales',                'sales',                'Store onboarding and subscription management',                   TRUE),
('Viewer',               'viewer',               'Read-only access across the platform',                           TRUE)
ON CONFLICT (slug) DO NOTHING;


-- =====================================================================
-- §3  PLATFORM ROLE ↔ PERMISSION MAPPING
-- =====================================================================
-- Table: admin_role_permissions (admin_role_id UUID, admin_permission_id UUID)
-- =====================================================================

-- ── super_admin → ALL permissions ────────────────────────────────────
INSERT INTO admin_role_permissions (admin_role_id, admin_permission_id)
SELECT r.id, p.id
FROM admin_roles r
CROSS JOIN admin_permissions p
WHERE r.slug = 'super_admin'
ON CONFLICT DO NOTHING;

-- ── platform_manager (33 permissions) ────────────────────────────────
INSERT INTO admin_role_permissions (admin_role_id, admin_permission_id)
SELECT r.id, p.id
FROM admin_roles r
CROSS JOIN admin_permissions p
WHERE r.slug = 'platform_manager'
  AND p.name IN (
    'stores.view', 'stores.edit', 'stores.create', 'stores.suspend',
    'stores.export', 'stores.impersonate',
    'billing.view', 'billing.edit', 'billing.plans',
    'tickets.view',
    'integrations.view', 'integrations.manage', 'integrations.test',
    'settings.view',
    'analytics.view', 'analytics.export',
    'announcements.view', 'announcements.create', 'announcements.manage',
    'users.view', 'users.manage', 'users.edit',
    'app_updates.view', 'app_updates.manage',
    'admin_team.view',
    'provider_roles.view', 'provider_roles.manage',
    'content.view', 'content.manage',
    'notifications.manage',
    'ui.manage',
    'security.view'
  )
ON CONFLICT DO NOTHING;

-- ── support_agent (8 permissions) ────────────────────────────────────
INSERT INTO admin_role_permissions (admin_role_id, admin_permission_id)
SELECT r.id, p.id
FROM admin_roles r
CROSS JOIN admin_permissions p
WHERE r.slug = 'support_agent'
  AND p.name IN (
    'stores.view',
    'tickets.view', 'tickets.respond', 'tickets.assign',
    'users.view',
    'analytics.view',
    'announcements.view',
    'kb.manage'
  )
ON CONFLICT DO NOTHING;

-- ── finance_admin (9 permissions) ────────────────────────────────────
INSERT INTO admin_role_permissions (admin_role_id, admin_permission_id)
SELECT r.id, p.id
FROM admin_roles r
CROSS JOIN admin_permissions p
WHERE r.slug = 'finance_admin'
  AND p.name IN (
    'stores.view',
    'billing.view', 'billing.edit', 'billing.refund',
    'billing.invoices', 'billing.plans',
    'analytics.view', 'analytics.export',
    'announcements.view'
  )
ON CONFLICT DO NOTHING;

-- ── integration_manager (6 permissions) ──────────────────────────────
INSERT INTO admin_role_permissions (admin_role_id, admin_permission_id)
SELECT r.id, p.id
FROM admin_roles r
CROSS JOIN admin_permissions p
WHERE r.slug = 'integration_manager'
  AND p.name IN (
    'stores.view',
    'integrations.view', 'integrations.manage', 'integrations.test',
    'analytics.view',
    'app_updates.view'
  )
ON CONFLICT DO NOTHING;

-- ── sales (7 permissions) ────────────────────────────────────────────
INSERT INTO admin_role_permissions (admin_role_id, admin_permission_id)
SELECT r.id, p.id
FROM admin_roles r
CROSS JOIN admin_permissions p
WHERE r.slug = 'sales'
  AND p.name IN (
    'stores.view', 'stores.create',
    'billing.view', 'billing.edit',
    'users.view',
    'analytics.view',
    'announcements.view'
  )
ON CONFLICT DO NOTHING;

-- ── viewer (5 permissions) ───────────────────────────────────────────
INSERT INTO admin_role_permissions (admin_role_id, admin_permission_id)
SELECT r.id, p.id
FROM admin_roles r
CROSS JOIN admin_permissions p
WHERE r.slug = 'viewer'
  AND p.name IN (
    'stores.view',
    'billing.view',
    'analytics.view',
    'announcements.view',
    'app_updates.view'
  )
ON CONFLICT DO NOTHING;


-- =====================================================================
-- §4  PROVIDER PERMISSIONS
-- =====================================================================
-- Table: provider_permissions (id UUID PK, name UNIQUE, "group", description,
--        description_ar, is_active)
-- Source: All provider_features docs
-- Naming: {module}.{action}
-- =====================================================================

INSERT INTO provider_permissions (name, "group", description, description_ar, is_active) VALUES

-- ── POS Terminal (7) ─────────────────────────────────────────────────
('pos.sell',               'pos',         'Process sales transactions on POS',                'معالجة معاملات البيع على نقطة البيع',            TRUE),
('pos.shift_open',         'pos',         'Open a cash shift session',                        'فتح جلسة مناوبة نقدية',                          TRUE),
('pos.shift_close',        'pos',         'Close a cash shift session (Z-report)',             'إغلاق جلسة مناوبة نقدية',                        TRUE),
('pos.return',             'pos',         'Process returns and refunds at POS',                'معالجة المرتجعات والاسترداد',                    TRUE),
('pos.tax_exempt',         'pos',         'Create tax-exempt sales (requires PIN)',            'إنشاء مبيعات معفاة من الضريبة',                  TRUE),
('pos.approve_discount',   'pos',         'Approve discounts above cashier threshold (PIN)',   'الموافقة على خصومات تتجاوز حد الكاشير',          TRUE),
('pos.void_transaction',   'pos',         'Void a completed transaction (requires PIN)',       'إلغاء معاملة مكتملة',                            TRUE),

-- ── Orders (4) ───────────────────────────────────────────────────────
('orders.view',            'orders',      'View order list and order details',                 'عرض قائمة وتفاصيل الطلبات',                      TRUE),
('orders.manage',          'orders',      'Create orders, update status, add notes',           'إنشاء الطلبات وتحديث حالتها',                    TRUE),
('orders.return',          'orders',      'Process returns and exchanges',                     'معالجة المرتجعات والاستبدال',                    TRUE),
('orders.void',            'orders',      'Void or cancel orders (requires PIN)',              'إلغاء الطلبات',                                  TRUE),

-- ── Products (2) ─────────────────────────────────────────────────────
('products.view',          'products',    'View product catalog',                              'عرض قائمة المنتجات',                             TRUE),
('products.manage',        'products',    'Create, edit, delete products and variants',        'إدارة المنتجات والتصنيفات',                      TRUE),

-- ── Inventory (5) ────────────────────────────────────────────────────
('inventory.view',         'inventory',   'View stock levels and movement audit trail',        'عرض مستويات المخزون',                            TRUE),
('inventory.manage',       'inventory',   'Goods receipts, purchase orders, recipes',          'إدارة استلام البضائع وأوامر الشراء',             TRUE),
('inventory.adjust',       'inventory',   'Stock adjustments and waste recording',             'تعديلات المخزون وتسجيل الهدر',                   TRUE),
('inventory.transfer',     'inventory',   'Create, approve, and receive stock transfers',      'إنشاء والموافقة على تحويلات المخزون',            TRUE),
('inventory.stocktake',    'inventory',   'Create stocktake sessions and apply variance',      'إنشاء جلسات الجرد وتطبيق الفروقات',             TRUE),

-- ── Payments (2) ─────────────────────────────────────────────────────
('payments.process',       'payments',    'Process payments and issue gift cards',             'معالجة المدفوعات وإصدار بطاقات الهدايا',         TRUE),
('payments.refund',        'payments',    'Process refunds (requires PIN for cash refund)',    'معالجة المبالغ المستردة',                        TRUE),

-- ── Cash Management (1) ──────────────────────────────────────────────
('cash.manage',            'cash',        'Open/close cash sessions, cash-in/out, expenses',  'إدارة الصندوق النقدي',                           TRUE),

-- ── Finance (2) ──────────────────────────────────────────────────────
('finance.commissions',    'finance',     'View commission summaries per staff',               'عرض ملخصات العمولات لكل موظف',                   TRUE),
('finance.settlements',    'finance',     'View Thawani settlement reports',                   'عرض تقارير تسويات ثواني',                        TRUE),

-- ── Reports (4) ──────────────────────────────────────────────────────
('reports.view',           'reports',     'View dashboard and sales/product/staff reports',    'عرض لوحة التحكم والتقارير',                      TRUE),
('reports.view_margin',    'reports',     'View cost price and margin/markup data',            'عرض بيانات التكلفة والهامش',                     TRUE),
('reports.view_financial', 'reports',     'View P&L, payment breakdowns, daily summaries',     'عرض تقارير الأرباح والخسائر',                    TRUE),
('reports.attendance',     'reports',     'View and export attendance records',                'عرض وتصدير سجلات الحضور',                        TRUE),

-- ── Staff (7) ────────────────────────────────────────────────────────
('staff.view',             'staff',       'View staff list, profiles, shifts, activity logs',  'عرض قائمة الموظفين',                             TRUE),
('staff.create',           'staff',       'Create new staff members',                          'إنشاء موظفين جدد',                               TRUE),
('staff.edit',             'staff',       'Edit staff profiles, NFC, commission config',       'تعديل ملفات الموظفين',                           TRUE),
('staff.delete',           'staff',       'Deactivate staff (soft delete)',                    'إلغاء تفعيل الموظفين',                           TRUE),
('staff.manage_shifts',    'staff',       'Create and update shift schedules',                 'إدارة جداول المناوبات',                          TRUE),
('staff.manage_pin',       'staff',       'Set and reset staff PINs',                          'تعيين وإعادة تعيين أرقام التعريف',               TRUE),
('staff.training_mode',    'staff',       'Access training mode sandbox POS',                  'الوصول إلى وضع التدريب',                         TRUE),

-- ── Roles (5) ────────────────────────────────────────────────────────
('roles.view',             'roles',       'View roles list and role details',                  'عرض قائمة الأدوار',                              TRUE),
('roles.create',           'roles',       'Create custom roles and duplicate roles',           'إنشاء أدوار مخصصة',                              TRUE),
('roles.edit',             'roles',       'Edit role permissions',                             'تعديل صلاحيات الأدوار',                          TRUE),
('roles.delete',           'roles',       'Delete custom roles',                               'حذف الأدوار المخصصة',                            TRUE),
('roles.audit',            'roles',       'View permission audit log and PIN override log',    'عرض سجل تدقيق الصلاحيات',                        TRUE),

-- ── Customers (2) ────────────────────────────────────────────────────
('customers.view',         'customers',   'View customer list, details, purchase history',     'عرض قائمة وتفاصيل العملاء',                      TRUE),
('customers.manage',       'customers',   'Create, edit, delete customers; loyalty and credit', 'إدارة بيانات العملاء والولاء',                   TRUE),

-- ── Promotions (2) ───────────────────────────────────────────────────
('promotions.manage',      'promotions',  'CRUD promotions and coupons, batch-generate codes', 'إدارة العروض والقسائم',                          TRUE),
('promotions.apply_manual','promotions',  'Manually apply or remove promotions at POS',        'تطبيق العروض يدوياً على نقطة البيع',             TRUE),

-- ── Labels (3) ───────────────────────────────────────────────────────
('labels.view',            'labels',      'View label templates',                              'عرض قوالب الملصقات',                             TRUE),
('labels.manage',          'labels',      'Create, edit, and delete label templates',          'إدارة قوالب الملصقات',                           TRUE),
('labels.print',           'labels',      'Print barcode labels',                              'طباعة ملصقات الباركود',                          TRUE),

-- ── Delivery (1) ─────────────────────────────────────────────────────
('delivery.manage',        'delivery',    'Configure delivery platforms and menu sync',        'إعداد منصات التوصيل ومزامنة القائمة',            TRUE),

-- ── Settings (7) ─────────────────────────────────────────────────────
('settings.manage',        'settings',    'POS customization, receipt templates, notifications','تخصيص نقطة البيع والإشعارات',                    TRUE),
('settings.view',          'settings',    'View subscription and billing info',                'عرض معلومات الاشتراك والفوترة',                  TRUE),
('settings.hardware',      'settings',    'Configure hardware (printers, scanners, drawers)',  'إعداد الأجهزة (الطابعات والماسحات)',              TRUE),
('settings.sync',          'settings',    'View sync status and manage sync settings',         'عرض حالة المزامنة وإدارتها',                     TRUE),
('settings.thawani',       'settings',    'Configure Thawani integration and API keys',        'إعداد تكامل ثواني ومفاتيح API',                  TRUE),
('settings.backup',        'settings',    'Create and restore database backups',               'إنشاء واستعادة النسخ الاحتياطية',                TRUE),
('settings.updates',       'settings',    'Manage POS application updates',                    'إدارة تحديثات تطبيق نقطة البيع',                TRUE),

-- ── Thawani Marketplace (1) ──────────────────────────────────────────
('thawani.menu',           'thawani',     'Push/sync catalog to Thawani, manage availability', 'مزامنة القائمة مع ثواني',                        TRUE),

-- ── Accounting (4) ───────────────────────────────────────────────────
('accounting.connect',     'accounting',  'Connect/disconnect accounting integrations',        'ربط أو فصل تكامل المحاسبة',                      TRUE),
('accounting.configure',   'accounting',  'Configure account mapping and auto-export',         'إعداد ربط الحسابات والتصدير التلقائي',           TRUE),
('accounting.export',      'accounting',  'Trigger manual exports to accounting system',       'تصدير يدوي إلى نظام المحاسبة',                  TRUE),
('accounting.view_history','accounting',  'View export history log',                           'عرض سجل التصدير',                                TRUE),

-- ── Pharmacy (2) ─────────────────────────────────────────────────────
('pharmacy.prescriptions',         'pharmacy',    'Create and manage prescription records',             'إنشاء وإدارة سجلات الوصفات الطبية',             TRUE),
('pharmacy.controlled_substances', 'pharmacy',    'Sell controlled/schedule drugs',                     'بيع الأدوية المجدولة والخاضعة للرقابة',          TRUE),

-- ── Jewelry (2) ──────────────────────────────────────────────────────
('jewelry.manage_rates',   'jewelry',     'Update daily gold/silver/platinum rates',            'تحديث أسعار الذهب والفضة والبلاتين',             TRUE),
('jewelry.buyback',        'jewelry',     'Process gold buyback transactions',                  'معالجة معاملات إعادة شراء الذهب',                TRUE),

-- ── Mobile/Electronics (3) ───────────────────────────────────────────
('mobile.repairs',         'mobile',      'Manage repair queue and status tracking',            'إدارة طلبات الإصلاح وتتبع الحالة',              TRUE),
('mobile.trade_in',        'mobile',      'Assess and accept device trade-ins',                 'تقييم وقبول الأجهزة المستبدلة',                  TRUE),
('mobile.imei',            'mobile',      'Register and track IMEI records',                    'تسجيل وتتبع سجلات IMEI',                        TRUE),

-- ── Florist (2) ──────────────────────────────────────────────────────
('flowers.arrangements',   'flowers',     'Create and manage flower arrangements',              'إنشاء وإدارة تنسيقات الزهور',                    TRUE),
('flowers.subscriptions',  'flowers',     'Manage recurring delivery subscriptions',            'إدارة اشتراكات التوصيل المتكررة',                TRUE),

-- ── Bakery (3) ───────────────────────────────────────────────────────
('bakery.production',      'bakery',      'Manage production schedules and log batches',        'إدارة جداول الإنتاج وتسجيل الدفعات',             TRUE),
('bakery.recipes',         'bakery',      'Create and edit recipes',                            'إنشاء وتعديل الوصفات',                           TRUE),
('bakery.custom_orders',   'bakery',      'Manage custom cake and item orders',                 'إدارة طلبات الكيك والمنتجات المخصصة',            TRUE),

-- ── Restaurant (5) ───────────────────────────────────────────────────
('restaurant.tables',      'restaurant',  'Manage table layout and status',                    'إدارة تخطيط وحالة الطاولات',                     TRUE),
('restaurant.kds',         'restaurant',  'Access kitchen display system, mark items ready',    'الوصول إلى نظام عرض المطبخ',                     TRUE),
('restaurant.reservations','restaurant',  'Create and manage table reservations',               'إنشاء وإدارة حجوزات الطاولات',                   TRUE),
('restaurant.tabs',        'restaurant',  'Open and close tabs',                                'فتح وإغلاق الحسابات المفتوحة',                   TRUE),
('restaurant.split_bill',  'restaurant',  'Split bills between diners',                        'تقسيم الفاتورة بين الزبائن',                     TRUE)

ON CONFLICT (name) DO NOTHING;


-- =====================================================================
-- §5  PROVIDER DEFAULT ROLE TEMPLATES
-- =====================================================================
-- Table: default_role_templates (id UUID PK, name, name_ar, slug UNIQUE,
--        description, description_ar)
-- These are cloned into per-store `roles` table on store creation.
-- =====================================================================

INSERT INTO default_role_templates (name, name_ar, slug, description, description_ar) VALUES
('Owner',           'المالك',         'owner',           'Full unrestricted access. Cannot be modified or restricted.',
                                                          'وصول كامل غير مقيّد. لا يمكن تعديله أو تقييده.'),
('Chain Manager',   'مدير السلسلة',   'chain_manager',   'Multi-branch oversight with full operational access across branches.',
                                                          'إشراف على فروع متعددة مع وصول تشغيلي كامل.'),
('Branch Manager',  'مدير الفرع',     'branch_manager',  'Full operational access within a single branch.',
                                                          'وصول تشغيلي كامل داخل فرع واحد.'),
('Cashier',         'كاشير',          'cashier',         'Point-of-sale operations: sales, payments, basic customer lookup.',
                                                          'عمليات نقطة البيع: البيع والدفع والبحث عن العملاء.'),
('Inventory Clerk', 'أمين المخزون',   'inventory_clerk', 'Stock management: receiving, adjustments, stocktake, label printing.',
                                                          'إدارة المخزون: الاستلام والتعديلات والجرد.'),
('Accountant',      'محاسب',          'accountant',      'Financial reports, accounting integration, settlements.',
                                                          'التقارير المالية وتكامل المحاسبة والتسويات.'),
('Kitchen Staff',   'موظف المطبخ',    'kitchen_staff',   'Kitchen display access for restaurant vertical.',
                                                          'الوصول إلى نظام عرض المطبخ لقطاع المطاعم.'),
('Viewer',          'مشاهد',          'viewer',          'Read-only access to orders, products, inventory, reports, and customers.',
                                                          'وصول للقراءة فقط إلى الطلبات والمنتجات والمخزون.')
ON CONFLICT (slug) DO NOTHING;


-- =====================================================================
-- §6  DEFAULT ROLE TEMPLATE ↔ PERMISSION MAPPING
-- =====================================================================
-- Owner = ALL permissions (handled dynamically — every provider_permission
-- is granted). We still insert them explicitly for referential integrity.
-- =====================================================================

-- ── Helper: temp table for cleaner inserts ───────────────────────────
CREATE TEMP TABLE _tpl_perms (
    tpl_slug   VARCHAR(30),
    perm_name  VARCHAR(50)
);

-- ── Owner → ALL permissions ──────────────────────────────────────────
INSERT INTO _tpl_perms (tpl_slug, perm_name)
SELECT 'owner', name FROM provider_permissions WHERE is_active = TRUE;

-- ── Chain Manager → same as Branch Manager + multi-branch oversight ──
INSERT INTO _tpl_perms (tpl_slug, perm_name) VALUES
-- POS
('chain_manager', 'pos.sell'),
('chain_manager', 'pos.shift_open'),
('chain_manager', 'pos.shift_close'),
('chain_manager', 'pos.return'),
('chain_manager', 'pos.tax_exempt'),
('chain_manager', 'pos.approve_discount'),
('chain_manager', 'pos.void_transaction'),
-- Orders
('chain_manager', 'orders.view'),
('chain_manager', 'orders.manage'),
('chain_manager', 'orders.return'),
('chain_manager', 'orders.void'),
-- Products
('chain_manager', 'products.view'),
('chain_manager', 'products.manage'),
-- Inventory
('chain_manager', 'inventory.view'),
('chain_manager', 'inventory.manage'),
('chain_manager', 'inventory.adjust'),
('chain_manager', 'inventory.transfer'),
('chain_manager', 'inventory.stocktake'),
-- Payments
('chain_manager', 'payments.process'),
('chain_manager', 'payments.refund'),
('chain_manager', 'cash.manage'),
-- Finance
('chain_manager', 'finance.commissions'),
('chain_manager', 'finance.settlements'),
-- Reports
('chain_manager', 'reports.view'),
('chain_manager', 'reports.view_margin'),
('chain_manager', 'reports.view_financial'),
('chain_manager', 'reports.attendance'),
-- Staff
('chain_manager', 'staff.view'),
('chain_manager', 'staff.create'),
('chain_manager', 'staff.edit'),
('chain_manager', 'staff.delete'),
('chain_manager', 'staff.manage_shifts'),
('chain_manager', 'staff.manage_pin'),
('chain_manager', 'staff.training_mode'),
-- Roles
('chain_manager', 'roles.view'),
('chain_manager', 'roles.create'),
('chain_manager', 'roles.edit'),
-- Customers
('chain_manager', 'customers.view'),
('chain_manager', 'customers.manage'),
-- Promotions
('chain_manager', 'promotions.manage'),
('chain_manager', 'promotions.apply_manual'),
-- Labels
('chain_manager', 'labels.view'),
('chain_manager', 'labels.manage'),
('chain_manager', 'labels.print'),
-- Delivery
('chain_manager', 'delivery.manage'),
-- Settings
('chain_manager', 'settings.manage'),
('chain_manager', 'settings.view'),
('chain_manager', 'settings.hardware'),
('chain_manager', 'settings.sync'),
('chain_manager', 'settings.backup'),
-- Thawani
('chain_manager', 'thawani.menu'),
-- Accounting
('chain_manager', 'accounting.connect'),
('chain_manager', 'accounting.configure'),
('chain_manager', 'accounting.export'),
('chain_manager', 'accounting.view_history');

-- ── Branch Manager ───────────────────────────────────────────────────
INSERT INTO _tpl_perms (tpl_slug, perm_name) VALUES
-- POS
('branch_manager', 'pos.sell'),
('branch_manager', 'pos.shift_open'),
('branch_manager', 'pos.shift_close'),
('branch_manager', 'pos.return'),
('branch_manager', 'pos.tax_exempt'),
('branch_manager', 'pos.approve_discount'),
('branch_manager', 'pos.void_transaction'),
-- Orders
('branch_manager', 'orders.view'),
('branch_manager', 'orders.manage'),
('branch_manager', 'orders.return'),
('branch_manager', 'orders.void'),
-- Products
('branch_manager', 'products.view'),
('branch_manager', 'products.manage'),
-- Inventory
('branch_manager', 'inventory.view'),
('branch_manager', 'inventory.manage'),
('branch_manager', 'inventory.adjust'),
('branch_manager', 'inventory.transfer'),
('branch_manager', 'inventory.stocktake'),
-- Payments
('branch_manager', 'payments.process'),
('branch_manager', 'payments.refund'),
('branch_manager', 'cash.manage'),
-- Finance
('branch_manager', 'finance.commissions'),
-- Reports
('branch_manager', 'reports.view'),
('branch_manager', 'reports.attendance'),
-- Staff
('branch_manager', 'staff.view'),
('branch_manager', 'staff.create'),
('branch_manager', 'staff.edit'),
('branch_manager', 'staff.manage_shifts'),
('branch_manager', 'staff.manage_pin'),
('branch_manager', 'staff.training_mode'),
-- Roles
('branch_manager', 'roles.view'),
-- Customers
('branch_manager', 'customers.view'),
('branch_manager', 'customers.manage'),
-- Promotions
('branch_manager', 'promotions.manage'),
('branch_manager', 'promotions.apply_manual'),
-- Labels
('branch_manager', 'labels.view'),
('branch_manager', 'labels.manage'),
('branch_manager', 'labels.print'),
-- Delivery
('branch_manager', 'delivery.manage'),
-- Settings
('branch_manager', 'settings.manage'),
('branch_manager', 'settings.view'),
('branch_manager', 'settings.hardware'),
('branch_manager', 'settings.sync'),
('branch_manager', 'settings.backup'),
-- Thawani
('branch_manager', 'thawani.menu'),
-- Accounting
('branch_manager', 'accounting.export'),
('branch_manager', 'accounting.view_history');

-- ── Cashier ──────────────────────────────────────────────────────────
INSERT INTO _tpl_perms (tpl_slug, perm_name) VALUES
('cashier', 'pos.sell'),
('cashier', 'pos.shift_open'),
('cashier', 'pos.shift_close'),
('cashier', 'orders.view'),
('cashier', 'orders.manage'),
('cashier', 'products.view'),
('cashier', 'inventory.view'),
('cashier', 'payments.process'),
('cashier', 'cash.manage'),
('cashier', 'customers.view'),
('cashier', 'promotions.apply_manual'),
('cashier', 'labels.view'),
('cashier', 'labels.print');

-- ── Inventory Clerk ──────────────────────────────────────────────────
INSERT INTO _tpl_perms (tpl_slug, perm_name) VALUES
('inventory_clerk', 'products.view'),
('inventory_clerk', 'products.manage'),
('inventory_clerk', 'inventory.view'),
('inventory_clerk', 'inventory.manage'),
('inventory_clerk', 'inventory.adjust'),
('inventory_clerk', 'inventory.stocktake'),
('inventory_clerk', 'labels.view'),
('inventory_clerk', 'labels.print'),
('inventory_clerk', 'reports.view');

-- ── Accountant ───────────────────────────────────────────────────────
INSERT INTO _tpl_perms (tpl_slug, perm_name) VALUES
('accountant', 'reports.view'),
('accountant', 'reports.view_margin'),
('accountant', 'reports.view_financial'),
('accountant', 'finance.commissions'),
('accountant', 'finance.settlements'),
('accountant', 'accounting.connect'),
('accountant', 'accounting.configure'),
('accountant', 'accounting.export'),
('accountant', 'accounting.view_history'),
('accountant', 'settings.view');

-- ── Kitchen Staff ────────────────────────────────────────────────────
INSERT INTO _tpl_perms (tpl_slug, perm_name) VALUES
('kitchen_staff', 'orders.view'),
('kitchen_staff', 'restaurant.kds'),
('kitchen_staff', 'restaurant.tables');

-- ── Viewer (read-only) ───────────────────────────────────────────────
INSERT INTO _tpl_perms (tpl_slug, perm_name) VALUES
('viewer', 'orders.view'),
('viewer', 'products.view'),
('viewer', 'inventory.view'),
('viewer', 'reports.view'),
('viewer', 'customers.view'),
('viewer', 'labels.view');


-- ── Materialize temp table into default_role_template_permissions ────
INSERT INTO default_role_template_permissions (default_role_template_id, provider_permission_id)
SELECT t.id, p.id
FROM _tpl_perms tp
JOIN default_role_templates t ON t.slug = tp.tpl_slug
JOIN provider_permissions   p ON p.name = tp.perm_name
ON CONFLICT (default_role_template_id, provider_permission_id) DO NOTHING;

DROP TABLE _tpl_perms;


-- =====================================================================
-- §7  SPATIE RUNTIME: permissions TABLE
-- =====================================================================
-- Table: permissions (id BIGSERIAL PK, name UNIQUE, display_name, module,
--        guard_name DEFAULT 'staff', requires_pin)
--
-- WHY TWO TABLES?
--   • provider_permissions = platform-admin registry (UUID PK, descriptions,
--     Arabic translations, grouping, is_active). Used by the Filament admin
--     panel to manage what permissions exist system-wide.
--   • permissions = Spatie Laravel Permission runtime table (BIGSERIAL PK,
--     guard_name, requires_pin). Used by HasPermissions / HasRoles traits
--     at runtime in the POS app.
--
-- Both tables share the same `name` values. This table is seeded once
-- globally; it is NOT per-store.
--
-- The roles / role_has_permissions / model_has_roles tables are populated
-- dynamically when a store is created:
--   1. default_role_templates → cloned into `roles` for that store_id
--   2. default_role_template_permissions → mapped into `role_has_permissions`
--   3. model_has_roles → populated when staff are assigned to roles
-- =====================================================================

INSERT INTO permissions (name, display_name, module, guard_name, requires_pin) VALUES

-- ── POS Terminal (7) ─────────────────────────────────────────────────
('pos.sell',               'Process Sales',                       'pos',         'staff', FALSE),
('pos.shift_open',         'Open Shift',                          'pos',         'staff', FALSE),
('pos.shift_close',        'Close Shift',                         'pos',         'staff', FALSE),
('pos.return',             'Process Returns',                     'pos',         'staff', FALSE),
('pos.tax_exempt',         'Tax-Exempt Sales',                    'pos',         'staff', TRUE),
('pos.approve_discount',   'Approve Discount',                    'pos',         'staff', TRUE),
('pos.void_transaction',   'Void Transaction',                    'pos',         'staff', TRUE),

-- ── Orders (4) ───────────────────────────────────────────────────────
('orders.view',            'View Orders',                         'orders',      'staff', FALSE),
('orders.manage',          'Manage Orders',                       'orders',      'staff', FALSE),
('orders.return',          'Process Returns & Exchanges',         'orders',      'staff', FALSE),
('orders.void',            'Void Orders',                         'orders',      'staff', TRUE),

-- ── Products (2) ─────────────────────────────────────────────────────
('products.view',          'View Products',                       'products',    'staff', FALSE),
('products.manage',        'Manage Products',                     'products',    'staff', FALSE),

-- ── Inventory (5) ────────────────────────────────────────────────────
('inventory.view',         'View Inventory',                      'inventory',   'staff', FALSE),
('inventory.manage',       'Manage Inventory',                    'inventory',   'staff', FALSE),
('inventory.adjust',       'Adjust Stock',                        'inventory',   'staff', FALSE),
('inventory.transfer',     'Transfer Stock',                      'inventory',   'staff', FALSE),
('inventory.stocktake',    'Stocktake',                           'inventory',   'staff', FALSE),

-- ── Payments (2) ─────────────────────────────────────────────────────
('payments.process',       'Process Payments',                    'payments',    'staff', FALSE),
('payments.refund',        'Process Refunds',                     'payments',    'staff', TRUE),

-- ── Cash Management (1) ──────────────────────────────────────────────
('cash.manage',            'Manage Cash Drawer',                  'cash',        'staff', FALSE),

-- ── Finance (2) ──────────────────────────────────────────────────────
('finance.commissions',    'View Commissions',                    'finance',     'staff', FALSE),
('finance.settlements',    'View Settlements',                    'finance',     'staff', FALSE),

-- ── Reports (4) ──────────────────────────────────────────────────────
('reports.view',           'View Reports',                        'reports',     'staff', FALSE),
('reports.view_margin',    'View Margins',                        'reports',     'staff', FALSE),
('reports.view_financial', 'View Financial Reports',              'reports',     'staff', FALSE),
('reports.attendance',     'View Attendance',                     'reports',     'staff', FALSE),

-- ── Staff (7) ────────────────────────────────────────────────────────
('staff.view',             'View Staff',                          'staff',       'staff', FALSE),
('staff.create',           'Create Staff',                        'staff',       'staff', FALSE),
('staff.edit',             'Edit Staff',                          'staff',       'staff', FALSE),
('staff.delete',           'Delete Staff',                        'staff',       'staff', FALSE),
('staff.manage_shifts',    'Manage Shifts',                       'staff',       'staff', FALSE),
('staff.manage_pin',       'Manage PINs',                         'staff',       'staff', FALSE),
('staff.training_mode',    'Training Mode',                       'staff',       'staff', FALSE),

-- ── Roles (5) ────────────────────────────────────────────────────────
('roles.view',             'View Roles',                          'roles',       'staff', FALSE),
('roles.create',           'Create Roles',                        'roles',       'staff', FALSE),
('roles.edit',             'Edit Roles',                          'roles',       'staff', FALSE),
('roles.delete',           'Delete Roles',                        'roles',       'staff', FALSE),
('roles.audit',            'Audit Roles',                         'roles',       'staff', FALSE),

-- ── Customers (2) ────────────────────────────────────────────────────
('customers.view',         'View Customers',                      'customers',   'staff', FALSE),
('customers.manage',       'Manage Customers',                    'customers',   'staff', FALSE),

-- ── Promotions (2) ───────────────────────────────────────────────────
('promotions.manage',      'Manage Promotions',                   'promotions',  'staff', FALSE),
('promotions.apply_manual','Apply Promotions Manually',           'promotions',  'staff', FALSE),

-- ── Labels (3) ───────────────────────────────────────────────────────
('labels.view',            'View Labels',                         'labels',      'staff', FALSE),
('labels.manage',          'Manage Labels',                       'labels',      'staff', FALSE),
('labels.print',           'Print Labels',                        'labels',      'staff', FALSE),

-- ── Delivery (1) ─────────────────────────────────────────────────────
('delivery.manage',        'Manage Delivery',                     'delivery',    'staff', FALSE),

-- ── Settings (7) ─────────────────────────────────────────────────────
('settings.manage',        'Manage Settings',                     'settings',    'staff', FALSE),
('settings.view',          'View Settings',                       'settings',    'staff', FALSE),
('settings.hardware',      'Configure Hardware',                  'settings',    'staff', FALSE),
('settings.sync',          'Manage Sync',                         'settings',    'staff', FALSE),
('settings.thawani',       'Configure Thawani',                   'settings',    'staff', FALSE),
('settings.backup',        'Manage Backups',                      'settings',    'staff', FALSE),
('settings.updates',       'Manage Updates',                      'settings',    'staff', FALSE),

-- ── Thawani Marketplace (1) ──────────────────────────────────────────
('thawani.menu',           'Manage Thawani Menu',                 'thawani',     'staff', FALSE),

-- ── Accounting (4) ───────────────────────────────────────────────────
('accounting.connect',     'Connect Accounting',                  'accounting',  'staff', FALSE),
('accounting.configure',   'Configure Accounting',                'accounting',  'staff', FALSE),
('accounting.export',      'Export to Accounting',                'accounting',  'staff', FALSE),
('accounting.view_history','View Export History',                  'accounting',  'staff', FALSE),

-- ── Pharmacy (2) ─────────────────────────────────────────────────────
('pharmacy.prescriptions',         'Manage Prescriptions',        'pharmacy',    'staff', FALSE),
('pharmacy.controlled_substances', 'Sell Controlled Substances',  'pharmacy',    'staff', TRUE),

-- ── Jewelry (2) ──────────────────────────────────────────────────────
('jewelry.manage_rates',   'Manage Metal Rates',                  'jewelry',     'staff', FALSE),
('jewelry.buyback',        'Process Buyback',                     'jewelry',     'staff', FALSE),

-- ── Mobile/Electronics (3) ───────────────────────────────────────────
('mobile.repairs',         'Manage Repairs',                      'mobile',      'staff', FALSE),
('mobile.trade_in',        'Process Trade-Ins',                   'mobile',      'staff', FALSE),
('mobile.imei',            'Track IMEI',                          'mobile',      'staff', FALSE),

-- ── Florist (2) ──────────────────────────────────────────────────────
('flowers.arrangements',   'Manage Arrangements',                 'flowers',     'staff', FALSE),
('flowers.subscriptions',  'Manage Subscriptions',                'flowers',     'staff', FALSE),

-- ── Bakery (3) ───────────────────────────────────────────────────────
('bakery.production',      'Manage Production',                   'bakery',      'staff', FALSE),
('bakery.recipes',         'Manage Recipes',                      'bakery',      'staff', FALSE),
('bakery.custom_orders',   'Manage Custom Orders',                'bakery',      'staff', FALSE),

-- ── Restaurant (5) ───────────────────────────────────────────────────
('restaurant.tables',      'Manage Tables',                       'restaurant',  'staff', FALSE),
('restaurant.kds',         'Kitchen Display',                     'restaurant',  'staff', FALSE),
('restaurant.reservations','Manage Reservations',                  'restaurant',  'staff', FALSE),
('restaurant.tabs',        'Manage Tabs',                         'restaurant',  'staff', FALSE),
('restaurant.split_bill',  'Split Bills',                         'restaurant',  'staff', FALSE)

ON CONFLICT (name) DO NOTHING;

-- NOTE: `role_has_permissions` is NOT seeded here.
-- It is populated dynamically when a store is created:
--   StoreCreationService reads `default_role_templates` and
--   `default_role_template_permissions`, then:
--   1. INSERT INTO roles (...) — one row per template, scoped to store_id
--   2. INSERT INTO role_has_permissions (...) — maps each new role.id
--      to the matching permissions.id from this table
--   3. The owner user gets model_has_roles pointing to the 'owner' role
--
-- Custom roles created later by store owners also write to
-- role_has_permissions using the same permissions.id values.


-- =====================================================================
-- §8  VERIFICATION QUERIES (run after seeding to validate)
-- =====================================================================

-- Count platform permissions by group
-- SELECT "group", COUNT(*) FROM admin_permissions GROUP BY "group" ORDER BY "group";

-- Count provider permissions by group
-- SELECT "group", COUNT(*) FROM provider_permissions GROUP BY "group" ORDER BY "group";

-- Verify platform role assignments
-- SELECT r.slug, COUNT(rp.admin_permission_id) AS perm_count
-- FROM admin_roles r
-- LEFT JOIN admin_role_permissions rp ON rp.admin_role_id = r.id
-- GROUP BY r.slug ORDER BY perm_count DESC;

-- Verify provider template assignments
-- SELECT t.slug, COUNT(tp.provider_permission_id) AS perm_count
-- FROM default_role_templates t
-- LEFT JOIN default_role_template_permissions tp ON tp.default_role_template_id = t.id
-- GROUP BY t.slug ORDER BY perm_count DESC;

-- Expected counts:
-- ┌──────────────────────┬───────────┬─────────────┐
-- │ Scope                │ Item      │ Count       │
-- ├──────────────────────┼───────────┼─────────────┤
-- │ Platform Permissions │ Total     │ 59          │
-- │ Platform Roles       │ Total     │ 7           │
-- │ Provider Permissions │ Total     │ 76          │
-- │ Role Templates       │ Total     │ 8           │
-- │ Spatie Permissions   │ Total     │ 76          │
-- │ Spatie Roles         │ Total     │ 0 (dynamic) │
-- │ role_has_permissions │ Total     │ 0 (dynamic) │
-- ├──────────────────────┼───────────┼─────────────┤
-- │ super_admin          │ Perms     │ 59 (all)    │
-- │ platform_manager     │ Perms     │ 33          │
-- │ finance_admin        │ Perms     │ 9           │
-- │ support_agent        │ Perms     │ 8           │
-- │ sales                │ Perms     │ 7           │
-- │ integration_manager  │ Perms     │ 6           │
-- │ viewer (platform)    │ Perms     │ 5           │
-- ├──────────────────────┼───────────┼─────────────┤
-- │ owner                │ Perms     │ 76 (all)    │
-- │ chain_manager        │ Perms     │ 57          │
-- │ branch_manager       │ Perms     │ 49          │
-- │ cashier              │ Perms     │ 13          │
-- │ inventory_clerk      │ Perms     │ 9           │
-- │ accountant           │ Perms     │ 10          │
-- │ kitchen_staff        │ Perms     │ 3           │
-- │ viewer (provider)    │ Perms     │ 6           │
-- └──────────────────────┴───────────┴─────────────┘

COMMIT;
