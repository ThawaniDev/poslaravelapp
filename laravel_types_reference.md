# Wameed POS — Laravel Migration Types Reference

> Quick-reference for converting `database_schema.sql` into Laravel 11 migrations.
> All 255 tables · 2,378 columns · PostgreSQL 15+ driver.

---

## 1. Column Type Mapping

| SQL Type | Laravel Migration Method | Count | Example |
|---|---|---|---|
| `UUID PRIMARY KEY DEFAULT gen_random_uuid()` | `$table->uuid('id')->primary()` | 238 | Every main table |
| `BIGSERIAL PRIMARY KEY` | `$table->id()` | 3 | `failed_jobs`, `roles`, `permissions` |
| `UUID` (FK) | `$table->foreignUuid('column')->constrained('table')` | 639 | `stores.organization_id` |
| `UUID` (nullable FK) | `$table->foreignUuid('column')->nullable()->constrained('table')` | — | `goods_receipts.supplier_id` |
| `VARCHAR(N)` | `$table->string('column', N)` | 537 | `organizations.name` |
| `TEXT` | `$table->text('column')` | 157 | `organizations.address` |
| `INT` | `$table->integer('column')` | 167 | `feature_flags.rollout_percentage` |
| `BIGINT` | `$table->bigInteger('column')` | 8 | `database_backups.file_size_bytes` |
| `SMALLINT` | `$table->smallInteger('column')` | 0 | (available if needed) |
| `BOOLEAN` | `$table->boolean('column')` | 152 | `organizations.is_active` |
| `DECIMAL(P,S)` | `$table->decimal('column', P, S)` | 192 | `subscription_plans.monthly_price` |
| `DATE` | `$table->date('column')` | 38 | `invoices.due_date` |
| `TIME` | `$table->time('column')` | — | `business_type_shift_templates.start_time` |
| `TIMESTAMP` | `$table->timestamp('column')` | 365 | `created_at`, `updated_at` |
| `JSONB` | `$table->jsonb('column')` | 74 | `admin_activity_logs.details` |

---

## 2. DECIMAL Variants Used

| SQL Definition | Laravel | Usage |
|---|---|---|
| `DECIMAL(3,2)` | `$table->decimal('col', 3, 2)` | Font scale |
| `DECIMAL(5,2)` | `$table->decimal('col', 5, 2)` | Percentages (discount %, commission %) |
| `DECIMAL(6,2)` | `$table->decimal('col', 6, 2)` | Label dimensions (mm) |
| `DECIMAL(8,3)` | `$table->decimal('col', 8, 3)` | Tare weight |
| `DECIMAL(8,4)` | `$table->decimal('col', 8, 4)` | Loyalty rates (SAR per point) |
| `DECIMAL(10,2)` | `$table->decimal('col', 10, 2)` | Plan prices, add-on prices |
| `DECIMAL(10,3)` | `$table->decimal('col', 10, 3)` | Jewelry weights |
| `DECIMAL(10,7)` | `$table->decimal('col', 10, 7)` | GPS coordinates (lat/lng) |
| `DECIMAL(12,2)` | `$table->decimal('col', 12, 2)` | **Most common** — monetary amounts (SAR) |
| `DECIMAL(12,3)` | `$table->decimal('col', 12, 3)` | Order totals, commission amounts (3-decimal for halala) |
| `DECIMAL(12,4)` | `$table->decimal('col', 12, 4)` | Unit costs with extra precision |
| `DECIMAL(14,2)` | `$table->decimal('col', 14, 2)` | Large aggregates (total spend, PO totals) |

---

## 3. VARCHAR Sizes Used

