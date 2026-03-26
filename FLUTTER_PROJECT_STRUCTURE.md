# Thawani POS — Flutter Project Structure

> **Purpose**: Canonical reference for the Flutter POS client project folder structure.
> **Architecture**: Feature-based with clean architecture layers — NOT the default Flutter flat layout.
> **Created**: 8 March 2026
> **Stack**: Flutter 3.x (Dart 3.x) + Riverpod + Drift (SQLite) + Dio + Null Safety

---

## Why Feature-Based?

The POS app spans ~342 screens across 16 sections with offline-first data for 255 tables. A flat `lib/models/`, `lib/screens/` structure would be unnavigable. Feature-based grouping:
- Keeps models, screens, providers, and services together per business domain
- Mirrors the Laravel backend domain structure exactly (same feature names)
- Enables independent feature development
- Makes widget trees, imports, and navigation predictable
- Simplifies lazy-loading features by subscription tier

---

## Rules (Must Follow)

1. **One feature = one folder** inside `lib/features/`
2. **Never place a model/screen in a different feature's folder** — follow the same mapping as the Laravel backend
3. **Enums live in their feature folder** under `enums/` or in `lib/core/enums/` if shared (3+ features)
4. **Cross-feature imports**: import from another feature's public barrel file — never duplicate code
5. **Every feature folder exports** a single barrel file: `{feature}.dart`
6. **Screens use the page suffix**: `ProductListPage`, `OrderDetailPage`, `PosTerminalPage`
7. **State management**: Riverpod only. One `providers/` folder per feature. No Bloc, no ChangeNotifier, no setState for business logic.
8. **Drift DAOs**: one DAO per feature inside `data/local/daos/`. DAOs are the only code that touches SQLite directly.
9. **API services**: one service class per feature inside `data/remote/`. Services are the only code that calls Dio directly.
10. **Repository pattern**: Repositories in `repositories/` coordinate between local DAO + remote API. All providers read from repositories, never from DAOs/APIs directly.
11. **File naming**: always `snake_case.dart` — no PascalCase filenames
12. **Tests mirror features**: `test/features/{feature}/`
13. **Null safety is mandatory**: every nullable field uses `?`, every non-nullable field is `required`

---

## Top-Level Directory Layout

