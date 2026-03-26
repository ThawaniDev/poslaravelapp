# Thawani POS — Laravel Project Structure

> **Purpose**: Canonical reference for the Laravel backend project folder structure.
> **Architecture**: Feature-based (domain-driven modules) — NOT the default Laravel flat layout.
> **Created**: 8 March 2026
> **Stack**: Laravel 11 + Filament v3 + PostgreSQL 15+ + Redis + Sanctum

---

## Why Feature-Based?

The default Laravel structure (`app/Models/`, `app/Http/Controllers/`) becomes unmanageable at 255 tables and 47 features. Feature-based grouping:
- Keeps related code together (model + controller + request + resource + service + enum in one folder)
- Makes it obvious what code belongs to what feature
- Enables independent feature development by different team members
- Simplifies code reviews — changes scoped to one folder
- Mirrors the database schema section groupings exactly

---

## Rules (Must Follow)

1. **One feature = one folder** inside `app/Domain/`
2. **Never place a new model in a different feature's folder** — if a table belongs to Catalog per the schema, its model goes in `Domain/Catalog/`
3. **Enums live in their feature folder** under `Enums/`, not in a global `app/Enums/`
4. **Shared enums** used by 3+ features go in `app/Domain/Shared/Enums/`
5. **Cross-feature references**: import from another domain's namespace — never copy/duplicate
6. **API controllers** go in the feature's `Controllers/Api/` subfolder; **Filament resources** go in `Filament/Resources/`
7. **Database migrations** remain in the standard `database/migrations/` directory (Laravel convention) — but prefix migration filenames with the feature name: `2026_03_08_000001_catalog_create_products_table.php`
8. **Tests mirror the domain structure**: `tests/Feature/Domain/Catalog/`, `tests/Unit/Domain/Catalog/`
9. **Route files** per feature in `routes/api/` and auto-loaded via `RouteServiceProvider`
10. **Config**: feature-specific config goes in `config/{feature}.php` (e.g. `config/zatca.php`)

---

## Top-Level Directory Layout

