# Stock Radar for Magento 2

Back-in-stock notifications that scale. Customers subscribe to out-of-stock products and get notified when inventory returns — but unlike most "notify me" extensions, Stock Radar is built for stores that move real volume.

Compatible with **Luma** and **Hyvä** themes, and exposes a full GraphQL surface for headless storefronts.

## Why another back-in-stock module

Most free competitors blast all subscribers in one go and forget about the merchandiser. Stock Radar fixes both ends:

- **Throttled batched notifications** — when 800 people subscribed to a sold-out hero SKU, dispatch is staggered over a configurable window (default 30 min) so a single restock event doesn't crash inventory or your mail provider.
- **Per-variant subscriptions on configurables** — subscribe to "Red, M" specifically, not just the parent.
- **Demand heatmap in admin** — sortable grid of products with the most pending subscriptions, so reorder decisions are data-driven.
- **GraphQL + REST endpoints** — Hyvä, headless, and PWA Studio out of the box.
- **GDPR-first** — guest subscribers stored with hashed email + unsubscribe token; data subject deletion lookups are O(1) by `email_hash`.
- **Pingbell integration (optional)** — high-demand product alerts piped through `Byte8_Pingbell` into the Magento admin notification inbox.

## Features

### Subscription
- "Notify me" button on out-of-stock product pages (Luma + Hyvä blocks, GraphQL mutation `byte8StockRadarSubscribe`)
- Customer + guest support
- Per-variant subscriptions for configurable products
- One-click unsubscribe via signed token
- Per-store email/storefront templates

### Dispatch
- Stock observer queues subscriptions into `byte8_stock_radar_dispatch` when `is_in_stock` flips from 0 to 1
- Cron worker drains the queue with a configurable throttle window
- Failed sends retried with exponential backoff (max 3 attempts)
- Skips subscriptions older than the configurable expiry (default 90 days)

### Admin
- Subscription grid (`Byte8 → Stock Radar → Subscriptions`)
- Demand heatmap (`Byte8 → Stock Radar → Demand`) — products ranked by pending subscriber count
- Per-store config (`Stores → Configuration → Byte8 → Stock Radar`):
  - enable/disable
  - throttle window (minutes)
  - subscription expiry (days)
  - email sender / template
  - Pingbell threshold (notify admin when subscriber count for a single SKU exceeds N)

### GraphQL
```graphql
mutation { byte8StockRadarSubscribe(input: { sku: "ABC-123", email: "x@y.com" }) { success message } }
mutation { byte8StockRadarUnsubscribe(token: "...") { success } }
query   { byte8StockRadarSubscriptions { items { sku created_at status } } }
```

## Database

Two tables:

- `byte8_stock_radar_subscription` — one row per (product, email, store)
- `byte8_stock_radar_dispatch` — staggered send queue, drained by cron

See `etc/db_schema.xml` for the full schema.

## Optional companion modules

- **`byte8/module-pingbell`** — admin notification bell badge for high-demand products
- **`byte8/module-stock-radar-plenty`** — bridges Stock Radar with the Byte8 PlentyONE connector so notifications fire on ERP-confirmed inbound stock, not just Magento stock saves

## Installation

```bash
composer require byte8/module-stock-radar
bin/magento module:enable Byte8_StockRadar
bin/magento setup:upgrade
bin/magento setup:di:compile
```

## Cron

The dispatch worker runs every minute by default (`byte8_stock_radar_dispatch`). Make sure Magento's `default` cron group is active.

## Support

Byte8 Ltd — support@byte8.io