```
thawani-pos-flutter/
├── lib/
│   ├── main.dart                                # App entry point
│   ├── app.dart                                 # MaterialApp / Router configuration
│   │
│   ├── core/                                    # ★ Shared infrastructure (used by all features)
│   │   ├── constants/
│   │   │   ├── app_constants.dart               # Timeouts, limits, version
│   │   │   ├── api_endpoints.dart               # Base URL, all endpoint paths
│   │   │   └── storage_keys.dart                # SharedPreferences / SecureStorage keys
│   │   │
│   │   ├── enums/                               # Shared enums (used by 3+ features)
│   │   │   ├── sync_status.dart
│   │   │   ├── business_type.dart
│   │   │   ├── locale_type.dart
│   │   │   └── currency.dart
│   │   │
│   │   ├── models/                              # Shared base classes
│   │   │   ├── paginated_response.dart
│   │   │   ├── api_error.dart
│   │   │   └── sync_item.dart
│   │   │
│   │   ├── network/
│   │   │   ├── dio_client.dart                  # Dio instance, interceptors, base headers
│   │   │   ├── auth_interceptor.dart            # Token refresh, 401 handling
│   │   │   ├── connectivity_service.dart        # Online/offline detection
│   │   │   └── api_response.dart                # Generic response wrapper
│   │   │
│   │   ├── database/
│   │   │   ├── app_database.dart                # Drift database class (single instance)
│   │   │   ├── app_database.g.dart              # Generated
│   │   │   └── migrations.dart                  # Schema version migrations
│   │   │
│   │   ├── sync/
│   │   │   ├── sync_engine.dart                 # Orchestrates bidirectional sync
│   │   │   ├── sync_queue.dart                  # Offline transaction queue
│   │   │   └── conflict_resolver.dart           # server_wins / client_wins / last_write_wins
│   │   │
│   │   ├── auth/
│   │   │   ├── auth_provider.dart               # Riverpod auth state
│   │   │   ├── auth_service.dart                # Login, PIN, token management
│   │   │   ├── token_storage.dart               # SecureStorage for tokens
│   │   │   └── auth_guard.dart                  # Route guard for protected pages
│   │   │
│   │   ├── router/
│   │   │   ├── app_router.dart                  # GoRouter / AutoRoute configuration
│   │   │   ├── route_names.dart                 # Named route constants
│   │   │   └── route_guards.dart                # Auth, role-based guards
│   │   │
│   │   ├── theme/
│   │   │   ├── app_theme.dart                   # ThemeData (light + dark)
│   │   │   ├── app_colors.dart                  # Color palette
│   │   │   ├── app_typography.dart              # Text styles (AR + EN)
│   │   │   └── app_spacing.dart                 # Consistent spacing values
│   │   │
│   │   ├── l10n/
│   │   │   ├── app_localizations.dart           # Generated localization delegate
│   │   │   ├── arb/
│   │   │   │   ├── app_ar.arb                   # Arabic strings
│   │   │   │   └── app_en.arb                   # English strings
│   │   │   └── locale_provider.dart             # Current locale state
│   │   │
│   │   ├── utils/
│   │   │   ├── formatters.dart                  # Currency, date, number formatting
│   │   │   ├── validators.dart                  # Input validation helpers
│   │   │   ├── extensions.dart                  # Dart extension methods
│   │   │   ├── debouncer.dart                   # Debounce utility
│   │   │   └── logger.dart                      # Structured logging
│   │   │
│   │   ├── widgets/                             # Reusable UI components (design system)
│   │   │   ├── app_scaffold.dart                # Standard page scaffold with drawer/sidebar
│   │   │   ├── app_button.dart
│   │   │   ├── app_text_field.dart
│   │   │   ├── app_card.dart
│   │   │   ├── app_data_table.dart
│   │   │   ├── app_search_bar.dart
│   │   │   ├── app_dialog.dart
│   │   │   ├── app_loading.dart
│   │   │   ├── app_empty_state.dart
│   │   │   ├── app_error_widget.dart
│   │   │   ├── status_badge.dart
│   │   │   ├── price_text.dart
│   │   │   └── arabic_numeral_text.dart
│   │   │
│   │   └── errors/
│   │       ├── app_exception.dart
│   │       ├── network_exception.dart
│   │       └── sync_exception.dart
│   │
│   ├── features/                                # ★ ALL business features live here
│   │   ├── auth/                                # Login, PIN, 2FA, session
│   │   ├── onboarding/                          # First-time setup wizard
│   │   ├── dashboard/                           # Main dashboard (role-based)
│   │   ├── pos_terminal/                        # POS screen, cart, checkout, shifts
│   │   ├── catalog/                             # Products, categories, variants, barcodes, modifiers
│   │   ├── inventory/                           # Stock levels, adjustments, transfers, POs
│   │   ├── orders/                              # POS orders, delivery orders, returns, exchanges
│   │   ├── payments/                            # Payment processing, cash sessions, gift cards, refunds
│   │   ├── customers/                           # Customer management, loyalty, store credit
│   │   ├── promotions/                          # Promotions, coupons, bundles
│   │   ├── thawani_integration/                 # Thawani connection, product/stock sync, settlements
│   │   ├── delivery_integration/                # Third-party delivery platforms
│   │   ├── zatca/                               # ZATCA e-invoicing, certificates, QR signing
│   │   ├── notifications/                       # Push, in-app, preferences
│   │   ├── reports/                             # Sales, product, inventory, staff reports
│   │   ├── staff/                               # Staff users, roles, attendance, scheduling
│   │   ├── branches/                            # Multi-store management
│   │   ├── subscription/                        # Plan view, billing, usage
│   │   ├── settings/                            # All settings screens
│   │   ├── support/                             # Help center, tickets
│   │   ├── hardware/                            # Printer, scanner, scale, cash drawer config
│   │   ├── labels/                              # Label templates, barcode label printing
│   │   ├── accounting/                          # Accounting integration config
│   │   ├── pos_customization/                   # POS layout, receipt templates, quick access
│   │   ├── industry_pharmacy/                   # Prescription management
│   │   ├── industry_jewelry/                    # Metal rates, buyback
│   │   ├── industry_electronics/                # IMEI tracking, repairs, trade-ins
│   │   ├── industry_florist/                    # Freshness tracking, subscriptions
│   │   ├── industry_bakery/                     # Recipes, production schedules, custom cakes
│   │   └── industry_restaurant/                 # Tables, kitchen tickets, reservations, tabs
│   │
│   └── generated/                               # Build-runner generated code
│       └── .gitkeep
│
├── test/
│   ├── core/                                    # Core infrastructure tests
│   ├── features/                                # Mirrors lib/features/ structure
│   │   ├── pos_terminal/
│   │   ├── catalog/
│   │   ├── inventory/
│   │   ├── orders/
│   │   └── ...
│   ├── integration/                             # Full-flow integration tests
│   └── golden/                                  # Golden image tests (receipts, RTL)
│
├── integration_test/                            # Flutter integration tests
│   ├── complete_sale_test.dart
│   ├── offline_sync_test.dart
│   └── zatca_signing_test.dart
│
├── assets/
│   ├── images/                                  # App images, logos
│   ├── fonts/                                   # Arabic + English fonts
│   ├── sounds/                                  # Notification sounds
│   └── icons/                                   # Custom SVG icons
│
├── windows/                                     # Windows-specific native code
├── android/                                     # Android-specific native code
├── ios/                                         # iOS-specific native code
├── web/                                         # Web-specific native code
│
├── scripts/
│   ├── build_windows.sh                         # Build + sign Windows installer
│   ├── build_release.sh                         # Full release pipeline
│   └── generate_code.sh                         # dart run build_runner build
│
├── .github/
│   └── workflows/
│       ├── ci.yml                               # Lint + test on push
│       ├── build_windows.yml                    # Build Windows artifact
│       └── build_mobile.yml                     # Build iOS/Android
│
├── pubspec.yaml
├── analysis_options.yaml
├── l10n.yaml
└── README.md
```