| Size | Usage | Laravel |
|---|---|---|
| `VARCHAR(3)` | RTL/LTR direction | `$table->string('col', 3)` |
| `VARCHAR(4)` | Card last four | `$table->string('col', 4)` |
| `VARCHAR(5)` | Country code, locale | `$table->string('col', 5)` |
| `VARCHAR(7)` | Hex colours (#FFFFFF) | `$table->string('col', 7)` |
| `VARCHAR(10)` | Currency, locale, short codes | `$table->string('col', 10)` |
| `VARCHAR(15)` | Short enum-like statuses | `$table->string('col', 15)` |
| `VARCHAR(20)` | Status fields, phone numbers, VAT numbers | `$table->string('col', 20)` |
| `VARCHAR(30)` | Method keys, category keys, coupon codes | `$table->string('col', 30)` |
| `VARCHAR(45)` | IP addresses (IPv4 + IPv6) | `$table->string('col', 45)` |
| `VARCHAR(48)` | API keys | `$table->string('col', 48)` |
| `VARCHAR(50)` | General fields, timezone, CR number | `$table->string('col', 50)` |
| `VARCHAR(64)` | Hashes, tokens, fingerprints | `$table->string('col', 64)` |
| `VARCHAR(100)` | Slugs, cities, reference numbers | `$table->string('col', 100)` |
| `VARCHAR(125)` | Spatie role/permission names | `$table->string('col', 125)` |
| `VARCHAR(200)` | Translation keys, footer text | `$table->string('col', 200)` |
| `VARCHAR(255)` | Names, emails, descriptions (**Laravel default**) | `$table->string('col')` |
| `VARCHAR(500)` | Long URLs (photo, document) | `$table->string('col', 500)` |

> **Tip:** `$table->string('col')` defaults to `VARCHAR(255)` — no size needed for 255.

---

## 4. Primary Key Patterns

### UUID Primary Key (238 tables)
```php
// In migration
$table->uuid('id')->primary();

// In model
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Store extends Model
{
    use HasUuids;
    
    public $incrementing = false;
    protected $keyType = 'string';
}
```

### Auto-increment Primary Key (3 tables)
```php
// failed_jobs, roles, permissions (Spatie)
$table->id(); // BIGSERIAL → bigIncrements
```

### Composite Primary Key (13 pivot tables)
```php
// Example: admin_role_permissions
$table->foreignUuid('admin_role_id')->constrained('admin_roles')->cascadeOnDelete();
$table->foreignUuid('admin_permission_id')->constrained('admin_permissions')->cascadeOnDelete();
$table->primary(['admin_role_id', 'admin_permission_id']);
```

---

## 5. Laravel Traits & Conventions

### Timestamps — `$table->timestamps()`
**71 tables** have both `created_at` + `updated_at` → use `$table->timestamps()` and `$timestamps = true` (default).

### Created-at Only — `$table->timestamp('created_at')`
**81 tables** have only `created_at` (log/event tables, pivot tables with dates):
```php
// In migration
$table->timestamp('created_at')->useCurrent();

// In model
public $timestamps = false;
// OR
const UPDATED_AT = null;
```

### Soft Deletes — `$table->softDeletes()`
**3 tables** use `deleted_at`: `products`, `customers`, `transactions`.
```php
// In migration
$table->softDeletes();

// In model
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;
}
```

---

## 6. Foreign Key Patterns

### Standard FK (constrained, ON DELETE CASCADE)
```php
// 101 foreign keys use ON DELETE CASCADE
$table->foreignUuid('store_id')->constrained()->cascadeOnDelete();
```

### Nullable FK (optional relationship)
```php
$table->foreignUuid('supplier_id')->nullable()->constrained()->nullOnDelete();
```

### FK to Non-Default Table
```php
// When column name doesn't match table_name + _id convention
$table->foreignUuid('performed_by')->constrained('users');
$table->foreignUuid('approved_by')->nullable()->constrained('users');
$table->foreignUuid('staff_user_id')->constrained('staff_users')->cascadeOnDelete();
```

### Deferred FK (13 cases — tables defined later)
```php
// In the original migration, just define the column:
$table->uuid('order_id');

// In a separate migration (or at the end):
Schema::table('commission_earnings', function (Blueprint $table) {
    $table->foreign('order_id')->references('id')->on('orders');
});
```

> **Laravel tip:** If you order your migrations by dependency (core tables first), you won't need deferred FKs. The deferred constraints in `database_schema.sql` were for SQL execution order only.

### SET NULL on Delete (1 case)
```php
$table->foreignUuid('parent_id')->nullable()->constrained('categories')->nullOnDelete();
```

---

## 7. Index Patterns

### Single Column Index (45 indexes total)
```php
$table->index('organization_id');           // CREATE INDEX idx_...
$table->index(['is_active', 'category']);   // Composite index
```

### Unique Index / Constraint
```php
// Inline unique (75 columns)
$table->string('slug', 100)->unique();
$table->string('email', 255)->unique();

// Multi-column unique (31 constraints)
$table->unique(['store_id', 'sku']);
$table->unique(['promotion_id', 'category_id']);
$table->unique(['delivery_platform_id', 'field_key']);
```

### Unique Index on Materialized View Equivalent
```php
// In Laravel these would be query-cached aggregates or database views
// No migration needed — use Eloquent query scopes or DB::select()
```

---

## 8. Default Value Patterns

| SQL Pattern | Laravel | Count |
|---|---|---|
| `DEFAULT gen_random_uuid()` | Handled by `HasUuids` trait | 238 |
| `DEFAULT NOW()` | `->useCurrent()` | 272 |
| `DEFAULT FALSE` | `->default(false)` | 70 |
| `DEFAULT TRUE` | `->default(true)` | 80 |
| `DEFAULT 0` | `->default(0)` | 135 |
| `DEFAULT '[]'` (JSONB) | `->default('[]')` | 33 |
| `DEFAULT '{}'` (JSONB) | `->default('{}')` | — |
| `DEFAULT 'value'` (string) | `->default('value')` | varies |

```php
// Full example
$table->boolean('is_active')->default(true);
$table->integer('sort_order')->default(0);
$table->decimal('discount_percent', 5, 2)->default(0);
$table->jsonb('metadata')->default('{}');
$table->timestamp('created_at')->useCurrent();
$table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
```

---

## 9. JSONB Column Patterns (74 columns)

Used for flexible/dynamic data throughout the schema:

```php
// Migration
$table->jsonb('details')->default('{}');
$table->jsonb('target_plan_ids')->default('[]');
$table->jsonb('config')->default('{}');
$table->jsonb('excluded_category_ids')->default('[]');

// Model — cast to array automatically
protected $casts = [
    'details'            => 'array',
    'target_plan_ids'    => 'array',
    'config'             => 'array',
    'metadata'           => AsCollection::class,  // or 'collection'
];
```

Common JSONB use cases in the schema:
- `system_settings.value` — dynamic configuration values
- `admin_activity_logs.details` — audit log metadata
- `feature_flags.target_plan_ids` — array of plan UUIDs
- `pos_customization_settings.custom_colors` — UI theme overrides
- `business_type_industry_configs.required_product_fields` — dynamic field lists
- `security_policies.config` — security rule config
- `notification_preferences.preferences` — per-user channel settings

---

## 10. Pivot Table Pattern (12 tables)

All pivot tables follow the same pattern:

```php
// Migration (no id, no timestamps)
Schema::create('theme_package_visibility', function (Blueprint $table) {
    $table->foreignUuid('theme_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('subscription_plan_id')->constrained()->cascadeOnDelete();
    $table->primary(['theme_id', 'subscription_plan_id']);
});

// Relationship in Model
public function subscriptionPlans(): BelongsToMany
{
    return $this->belongsToMany(SubscriptionPlan::class, 'theme_package_visibility');
}
```

**Pivot tables in the schema:**
| Pivot Table | Table A | Table B |
|---|---|---|
| `admin_role_permissions` | `admin_roles` | `admin_permissions` |
| `admin_user_roles` | `admin_users` | `admin_roles` |
| `theme_package_visibility` | `themes` | `subscription_plans` |
| `layout_package_visibility` | `pos_layout_templates` | `subscription_plans` |
| `receipt_template_package_visibility` | `receipt_layout_templates` | `subscription_plans` |
| `cfd_theme_package_visibility` | `cfd_themes` | `subscription_plans` |
| `signage_template_business_types` | `signage_templates` | `business_types` |
| `signage_template_package_visibility` | `signage_templates` | `subscription_plans` |
| `label_template_business_types` | `label_layout_templates` | `business_types` |
| `label_template_package_visibility` | `label_layout_templates` | `subscription_plans` |
| `store_add_ons` | `stores` | `plan_add_ons` |
| `role_has_permissions` | `roles` | `permissions` |

---

## 11. Spatie Permission Tables (3 tables)

The `roles`, `permissions`, and `role_has_permissions` tables come from **spatie/laravel-permission**:

```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

These tables use `BIGSERIAL` (auto-increment) — **not UUID** — because Spatie defaults to incrementing IDs. The `model_has_roles` table uses a polymorphic `model_type` + `model_id` pattern.

```php
// In staff_users model (provider side)
use Spatie\Permission\Traits\HasRoles;

class StaffUser extends Model
{
    use HasRoles;
    protected $guard_name = 'provider';
}
```

---

## 12. Seed Data (INSERT statements in schema)

The schema includes seed data for these tables — create Laravel seeders:

```php
// database/seeders/
SupportedLocalesSeeder::class     // ar, en
PaymentMethodsSeeder::class       // cash, card_mada, card_visa, etc.
PlatformUiDefaultsSeeder::class   // handedness, font_size, theme
```

---

## 13. PostgreSQL Extensions Required

```php
// In a migration (run first, before all table migrations)
public function up(): void
{
    DB::statement('CREATE EXTENSION IF NOT EXISTS "pgcrypto"');     // gen_random_uuid()
    DB::statement('CREATE EXTENSION IF NOT EXISTS "pg_trgm"');      // Full-text search
    DB::statement('CREATE EXTENSION IF NOT EXISTS "btree_gin"');    // Composite GIN indexes
}
```

---

## 14. Migration File Naming Convention

Laravel runs migrations in alphabetical (date-prefixed) order. Group them by dependency:

```
database/migrations/
├── 0001_01_01_000000_create_extensions.php
├── 0001_01_01_000001_create_failed_jobs_table.php           (Laravel default)
│
├── 2026_01_01_000100_create_organizations_table.php         (Core)
├── 2026_01_01_000101_create_stores_table.php
├── 2026_01_01_000102_create_users_table.php
├── 2026_01_01_000103_create_registers_table.php
│
├── 2026_01_01_000200_create_admin_users_table.php           (Platform)
├── 2026_01_01_000201_create_admin_roles_table.php
├── ...
│
├── 2026_01_01_000300_create_system_settings_table.php       (Config)
├── ...
│
├── 2026_01_01_001000_create_store_subscriptions_table.php   (Provider)
├── ...
│
├── 2026_01_01_009000_create_deferred_foreign_keys.php       (Deferred FKs)
└── 2026_01_01_009999_seed_default_data.php
```

---

## 15. Sample Full Migration

Here's a representative migration showing all patterns combined:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            // PK
            $table->uuid('id')->primary();

            // Foreign keys
            $table->foreignUuid('store_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers');

            // Strings
            $table->string('name_ar');                    // VARCHAR(255) default
            $table->string('name_en');
            $table->string('sku', 50)->nullable();
            $table->string('barcode', 100)->nullable();
            $table->string('unit_type', 20)->default('piece');
            $table->string('tax_category', 20)->default('standard');

            // Text
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->text('image_url')->nullable();

            // Numerics
            $table->decimal('price', 12, 3)->default(0);
            $table->decimal('cost_price', 12, 3)->default(0);
            $table->decimal('tare_weight', 8, 3)->default(0);
            $table->integer('sort_order')->default(0);
            $table->integer('low_stock_threshold')->default(5);

            // Booleans
            $table->boolean('is_active')->default(true);
            $table->boolean('is_weighable')->default(false);
            $table->boolean('track_inventory')->default(true);
            $table->boolean('age_restricted')->default(false);

            // JSON
            $table->jsonb('variant_options')->default('[]');
            $table->jsonb('modifier_groups')->default('[]');

            // Timestamps & Soft Delete
            $table->timestamps();                         // created_at + updated_at
            $table->softDeletes();                        // deleted_at
        });

        // Indexes
        $table->index('store_id');
        $table->index(['store_id', 'is_active']);
        $table->unique(['store_id', 'sku']);
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
```

---

## 16. Sample Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'store_id', 'category_id', 'supplier_id',
        'name_ar', 'name_en', 'sku', 'barcode',
        'unit_type', 'tax_category',
        'description_ar', 'description_en', 'image_url',
        'price', 'cost_price', 'tare_weight',
        'sort_order', 'low_stock_threshold',
        'is_active', 'is_weighable', 'track_inventory', 'age_restricted',
        'variant_options', 'modifier_groups',
    ];

    protected $casts = [
        'price'            => 'decimal:3',
        'cost_price'       => 'decimal:3',
        'tare_weight'      => 'decimal:3',
        'is_active'        => 'boolean',
        'is_weighable'     => 'boolean',
        'track_inventory'  => 'boolean',
        'age_restricted'   => 'boolean',
        'variant_options'  => 'array',
        'modifier_groups'  => 'array',
    ];

    // ── Relationships ──────────────────────────────

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
```

---

## 17. Quick Cheat Sheet

```
SQL                              → Laravel
─────────────────────────────────────────────────────
UUID PK DEFAULT gen_random_uuid  → $table->uuid('id')->primary()  +  HasUuids trait
BIGSERIAL PK                     → $table->id()
VARCHAR(255)                     → $table->string('col')
VARCHAR(N)                       → $table->string('col', N)
TEXT                             → $table->text('col')
INT                              → $table->integer('col')
BIGINT                           → $table->bigInteger('col')
BOOLEAN                          → $table->boolean('col')
DECIMAL(P,S)                     → $table->decimal('col', P, S)
DATE                             → $table->date('col')
TIME                             → $table->time('col')
TIMESTAMP                        → $table->timestamp('col')
JSONB                            → $table->jsonb('col')
created_at + updated_at          → $table->timestamps()
deleted_at                       → $table->softDeletes()
NOT NULL                         → (default) — use ->nullable() for NULL
DEFAULT X                        → ->default(X)
DEFAULT NOW()                    → ->useCurrent()
UNIQUE                           → ->unique()
INDEX                            → ->index()
REFERENCES t(id)                 → ->constrained('t')
ON DELETE CASCADE                → ->cascadeOnDelete()
ON DELETE SET NULL               → ->nullOnDelete()
PRIMARY KEY (a, b)               → $table->primary(['a', 'b'])
UNIQUE (a, b)                    → $table->unique(['a', 'b'])
```

---

## 18. Status / Type Fields — Complete PHP Enum Reference (213 fields)

All status/type fields use `VARCHAR` in the database — validated via **PHP 8.1+ Backed Enums** in Laravel:

```php
// app/Enums/OrderStatus.php
enum OrderStatus: string
{
    case New = 'new';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Dispatched = 'dispatched';
    case Delivered = 'delivered';
    case PickedUp = 'picked_up';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Voided = 'voided';
}