```
thawani-pos-api/
├── app/
│   ├── Domain/                          # ★ ALL business logic lives here
│   │   ├── Shared/                      # Cross-cutting concerns (3+ features)
│   │   ├── Core/                        # Organizations, Stores, Registers
│   │   ├── Auth/                        # Users, PIN, Sanctum, Guards
│   │   ├── AdminPanel/                  # Admin Users, Roles, Permissions, Activity Logs
│   │   ├── SystemConfig/                # System Settings, Feature Flags, Locales, Translations
│   │   ├── Subscription/                # Plans, Billing, Add-Ons, Payment Gateways, Discounts
│   │   ├── ContentOnboarding/           # Business Types, Layout Templates, Themes, Onboarding Steps
│   │   ├── Catalog/                     # Categories, Products, Variants, Barcodes, Combos, Modifiers
│   │   ├── Inventory/                   # Stock Levels, Movements, Adjustments, Transfers, POs, Batches, Recipes
│   │   ├── Promotion/                   # Promotions, Coupons, Bundles
│   │   ├── Customer/                    # Customers, Groups, Loyalty, Store Credit, Digital Receipts
│   │   ├── PosTerminal/                 # POS Sessions, Transactions, Transaction Items, Held Carts, Exchanges, Tax Exemptions
│   │   ├── PosCustomization/            # POS Settings, Receipt Templates, Quick Access Configs
│   │   ├── Order/                       # Orders, Order Items, Modifiers, Status History, Returns, Exchanges, Delivery Info
│   │   ├── Payment/                     # Payments, Cash Sessions, Cash Events, Expenses, Gift Cards, Refunds
│   │   ├── DeliveryIntegration/         # Store Delivery Platforms, Configs, Order Mappings, Menu Sync
│   │   ├── AccountingIntegration/       # Store Accounting Configs, Account Mappings, Exports
│   │   ├── ThawaniIntegration/          # Thawani Store Config, Product Mappings, Order Mappings, Settlements
│   │   ├── ZatcaCompliance/             # ZATCA Invoices, Certificates
│   │   ├── Notification/                # Notifications, Preferences, FCM Tokens, Event Logs, Delivery Logs
│   │   ├── Announcement/               # Platform Announcements, Dismissals, Payment Reminders
│   │   ├── Report/                      # Product Sales Summary, Daily Sales Summary
│   │   ├── PlatformAnalytics/           # Platform Daily Stats, Plan Stats, Feature Adoption, Store Health
│   │   ├── Hardware/                    # Hardware Configurations, Event Logs, Hardware Sales, Implementation Fees
│   │   ├── BackupSync/                  # Backup History, Update Rollouts, Sync Conflicts, Sync Log
│   │   ├── Support/                     # Support Tickets, Messages, Canned Responses
│   │   ├── StaffManagement/             # Staff Users, Roles, Permissions, Attendance, Breaks, Commissions, Scheduling, Tips
│   │   ├── ProviderRegistration/        # Provider Applications, Verification, Store Setup Wizard
│   │   ├── ProviderSubscription/        # Provider Subscriptions, Invoices, Usage
│   │   ├── Security/                    # Admin Sessions, Trusted Devices, IP Allow/Block Lists, Login Attempts, Audit Logs
│   │   ├── LabelPrinting/              # Label Templates, Print History
│   │   ├── NiceToHave/                  # CFD Configs, Signage, Appointments, Gift Registries, Wishlists, Loyalty Challenges/Badges/Tiers
│   │   ├── IndustryPharmacy/            # Prescriptions, Drug Schedules
│   │   ├── IndustryJewelry/             # Daily Metal Rates, Jewelry Product Details, Buyback Transactions
│   │   ├── IndustryElectronics/         # Device IMEI Records, Repair Jobs, Trade-In Records
│   │   ├── IndustryFlorist/             # Flower Arrangements, Freshness Logs, Subscriptions
│   │   ├── IndustryBakery/              # Bakery Recipes, Production Schedules, Custom Cake Orders
│   │   ├── IndustryRestaurant/          # Restaurant Tables, Kitchen Tickets, Reservations, Open Tabs
│   │   └── DeliveryPlatformRegistry/    # Delivery Platforms (master list), Fields, Endpoints, Webhook Templates
│   │
│   ├── Filament/                        # Filament v3 Super Admin Panel
│   │   ├── Resources/                   # CRUD resources (auto-generated from Domain models)
│   │   ├── Pages/                       # Custom Filament pages (Dashboard, Analytics, etc.)
│   │   ├── Widgets/                     # Dashboard widgets
│   │   └── Navigation/                  # Panel, Menu, Sidebar configuration
│   │
│   ├── Http/
│   │   ├── Middleware/                  # Global middleware (CORS, TenantScope, RateLimiting)
│   │   └── Kernel.php
│   │
│   ├── Providers/                       # Service providers (auto-discovery + feature registration)
│   │   ├── AppServiceProvider.php
│   │   ├── RouteServiceProvider.php
│   │   ├── EventServiceProvider.php
│   │   └── AuthServiceProvider.php
│   │
│   └── Console/
│       ├── Kernel.php
│       └── Commands/                    # Artisan commands (grouped by feature if needed)
│
├── config/
│   ├── app.php
│   ├── auth.php
│   ├── database.php
│   ├── filesystems.php
│   ├── horizon.php
│   ├── sanctum.php
│   ├── zatca.php                        # ZATCA-specific config
│   ├── thawani.php                      # Thawani integration config
│   ├── sync.php                         # Sync intervals, conflict resolution
│   ├── pos.php                          # POS defaults (rounding, receipt, etc.)
│   └── subscription.php                 # Plan limits, trial days, etc.
│
├── database/
│   ├── migrations/                      # Prefixed by feature: catalog_, order_, etc.
│   ├── seeders/
│   │   ├── DatabaseSeeder.php
│   │   ├── BusinessTypeSeeder.php
│   │   ├── PermissionSeeder.php
│   │   ├── SystemSettingsSeeder.php
│   │   └── DemoDataSeeder.php
│   └── factories/                       # Model factories for testing
│
├── routes/
│   ├── api/                             # ★ One route file per feature
│   │   ├── auth.php
│   │   ├── core.php
│   │   ├── catalog.php
│   │   ├── inventory.php
│   │   ├── pos.php
│   │   ├── orders.php
│   │   ├── payments.php
│   │   ├── customers.php
│   │   ├── promotions.php
│   │   ├── thawani.php
│   │   ├── zatca.php
│   │   ├── delivery.php
│   │   ├── accounting.php
│   │   ├── notifications.php
│   │   ├── reports.php
│   │   ├── staff.php
│   │   ├── settings.php
│   │   ├── hardware.php
│   │   ├── support.php
│   │   ├── labels.php
│   │   ├── subscription.php
│   │   └── industry.php                 # Pharmacy, Jewelry, Electronics, Florist, Bakery, Restaurant
│   ├── web.php                          # Filament + web dashboard routes
│   └── console.php
│
├── resources/
│   ├── views/
│   │   └── filament/                    # Custom Filament blade views (if any)
│   ├── lang/
│   │   ├── ar/                          # Arabic translations
│   │   └── en/                          # English translations
│   └── css/ & js/                       # Filament asset overrides
│
├── storage/                             # Laravel standard (logs, cache, uploads)
├── tests/
│   ├── Feature/
│   │   └── Domain/                      # Mirrors app/Domain/ structure
│   │       ├── Auth/
│   │       ├── Catalog/
│   │       ├── Inventory/
│   │       ├── PosTerminal/
│   │       ├── Order/
│   │       └── ...
│   ├── Unit/
│   │   └── Domain/                      # Mirrors app/Domain/ structure
│   └── Integration/                     # API integration tests
│
├── docker/
│   ├── Dockerfile
│   ├── docker-compose.yml               # Laravel + PostgreSQL + Redis + Horizon
│   └── nginx/
│
├── .github/
│   └── workflows/
│       ├── ci.yml                       # Lint + test on push
│       └── deploy.yml                   # Deploy to staging/production
│
├── composer.json
├── artisan
├── .env.example
└── README.md
```

