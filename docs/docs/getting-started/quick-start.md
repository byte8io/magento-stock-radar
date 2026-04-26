---
title: Quick start
description: Install Byte8 Stock Radar in 5 minutes — Composer, enable, run setup:upgrade, flip the on switch.
---

# Quick start

Five-minute install. Composer pulls the module, one CLI command enables it, one config flag turns it on, and the cron does everything else.

## 1. Install via Composer

```bash
composer require byte8/module-stock-radar
```

If you're running Hyvä, also pull the companion:

```bash
composer require byte8/module-stock-radar-hyva
```

## 2. Enable the module

```bash
bin/magento module:enable Byte8_StockRadar Byte8_StockRadarHyva
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

## 3. Turn it on

Go to **Stores → Configuration → Byte8 → Stock Radar → General** and set **Enable** to **Yes**.

That's it. Subscribers can now click "Notify me when back in stock" on out-of-stock product pages.

## 4. Verify the cron

Stock Radar's dispatch worker runs every minute via Magento's `default` cron group:

```bash
bin/magento cron:run --group=default
```

If you're not sure cron is wired up, check the database after a manual trigger:

```sql
SELECT name, status, last_executed_at FROM cron_schedule
WHERE job_code LIKE 'byte8_stock_radar%' ORDER BY scheduled_at DESC LIMIT 5;
```

## 5. Smoke test the flow

1. Find a simple product. Set its stock to 0 and `is_in_stock = 0`.
2. Visit the product page on the storefront. The "Notify me" form should appear.
3. Subscribe with a test email.
4. In admin, set the product's stock back to 100 and save.
5. Within the throttle window (default 30 minutes — set it to `0` for testing), the cron will dispatch and the test email should arrive.

You can speed up the smoke test by setting the throttle window to `0` in **Stores → Configuration → Byte8 → Stock Radar → Dispatch**.

## What's next

- **[Configure dispatch](/docs/configuration/dispatch)** — throttle window, subscription expiry, batch limit
- **[Email template](/docs/configuration/email)** — sender, template, and how to switch to the Plenty-enriched variant
- **[Demand heatmap](/docs/admin/demand-heatmap)** — the merchandiser's reorder report, ready as soon as you have subscribers
- **[GraphQL](/docs/advanced/graphql)** — for Hyvä, Velafront, or any headless storefront