// In Model
protected $casts = [
    'status' => OrderStatus::class,
];

// In FormRequest validation
'status' => ['required', new Illuminate\Validation\Rules\Enum(OrderStatus::class)],
```

---

### 18.1 Orders & Returns

| Table.Column | Values |
|---|---|
| `orders.source` | `pos`, `thawani`, `hungerstation`, `jahez`, `marsool`, `phone`, `web` |
| `orders.status` | `new`, `preparing`, `ready`, `dispatched`, `delivered`, `picked_up`, `completed`, `cancelled`, `voided` |
| `order_status_history.from_status` | *(same as orders.status)* |
| `order_status_history.to_status` | *(same as orders.status)* |
| `order_delivery_info.platform` | `hungerstation`, `jahez`, `marsool`, `internal`, `phone` |
| `returns.type` | `full`, `partial` |
| `returns.refund_method` | `original_method`, `cash`, `store_credit` |

### 18.2 Payments & Finance

| Table.Column | Values |
|---|---|
| `payments.method` | `cash`, `card_mada`, `card_visa`, `card_mastercard`, `store_credit`, `gift_card`, `mobile_payment` |
| `payment_methods.method_key` | `cash`, `card_mada`, `card_visa`, `card_mastercard`, `store_credit`, `gift_card`, `mobile_payment` |
| `payment_methods.category` | `cash`, `card`, `digital`, `credit` |
| `cash_sessions.status` | `open`, `closed` |
| `cash_events.type` | `cash_in`, `cash_out` |
| `expenses.category` | `supplies`, `food`, `transport`, `maintenance`, `utility`, `other` |
| `gift_cards.status` | `active`, `redeemed`, `expired`, `deactivated` |
| `gift_card_transactions.type` | `redemption`, `top_up`, `refund` |
| `refunds.method` | `cash`, `card_mada`, `card_visa`, `card_mastercard`, `store_credit`, `gift_card`, `mobile_payment` |
| `refunds.status` | `completed`, `pending`, `failed` |

### 18.3 POS Terminal & Transactions

| Table.Column | Values |
|---|---|
| `transactions.type` | `sale`, `return`, `void`, `exchange` |
| `transactions.status` | `completed`, `voided`, `pending` |
| `transactions.sync_status` | `pending`, `synced`, `failed` |
| `transactions.zatca_status` | `pending`, `submitted`, `accepted`, `rejected` |
| `transactions.external_type` | `thawani`, `hungerstation`, `keeta` |
| `transaction_items.discount_type` | `percentage`, `fixed` |
| `pos_sessions.status` | `open`, `closed` |
| `tax_exemptions.exemption_type` | `diplomatic`, `government`, `export`, `charity` |

### 18.4 Inventory

| Table.Column | Values |
|---|---|
| `stock_movements.type` | `receipt`, `sale`, `adjustment_in`, `adjustment_out`, `transfer_out`, `transfer_in`, `waste`, `recipe_deduction` |
| `stock_movements.reference_type` | `goods_receipt`, `adjustment`, `transfer`, `transaction`, `waste`, `stocktake` |
| `stock_adjustments.type` | `increase`, `decrease` |
| `stock_transfers.status` | `pending`, `in_transit`, `completed`, `cancelled` |
| `purchase_orders.status` | `draft`, `sent`, `partially_received`, `fully_received`, `cancelled` |
| `goods_receipts.status` | `draft`, `confirmed` |
| `products.unit` | `piece`, `kg`, `litre`, `custom` |

### 18.5 Products & Promotions

| Table.Column | Values |
|---|---|
| `promotions.type` | `percentage`, `fixed_amount`, `bogo`, `bundle`, `happy_hour` |
| `loyalty_transactions.type` | `earn`, `redeem`, `adjust`, `expire` |
| `loyalty_challenges.challenge_type` | `purchase_count`, `spend_amount`, `category_explore`, `streak` |
| `loyalty_challenges.reward_type` | `points`, `discount_coupon`, `free_item`, `badge` |
| `store_credit_transactions.type` | `refund_credit`, `top_up`, `spend`, `adjust` |
| `digital_receipt_log.channel` | `email`, `whatsapp` |
| `digital_receipt_log.status` | `sent`, `delivered`, `failed` |

### 18.6 Staff & User Management

| Table.Column | Values |
|---|---|
| `staff_users.status` | `active`, `inactive`, `on_leave` |
| `staff_users.employment_type` | `full_time`, `part_time`, `contractor` |
| `staff_users.salary_type` | `hourly`, `monthly`, `commission_only` |
| `shift_schedules.status` | `scheduled`, `completed`, `missed`, `swapped` |
| `attendance_records.auth_method` | `pin`, `nfc`, `biometric` |
| `commission_rules.type` | `flat_percentage`, `tiered`, `per_item` |
| `staff_documents.document_type` | `national_id`, `contract`, `certificate`, `visa` |
| `staff_activity_log.entity_type` | `order`, `product`, `customer` |
| `login_attempts.attempt_type` | `pin`, `password`, `biometric`, `two_factor` |
| `role_audit_log.action` | `role_created`, `role_updated`, `permission_granted`, `permission_revoked` |
| `users.role` | `owner`, `chain_manager`, `branch_manager`, `cashier`, `inventory_clerk`, `accountant`, `kitchen_staff` |

### 18.7 Notifications

| Table.Column | Values |
|---|---|
| `notification_templates.channel` | `in_app`, `push`, `sms`, `email`, `whatsapp` |
| `notification_preferences.channel` | `in_app`, `push`, `sms`, `email`, `whatsapp` |
| `notification_events_log.channel` | `in_app`, `push`, `email`, `sound` |
| `notification_events_log.status` | `sent`, `delivered`, `failed` |
| `notification_delivery_logs.channel` | `in_app`, `push`, `sms`, `email`, `whatsapp` |
| `notification_delivery_logs.status` | `pending`, `sent`, `delivered`, `failed` |
| `notification_provider_status.channel` | `sms`, `email` |
| `notification_provider_status.provider` | `unifonic`, `taqnyat`, `msegat`, `mailgun`, `ses`, `smtp` |
| `fcm_tokens.device_type` | `android`, `ios` |
| `platform_announcements.type` | `info`, `warning`, `maintenance`, `update` |
| `payment_reminders.reminder_type` | `upcoming`, `overdue` |
| `payment_reminders.channel` | `email`, `sms`, `push` |

### 18.8 Subscription & Billing

| Table.Column | Values |
|---|---|
| `store_subscriptions.status` | `trial`, `active`, `grace`, `cancelled`, `expired` |
| `store_subscriptions.billing_cycle` | `monthly`, `yearly` |
| `store_subscriptions.payment_method` | `credit_card`, `mada`, `bank_transfer` |
| `subscription_discounts.type` | `percentage`, `fixed` |
| `subscription_usage_snapshots.resource_type` | `products`, `staff`, `branches`, `transactions_month` |
| `invoices.status` | `draft`, `pending`, `paid`, `failed`, `refunded` |
| `implementation_fees.fee_type` | `setup`, `training`, `custom_dev` |
| `implementation_fees.status` | `invoiced`, `paid` |
| `payment_gateway_configs.environment` | `sandbox`, `production` |
| `payment_gateway_configs.gateway_name` | `thawani_pay`, `stripe`, `moyasar` |

### 18.9 Delivery Platforms

| Table.Column | Values |
|---|---|
| `delivery_platforms.auth_method` | `bearer`, `api_key`, `basic`, `oauth2` |
| `delivery_platform_fields.field_type` | `text`, `password`, `url` |
| `delivery_platform_endpoints.operation` | `product_create`, `product_update`, `product_delete`, `category_sync`, `bulk_menu_push` |
| `delivery_platform_endpoints.http_method` | `POST`, `PUT`, `DELETE`, `PATCH` |
| `delivery_platform_configs.platform` | `hungerstation`, `jahez`, `marsool` |
| `delivery_menu_sync_logs.status` | `success`, `partial`, `failed` |
| `delivery_order_mappings.external_status` | *(platform-specific, varies)* |
| `store_delivery_platforms.sync_status` | `ok`, `error`, `pending` |

### 18.10 POS Interface & Customization

| Table.Column | Values |
|---|---|
| `pos_customization_settings.theme` | `light`, `dark`, `custom` |
| `pos_customization_settings.handedness` | `left`, `right` |
| `pos_customization_settings.cart_display_mode` | `compact`, `detailed` |
| `pos_customization_settings.layout_direction` | `ltr`, `rtl`, `auto` |
| `user_preferences.pos_handedness` | `right`, `left`, `center` |
| `user_preferences.font_size` | `small`, `medium`, `large`, `extra-large` |
| `user_preferences.theme` | `light_classic`, `dark_mode`, `high_contrast`, `thawani_brand`, `custom` |
| `registers.platform` | `windows`, `macos`, `ios`, `android` |

### 18.11 Hardware

| Table.Column | Values |
|---|---|
| `certified_hardware.device_type` | `receipt_printer`, `barcode_scanner`, `weighing_scale`, `label_printer`, `cash_drawer`, `card_terminal`, `nfc_reader`, `customer_display` |
| `certified_hardware.driver_protocol` | `esc_pos`, `zpl`, `tspl`, `serial_scale`, `hid`, `nearpay_sdk`, `nfc_hid` |
| `hardware_configurations.device_type` | `receipt_printer`, `barcode_scanner`, `cash_drawer`, `customer_display`, `weighing_scale`, `label_printer`, `card_terminal`, `nfc_reader` |
| `hardware_configurations.connection_type` | `usb`, `network`, `bluetooth`, `serial` |
| `hardware_event_log.device_type` | *(same as hardware_configurations.device_type)* |
| `hardware_sales.item_type` | `terminal`, `printer`, `scanner`, `other` |

### 18.12 POS Layout Templates & Labels

| Table.Column | Values |
|---|---|
| `label_layout_templates.label_type` | `barcode`, `price`, `shelf`, `jewelry`, `pharmacy` |
| `label_layout_templates.barcode_type` | `CODE128`, `EAN13`, `EAN8`, `QR`, `Code39` |
| `label_layout_templates.default_font_size` | `small`, `medium`, `large` |
| `label_layout_templates.border_style` | `solid`, `dashed` |
| `label_layout_templates.font_family` | `system`, *(custom values)* |
| `signage_templates.template_type` | `menu_board`, `promo_slideshow`, `queue_display`, `info_board` |
| `cfd_themes.cart_layout` | `list`, `grid` |
| `cfd_themes.idle_layout` | `slideshow`, `static_image`, `video_loop` |
| `cfd_themes.animation_style` | `fade`, `slide`, `none` |
| `cfd_themes.thank_you_animation` | `confetti`, `check`, `none` |
| `cfd_themes.font_family` | `system`, *(custom values)* |
| `receipt_layout_templates.zatca_qr_position` | `header`, `footer` |
| `business_type_receipt_templates.zatca_qr_position` | `header`, `footer` |
| `business_type_receipt_templates.font_size` | `small`, `medium`, `large` |

### 18.13 Security & Audit

| Table.Column | Values |
|---|---|
| `security_alerts.alert_type` | `brute_force`, `bulk_export`, `unusual_ip`, `permission_escalation`, `after_hours_access` |
| `security_alerts.severity` | `low`, `medium`, `high`, `critical` |
| `security_alerts.status` | `new`, `investigating`, `resolved` |
| `security_audit_log.user_type` | `staff`, `owner`, `system` |
| `security_audit_log.action` | `login`, `logout`, `pin_override`, `failed_login`, `settings_change`, `remote_wipe` |
| `security_audit_log.resource_type` | `order`, `product`, `staff_user`, `settings` |
| `security_audit_log.severity` | `info`, `warning`, `critical` |

### 18.14 Support Tickets

| Table.Column | Values |
|---|---|
| `support_tickets.status` | `open`, `in_progress`, `resolved`, `closed` |
| `support_tickets.priority` | `low`, `medium`, `high`, `critical` |
| `support_tickets.category` | `billing`, `technical`, `zatca`, `feature_request`, `general` |
| `support_ticket_messages.sender_type` | `provider`, `admin` |
| `canned_responses.category` | *(same as support_tickets.category)* |
| `cancellation_reasons.reason_category` | `price`, `features`, `competitor`, `support`, `other` |
| `knowledge_base_articles.category` | `getting_started`, `pos_usage`, `inventory`, `delivery`, `billing`, `troubleshooting` |

### 18.15 ZATCA Compliance

| Table.Column | Values |
|---|---|
| `zatca_invoices.invoice_type` | `standard`, `simplified`, `credit_note`, `debit_note` |
| `zatca_invoices.submission_status` | `pending`, `submitted`, `accepted`, `rejected`, `warning` |
| `zatca_certificates.certificate_type` | `compliance`, `production` |
| `zatca_certificates.status` | `active`, `expired`, `revoked` |

### 18.16 Accounting & Sync

| Table.Column | Values |
|---|---|
| `accounting_exports.status` | `pending`, `processing`, `success`, `failed` |
| `accounting_exports.triggered_by` | `manual`, `scheduled` |
| `auto_export_configs.frequency` | `daily`, `weekly`, `monthly` |
| `accounting_integration_configs.provider_name` | `quickbooks`, `xero`, `qoyod` |
| `sync_conflicts.resolution` | `local_wins`, `cloud_wins`, `merged` |
| `sync_log.direction` | `push`, `pull`, `full` |
| `sync_log.status` | `success`, `partial`, `failed` |

### 18.17 Backup & Updates

| Table.Column | Values |
|---|---|
| `backup_history.backup_type` | `auto`, `manual`, `pre_update` |
| `backup_history.status` | `completed`, `failed`, `corrupted` |
| `database_backups.backup_type` | `auto_daily`, `auto_weekly`, `manual` |
| `database_backups.status` | `in_progress`, `completed`, `failed` |
| `provider_backup_status.status` | `healthy`, `warning`, `critical`, `unknown` |
| `app_releases.platform` | `windows`, `macos`, `ios`, `android` |
| `app_releases.channel` | `stable`, `beta`, `testflight`, `internal_test` |
| `app_releases.submission_status` | `not_applicable`, `submitted`, `in_review`, `approved`, `rejected`, `live` |
| `app_update_stats.status` | `pending`, `downloading`, `downloaded`, `installed`, `failed` |

### 18.18 Thawani Marketplace

| Table.Column | Values |
|---|---|
| `thawani_marketplace_config.connection_status` | `connected`, `failed`, `unknown` |
| `thawani_order_mappings.status` | `new`, `accepted`, `preparing`, `ready`, `dispatched`, `completed`, `rejected`, `cancelled` |
| `thawani_order_mappings.delivery_type` | `delivery`, `pickup` |

### 18.19 Industry-Specific Workflows

| Table.Column | Values |
|---|---|
| `drug_schedules.schedule_type` | `otc`, `prescription_only`, `controlled` |
| `jewelry_product_details.metal_type` | `gold`, `silver`, `platinum` |
| `jewelry_product_details.making_charges_type` | `flat`, `percentage`, `per_gram` |
| `daily_metal_rates.metal_type` | `gold`, `silver`, `platinum` |
| `buyback_transactions.metal_type` | `gold`, `silver`, `platinum` |
| `buyback_transactions.payment_method` | `cash`, `bank_transfer`, `credit_note` |
| `device_imei_records.status` | `in_stock`, `sold`, `traded_in`, `returned` |
| `device_imei_records.condition_grade` | `A`, `B`, `C`, `D` |
| `repair_jobs.status` | `received`, `diagnosing`, `repairing`, `testing`, `ready`, `collected`, `cancelled` |
| `flower_freshness_log.status` | `fresh`, `marked_down`, `disposed` |
| `flower_subscriptions.frequency` | `weekly`, `biweekly`, `monthly` |
| `custom_cake_orders.status` | `ordered`, `in_production`, `ready`, `delivered` |
| `production_schedules.status` | `planned`, `in_progress`, `completed` |
| `kitchen_tickets.status` | `pending`, `preparing`, `ready`, `served` |
| `restaurant_tables.status` | `available`, `occupied`, `reserved`, `cleaning` |
| `table_reservations.status` | `confirmed`, `seated`, `completed`, `cancelled`, `no_show` |
| `open_tabs.status` | `open`, `closed` |
| `appointments.status` | `scheduled`, `confirmed`, `in_progress`, `completed`, `cancelled`, `no_show` |
| `gift_registries.event_type` | `wedding`, `birthday`, `baby_shower`, `other` |

### 18.20 Business Type Templates

| Table.Column | Values |
|---|---|
| `organizations.business_type` | `retail`, `restaurant`, `pharmacy`, `grocery`, `jewelry`, `mobile_shop`, `flower_shop`, `bakery`, `service`, `custom` |
| `stores.business_type` | *(same as organizations.business_type)* |
| `business_type_loyalty_configs.program_type` | `points`, `stamps`, `cashback`, `none` |
| `business_type_promotion_templates.promotion_type` | `percentage`, `fixed`, `bogo`, `happy_hour` |
| `business_type_promotion_templates.applies_to` | `all_products`, `specific_category` |
| `business_type_commission_templates.commission_type` | `percentage`, `fixed_per_transaction`, `tiered` |
| `business_type_commission_templates.applies_to` | `all_sales`, `specific_category`, `specific_product` |
| `business_type_waste_reason_templates.category` | `spoilage`, `damage`, `theft`, `sampling`, `operational` |
| `business_type_appointment_configs.cancellation_fee_type` | `none`, `fixed`, `percentage` |
| `business_type_gamification_badges.trigger_type` | `purchase_count`, `spend_total`, `streak_days`, `category_explorer`, `referral_count` |
| `business_type_gamification_challenges.challenge_type` | `buy_x_get_y`, `spend_target`, `visit_streak`, `category_spend`, `referral` |
| `business_type_gamification_challenges.reward_type` | `points`, `discount_percentage`, `free_item`, `badge` |
| `business_type_gamification_milestones.milestone_type` | `total_spend`, `total_visits`, `membership_days` |
| `business_type_gamification_milestones.reward_type` | `points`, `discount_percentage`, `tier_upgrade`, `badge` |
| `onboarding_progress.current_step` | `welcome`, `business_info`, `business_type`, `tax`, `hardware`, `products`, `staff`, `review` |

### 18.21 Platform Admin & Roles

| Table.Column | Values |
|---|---|
| `admin_roles.slug` | `super_admin`, `platform_manager`, `support_agent`, `finance_admin`, `integration_manager`, `sales`, `viewer` |
| `admin_permissions.group` | `Stores`, `Billing`, `Tickets`, `Integrations`, `Settings`, `Analytics`, `Announcements`, `Users`, `App Updates` |
| `admin_activity_logs.entity_type` | `store`, `admin_user`, `subscription_plan`, `ticket`, `announcement` *(open set)* |
| `provider_registrations.status` | `pending`, `approved`, `rejected` |
| `store_health_snapshots.sync_status` | `ok`, `error`, `pending` |

### 18.22 System Configuration

| Table.Column | Values |
|---|---|
| `system_settings.group` | `zatca`, `payment`, `sms`, `email`, `push`, `whatsapp`, `sync`, `vat`, `locale`, `maintenance` |
| `master_translation_strings.category` | `ui`, `receipt`, `notification`, `report` |
| `supported_locales.direction` | `ltr`, `rtl` |
| `supported_locales.calendar_system` | `gregorian`, `hijri`, `both` |
| `platform_ui_defaults.key` | `handedness`, `font_size`, `theme` |

### 18.23 Feature & Limit Keys (for toggles)

| Table.Column | Values |
|---|---|
| `plan_feature_toggles.feature_key` | `kitchen_display`, `advanced_analytics`, `custom_themes`, `modifier_groups`, `recipe_inventory`, `employee_scheduling`, `accounting_export`, `tip_management`, `reservation_system`, `loyalty_program`, `advanced_coupons`, `white_label`, `api_full_access`, `multi_branch`, `inventory_expiry_tracking`, `third_party_integrations`, `custom_roles`, `customer_facing_display`, `digital_signage`, `appointment_booking`, `gift_registry`, `wishlist`, `loyalty_gamification` |
| `plan_limits.limit_key` | `max_cashiers`, `max_terminals`, `max_products`, `max_branches`, `max_delivery_platforms`, `max_custom_roles`, `max_storage_gb` |

---

## 19. Stats Summary

| Metric | Value |
|---|---|
| Total tables | 255 |
| Total columns | 2,378 |
| UUID PK tables | 238 |
| Auto-increment PK tables | 3 (Spatie + failed_jobs) |
| Pivot tables (no id) | 12 |
| Composite PK tables | 13 |
| Foreign keys | 375 + 13 deferred |
| ON DELETE CASCADE | 101 |
| Soft delete tables | 3 (`products`, `customers`, `transactions`) |
| Tables with `timestamps()` | 71 |
| Tables with `created_at` only | 81 |
| JSONB columns | 74 |
| Indexes | 45 |
| Unique constraints | 31 (table-level) + 75 (inline) |
| Seed tables | 3 (`supported_locales`, `payment_methods`, `platform_ui_defaults`) |