---

## Feature Folder Internal Structure (Template)

Every feature folder inside `app/Domain/{Feature}/` follows this exact layout:

```
Domain/Catalog/
├── Models/
│   ├── Category.php
│   ├── Product.php
│   ├── ProductBarcode.php
│   ├── ProductVariantGroup.php
│   ├── ProductVariant.php
│   ├── ProductImage.php
│   ├── ComboProduct.php
│   ├── ComboProductItem.php
│   ├── ModifierGroup.php
│   ├── ModifierOption.php
│   ├── Supplier.php
│   ├── ProductSupplier.php
│   ├── StorePrice.php
│   └── InternalBarcodeSequence.php
│
├── Enums/
│   ├── ProductUnit.php
│   ├── BarcodeType.php
│   └── VariantDisplayType.php
│
├── Controllers/
│   └── Api/
│       ├── ProductController.php
│       ├── CategoryController.php
│       ├── VariantController.php
│       ├── BarcodeController.php
│       └── SupplierController.php
│
├── Requests/                            # Form request validation classes
│   ├── StoreProductRequest.php
│   ├── UpdateProductRequest.php
│   ├── ImportProductsRequest.php
│   └── StoreCategoryRequest.php
│
├── Resources/                           # API resource transformers (JSON output)
│   ├── ProductResource.php
│   ├── ProductCollection.php
│   ├── CategoryResource.php
│   └── VariantResource.php
│
├── Services/                            # Business logic
│   ├── ProductService.php
│   ├── BarcodeGeneratorService.php
│   ├── ProductImportService.php
│   └── PricingService.php
│
├── Actions/                             # Single-responsibility action classes
│   ├── CreateProduct.php
│   ├── DuplicateProduct.php
│   ├── BulkUpdatePrices.php
│   └── GenerateInternalBarcode.php
│
├── Events/
│   ├── ProductCreated.php
│   ├── ProductUpdated.php
│   ├── ProductDeleted.php
│   └── PriceChanged.php
│
├── Listeners/
│   ├── SyncProductToThawani.php
│   ├── UpdateStockOnProductDelete.php
│   └── LogPriceChange.php
│
├── Jobs/
│   ├── BulkImportProducts.php
│   └── SyncCatalogToThawani.php
│
├── Policies/
│   └── ProductPolicy.php
│
├── Observers/
│   └── ProductObserver.php
│
├── Scopes/
│   ├── ActiveScope.php
│   └── StoreScope.php
│
├── DTOs/                                # Data Transfer Objects (structured input)
│   ├── ProductData.php
│   └── ImportRowData.php
│
├── Exceptions/
│   ├── ProductNotFoundException.php
│   └── DuplicateSkuException.php
│
└── Tests/                               # Optional: co-located tests (or use top-level tests/)
    ├── ProductServiceTest.php
    └── BarcodeGeneratorTest.php
```

