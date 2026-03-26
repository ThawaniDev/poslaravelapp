# Thawani POS — Provider DB Reference (Tables, Joins & Indexes)

> Quick-reference for table names, join/pivot tables, and recommended indexes.  
> **No SQL here** — just names and notes for planning.  
> All provider-side tables live in the **shared PostgreSQL database** (cloud) and are **mirrored locally in SQLite via Drift** for offline POS operation.

---

## 🏢 Core / Shared (used across many features)

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **organizations** | Top-level chain / brand entity | Has tax_number, cr_number, settings JSONB |
| **stores** | Individual branch / location | FK → organizations. Holds ZATCA device creds, lat/lng, timezone |
| **registers** | POS terminal / register device | FK → stores. device_id, app_version, last_sync_at |
| **users** | All staff accounts (owner → cashier) | FK → organizations, stores. Has pin_hash, role, permissions JSONB |

### Indexes
- `organizations.tax_number` — UNIQUE
- `stores.organization_id`
- `stores.code` — UNIQUE
- `registers(store_id, register_number)` — UNIQUE composite
- `users.email` — UNIQUE
- `users.phone` — UNIQUE
- `users(organization_id, store_id)`

---

## 🖥️ POS Terminal (Cashier)

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **pos_sessions** | Shift open / close record | FK → stores, registers, users (cashier). opening_cash, closing_cash, expected_cash, cash_difference, totals per payment method |
| **transactions** | Every sale, return, void | FK → organizations, stores, registers, pos_sessions, users. Holds ZATCA fields (uuid, hash, qr_code, status). external_type + external_id for delivery orders |
| **transaction_items** | Line items per transaction | FK → transactions, products. Snapshot of price at sale time. serial_number, batch_number, expiry_date |
| **payments** | Payment legs (supports split) | FK → transactions. method (cash/card/wallet/store_credit), cash_tendered, change_given, card details, tip_amount |
| **held_carts** | Held / recalled carts | FK → stores, registers, users. cart_data JSONB, held_at, recalled_at |
| **exchange_transactions** | Links return txn to new sale txn | FK → transactions (return_transaction_id, sale_transaction_id). One row per exchange |
| **tax_exemptions** | Tax-exempt sale records | FK → transactions, customers. exemption_type, certificate_number, customer_tax_id |

### Indexes
- `pos_sessions(store_id, status)` — find open sessions fast
- `pos_sessions.cashier_id`
- `transactions.transaction_number` — UNIQUE
- `transactions(store_id, created_at)` — daily report queries
- `transactions(store_id, status)`
- `transactions.customer_id`
- `transactions.zatca_uuid` — UNIQUE
- `transactions.external_id` — lookup delivery orders
- `transaction_items.transaction_id`
- `transaction_items.product_id`
- `transaction_items.barcode`
- `payments.transaction_id`
- `held_carts(store_id, register_id)` — recall by register

---

## 📦 Product & Catalog Management

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **categories** | Product category tree | FK → organizations, self-ref parent_id for hierarchy. name / name_ar, sort_order |
| **products** | Master product catalog | FK → organizations, categories. barcode, sku, sell_price, cost_price, unit, is_weighable, is_active, image_url, tax_rate, age_restricted flag, sync_version |
| **product_barcodes** | Multiple barcodes per product | FK → products. barcode (UNIQUE), is_primary flag |
| **store_prices** | Store-specific price override | FK → stores, products. sell_price, valid_from, valid_to |
| **product_variants** | Size / colour / etc. matrix | FK → products. variant_group, variant_value, sku, barcode, price_adjustment |
| **product_variant_groups** | Variant axis definitions | FK → organizations. name (e.g. "Size", "Colour") |
| **product_images** | Additional product images | FK → products. image_url, sort_order |
| **combo_products** | Bundle / combo definitions | FK → products (combo_product_id). Combo header |
| **combo_product_items** | Items inside a combo | FK → combo_products, products. quantity, is_optional |
| **modifier_groups** | Modifier group per product | FK → products. name / name_ar, is_required, min_select, max_select, sort_order |
| **modifier_options** | Options within a modifier group | FK → modifier_groups. name / name_ar, price_adjustment, is_default, sort_order |
| **suppliers** | Supplier directory | FK → organizations. name, phone, email, address, notes |
| **product_suppliers** | Link product ↔ supplier | Join: FK → products, suppliers. cost_price, lead_time_days, supplier_sku |
| **internal_barcode_sequence** | Auto-increment counter for 200-prefix barcodes | FK → stores. last_sequence |