---

## Feature Folder Internal Structure (Template)

Every feature folder inside `lib/features/{feature}/` follows this exact layout:

```
features/catalog/
├── catalog.dart                         # ★ Barrel file — single public export
│
├── enums/
│   ├── product_unit.dart
│   ├── barcode_type.dart
│   └── variant_display_type.dart
│
├── models/                              # Dart data classes (from JSON API)
│   ├── product.dart
│   ├── category.dart
│   ├── product_barcode.dart
│   ├── product_variant.dart
│   ├── product_variant_group.dart
│   ├── product_image.dart
│   ├── combo_product.dart
│   ├── combo_product_item.dart
│   ├── modifier_group.dart
│   ├── modifier_option.dart
│   ├── supplier.dart
│   ├── product_supplier.dart
│   ├── store_price.dart
│   └── internal_barcode_sequence.dart
│
├── data/
│   ├── local/
│   │   ├── tables/                      # Drift table definitions
│   │   │   ├── products_table.dart
│   │   │   ├── categories_table.dart
│   │   │   └── product_barcodes_table.dart
│   │   │
│   │   └── daos/                        # Drift DAOs (SQLite queries)
│   │       ├── product_dao.dart
│   │       ├── category_dao.dart
│   │       └── barcode_dao.dart
│   │
│   └── remote/                          # API service classes (Dio calls)
│       ├── product_api_service.dart
│       ├── category_api_service.dart
│       └── supplier_api_service.dart
│
├── repositories/                        # Coordinate local DAO + remote API
│   ├── product_repository.dart
│   ├── category_repository.dart
│   └── supplier_repository.dart
│
├── providers/                           # Riverpod providers
│   ├── product_providers.dart           # productListProvider, productDetailProvider, etc.
│   ├── category_providers.dart
│   ├── product_search_provider.dart
│   └── barcode_scan_provider.dart
│
├── pages/                               # Full-page screens
│   ├── product_list_page.dart
│   ├── product_detail_page.dart
│   ├── product_form_page.dart
│   ├── category_list_page.dart
│   ├── category_form_page.dart
│   ├── product_import_page.dart
│   ├── barcode_label_print_page.dart
│   └── bulk_price_edit_page.dart
│
├── widgets/                             # Feature-specific reusable widgets
│   ├── product_card.dart
│   ├── product_grid.dart
│   ├── category_tree.dart
│   ├── variant_selector.dart
│   ├── modifier_picker.dart
│   ├── barcode_display.dart
│   └── price_input.dart
│
└── utils/                               # Feature-specific helpers
    ├── barcode_generator.dart
    ├── product_search_helper.dart
    └── sku_validator.dart
```