---

## Complete Feature → Tables Mapping

This is the **definitive mapping** from database schema sections to Domain folders. When adding a new table, find its schema section below and place it in the corresponding Domain.

### `Domain/Core/` — PROVIDER CORE: Organizations & Stores
| Table | Model |
|---|---|
| `organizations` | Organization |
| `stores` | Store |
| `registers` | Register |

### `Domain/Auth/` — Users & Authentication
| Table | Model |
|---|---|
| `users` | User |

### `Domain/AdminPanel/` — PLATFORM: Admin Users & Roles
| Table | Model |
|---|---|
| `admin_users` | AdminUser |
| `admin_roles` | AdminRole |
| `admin_permissions` | AdminPermission |
| `admin_role_permissions` | AdminRolePermission |
| `admin_user_roles` | AdminUserRole |
| `admin_activity_logs` | AdminActivityLog |

### `Domain/SystemConfig/` — PLATFORM: System Configuration
| Table | Model |
|---|---|
| `system_settings` | SystemSetting |
| `feature_flags` | FeatureFlag |
| `supported_locales` | SupportedLocale |
| `master_translation_strings` | MasterTranslationString |
| `translation_versions` | TranslationVersion |
| `accounting_integration_configs` | AccountingIntegrationConfig |
| `payment_methods` | PaymentMethod |
| `certified_hardware` | CertifiedHardware |
| `tax_exemption_types` | TaxExemptionType |
| `age_restricted_categories` | AgeRestrictedCategory |
| `thawani_marketplace_config` | ThawaniMarketplaceConfig |

### `Domain/Subscription/` — PLATFORM: Subscription Plans & Billing
| Table | Model |
|---|---|
| `subscription_plans` | SubscriptionPlan |
| `plan_feature_toggles` | PlanFeatureToggle |
| `plan_limits` | PlanLimit |
| `plan_add_ons` | PlanAddOn |
| `subscription_discounts` | SubscriptionDiscount |
| `payment_gateway_configs` | PaymentGatewayConfig |
| `payment_retry_rules` | PaymentRetryRule |

### `Domain/ContentOnboarding/` — PLATFORM: Content & Onboarding
| Table | Model |
|---|---|
| `business_types` | BusinessType |
| `pos_layout_templates` | PosLayoutTemplate |
| `platform_ui_defaults` | PlatformUiDefault |
| `themes` | Theme |
| `theme_package_visibility` | ThemePackageVisibility (Pivot) |
| `layout_package_visibility` | LayoutPackageVisibility (Pivot) |
| `receipt_layout_templates` | ReceiptLayoutTemplate |
| `receipt_template_package_visibility` | ReceiptTemplatePackageVisibility (Pivot) |
| `cfd_themes` | CfdTheme |
| `cfd_theme_package_visibility` | CfdThemePackageVisibility (Pivot) |
| `signage_templates` | SignageTemplate |
| `signage_template_business_types` | SignageTemplateBusinessType (Pivot) |
| `signage_template_package_visibility` | SignageTemplatePackageVisibility (Pivot) |
| `label_layout_templates` | LabelLayoutTemplate |
| `label_template_business_types` | LabelTemplateBusinessType (Pivot) |
| `label_template_package_visibility` | LabelTemplatePackageVisibility (Pivot) |
| `business_type_category_templates` | BusinessTypeCategoryTemplate |
| `business_type_shift_templates` | BusinessTypeShiftTemplate |
| `business_type_receipt_templates` | BusinessTypeReceiptTemplate |
| `business_type_industry_configs` | BusinessTypeIndustryConfig |
| `business_type_promotion_templates` | BusinessTypePromotionTemplate |
| `business_type_commission_templates` | BusinessTypeCommissionTemplate |
| `business_type_loyalty_configs` | BusinessTypeLoyaltyConfig |
| `business_type_customer_group_templates` | BusinessTypeCustomerGroupTemplate |
| `business_type_return_policies` | BusinessTypeReturnPolicy |
| `business_type_waste_reason_templates` | BusinessTypeWasteReasonTemplate |
| `business_type_appointment_configs` | BusinessTypeAppointmentConfig |
| `business_type_service_category_templates` | BusinessTypeServiceCategoryTemplate |
| `business_type_gift_registry_types` | BusinessTypeGiftRegistryType |
| `business_type_gamification_badges` | BusinessTypeGamificationBadge |
| `business_type_gamification_challenges` | BusinessTypeGamificationChallenge |
| `business_type_gamification_milestones` | BusinessTypeGamificationMilestone |
| `onboarding_steps` | OnboardingStep |
| `knowledge_base_articles` | KnowledgeBaseArticle |
| `pricing_page_content` | PricingPageContent |