### Indexes
- `products.barcode` — fast scan lookup
- `products(organization_id, category_id)`
- `products(organization_id, is_active)`
- `products.sku`
- `product_barcodes.barcode` — UNIQUE
- `store_prices(store_id, product_id)` — UNIQUE composite
- `product_variants(product_id, variant_group)`
- `modifier_groups.product_id`
- `modifier_options.modifier_group_id`
- `product_suppliers(product_id, supplier_id)` — UNIQUE composite

---

## 🏷️ Barcode Label Printing

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **barcode_templates** | Label layout templates | FK → organizations. name, type (product/shelf/weighable/promo), width_mm, height_mm, template_config JSONB |
| **barcode_print_history** | Audit trail of label prints | FK → users, products, stores. template_id, quantity, printed_at |

### Indexes
- `barcode_print_history(store_id, printed_at)` — audit queries
- `barcode_print_history.product_id`
- `barcode_templates(organization_id, type)`

---

## 📊 Inventory Management

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **inventory** | Current stock per store × product | FK → stores, products. quantity, reserved_quantity, last_counted_at. UNIQUE(store_id, product_id) |
| **stock_movements** | Every stock change (sale, purchase, adjust, transfer, return, damage, waste) | FK → stores, products, users. type, quantity (+/−), quantity_before, quantity_after, reference_type, reference_id |
| **purchase_orders** | PO header | FK → stores, suppliers, users (created_by, approved_by). status (draft/pending_approval/approved/sent/partial/received/cancelled), total |
| **purchase_order_items** | PO line items | FK → purchase_orders, products. quantity_ordered, quantity_received, unit_cost |
| **stock_transfers** | Transfer header between branches | FK → organizations. from_store_id, to_store_id, status (requested/approved/shipped/received/cancelled), requested_by, approved_by |
| **stock_transfer_items** | Transfer line items | FK → stock_transfers, products. quantity_requested, quantity_shipped, quantity_received |
| **inventory_counts** | Physical count session | FK → stores, users. status (in_progress/completed), started_at, completed_at, count_type (full/partial) |
| **inventory_count_items** | Per-product count entries | FK → inventory_counts, products. system_qty, counted_qty, difference, notes |
| **recipes** | Bill of Materials header (ingredient recipe) | FK → products (the finished menu item), organizations. yield_quantity |
| **recipe_ingredients** | Ingredients in a recipe | FK → recipes, products (ingredient). quantity_required, unit |
| **waste_logs** | Waste / spoilage records | FK → stores, products, users. quantity, reason_code (expired/damaged/overproduction/other), cost_impact, notes, logged_at |

### Join Tables
- **product_suppliers** — products ↔ suppliers (listed under Products above)

### Indexes
- `inventory(store_id, product_id)` — UNIQUE composite
- `stock_movements(store_id, product_id, created_at)`
- `stock_movements.type`
- `stock_movements.reference_id`
- `purchase_orders(store_id, status)`
- `purchase_orders.supplier_id`
- `stock_transfers(organization_id, status)`
- `stock_transfers.from_store_id`
- `stock_transfers.to_store_id`
- `inventory_counts(store_id, status)`
- `recipes.product_id` — UNIQUE (one recipe per finished item)
- `recipe_ingredients(recipe_id, product_id)`
- `waste_logs(store_id, logged_at)`
- `waste_logs.reason_code`

---

