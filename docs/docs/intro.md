---
sidebar_position: 1
slug: /
title: Introduction
description: Byte8 Stock Radar — back-in-stock notifications for Magento 2 with throttled batches, per-variant subscriptions, and a real demand heatmap.
---

# Byte8 Stock Radar

A free, MIT-licensed Magento 2 module that handles **back-in-stock notifications** the way they should have been done in 2020 — throttled, per-variant, headless-ready, with a merchandiser-grade **demand heatmap** baked into the admin.

## Why this exists

Most "notify me when back in stock" modules — free or paid — ship the same UX from 2015:

1. **Customer enters email**, gets blasted as soon as inventory flips.
2. **Configurables only subscribe to the parent SKU**, not the specific size/colour the customer wanted.
3. **No admin-side merchandiser tool** — the email goes out and that's it.

Stock Radar fixes all three:

- **Throttled batched notifications** — `random_int(0, throttle_window)` per row spreads a single restock over the configured window (default 30 minutes), avoiding inventory crashes and spam-filter pattern-matching.
- **Per-variant subscriptions** — subscribe to "Red, M" specifically. The variant SKU map is server-emitted so the JS doesn't need to introspect Magento internals.
- **Demand heatmap** — sortable admin grid ranking products by pending subscriber count. Real reorder report, not vanity dashboard.

## Where to start

If you've never installed the module, jump to the [Quick start](/docs/getting-started/quick-start) — Composer install, one config flag, and the cron does the rest.

If you're integrating into a **headless / Hyvä storefront**, the [GraphQL page](/docs/advanced/graphql) and the [Hyvä](/docs/frontend/hyva) and [VelaFront](/docs/frontend/velafront) pages are what you want.

If you're a **merchandiser** evaluating the admin tools, the [Demand heatmap](/docs/admin/demand-heatmap) page is the one to read.

If you're a **DACH merchant running PlentyONE**, the paid [Plenty bridge](/docs/advanced/plenty-bridge) extends the heatmap with live ERP data and enriches the back-in-stock email with PO ETAs.

## What this module is NOT

- **Not** an inventory-management system. Stock Radar reads `cataloginventory_stock_item` transitions and dispatches emails. Inventory still lives wherever Magento puts it (single-source, MSI, or your ERP).
- **Not** a marketing automation tool. We send transactional back-in-stock emails. We do not run drip campaigns, segment audiences, or A/B test subject lines. Use Klaviyo or Mailchimp for that.
- **Not** an ERP connector. The free module knows nothing about purchase orders, supplier ETAs, or warehouse counts. The paid [Plenty bridge](/docs/advanced/plenty-bridge) handles that for PlentyONE specifically; future bridges (Shopware, SAP, Microsoft Dynamics) follow the same pattern.

## Module ecosystem

| Package | Licence | Audience | Repository |
|---|---|---|---|
| `byte8/module-stock-radar` | MIT | All Magento stores | [GitHub](https://github.com/byte8io/magento-stock-radar) |
| `byte8/module-stock-radar-hyva` | MIT | Hyvä storefronts | bundled |
| `byte8/module-stock-radar-plenty` | Commercial | DACH stores running PlentyONE | private |

Install the first two via Composer; the third needs a Byte8 licence key.
