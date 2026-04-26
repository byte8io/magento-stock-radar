---
title: Installation
description: Detailed Composer installation, system requirements, optional companion modules, and post-install verification.
---

# Installation

## Requirements

| Component | Minimum | Notes |
|---|---|---|
| Magento | 2.4.4 | 2.4.7 / 2.4.8 recommended |
| PHP | 8.2 | 8.3 / 8.4 / 8.5 supported |
| MySQL | 8.0 | MariaDB 10.6+ also works |
| Node | n/a | Frontend assets ship pre-built |

The free module has **no external API keys, no third-party services, no SaaS account**. Composer pull, enable, done.

## Install the core module

```bash
composer require byte8/module-stock-radar
bin/magento module:enable Byte8_StockRadar
bin/magento setup:upgrade
bin/magento setup:di:compile
```

## Optional: Hyvä companion

If your storefront runs Hyvä, install the companion module so the "Notify me" form uses Alpine.js + Tailwind instead of jQuery + RequireJS:

```bash
composer require byte8/module-stock-radar-hyva
bin/magento module:enable Byte8_StockRadarHyva
bin/magento setup:upgrade
```

The companion is a thin layout-only override — same block class, same Service layer, same database. You can install both modules and Magento will use the right template per theme.

## Optional: PlentyONE bridge (paid)

For DACH stores running PlentyONE as the ERP. Requires a Byte8 licence key and the private repo configured in `composer.json`.

```bash
composer config repositories.byte8 composer https://byte8.repo.packagist.com/your-key/
composer require byte8/module-stock-radar-plenty
bin/magento module:enable Byte8_StockRadarPlenty
bin/magento setup:upgrade
```

See the [Plenty bridge](/docs/advanced/plenty-bridge) page for what it adds.

## Post-install verification

```bash
bin/magento module:status Byte8_StockRadar
# Module is enabled
```

Database tables should exist:

```sql
SHOW TABLES LIKE 'byte8_stock_radar%';
-- byte8_stock_radar_subscription
-- byte8_stock_radar_dispatch
```

Cron jobs should be registered:

```sql
SELECT job_code FROM cron_schedule
WHERE job_code LIKE 'byte8_stock_radar%' LIMIT 5;
```

You should see `byte8_stock_radar_dispatch` (every minute) and `byte8_stock_radar_expire` (03:15 daily). If these aren't appearing, run `bin/magento cron:run --group=default` once manually to populate the schedule.