## 📋 Order Management

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **orders** | Unified order record (POS / delivery / Thawani) | FK → stores, users. order_number, source (pos/thawani/hungerstation/…), type (dine_in/takeaway/delivery/pickup), status, table_id, server_user_id, scheduled_at (for pre-orders) |
| **order_items** | Line items per order | FK → orders, products. quantity, unit_price, modifier_selections JSONB, notes, kitchen_station_id, status (pending/preparing/ready/served) |
| **order_item_modifiers** | Selected modifier options per order item | FK → order_items, modifier_options. quantity, price |
| **kitchen_stations** | Prep stations (grill, fryer, etc.) | FK → stores. name / name_ar, printer_id, display_device_id |
| **kitchen_tickets** | Printed/displayed kitchen ticket per station per order | FK → orders, kitchen_stations. printed_at, status (pending/in_progress/done) |
| **floors** | Floor / zone definition (Main Hall, Terrace, VIP) | FK → stores. name / name_ar, sort_order |
| **tables** | Individual table | FK → floors, stores. table_number, seating_capacity, position_x, position_y, status (available/occupied/reserved/dirty), current_order_id |
| **reservations** | Table reservations | FK → stores, tables, customers. party_size, reserved_at (datetime), duration_minutes, status (pending/confirmed/seated/no_show/cancelled), notes |
| **waitlist_entries** | Waitlist queue | FK → stores, customers. party_size, estimated_wait_minutes, status (waiting/notified/seated/left), notified_at, seated_at |

### Indexes
- `orders(store_id, status, created_at)`
- `orders.order_number` — UNIQUE
- `orders.source`
- `orders.table_id`
- `orders.scheduled_at` — pre-order lookup
- `order_items.order_id`
- `order_items.kitchen_station_id`
- `order_items.status`
- `kitchen_tickets(kitchen_station_id, status)`
- `tables(floor_id, status)`
- `tables.store_id`
- `reservations(store_id, reserved_at)` — date range queries
- `reservations.status`
- `waitlist_entries(store_id, status)`

---

## 🚚 Third-Party Delivery Integrations

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **store_delivery_platforms** | Provider's connected platforms | FK → stores. platform_slug (hungerstation/keeta/…), is_enabled, api_credentials_encrypted, webhook_api_key |
| **delivery_sync_logs** | Sync event log per platform | FK → store_delivery_platforms. direction (inbound/outbound), entity_type (product/order), status (ok/error), error_message, synced_at |
| **delivery_orders** | Inbound orders from external platforms | FK → stores, store_delivery_platforms. external_order_id, platform_slug, payload JSONB, status, linked_order_id (FK → orders after acceptance) |

### Indexes
- `store_delivery_platforms(store_id, platform_slug)` — UNIQUE composite
- `delivery_sync_logs(store_delivery_platform_id, synced_at)`
- `delivery_sync_logs.status`
- `delivery_orders(store_id, status)`
- `delivery_orders.external_order_id`

---

## 💳 Payments & Finance

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **payments** | Payment legs per transaction | (listed under POS Terminal). tip_amount column added |
| **gift_cards** | Issued gift cards | FK → organizations, stores (issued_at_store). code (UNIQUE), initial_balance, current_balance, is_active, issued_to_customer_id, expires_at |
| **gift_card_transactions** | Usage / top-up log | FK → gift_cards, transactions. type (redeem/topup), amount, balance_after |
| **customer_credits** | Store credit ledger | FK → customers, stores. type (issue/redeem/refund_credit), amount, balance_after, reference_type (return/manual/deposit), reference_id, created_by |
| **expenses** | Store expense records | FK → stores, users (recorded_by). category (rent/utilities/supplies/petty_cash/other), amount, description, expense_date, receipt_image_url |
| **expense_categories** | Configurable expense categories | FK → organizations. name / name_ar, is_active |
| **accounting_exports** | Export history log | FK → stores. target (quickbooks/xero/qoyod/csv), exported_at, date_range_start, date_range_end, status, file_url |

### Indexes
- `gift_cards.code` — UNIQUE
- `gift_cards(organization_id, is_active)`
- `gift_card_transactions.gift_card_id`
- `customer_credits(customer_id, created_at)`
- `expenses(store_id, expense_date)`
- `expenses.category`
- `accounting_exports(store_id, exported_at)`

---

## 📈 Reports & Analytics

> Reports are **read-only aggregation queries** — no dedicated tables.  
> They query: transactions, transaction_items, payments, inventory, stock_movements, pos_sessions, waste_logs, tip data, coupons, delivery_orders.

### Materialised / Cache Tables (optional, for performance)
| Table | Purpose | Notes |
|---|---|---|
| **daily_sales_summary** | Pre-aggregated daily totals | store_id, date, total_sales, total_tax, total_discount, transaction_count, cash_total, card_total. Rebuilt nightly or on shift close |
| **product_sales_summary** | Product-level aggregation | store_id, product_id, period_type (daily/weekly/monthly), period_start, quantity_sold, revenue, cost, margin |