---

## Complete Feature → Models Mapping

This is the **definitive mapping** from database tables to Flutter feature folders. Mirrors the Laravel `Domain/` structure exactly.

### `features/auth/` — Users & Authentication
| Model | File |
|---|---|
| User | `models/user.dart` |

### `features/dashboard/` — Dashboard
No dedicated models — aggregates data from other features' providers.

### `features/onboarding/` — First-Time Setup
| Model | File |
|---|---|
| OnboardingProgress | `models/onboarding_progress.dart` |

### `features/pos_terminal/` — POS Terminal
| Model | File |
|---|---|
| PosSession | `models/pos_session.dart` |
| Transaction | `models/transaction.dart` |
| TransactionItem | `models/transaction_item.dart` |
| HeldCart | `models/held_cart.dart` |
| ExchangeTransaction | `models/exchange_transaction.dart` |
| TaxExemption | `models/tax_exemption.dart` |

### `features/catalog/` — Products & Categories
| Model | File |
|---|---|
| Category | `models/category.dart` |
| Product | `models/product.dart` |
| ProductBarcode | `models/product_barcode.dart` |
| StorePrice | `models/store_price.dart` |
| ProductVariantGroup | `models/product_variant_group.dart` |
| ProductVariant | `models/product_variant.dart` |
| ProductImage | `models/product_image.dart` |
| ComboProduct | `models/combo_product.dart` |
| ComboProductItem | `models/combo_product_item.dart` |
| ModifierGroup | `models/modifier_group.dart` |
| ModifierOption | `models/modifier_option.dart` |
| Supplier | `models/supplier.dart` |
| ProductSupplier | `models/product_supplier.dart` |
| InternalBarcodeSequence | `models/internal_barcode_sequence.dart` |

### `features/inventory/` — Stock Management
| Model | File |
|---|---|
| StockLevel | `models/stock_level.dart` |
| StockMovement | `models/stock_movement.dart` |
| GoodsReceipt | `models/goods_receipt.dart` |
| GoodsReceiptItem | `models/goods_receipt_item.dart` |
| StockAdjustment | `models/stock_adjustment.dart` |
| StockAdjustmentItem | `models/stock_adjustment_item.dart` |
| StockTransfer | `models/stock_transfer.dart` |
| StockTransferItem | `models/stock_transfer_item.dart` |
| PurchaseOrder | `models/purchase_order.dart` |
| PurchaseOrderItem | `models/purchase_order_item.dart` |
| StockBatch | `models/stock_batch.dart` |
| Recipe | `models/recipe.dart` |
| RecipeIngredient | `models/recipe_ingredient.dart` |

### `features/orders/` — Order Management
| Model | File |
|---|---|
| Order | `models/order.dart` |
| OrderItem | `models/order_item.dart` |
| OrderItemModifier | `models/order_item_modifier.dart` |
| OrderStatusHistory | `models/order_status_history.dart` |
| SaleReturn | `models/sale_return.dart` |
| ReturnItem | `models/return_item.dart` |
| Exchange | `models/exchange.dart` |
| OrderDeliveryInfo | `models/order_delivery_info.dart` |
| PendingOrder | `models/pending_order.dart` |

### `features/payments/` — Payments & Finance
| Model | File |
|---|---|
| Payment | `models/payment.dart` |
| CashSession | `models/cash_session.dart` |
| CashEvent | `models/cash_event.dart` |
| Expense | `models/expense.dart` |
| GiftCard | `models/gift_card.dart` |
| GiftCardTransaction | `models/gift_card_transaction.dart` |
| Refund | `models/refund.dart` |