### `Domain/DeliveryPlatformRegistry/` — PLATFORM: Delivery Platform Registry
| Table | Model |
|---|---|
| `delivery_platforms` | DeliveryPlatform |
| `delivery_platform_fields` | DeliveryPlatformField |
| `delivery_platform_endpoints` | DeliveryPlatformEndpoint |
| `delivery_platform_webhook_templates` | DeliveryPlatformWebhookTemplate |

### `Domain/Notification/` — PLATFORM: Notification Templates + PROVIDER: Notifications
| Table | Model |
|---|---|
| `notification_templates` | NotificationTemplate |
| `notification_provider_status` | NotificationProviderStatus |
| `notifications` | Notification |
| `notification_preferences` | NotificationPreference |
| `fcm_tokens` | FcmToken |
| `notification_events_log` | NotificationEventsLog |
| `notification_delivery_logs` | NotificationDeliveryLog |

### `Domain/Security/` — PLATFORM: Security & Audit + PROVIDER CORE: Security
| Table | Model |
|---|---|
| `admin_sessions` | AdminSession |
| `admin_trusted_devices` | AdminTrustedDevice |
| `admin_ip_allowlist` | AdminIpAllowlist |
| `admin_ip_blocklist` | AdminIpBlocklist |
| `login_attempts` | LoginAttempt |
| `audit_logs` | AuditLog |
| `security_events` | SecurityEvent |

### `Domain/StaffManagement/` — PROVIDER CORE: Staff & Attendance + Roles
| Table | Model |
|---|---|
| `staff_users` | StaffUser |
| `staff_roles` | StaffRole |
| `staff_permissions` | StaffPermission |
| `staff_role_permissions` | StaffRolePermission (Pivot) |
| `attendance_records` | AttendanceRecord |
| `break_records` | BreakRecord |
| `employee_schedules` | EmployeeSchedule |
| `tip_distributions` | TipDistribution |
| `commission_rules` | CommissionRule |
| `commission_records` | CommissionRecord |

### `Domain/ProviderRegistration/` — PROVIDER CORE: Provider Registration
| Table | Model |
|---|---|
| `business_type_templates` | BusinessTypeTemplate |
| `onboarding_progress` | OnboardingProgress |

### `Domain/ProviderSubscription/` — PROVIDER CORE: Subscription & Billing
| Table | Model |
|---|---|
| `provider_subscriptions` | ProviderSubscription |
| `provider_invoices` | ProviderInvoice |
| `provider_usage_records` | ProviderUsageRecord |

### `Domain/Shared/` — User Preferences & Localization
| Table | Model |
|---|---|
| `user_preferences` | UserPreference |
| `translation_overrides` | TranslationOverride |

### `Domain/Catalog/` — CATALOG: Categories & Products
| Table | Model |
|---|---|
| `categories` | Category |
| `products` | Product |
| `product_barcodes` | ProductBarcode |
| `store_prices` | StorePrice |
| `product_variant_groups` | ProductVariantGroup |
| `product_variants` | ProductVariant |
| `product_images` | ProductImage |
| `combo_products` | ComboProduct |
| `combo_product_items` | ComboProductItem |
| `modifier_groups` | ModifierGroup |
| `modifier_options` | ModifierOption |
| `suppliers` | Supplier |
| `product_suppliers` | ProductSupplier |
| `internal_barcode_sequence` | InternalBarcodeSequence |