### Indexes
- `daily_sales_summary(store_id, date)` — UNIQUE composite
- `product_sales_summary(store_id, product_id, period_type, period_start)`

---

## 👥 Customer Management

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **customers** | Customer profiles | FK → organizations. name, phone (UNIQUE per org), email, address, loyalty_points, loyalty_tier, store_credit_balance, notes, created_at |
| **customer_groups** | Segment groups | FK → organizations. name / name_ar, description |
| **customer_group_members** | Link customer ↔ group | Join: FK → customer_groups, customers |
| **loyalty_transactions** | Points earn / redeem log | FK → customers, transactions. type (earn/redeem/adjust/expire), points (+/−), description, created_at |
| **customer_feedback** | Post-sale satisfaction | FK → customers, transactions. rating (1–5), comment, submitted_at |
| **customer_deposits** | Deposits / layaway payments | FK → customers, stores. order_id (optional), amount, status (held/applied/refunded), created_at |

### Indexes
- `customers(organization_id, phone)` — UNIQUE composite
- `customers.email`
- `customers.loyalty_tier`
- `customer_group_members(customer_group_id, customer_id)` — UNIQUE composite
- `loyalty_transactions(customer_id, created_at)`
- `loyalty_transactions.transaction_id`
- `customer_feedback.transaction_id`
- `customer_deposits(customer_id, status)`

---

## 🎟️ Promotions & Coupons

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **promotions** | Promotion definition | FK → organizations, stores (NULL = org-wide). type (percentage/fixed/bogo/bundle/happy_hour), name / name_ar, value, min_cart_value, start_at, end_at, is_active, stacking_allowed, max_uses |
| **promotion_products** | Products / categories targeted | Join: FK → promotions, products (nullable), categories (nullable). Determines scope |
| **promotion_schedules** | Time-of-day rules (happy hour / menu schedule) | FK → promotions. day_of_week, start_time, end_time |
| **coupons** | Coupon codes | FK → promotions (optional), organizations. code (UNIQUE per org), type (single/multi), max_uses, times_used, is_active |
| **coupon_usages** | Redemption log | FK → coupons, transactions, customers. redeemed_at |
| **menu_schedules** | Auto-switch active categories by time of day | FK → stores. name (e.g. "Breakfast"), day_of_week, start_time, end_time |
| **menu_schedule_categories** | Categories visible during a menu schedule | Join: FK → menu_schedules, categories |

### Indexes
- `promotions(organization_id, is_active, start_at, end_at)`
- `promotions.type`
- `promotion_products(promotion_id, product_id)`
- `promotion_schedules.promotion_id`
- `coupons(organization_id, code)` — UNIQUE composite
- `coupon_usages(coupon_id, customer_id)`
- `coupon_usages.transaction_id`
- `menu_schedules(store_id, day_of_week)`

---

## 🖐️ POS Interface Customization

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **pos_layout_templates** | Platform-defined layout variants | FK → business_types. layout_key, name / name_ar, config JSONB, is_default, sort_order. Managed by platform admin |
| **store_pos_settings** | Store-level overrides | FK → stores. selected_layout_id, theme, primary_color, accent_color, logo_url, handedness, font_size, receipt_header, receipt_footer |
| **user_pos_preferences** | Per-user UI preferences | FK → users. handedness, font_size, theme, selected_layout_id |

### Indexes
- `pos_layout_templates(business_type_id, is_default)`
- `store_pos_settings.store_id` — UNIQUE
- `user_pos_preferences.user_id` — UNIQUE

---

## 🔔 Notifications

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **notification_preferences** | Per-user channel + event toggles | FK → users. event_key (e.g. "order.new"), channel (in_app/push/sms/email/whatsapp/webhook), is_enabled |
| **notifications** | Sent notification log | FK → users, stores. event_key, channel, title, body, is_read, sent_at, read_at |
| **notification_quiet_hours** | Do-not-disturb windows | FK → users. start_time, end_time, allow_critical |
| **notification_thresholds** | Configurable trigger values | FK → stores. threshold_key (low_stock_qty/large_txn_amount/expiry_warning_days), value |

### Indexes
- `notification_preferences(user_id, event_key, channel)` — UNIQUE composite
- `notifications(user_id, is_read, sent_at)`
- `notifications.event_key`
- `notification_quiet_hours.user_id`
- `notification_thresholds(store_id, threshold_key)` — UNIQUE composite

