# Wameed POS — Platform (Admin) Features (Short Reference)

> Features available to the Thawani internal team through the Super Admin Panel.  
> Store owners (providers) have no access to this scope.

---

## 👑 Platform Roles

| Role | Key Access |
|---|---|
| **Super Admin** | Full platform control — stores, billing, infrastructure, all settings |
| **Platform Manager** | Manage providers, approve registrations, assign packages |
| **Support Agent** | Read-only view of any provider account, respond to tickets |
| **Finance Admin** | Billing, subscriptions, invoices, package pricing |
| **Integration Manager** | Create/edit third-party platform configs, test connections |
| **Sales** | View store pipeline, create trial accounts, apply discounts within limits |
| **Viewer** | Read-only dashboard and reports (stakeholders, investors) |
| **Custom Role** | Admin-defined permission combination — pick individual permissions from any category |

- Custom admin roles: create any combination of granular permissions (view stores, edit billing, manage tickets, etc.)
- Assign multiple roles to one admin user
- All role changes audit-logged

---

## 🏪 Provider Management
- List all registered stores / chains with search and filter
- View full store profile: owner details, active plan, branches, staff count, usage
- Approve or reject new provider registrations
- Suspend / reactivate any provider account
- Impersonate a store account for support investigation
- View live usage metrics per store (cashiers, products, orders, sync status)
- View store's POS terminals and ZATCA status
- View store's transaction history
- Export store data
- Assign or change subscription package
- Apply one-off per-store limit overrides (e.g. temporarily raise cashier cap)
- Internal notes and support history per provider
- Manual store onboarding: create store on behalf of a provider
- Cancellation reason tracking when a provider ends subscription
- View which third-party delivery platforms each store has enabled
- View store's employee scheduling, tip distribution, and commission configuration
- View store accounting integration status (connected / disconnected)

---

## 📦 Package & Subscription Management
- Create, edit, and deactivate subscription packages
- **Package Builder** fields per plan:
  - Name (AR / EN), slug, monthly price, annual price
  - Feature toggles: kitchen display, advanced analytics, custom themes, full API access, white-label, multi-branch, loyalty program, advanced coupons, third-party integrations, inventory expiry tracking, recipe inventory, modifier groups, employee scheduling, accounting export, tip management, reservation system, etc.
  - Hard limits: max cashiers, max terminals, max products, max branches, max delivery platforms, set price per additional unit above limit (e.g. $10/month per extra cashier above 5 included in Professional)
  - Highlight flag ("Most Popular" badge on pricing page)
  - Sort order for pricing page display
- Feature-level sub-limits (e.g. max 3 delivery platforms on Professional)
- Plan changes apply immediately to all subscribers on that plan
- Grace period configuration for expired subscriptions
- Trial period settings per plan
- Pricing page content driven by package data
- View all active subscriptions with search and filter
- View subscription details: provider, plan, start date, next billing date, payment method, status
- Manual invoice generation and refund processing
- Apply one-off discounts or credits to a subscription
- Export subscription and billing data
- Cancellation reason tracking when a provider ends subscription

---

## 🚚 Third-Party Delivery Platform Management
- Add any new delivery platform (HungerStation, Keeta, Jahez, Noon Food, Ninja, Mrsool, The Chefz, Talabat, ToYou, or custom) **without code deployment**
- Per-platform configuration:
  - Platform name, logo URL, slug
  - Auth method: Bearer Token, API Key header, Basic Auth, OAuth2
  - Custom key field definitions (label shown to provider + internal key name) — unlimited fields
  - Operation endpoints: product create, product update, product delete, category sync, bulk menu push
  - Inbound order webhook path template
  - Request field mapping (Thawani product schema → platform schema)
- Enable / disable platforms globally
- Test connectivity to any platform's endpoint
- View which providers have each platform enabled
- Monitor platform-wide sync health and error rates

---

## 🔔 Notification Template Management
- Manage notification message templates for all system events in Arabic and English
- Available variable tokens per template (e.g. `{{platform}}`, `{{order_id}}`, `{{total}}`, `{{store_name}}`)
- Events covered: order events, inventory events, finance events, system events, staff events
- Templates apply across all providers on the platform
- Preview rendered notification before saving

---

## 🎨 POS Interface & Layout Management
- **Layout templates per business type**: manage multiple POS view options for each type (e.g. Supermarket has 5 layouts: barcode-scan grid, full product grid, split view, express checkout, self-checkout kiosk; Restaurant has 3: table management, quick-order, KDS-integrated)
- Add, edit, reorder, or disable layout templates per business type
- Set which layout is the default for each business type
- **Handedness defaults**: set platform-wide default (right-hand / left-hand / centered); providers and users can override
- **Font size defaults**: set platform-wide default (small 0.85× / medium 1× / large 1.2× / extra-large 1.5×)
- **Theme management**: create, edit, and deactivate theme presets (Light Classic, Dark Mode, High Contrast, Thawani Brand); add new custom themes with hex colour palette
- Provider and user preferences override the platform default (cascade: user → store → platform)
- Control which customisation options (themes, layouts, handedness, font sizes) are visible per package tier

---