### `Domain/Inventory/` — CATALOG: Inventory
| Table | Model |
|---|---|
| `stock_levels` | StockLevel |
| `stock_movements` | StockMovement |
| `goods_receipts` | GoodsReceipt |
| `goods_receipt_items` | GoodsReceiptItem |
| `stock_adjustments` | StockAdjustment |
| `stock_adjustment_items` | StockAdjustmentItem |
| `stock_transfers` | StockTransfer |
| `stock_transfer_items` | StockTransferItem |
| `purchase_orders` | PurchaseOrder |
| `purchase_order_items` | PurchaseOrderItem |
| `stock_batches` | StockBatch |
| `recipes` | Recipe |
| `recipe_ingredients` | RecipeIngredient |

### `Domain/Promotion/` — CATALOG: Promotions & Coupons
| Table | Model |
|---|---|
| `promotions` | Promotion |
| `promotion_products` | PromotionProduct |
| `promotion_categories` | PromotionCategory |
| `promotion_customer_groups` | PromotionCustomerGroup |
| `coupon_codes` | CouponCode |
| `promotion_usage_log` | PromotionUsageLog |
| `bundle_products` | BundleProduct |

### `Domain/Customer/` — CUSTOMERS: Core + Nice-to-Have
| Table | Model |
|---|---|
| `customers` | Customer |
| `customer_groups` | CustomerGroup |
| `loyalty_transactions` | LoyaltyTransaction |
| `store_credit_transactions` | StoreCreditTransaction |
| `loyalty_config` | LoyaltyConfig |
| `digital_receipt_log` | DigitalReceiptLog |
| `cfd_configurations` | CfdConfiguration |
| `signage_playlists` | SignagePlaylist |
| `appointments` | Appointment |
| `gift_registries` | GiftRegistry |
| `gift_registry_items` | GiftRegistryItem |
| `wishlists` | Wishlist |
| `loyalty_challenges` | LoyaltyChallenge |
| `loyalty_badges` | LoyaltyBadge |
| `loyalty_tiers` | LoyaltyTier |
| `customer_challenge_progress` | CustomerChallengeProgress |
| `customer_badges` | CustomerBadge |

### `Domain/PosTerminal/` — POS TERMINAL: Sessions & Transactions
| Table | Model |
|---|---|
| `pos_sessions` | PosSession |
| `transactions` | Transaction |
| `transaction_items` | TransactionItem |
| `held_carts` | HeldCart |
| `exchange_transactions` | ExchangeTransaction |
| `tax_exemptions` | TaxExemption |

### `Domain/PosCustomization/` — POS TERMINAL: Customization
| Table | Model |
|---|---|
| `pos_customization_settings` | PosCustomizationSetting |
| `receipt_templates` | ReceiptTemplate |
| `quick_access_configs` | QuickAccessConfig |

### `Domain/Order/` — ORDERS: Order Management
| Table | Model |
|---|---|
| `orders` | Order |
| `order_items` | OrderItem |
| `order_item_modifiers` | OrderItemModifier |
| `order_status_history` | OrderStatusHistory |
| `returns` | SaleReturn |
| `return_items` | ReturnItem |
| `exchanges` | Exchange |
| `order_delivery_info` | OrderDeliveryInfo |
| `pending_orders` | PendingOrder |

### `Domain/Payment/` — ORDERS: Payments & Finance
| Table | Model |
|---|---|
| `payments` | Payment |
| `cash_sessions` | CashSession |
| `cash_events` | CashEvent |
| `expenses` | Expense |
| `gift_cards` | GiftCard |
| `gift_card_transactions` | GiftCardTransaction |
| `refunds` | Refund |

### `Domain/DeliveryIntegration/` — INTEGRATIONS: Delivery Platforms
| Table | Model |
|---|---|
| `store_delivery_platforms` | StoreDeliveryPlatform |
| `delivery_platform_configs` | DeliveryPlatformConfig |
| `delivery_order_mappings` | DeliveryOrderMapping |
| `delivery_menu_sync_logs` | DeliveryMenuSyncLog |
| `platform_delivery_integrations` | PlatformDeliveryIntegration |
| `store_delivery_platform_enrollments` | StoreDeliveryPlatformEnrollment |