---

## 🛡️ Roles & Permissions

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **roles** | Role definitions per org | FK → organizations. name, slug, is_system (true for default roles), is_custom |
| **permissions** | Master permission list | name (e.g. "pos.sell"), group (POS/Inventory/Reports/…), description. Platform-seeded |
| **role_permissions** | Permissions assigned to a role | Join: FK → roles, permissions |
| **user_roles** | Roles assigned to a user | Join: FK → users, roles. Store-scoped if needed (store_id) |

### Indexes
- `roles(organization_id, slug)` — UNIQUE composite
- `role_permissions(role_id, permission_id)` — UNIQUE composite
- `user_roles(user_id, role_id)` — UNIQUE composite
- `permissions.name` — UNIQUE

---

## 👤 Staff & User Management

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **users** | (Core table — see above) | pin_hash, is_active, last_login_at |
| **time_clock_entries** | Clock in / clock out | FK → users, stores. clock_in_at, clock_out_at, total_hours, notes |
| **employee_schedules** | Shift roster | FK → users, stores. date, shift_start, shift_end, status (scheduled/confirmed/swap_requested/swapped) |
| **schedule_swap_requests** | Shift swap workflow | FK → employee_schedules (from_schedule_id, to_schedule_id), users (requester, target). status (pending/approved/rejected) |
| **commission_rules** | Commission config | FK → organizations, stores. applies_to (role/product/category), target_id, rate_type (percentage/fixed_per_unit), rate_value |
| **commission_earnings** | Earned commissions per transaction | FK → users, transactions, commission_rules. amount_earned |
| **tip_pools** | Tip pool configuration | FK → stores. name, distribution_method (equal/weighted/custom) |
| **tip_pool_members** | Staff in a tip pool | Join: FK → tip_pools, users. weight (for weighted distribution) |
| **tip_entries** | Individual tip records | FK → transactions, users (server). amount, pool_id (nullable) |
| **tip_payouts** | Tip payout to employee | FK → users, stores. period_start, period_end, total_amount, paid_at |
| **audit_logs** | All sensitive-action log | FK → users, stores. action, entity_type, entity_id, ip_address, details JSONB, created_at |

### Indexes
- `time_clock_entries(user_id, clock_in_at)`
- `time_clock_entries(store_id, clock_in_at)`
- `employee_schedules(user_id, date)`
- `employee_schedules(store_id, date)`
- `commission_earnings(user_id, created_at)`
- `commission_earnings.transaction_id`
- `tip_entries.transaction_id`
- `tip_entries.user_id`
- `tip_payouts(user_id, period_start)`
- `audit_logs(store_id, created_at)`
- `audit_logs(user_id, action)`
- `audit_logs.entity_type`

---

## 💳 Subscription & Billing (self-service view)

> Provider-side reads from platform-managed tables. See **platform DB reference** for full schema.  
> Provider only sees their own record.

### Tables (read-only from provider perspective)
| Table | Purpose | Notes |
|---|---|---|
| **store_subscriptions** | Current plan link | FK → stores, subscription_plans. status, current_period_start/end, trial_ends_at |
| **invoices** | Billing history | FK → store_subscriptions. amount, status (paid/pending/failed), pdf_url |

---

## 🌍 Language & Localization

> No dedicated tables. All content tables have dual columns: `name` / `name_ar`, `description` / `description_ar`.  
> User preference stored in `users.locale` (en/ar) and `users.numeral_format` (western/arabic_indic), `users.calendar` (gregorian/hijri).

---

## 🖨️ Hardware Support

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **store_printers** | Configured printers per store | FK → stores. type (receipt/label), brand, model, connection_type (usb/network/bluetooth/serial), address (IP or port), is_default |
| **store_hardware** | Other hardware config | FK → stores. type (scanner/scale/cash_drawer/pole_display), brand, model, connection_details JSONB |

### Indexes
- `store_printers(store_id, type, is_default)`
- `store_hardware(store_id, type)`

---

## ☁️ Offline / Online Sync

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **sync_queue** | Pending sync items (local SQLite) | store_id, register_id, entity_type (transaction/inventory/product), entity_id, action (create/update/delete), payload JSONB, priority, status, attempts, last_error |
| **sync_logs** | Completed sync history (cloud) | FK → stores. entity_type, entity_id, action, source (pos/thawani/admin), synced_at, data JSONB |