## 📊 Platform Analytics & Reporting
- MRR, ARR, and revenue by package tier
- Total active stores, trials, churn rate, and subscription lifecycle metrics
- New provider registrations trend
- Platform-wide orders processed (daily / monthly / yearly)
- Top-performing stores by GMV
- Feature adoption rates (which features are actively used)
- Third-party integration usage by platform
- Delivery sync error rate per platform
- Support ticket volume and resolution time
- Geographic distribution of stores (map view)
- Hardware type breakdown (printer models, scanner models)
- ZATCA compliance rate across all stores
- System health status dashboard
- Error / crash report aggregation from stores
- API usage metrics (requests, latency, error rate)
- Real-time activity feed (recent signups, payments, tickets)
- Notification delivery analytics (sent, delivered, opened rates per template)
- Speed-of-service benchmarking across restaurant-type stores
- Waste cost aggregation across stores (providers opted in)
- Tip and commission totals across all stores (anonymised trends)

---

## 🎫 Support Ticket System
- View and manage all provider-submitted support tickets
- Assign tickets to support agents
- Internal notes (not visible to provider)
- Status workflow: open → in-progress → resolved → closed
- Priority levels: low, medium, high, critical
- Full ticket history per provider
- SLA tracking per ticket
- Canned responses library (with shortcuts, e.g. `/greeting`)
- Knowledge base / help articles management
- Live chat integration (optional)
- Store remote access for troubleshooting (impersonate + device view)
- Support analytics: ticket volume, response times, resolution rates, agent performance

---

## 💰 Billing & Finance Admin
- View all subscriptions: active, trial, grace period, cancelled
- Invoice history per provider, with manual invoice generation
- Process refunds and credits
- Failed payment handling and retry rules configuration
- Revenue breakdown dashboard by package tier
- Upcoming renewals list
- Payment gateway credentials management (Thawani Pay / Stripe / etc.)
- Subscription discount codes and coupon management for plans
- Add-on management: paid extras (Thawani integration, white-label, API access, accounting export, reservation system) with independent pricing
- Hardware sales tracking (POS terminals, printers, scanners)
- Implementation / training fee tracking per store

---

## 🔐 Security & Audit (Platform)
- All admin actions logged: actor, role, IP, action, timestamp
- Role-based access control: support agents are read-only; finance admins cannot impersonate
- Two-factor authentication enforced for all platform admin accounts
- IP allowlist for admin panel access
- Suspicious activity alerts (brute-force logins, bulk data exports)
- IP blocklist management for known threat sources
- Device trust management (trusted devices per admin account)
- Security event investigation and resolution workflow
- View and revoke active platform admin sessions
- Encrypted storage of all platform-level secrets and credentials

---

## ⚙️ System Configuration
- ZATCA environment toggle (sandbox / production) and API credentials
- Payment gateway credentials and webhook URL management
- SMS provider settings (Unifonic / Taqnyat / Msegat)
- Email provider settings (SMTP / Mailgun / SES)
- FCM / APNs push notification credentials
- WhatsApp Business API configuration
- Maintenance mode toggle with provider-facing banner message
- Feature flags for gradual rollouts to subsets of providers
- Global VAT rate and currency/locale defaults
- Sync conflict resolution policy settings
- Accounting integration settings: API credentials for QuickBooks, Xero, Qoyod (Saudi) — providers connect from their store settings
- Tax exemption category management: define exemption types, required documentation
- Age-restricted product category management: define which categories trigger verification prompt

---

## 🚀 App & Update Management
- Manage Flutter desktop app release versions (Windows, macOS)
- Minimum supported version setting (force-update below this version)
- Force-update flag per version
- Staged rollout percentage per version
- Release notes / changelog (AR / EN) shown in-app on update prompt
- Download URL management per platform per version
- Update channel management: stable, beta
- Rollback capability: revert stores to a previous version
- Auto-rollback trigger on crash-loop detection
- Update statistics: adoption rate, pending updates, failed installs

---

## 👥 User Management (Cross-Store)
- List all users across all stores with search by email / phone
- Reset passwords / force password change
- Disable / enable individual user accounts
- View user activity logs
- Super admin team management (invite, roles, deactivate)

---

## 🛡️ Provider Roles & Permissions Management
- Define the **master list of provider-side permissions** available across the platform (e.g. `pos.sell`, `pos.void`, `pos.discount`, `inventory.adjust`, `reports.view`, `settings.edit`, etc.)
- Manage the **default role templates** shipped to every new provider: Owner, Chain Manager, Branch Manager, Cashier, Inventory Clerk, Accountant, Kitchen Staff
- Edit permission sets of default role templates (changes apply to all new stores)
- **Custom Role** feature flag: enable/disable the ability for providers to create their own custom roles (gated by package tier — e.g. Professional and Enterprise only)
- Set maximum number of custom roles a provider can create per package tier
- View which providers have created custom roles and what permissions they include
- Audit log of all role and permission changes across providers

---

## 🔔 Platform Announcements
- Send announcements to all stores or filtered by plan / region
- Announcement types: info, warning, maintenance, update
- System maintenance notifications with scheduled time window
- Payment reminder automation for upcoming / overdue renewals
- ZATCA deadline alerts
- In-app banner display with start / end date scheduling

---

## ☁️ Infrastructure & Operations
- Queue worker health dashboard (Laravel Horizon)
- Background job failure alerts and manual retry controls
- Cache management (Redis flush, key inspection)
- Storage management: product images, receipts, backup files
- Automated database backup status, schedule, and restore controls
- Server health metrics: CPU, memory, disk, queue depth

---

## 📋 Content & Onboarding Management
- Manage business type options shown to providers during onboarding (supermarket, restaurant, pharmacy, bakery, etc.)
- Manage POS layout templates available per business type
- Manage onboarding flow steps and instructions
- In-app announcement banners (target all providers or specific plan)
- Help articles and integration guides per third-party platform
- Public pricing page content (package names, feature bullet lists, FAQs)