### `Domain/AccountingIntegration/` — INTEGRATIONS: Accounting
| Table | Model |
|---|---|
| `store_accounting_configs` | StoreAccountingConfig |
| `account_mappings` | AccountMapping |
| `accounting_exports` | AccountingExport |
| `auto_export_configs` | AutoExportConfig |

### `Domain/ThawaniIntegration/` — INTEGRATIONS: Thawani Marketplace
| Table | Model |
|---|---|
| `thawani_store_config` | ThawaniStoreConfig |
| `thawani_product_mappings` | ThawaniProductMapping |
| `thawani_order_mappings` | ThawaniOrderMapping |
| `thawani_settlements` | ThawaniSettlement |

### `Domain/ZatcaCompliance/` — INTEGRATIONS: ZATCA Compliance
| Table | Model |
|---|---|
| `zatca_invoices` | ZatcaInvoice |
| `zatca_certificates` | ZatcaCertificate |

### `Domain/Announcement/` — PLATFORM: Announcements
| Table | Model |
|---|---|
| `platform_announcements` | PlatformAnnouncement |
| `platform_announcement_dismissals` | PlatformAnnouncementDismissal |
| `payment_reminders` | PaymentReminder |

### `Domain/Report/` — REPORTS: Provider Analytics
| Table | Model |
|---|---|
| `product_sales_summary` | ProductSalesSummary |
| `daily_sales_summary` | DailySalesSummary |

### `Domain/PlatformAnalytics/` — REPORTS: Platform Analytics
| Table | Model |
|---|---|
| `platform_daily_stats` | PlatformDailyStat |
| `platform_plan_stats` | PlatformPlanStat |
| `feature_adoption_stats` | FeatureAdoptionStat |
| `store_health_snapshots` | StoreHealthSnapshot |

### `Domain/Hardware/` — HARDWARE: Configuration
| Table | Model |
|---|---|
| `hardware_configurations` | HardwareConfiguration |
| `hardware_event_log` | HardwareEventLog |
| `hardware_sales` | HardwareSale |
| `implementation_fees` | ImplementationFee |

### `Domain/BackupSync/` — OPERATIONS: Backup & Sync
| Table | Model |
|---|---|
| `backup_history` | BackupHistory |
| `update_rollouts` | UpdateRollout |
| `sync_conflicts` | SyncConflict |
| `sync_log` | SyncLog |

### `Domain/Support/` — SUPPORT: Tickets & Help
| Table | Model |
|---|---|
| `support_tickets` | SupportTicket |
| `support_ticket_messages` | SupportTicketMessage |
| `canned_responses` | CannedResponse |

### `Domain/LabelPrinting/`
| Table | Model |
|---|---|
| `label_templates` | LabelTemplate |
| `label_print_history` | LabelPrintHistory |

### `Domain/IndustryPharmacy/`
| Table | Model |
|---|---|
| `prescriptions` | Prescription |
| `drug_schedules` | DrugSchedule |

### `Domain/IndustryJewelry/`
| Table | Model |
|---|---|
| `daily_metal_rates` | DailyMetalRate |
| `jewelry_product_details` | JewelryProductDetail |
| `buyback_transactions` | BuybackTransaction |

### `Domain/IndustryElectronics/`
| Table | Model |
|---|---|
| `device_imei_records` | DeviceImeiRecord |
| `repair_jobs` | RepairJob |
| `trade_in_records` | TradeInRecord |

### `Domain/IndustryFlorist/`
| Table | Model |
|---|---|
| `flower_arrangements` | FlowerArrangement |
| `flower_freshness_log` | FlowerFreshnessLog |
| `flower_subscriptions` | FlowerSubscription |

### `Domain/IndustryBakery/`
| Table | Model |
|---|---|
| `bakery_recipes` | BakeryRecipe |
| `production_schedules` | ProductionSchedule |
| `custom_cake_orders` | CustomCakeOrder |

### `Domain/IndustryRestaurant/`
| Table | Model |
|---|---|
| `restaurant_tables` | RestaurantTable |
| `kitchen_tickets` | KitchenTicket |
| `table_reservations` | TableReservation |
| `open_tabs` | OpenTab |

### `Domain/AppUpdateManagement/` — PLATFORM: App Update Management
| Table | Model |
|---|---|
| `app_releases` | AppRelease |
| `app_update_stats` | AppUpdateStat |