### Indexes
- `sync_queue(status, priority)` — process highest-priority pending first
- `sync_queue(store_id, entity_type)`
- `sync_logs(store_id, entity_type, synced_at)`

---

## 🔗 Thawani Marketplace Integration

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **thawani_integration** | Store's Thawani connection config | FK → stores. api_key_encrypted, is_enabled, last_sync_at |
| **thawani_product_map** | POS product ↔ Thawani product mapping | FK → products. thawani_product_id, last_synced_at |

> Inbound Thawani orders flow into the **orders** table with source = "thawani" and link to **delivery_orders**.

### Indexes
- `thawani_integration.store_id` — UNIQUE
- `thawani_product_map(product_id, thawani_product_id)` — UNIQUE composite

---

## 🏪 Business Type Selection (Onboarding)

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **business_types** | Master list of business types | name / name_ar, slug, icon, default_layout_id, is_active, sort_order. Platform-managed |
| **business_type_category_templates** | Seed categories per business type | FK → business_types. category_name / category_name_ar, sort_order |

> Store's chosen type stored in `stores.business_type_id` (FK → business_types).

### Indexes
- `business_types.slug` — UNIQUE
- `business_type_category_templates.business_type_id`

---

## 🏥 Industry-Specific Workflows

### Pharmacy
| Table | Purpose | Notes |
|---|---|---|
| **prescriptions** | Prescription records | FK → stores, customers. prescriber_name, prescriber_license, image_url, status (pending/dispensed/partially_dispensed) |
| **prescription_items** | Products on a prescription | FK → prescriptions, products. quantity_prescribed, quantity_dispensed |
| **insurance_claims** | Insurance claim records | FK → transactions, customers. insurer_name, policy_number, claim_amount, status, submitted_at |
| **drug_interactions** | Interaction warning rules | product_id_a, product_id_b, severity (info/warning/critical), description |
| **controlled_substance_log** | Controlled substance tracking | FK → products, users, transactions. quantity, action (dispense/receive/adjust), logged_at |

### Jewelry
| Table | Purpose | Notes |
|---|---|---|
| **gold_rate_feeds** | Live gold rate history | karat, rate_per_gram, fetched_at |
| **certifications** | GIA or other certs per product | FK → products. certification_body, certificate_number, image_url |
| **buyback_records** | Buy-back tracking | FK → stores, customers, products. weight, karat, rate_at_buyback, amount_paid |
| **layaway_plans** | Layaway payment plan | FK → transactions, customers. total_amount, deposit_amount, remaining_balance, status (active/completed/cancelled), due_date |
| **layaway_payments** | Instalment payments | FK → layaway_plans. amount, paid_at |
| **appraisals** | Item appraisals | FK → stores, customers. description, appraised_value, appraiser_user_id, appraised_at |

### Mobile Phone Shop
| Table | Purpose | Notes |
|---|---|---|
| **imei_tracking** | IMEI per unit | FK → products, stores. imei (UNIQUE), serial_number, status (in_stock/sold/returned/traded_in) |
| **warranty_records** | Warranty per sold unit | FK → imei_tracking, transactions, customers. warranty_start, warranty_end, warranty_type, status |
| **trade_ins** | Trade-in records | FK → stores, customers. device_description, imei, assessed_value, status (assessed/accepted/rejected), applied_to_transaction_id |
| **work_orders** | Repair / service tracking | FK → stores, customers. device_description, imei, issue_description, status (intake/diagnose/repair/test/ready/returned/cancelled), assigned_to_user_id, estimated_cost, actual_cost |
| **work_order_parts** | Parts used in repair | FK → work_orders, products. quantity, unit_cost |

### Flower Shop
| Table | Purpose | Notes |
|---|---|---|
| **occasions** | Occasion types for browsing | name / name_ar, icon, sort_order |
| **greeting_cards** | Card message per order | FK → orders. message_text |
| **freshness_tracking** | Freshness per batch | FK → products, stores. received_at, expected_life_days, status (fresh/fading/expired) |

### Bakery
| Table | Purpose | Notes |
|---|---|---|
| **production_batches** | Daily production tracking | FK → products, stores. quantity_produced, baked_at, status (fresh/selling/discounted/expired) |
| **custom_cake_orders** | Special cake orders | FK → orders, customers. description, size, flavour, decoration_notes, pickup_date, status |