### `features/customers/` — Customer Management
| Model | File |
|---|---|
| Customer | `models/customer.dart` |
| CustomerGroup | `models/customer_group.dart` |
| LoyaltyTransaction | `models/loyalty_transaction.dart` |
| StoreCreditTransaction | `models/store_credit_transaction.dart` |
| LoyaltyConfig | `models/loyalty_config.dart` |
| DigitalReceiptLog | `models/digital_receipt_log.dart` |
| Appointment | `models/appointment.dart` |
| GiftRegistry | `models/gift_registry.dart` |
| GiftRegistryItem | `models/gift_registry_item.dart` |
| Wishlist | `models/wishlist.dart` |
| LoyaltyChallenge | `models/loyalty_challenge.dart` |
| LoyaltyBadge | `models/loyalty_badge.dart` |
| LoyaltyTier | `models/loyalty_tier.dart` |
| CustomerChallengeProgress | `models/customer_challenge_progress.dart` |
| CustomerBadge | `models/customer_badge.dart` |
| CfdConfiguration | `models/cfd_configuration.dart` |
| SignagePlaylist | `models/signage_playlist.dart` |

### `features/promotions/` — Promotions & Coupons
| Model | File |
|---|---|
| Promotion | `models/promotion.dart` |
| PromotionProduct | `models/promotion_product.dart` |
| PromotionCategory | `models/promotion_category.dart` |
| PromotionCustomerGroup | `models/promotion_customer_group.dart` |
| CouponCode | `models/coupon_code.dart` |
| PromotionUsageLog | `models/promotion_usage_log.dart` |
| BundleProduct | `models/bundle_product.dart` |

### `features/thawani_integration/` — Thawani Marketplace
| Model | File |
|---|---|
| ThawaniStoreConfig | `models/thawani_store_config.dart` |
| ThawaniProductMapping | `models/thawani_product_mapping.dart` |
| ThawaniOrderMapping | `models/thawani_order_mapping.dart` |
| ThawaniSettlement | `models/thawani_settlement.dart` |

### `features/delivery_integration/` — Delivery Platforms
| Model | File |
|---|---|
| StoreDeliveryPlatform | `models/store_delivery_platform.dart` |
| DeliveryPlatformConfig | `models/delivery_platform_config.dart` |
| DeliveryOrderMapping | `models/delivery_order_mapping.dart` |
| DeliveryMenuSyncLog | `models/delivery_menu_sync_log.dart` |

### `features/accounting/` — Accounting Integration
| Model | File |
|---|---|
| StoreAccountingConfig | `models/store_accounting_config.dart` |
| AccountMapping | `models/account_mapping.dart` |
| AccountingExport | `models/accounting_export.dart` |
| AutoExportConfig | `models/auto_export_config.dart` |

### `features/zatca/` — ZATCA Compliance
| Model | File |
|---|---|
| ZatcaInvoice | `models/zatca_invoice.dart` |
| ZatcaCertificate | `models/zatca_certificate.dart` |

### `features/notifications/` — Notifications
| Model | File |
|---|---|
| Notification | `models/notification.dart` |
| NotificationPreference | `models/notification_preference.dart` |
| FcmToken | `models/fcm_token.dart` |

### `features/reports/` — Reports & Analytics
| Model | File |
|---|---|
| ProductSalesSummary | `models/product_sales_summary.dart` |
| DailySalesSummary | `models/daily_sales_summary.dart` |

### `features/staff/` — Staff Management
| Model | File |
|---|---|
| StaffUser | `models/staff_user.dart` |
| StaffRole | `models/staff_role.dart` |
| StaffPermission | `models/staff_permission.dart` |
| AttendanceRecord | `models/attendance_record.dart` |
| BreakRecord | `models/break_record.dart` |
| EmployeeSchedule | `models/employee_schedule.dart` |
| TipDistribution | `models/tip_distribution.dart` |
| CommissionRule | `models/commission_rule.dart` |
| CommissionRecord | `models/commission_record.dart` |

### `features/branches/` — Multi-Store
| Model | File |
|---|---|
| Organization | `models/organization.dart` |
| Store | `models/store.dart` |
| Register | `models/register.dart` |

### `features/subscription/` — Plans & Billing (Provider View)
| Model | File |
|---|---|
| ProviderSubscription | `models/provider_subscription.dart` |
| ProviderInvoice | `models/provider_invoice.dart` |
| ProviderUsageRecord | `models/provider_usage_record.dart` |
| SubscriptionPlan | `models/subscription_plan.dart` |

### `features/settings/` — App Settings
| Model | File |
|---|---|
| UserPreference | `models/user_preference.dart` |
| TranslationOverride | `models/translation_override.dart` |

