# Changelog

All notable changes to `byte8/module-stock-radar` are documented here. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); subsequent releases are written automatically by [release-please](https://github.com/googleapis/release-please) from Conventional Commits — see [`RELEASING.md`](./RELEASING.md).

## [1.0.0] — 2026-05-13

Initial public release.

### Features

#### Core

- "Notify me when back in stock" button on out-of-stock product pages — Luma block + GraphQL mutation `byte8StockRadarSubscribe`. Hyvä support lives in [`byte8/module-stock-radar-hyva`](https://github.com/byte8io/magento-stock-radar-hyva).
- Per-variant subscriptions on configurable products — clicking an out-of-stock swatch publishes the simple SKU and reveals the subscribe form (overrides the default disabled state on OOS swatch options).
- Guest and customer subscriptions; logged-in customer email is pre-filled.
- Customer-account page "My Stock Notifications" lists pending + notified subscriptions with a one-click Cancel.
- Storefront session remembers SKUs the visitor has just subscribed to, so a page reload renders a "you're on the list" panel instead of an empty form.

#### Dispatch + observer

- `cataloginventory_stock_item_save_after` observer enqueues a dispatch row per pending subscription when `is_in_stock` flips 0→1 (or qty 0→positive). Re-saves with unchanged stock are filtered.
- Random `scheduled_at` offset across an admin-configurable throttle window (default 30 minutes) so a single restock event never blasts all subscribers at once.
- Per-website subscription filter — a stock-save on store A only dispatches subscriptions for products actually assigned to that store's website.
- Dispatch cron drains the queue every minute, max 200 rows per tick.
- **Exponential backoff** on transient send failures: `2^attempts` minutes (capped at 60 min) before retry; row is marked failed after `MAX_ATTEMPTS = 3`.
- Nightly expiry cron cancels pending subscriptions older than the configured expiry (default 90 days).
- Notifier emulates the frontend area, sends via standard `Magento\Framework\Mail\Template\TransportBuilder`. Any SMTP module in the stack delivers the email.

#### GraphQL surface (headless)

- `byte8StockRadarSubscribe(input: { sku, email }): { success, created, message }`
- `byte8StockRadarUnsubscribe(token): { success, message }` — constant-response by design (no token enumeration).
- `byte8StockRadarMySubscriptions: { items, total_count }` — requires customer auth.

#### Admin

- Subscriptions grid (`Byte8 → Stock Radar → Subscriptions`) with SKU + product name + email + status + customer/guest flag + timestamps. Per-row Cancel + Edit Product actions. Mass-cancel from the toolbar. Status filter includes Awaiting Confirmation, Pending, Notified, Cancelled, Bounced.
- Demand Heatmap (`Byte8 → Stock Radar → Demand Heatmap`) — aggregates pending subscriber counts per product+store, sortable, with Edit Product action.
- Per-store config under `Stores → Configuration → Byte8 → Stock Radar`: enable, throttle window, expiry days, email sender + template, admin-alert threshold + dual transport (admin bell inbox + transactional email to a configured recipient, each toggleable independently — see `docs/advanced/admin-alerts.md`).

#### Abuse protection (admin-toggleable)

All five protections individually toggleable under `Stores → Configuration → Byte8 → Stock Radar → Abuse protection`.

- **Rate limiter** — per-IP (default 5 / 5 min) and per-email-hash (default 3 / hour) buckets backed by `CacheInterface`. Applies to both controller and GraphQL paths. **Enabled by default.**
- **Honeypot** — hidden `website` form field; bot submissions silently succeed at the HTTP layer but skip the DB insert. **Enabled by default.**
- **Hide `created` flag** — GraphQL `byte8StockRadarSubscribe` always returns `created: true`, killing the "is alice@… subscribed to SKU X?" enumeration probe. **Enabled by default.**
- **CAPTCHA** — registers a `byte8_stock_radar_subscribe` form id with `Magento_Captcha`; admin picks the type via core CAPTCHA config. Off by default.
- **Double opt-in** — new `STATUS_UNCONFIRMED` rows + confirmation email + `/stockradar/subscription/confirm?token=…` controller. Restock dispatcher filters by `STATUS_PENDING`, so unconfirmed rows are silently ignored until the user clicks confirm. Off by default. Required for full GDPR posture.

#### CLI commands

- `bin/magento byte8:stock-radar:forget <email> [--dry-run]` — GDPR right-to-be-forgotten. O(1) lookup by `email_hash`, dispatch rows cascade.
- `bin/magento byte8:stock-radar:notify <sku> [--store=<id>] [--dry-run]` — manually enqueue dispatch rows as if the observer had fired. For channels that don't trigger Magento's stock save event.
- `bin/magento byte8:stock-radar:dispatch:run` — drain the queue now (mirrors the cron job).
- `bin/magento byte8:stock-radar:expire` — run the expiry sweep now (mirrors the nightly cron). Reports rows cancelled.
- `bin/magento byte8:stock-radar:cancel [--email=] [--sku=] [--store=] [--status=] [--dry-run]` — bulk cancel by criteria; terminal rows are skipped.
- `bin/magento byte8:stock-radar:stats` — health snapshot: counts by status, oldest pending row, dispatch queue counts, top-10 most-subscribed SKUs.

#### GDPR primitives

- SHA-256 `email_hash` column on every subscription, indexed → O(1) right-to-be-forgotten via the `forget` CLI command or `SubscriptionService::forgetByEmail()`.
- 48-char random unsubscribe token via Magento's `Random` — one-click unsubscribe link in every notification email; constant-response controller hides token validity.

#### Other

- Database schema: `byte8_stock_radar_subscription` (unique on product_id + email_hash + store_id; FK to `catalog_product_entity` and `store`) and `byte8_stock_radar_dispatch` (FK to subscription with CASCADE; status+scheduled_at index).
- i18n: en_US, de_DE.
- Email templates: back-in-stock (`byte8_stock_radar_email_template`), double-opt-in confirmation (`byte8_stock_radar_security_confirmation_email_template`), admin alert (`byte8_stock_radar_admin_alert_email_template`).
- Docusaurus documentation site under `docs/docs/`.

### Documentation

- [`README.md`](./README.md), [`packages/docs/stock-radar/STOCK_RADAR_PRODUCT_CONCEPT.md`](../../docs/stock-radar/STOCK_RADAR_PRODUCT_CONCEPT.md), and [`packages/docs/stock-radar/MANUAL_TEST_PLAN.md`](../../docs/stock-radar/MANUAL_TEST_PLAN.md).
- Docusaurus site: getting-started, configuration, admin, frontend (Luma / Hyvä / VelaFront), advanced (GraphQL / events / GDPR / Plenty bridge / admin alerts).

### License

- MIT.