---

## Shared Enums (used by 3+ features)

Place these in `app/Domain/Shared/Enums/`:

| Enum | Used By |
|---|---|
| `SyncStatus` | PosTerminal, Inventory, DeliveryIntegration, ThawaniIntegration |
| `BusinessType` | ContentOnboarding, Core, Catalog, ProviderRegistration |
| `Currency` | Core, Payment, Subscription |
| `Locale` | Core, Auth, Shared |
| `ActiveStatus` | Many features (is_active boolean wrapper) |

All other enums stay in their feature's `Enums/` folder.

---

## Namespace Convention

```php
// Model
namespace App\Domain\Catalog\Models;
class Product extends Model { ... }

// Enum
namespace App\Domain\Catalog\Enums;
enum ProductUnit: string { ... }

// Controller
namespace App\Domain\Catalog\Controllers\Api;
class ProductController extends Controller { ... }

// Service
namespace App\Domain\Catalog\Services;
class ProductService { ... }

// Request
namespace App\Domain\Catalog\Requests;
class StoreProductRequest extends FormRequest { ... }

// Resource
namespace App\Domain\Catalog\Resources;
class ProductResource extends JsonResource { ... }

// Cross-feature import
use App\Domain\Core\Models\Store;
use App\Domain\Auth\Models\User;
use App\Domain\Shared\Enums\SyncStatus;
```

---

## Migration Naming Convention

```
{date}_{sequence}_{feature}_{action}_{table}.php

Examples:
2026_03_08_000001_core_create_organizations_table.php
2026_03_08_000002_core_create_stores_table.php
2026_03_08_000003_auth_create_users_table.php
2026_03_08_000010_catalog_create_categories_table.php
2026_03_08_000011_catalog_create_products_table.php
2026_03_08_000050_order_create_orders_table.php
2026_03_08_000100_industry_pharmacy_create_prescriptions_table.php
```

Sequence numbers grouped by feature (Core: 1-9, Catalog: 10-19, Inventory: 20-29, etc.) to maintain FK dependency order.

---

## API Route Convention

```
/api/v2/{feature}/{resource}

Examples:
GET    /api/v2/catalog/products
POST   /api/v2/catalog/products
GET    /api/v2/catalog/products/{id}
PUT    /api/v2/catalog/products/{id}
DELETE /api/v2/catalog/products/{id}

GET    /api/v2/inventory/stock-levels
POST   /api/v2/inventory/adjustments
GET    /api/v2/pos/sessions/current
POST   /api/v2/pos/transactions
GET    /api/v2/orders
POST   /api/v2/orders/{id}/status
GET    /api/v2/reports/daily-sales
```

---

## Key Packages (composer.json)

```json
{
    "require": {
        "php": "^8.2",
        "laravel/framework": "^11.0",
        "laravel/sanctum": "^4.0",
        "laravel/horizon": "^5.0",
        "filament/filament": "^3.0",
        "spatie/laravel-permission": "^6.0",
        "bezhansalleh/filament-shield": "^3.0",
        "spatie/laravel-activitylog": "^4.0",
        "spatie/laravel-backup": "^9.0",
        "spatie/laravel-media-library": "^11.0",
        "maatwebsite/excel": "^3.1",
        "barryvdh/laravel-dompdf": "^3.0",
        "knuckleswtf/scribe": "^4.0",
        "sentry/sentry-laravel": "^4.0",
        "laravel-notification-channels/fcm": "^4.0"
    }
}
```

---

## Checklist: Adding a New Feature

1. Create `app/Domain/{FeatureName}/` with the template structure above
2. Create models in `Models/`, enums in `Enums/`
3. Create migration in `database/migrations/` with feature prefix
4. Create API route file in `routes/api/{feature}.php`
5. Register route file in `RouteServiceProvider`
6. Create Filament resource in `app/Filament/Resources/` if admin CRUD is needed
7. Create tests in `tests/Feature/Domain/{FeatureName}/`
8. Update this document's Feature → Tables mapping

---

*Document Version: 1.0*
*Created: 8 March 2026*
*Tables: 255 across 37 domain modules*
*Source: database_schema.sql, technologies_to_use.md, POS_PAGES_STRUCTURE.md, provider/platform feature files*