### `features/pos_customization/` — POS Customization
| Model | File |
|---|---|
| PosCustomizationSetting | `models/pos_customization_setting.dart` |
| ReceiptTemplate | `models/receipt_template.dart` |
| QuickAccessConfig | `models/quick_access_config.dart` |

### `features/support/` — Support & Help
| Model | File |
|---|---|
| SupportTicket | `models/support_ticket.dart` |
| SupportTicketMessage | `models/support_ticket_message.dart` |

### `features/hardware/` — Hardware Configuration
| Model | File |
|---|---|
| HardwareConfiguration | `models/hardware_configuration.dart` |
| HardwareEventLog | `models/hardware_event_log.dart` |

### `features/labels/` — Label Printing
| Model | File |
|---|---|
| LabelTemplate | `models/label_template.dart` |
| LabelPrintHistory | `models/label_print_history.dart` |

### `features/industry_pharmacy/`
| Model | File |
|---|---|
| Prescription | `models/prescription.dart` |
| DrugSchedule | `models/drug_schedule.dart` |

### `features/industry_jewelry/`
| Model | File |
|---|---|
| DailyMetalRate | `models/daily_metal_rate.dart` |
| JewelryProductDetail | `models/jewelry_product_detail.dart` |
| BuybackTransaction | `models/buyback_transaction.dart` |

### `features/industry_electronics/`
| Model | File |
|---|---|
| DeviceImeiRecord | `models/device_imei_record.dart` |
| RepairJob | `models/repair_job.dart` |
| TradeInRecord | `models/trade_in_record.dart` |

### `features/industry_florist/`
| Model | File |
|---|---|
| FlowerArrangement | `models/flower_arrangement.dart` |
| FlowerFreshnessLog | `models/flower_freshness_log.dart` |
| FlowerSubscription | `models/flower_subscription.dart` |

### `features/industry_bakery/`
| Model | File |
|---|---|
| BakeryRecipe | `models/bakery_recipe.dart` |
| ProductionSchedule | `models/production_schedule.dart` |
| CustomCakeOrder | `models/custom_cake_order.dart` |

### `features/industry_restaurant/`
| Model | File |
|---|---|
| RestaurantTable | `models/restaurant_table.dart` |
| KitchenTicket | `models/kitchen_ticket.dart` |
| TableReservation | `models/table_reservation.dart` |
| OpenTab | `models/open_tab.dart` |

---

## Shared Enums (in `lib/core/enums/`)

| Enum | Used By |
|---|---|
| `SyncStatus` | pos_terminal, inventory, delivery_integration, thawani_integration |
| `BusinessType` | branches, catalog, onboarding, settings |
| `Currency` | branches, payments, subscription |
| `Locale` | auth, settings |

All other enums stay in their feature's `enums/` folder.

---

## Data Flow Architecture

```
┌─────────────────────────────────────────────────────────┐
│  UI Layer (Pages + Widgets)                             │
│  ↕ reads/watches Riverpod providers                     │
├─────────────────────────────────────────────────────────┤
│  Providers Layer (Riverpod)                             │
│  ↕ calls repository methods                             │
├─────────────────────────────────────────────────────────┤
│  Repository Layer                                       │
│  ↕ coordinates local ← → remote                        │
├──────────────────────────┬──────────────────────────────┤
│  Local (Drift DAO)       │  Remote (Dio API Service)    │
│  SQLite queries          │  REST API calls              │
├──────────────────────────┴──────────────────────────────┤
│  Models Layer (Dart classes with fromJson/toJson)       │
│  + Enums (string-backed with .value)                    │
└─────────────────────────────────────────────────────────┘
```

### Flow Rules
- **Pages** only call providers (via `ref.watch` / `ref.read`)
- **Providers** only call repositories
- **Repositories** decide: read from local DAO (offline-first), fall back to remote API, queue writes for sync
- **DAOs** only touch Drift/SQLite — never call API
- **API Services** only touch Dio/HTTP — never call SQLite
- **Models** are pure data classes — no business logic, no side effects

---

## Model Class Pattern

Every model in `features/{feature}/models/` follows this pattern (matching the generated models in `POS/flutter/models/`):