### Restaurant / Café / Fast Food
> Core tables already covered under **Order Management**: orders, order_items, order_item_modifiers, kitchen_stations, kitchen_tickets, floors, tables, reservations, waitlist_entries.

| Table | Purpose | Notes |
|---|---|---|
| **courses** | Course grouping per order | FK → orders. course_number (1=appetizer, 2=main, 3=dessert), status (draft/sent/fired/served) |
| **server_table_assignments** | Server ↔ table link | FK → users (server), tables. assigned_at |
| **speed_of_service_logs** | Per-order timing | FK → orders. order_placed_at, kitchen_received_at, food_ready_at, served_at, total_seconds |

### Indexes (across all industry tables)
- `prescriptions(store_id, status)`
- `insurance_claims.transaction_id`
- `drug_interactions(product_id_a, product_id_b)` — UNIQUE composite
- `controlled_substance_log(product_id, logged_at)`
- `imei_tracking.imei` — UNIQUE
- `work_orders(store_id, status)`
- `work_orders.customer_id`
- `production_batches(store_id, baked_at)`
- `reservations(store_id, reserved_at, status)`
- `speed_of_service_logs.order_id`
- `courses.order_id`
- `layaway_plans(customer_id, status)`
- `gold_rate_feeds(karat, fetched_at)`

---

## 💾 Backup & Recovery

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **backups** | Backup metadata (local SQLite + cloud) | store_id, type (auto_local/auto_cloud/pre_update/manual), file_path, file_size_bytes, created_at, status (completed/corrupted), retained_until |

### Indexes
- `backups(store_id, type, created_at)`

---

## 🔄 Auto-Updates

> Primarily platform-managed. Provider-side only stores local state.

### Tables (local SQLite)
| Table | Purpose | Notes |
|---|---|---|
| **update_state** | Local update tracking | current_version, pending_version, download_path, download_status, channel (stable/beta), last_checked_at |
| **previous_versions** | Rollback versions kept locally | version, backup_path, installed_at |

---

## 🧾 ZATCA e-Invoicing Compliance

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **zatca_device_config** | ZATCA onboarding state per store | FK → stores. csr, compliance_csid, production_csid, otp, environment (sandbox/production), onboarding_status |
| **zatca_invoices** | Invoice submission log | FK → transactions. uuid, hash, xml, qr_code, invoice_type (simplified_B2C/standard_B2B), status (pending/submitted/accepted/rejected), attempts, last_response JSONB, submitted_at |

### Indexes
- `zatca_device_config.store_id` — UNIQUE
- `zatca_invoices.transaction_id` — UNIQUE
- `zatca_invoices(store_id, status)`
- `zatca_invoices.uuid` — UNIQUE

---

## 💱 Additional Nice-to-Have

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **currency_rates** | Exchange rates | base_currency, target_currency, rate, fetched_at |
| **price_books** | Named price lists (wholesale/retail/VIP) | FK → organizations. name, is_default |
| **price_book_entries** | Per-product price in a price book | FK → price_books, products. sell_price |
| **price_book_customer_groups** | Assign price book to customer group | Join: FK → price_books, customer_groups |
| **payment_surcharges** | Surcharge per payment method | FK → stores. payment_method, surcharge_type (percentage/fixed), surcharge_value |
| **appointments** | Service bookings | FK → stores, customers, users (service_provider). service_name, start_at, end_at, status (booked/confirmed/completed/cancelled/no_show), notes |

### Indexes
- `currency_rates(base_currency, target_currency, fetched_at)`
- `price_book_entries(price_book_id, product_id)` — UNIQUE composite
- `payment_surcharges(store_id, payment_method)` — UNIQUE composite
- `appointments(store_id, start_at)`
- `appointments(customer_id, status)`
- `appointments.user_id`

---

## 🔐 Security (Provider Side)

> Security is enforced through **roles**, **permissions**, **audit_logs**, and **users** tables (already listed).  
> Third-party credentials stored AES-256 encrypted in `store_delivery_platforms.api_credentials_encrypted`.  
> ZATCA private key in `zatca_device_config` — marked not-exportable at app level.  
> No extra dedicated security tables on the provider side.
