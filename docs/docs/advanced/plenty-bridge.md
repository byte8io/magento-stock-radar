---
title: PlentyONE bridge
description: byte8/module-stock-radar-plenty — the paid bridge that brings live PlentyONE inventory data into the demand heatmap and back-in-stock email.
---

# PlentyONE bridge

`byte8/module-stock-radar-plenty` is a **paid** companion module for DACH stores running PlentyONE as their ERP. It extends the demand heatmap with live ERP data and enriches the back-in-stock email with PO information — turning Stock Radar from "back-in-stock notifier" into "ERP-aware reorder dashboard."

## What you get

### Demand heatmap with live ERP data

Four extra columns sourced from `plenty_stock_entity`, joined into the same single SQL pass:

| Column | Source | Meaning |
|---|---|---|
| **Plenty physical** | `SUM(stock_physical)` | Units actually on shelves across all warehouses |
| **Plenty net** | `SUM(stock_net)` | Physical minus reservations |
| **Inbound (PO)** | `SUM(reorder_delta)` | Units already on order from suppliers |
| **Last Plenty sync** | `MAX(processed_at)` | When Plenty last pushed an update for this product |

A merchandiser opening the heatmap can now answer two questions at once: **"what should I reorder?"** and **"what's already on the way?"** — without leaving Magento, without crosschecking PlentyONE.

### Enriched back-in-stock email

Adds a new email template `byte8_stock_radar_email_template_with_inbound`. Switch to it in **Stores → Configuration → Byte8 → Stock Radar → Email → Template**.

The template injects four extra variables onto the product:

```
{{var product.plenty_physical_qty}}
{{var product.plenty_net_qty}}
{{var product.plenty_inbound_qty}}
{{var product.plenty_latest_synced_at}}
```

When values are present, the email renders a "Live warehouse data" callout:

> **Good news — it's back!**
>
> Stylish Sneakers (SKU-12345) is back in stock at Acme Store.
>
> **Live warehouse data:** 47 units on hand right now · 200 more arriving from suppliers
>
> [View product]

When values are zero or null (e.g. product not tracked in Plenty), the callout silently disappears via `{{depend product.plenty_physical_qty}}` — same template works for tracked and untracked products.

## Pricing

| Tier | Price | Scope |
|---|---|---|
| Single store | **€199/year** | One Magento instance |
| Multi-store | **€499/year** | Up to 5 Magento instances |
| Enterprise | Custom | 5+ instances, custom dashboards |

Bundling — same pattern as the rest of the Byte8 paid catalogue:

| Scenario | Recommendation |
|---|---|
| Pro Service Support Plan subscriber | Single tier free |
| Multi-module SaaS suite ≥ €1,000/year | Single tier free |
| Magento + Plenty integration project ≥ €15k | Single tier free for year 1 |
| Direct purchase | €199/year Single, €499/year Multi-store |

See the full commercial concept in [`packages/docs/stock-radar/PRODUCT_CONCEPT.md`](https://github.com/byte8io/magento-stock-radar/blob/main/packages/docs/stock-radar/PRODUCT_CONCEPT.md).

## Install

```bash
composer config repositories.byte8 composer https://byte8.repo.packagist.com/your-key/
composer require byte8/module-stock-radar-plenty
bin/magento module:enable Byte8_StockRadarPlenty
bin/magento setup:upgrade
bin/magento setup:di:compile
```

Requires `byte8/module-stock-radar` and `byte8/module-plenty-stock` to be installed and enabled.

## Architecture

The bridge is **plugins only** — no new tables, no new admin pages, no new cron.

| Hook | Class | Purpose |
|---|---|---|
| `afterGetSelect` on `Byte8\StockRadar\Ui\Component\Demand\Collection` | `Plugin\Demand\CollectionPlugin` | LEFT-joins a per-product Plenty aggregate subquery onto the heatmap collection. Idempotent — safe to call multiple times during query lifecycle. |
| `beforeNotify` on `Byte8\StockRadar\Model\Notifier` | `Plugin\Notifier\EnrichWithInboundPlugin` | Reads `plenty_stock_entity` for the product being notified, attaches the result to the product object so the email template can access it. Best-effort — never breaks a notification on Plenty errors. |

The heatmap join uses a subquery aggregate (not a raw table join) so Plenty's per-warehouse rows don't multiply the parent `GROUP BY (product_id, store_id)`. SQL stays single-pass.

## What this bridge does *not* do

- **Does not modify the dispatch logic.** The free Stock Radar's throttled batched dispatch is the canonical path; the bridge enriches what gets sent, not when.
- **Does not push back to PlentyONE.** Read-only join. Subscriber data stays in Magento.
- **Does not require any PlentyONE configuration changes.** The bridge reads `plenty_stock_entity` which `byte8/module-plenty-stock` already maintains.
- **Does not work with non-PlentyONE ERPs.** A Shopware / SAP / Microsoft Dynamics bridge would follow the same pattern but be a separate module — the join target table differs.

## Future Plenty extensions (roadmap)

- **PO ETA in the email** — read `purchase_order.expected_delivery_at` (when the field is available in `plenty_stock_entity` extension table) and render "arriving from suppliers by 2026-05-10".
- **Source-aware dispatch** — only fire notifications when the **primary sales source** gets restocked, not when a back-office warehouse does. Avoids "back in stock!" emails for SKUs that customers can't actually buy yet.
- **Demand → reorder push** — one-click create-PO flow from the heatmap row. Requires PlentyONE write-API access.