```dart
import '../enums/order_status.dart';

class Order {
  final String id;                    // Non-nullable: NOT NULL in schema
  final String? customerId;           // Nullable: no NOT NULL in schema
  final OrderStatus status;           // Enum type from casts
  final double subtotal;
  final DateTime? createdAt;

  const Order({
    required this.id,                 // required for non-nullable
    this.customerId,                  // optional for nullable
    required this.status,
    required this.subtotal,
    this.createdAt,
  });

  factory Order.fromJson(Map<String, dynamic> json) {
    return Order(
      id: json['id'] as String,
      customerId: json['customer_id'] as String?,
      status: OrderStatus.fromValue(json['status'] as String),
      subtotal: (json['subtotal'] as num).toDouble(),
      createdAt: json['created_at'] != null
          ? DateTime.parse(json['created_at'] as String)
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'customer_id': customerId,
      'status': status.value,
      'subtotal': subtotal,
      'created_at': createdAt?.toIso8601String(),
    };
  }

  Order copyWith({ ... });           // Immutable update
  @override bool operator ==(...);   // Equality by id
  @override int get hashCode => ...;
  @override String toString() => ...;
}
```

---

## Enum Class Pattern

Every enum in `features/{feature}/enums/` follows this pattern (matching `POS/flutter/enums/`):

```dart
enum OrderStatus {
  newValue('new'),          // 'new' is Dart reserved → newValue
  preparing('preparing'),
  completed('completed');

  const OrderStatus(this.value);
  final String value;

  static OrderStatus fromValue(String value) {
    return OrderStatus.values.firstWhere(
      (e) => e.value == value,
      orElse: () => throw ArgumentError('Invalid OrderStatus: $value'),
    );
  }

  static OrderStatus? tryFromValue(String? value) {
    if (value == null) return null;
    try { return fromValue(value); } catch (_) { return null; }
  }
}
```

---

## Drift Table Pattern

```dart
// features/catalog/data/local/tables/products_table.dart
import 'package:drift/drift.dart';

class Products extends Table {
  TextColumn get id => text()();
  TextColumn get organizationId => text()();
  TextColumn get categoryId => text().nullable()();
  TextColumn get name => text()();
  TextColumn get nameAr => text().nullable()();
  RealColumn get sellPrice => real()();
  RealColumn get costPrice => real().nullable()();
  TextColumn get unit => text().nullable()();  // Enum stored as string
  BoolColumn get isActive => boolean().withDefault(const Constant(true))();
  DateTimeColumn get createdAt => dateTime().nullable()();
  DateTimeColumn get deletedAt => dateTime().nullable()();

  @override
  Set<Column> get primaryKey => {id};
}
```

---

## Riverpod Provider Pattern

```dart
// features/catalog/providers/product_providers.dart
import 'package:riverpod/riverpod.dart';
import '../repositories/product_repository.dart';
import '../models/product.dart';

final productRepositoryProvider = Provider<ProductRepository>((ref) {
  return ProductRepository(
    dao: ref.read(productDaoProvider),
    api: ref.read(productApiProvider),
  );
});

final productListProvider = FutureProvider.family<List<Product>, String>(
  (ref, categoryId) {
    return ref.read(productRepositoryProvider).getByCategory(categoryId);
  },
);

final productSearchProvider = StateNotifierProvider<ProductSearchNotifier, AsyncValue<List<Product>>>(
  (ref) => ProductSearchNotifier(ref.read(productRepositoryProvider)),
);
```

---

## Navigation / Routing Convention

Routes match the POS_PAGES_STRUCTURE.md paths:

```dart
// core/router/route_names.dart
class Routes {
  static const login = '/login';
  static const loginPin = '/login/pin';
  static const dashboard = '/dashboard';
  static const pos = '/pos';
  static const posCheckout = '/pos/checkout';
  static const posShiftOpen = '/pos/shift/open';
  static const products = '/products';
  static const productsAdd = '/products/add';
  static const productDetail = '/products/:id';
  static const orders = '/orders';
  static const orderDetail = '/orders/:id';
  static const reports = '/reports';
  static const reportsSales = '/reports/sales';
  static const settings = '/settings';
  // ... ~342 routes total
}
```

---

## Key Packages (pubspec.yaml)

```yaml
dependencies:
  flutter:
    sdk: flutter

  # State Management
  flutter_riverpod: ^2.5.0
  riverpod_annotation: ^2.3.0

  # Networking
  dio: ^5.4.0
  connectivity_plus: ^6.0.0

  # Local Database
  drift: ^2.15.0
  sqlite3_flutter_libs: ^0.5.0

  # Routing
  go_router: ^14.0.0

  # Authentication
  flutter_secure_storage: ^9.0.0

  # UI
  flutter_adaptive_scaffold: ^0.2.0
  fl_chart: ^0.68.0

  # Localisation
  flutter_localizations:
    sdk: flutter
  intl: ^0.19.0

  # Printing
  esc_pos_printer: ^4.1.0
  esc_pos_utils: ^1.1.0
  pdf: ^3.10.0
  printing: ^5.12.0

  # Barcode
  barcode: ^2.2.0
  qr_flutter: ^4.1.0

  # ZATCA Crypto
  pointycastle: ^3.9.0
  asn1lib: ^1.5.0
  xml: ^6.5.0

  # Hardware
  flutter_libserialport: ^0.4.0
  window_manager: ^0.4.0
  system_tray: ^2.0.0

  # Storage
  shared_preferences: ^2.3.0
  path_provider: ^2.1.0

  # Monitoring
  sentry_flutter: ^8.0.0

dev_dependencies:
  flutter_test:
    sdk: flutter
  riverpod_generator: ^2.4.0
  drift_dev: ^2.15.0
  build_runner: ^2.4.0
  mockito: ^5.4.0
  integration_test:
    sdk: flutter
```

---

## Checklist: Adding a New Feature

1. Create `lib/features/{feature_name}/` with the template structure
2. Add models in `models/`, enums in `enums/`
3. Create Drift tables in `data/local/tables/` if offline storage needed
4. Create DAO in `data/local/daos/`
5. Create API service in `data/remote/`
6. Create repository in `repositories/`
7. Create Riverpod providers in `providers/`
8. Create pages in `pages/` and widgets in `widgets/`
9. Add routes to `core/router/app_router.dart`
10. Add ARB strings to `core/l10n/arb/app_ar.arb` and `app_en.arb`
11. Register Drift tables in `core/database/app_database.dart`
12. Create tests in `test/features/{feature_name}/`
13. Export via barrel file `{feature_name}.dart`
14. Update this document's Feature → Models mapping

---

## Platform-Specific Considerations

| Platform | Notes |
|---|---|
| **Windows Desktop** (Phase 1) | Primary target. keyboard shortcuts, multi-window (customer display via `window_manager`), USB HID barcode scanners, network printers |
| **Flutter Web** (Phase 1) | Store owner dashboard. No printing, no hardware. Use responsive layout. Drift uses IndexedDB backend. |
| **Android Tablet** (Phase 2) | Same codebase, responsive layout. Bluetooth printing, camera barcode scanning. |
| **iOS/Android Mobile** (Phase 3) | Companion app — reports, push notifications, inventory check. Read-mostly offline cache. |

Use `dart:io` conditionally with `kIsWeb` checks. Hardware features wrapped in platform-aware services.

---

## Naming Conventions Summary

| Item | Convention | Example |
|---|---|---|
| Feature folders | `snake_case` | `pos_terminal/`, `industry_pharmacy/` |
| Dart files | `snake_case.dart` | `product_list_page.dart` |
| Classes | `PascalCase` | `ProductListPage`, `OrderStatus` |
| Enum values | `camelCase` | `newValue`, `preparing`, `pickedUp` |
| Providers | `camelCase` + type suffix | `productListProvider`, `orderDetailProvider` |
| Routes | `/kebab-path` or `/snake_path` | `/pos/shift/open`, `/products/:id` |
| JSON keys | `snake_case` | `created_at`, `store_id`, `order_number` |
| Dart fields | `camelCase` | `createdAt`, `storeId`, `orderNumber` |

---

*Document Version: 1.0*
*Created: 8 March 2026*
*Features: 30 feature modules + core*
*Models: 254 across all features (162 enums)*
*Source: database_schema.sql, technologies_to_use.md, POS_PAGES_STRUCTURE.md, POS/flutter/models/, POS/flutter/enums/*
