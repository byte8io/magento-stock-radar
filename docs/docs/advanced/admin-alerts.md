---
title: Admin alerts
description: How Stock Radar posts a "high-demand SKU" message into the Magento admin notification inbox when a product first crosses the subscriber threshold.
---

# Admin alerts

When the pending subscriber count for a single product **first crosses** the configured threshold, Stock Radar alerts the admin via **two independent transports**, each toggleable:

- **Admin notification inbox** — bell icon top-right in the admin header (`Magento\Framework\Notification\NotifierInterface`). Seen when the admin is already in the panel.
- **Transactional email** — sent to the configured recipient using Magento's standard mail transport. Reaches the admin even when they're not at their desk.

Both default **on**. No external service, no companion module, no API keys.

## Configuration

`Stores → Configuration → Byte8 → Stock Radar → Admin alerts`.

| Field | Default | Notes |
|---|---|---|
| **High-demand subscriber threshold** | `50` | When pending count for a single product first reaches this, alert the admin. Set to `0` to disable both transports. Scope: per-website. |
| **Post to admin notification inbox** | Yes | Bell-icon notification. Toggle off if the email transport is enough. |
| **Send email to admin** | Yes | Transactional email. |
| **Admin recipient email** | *(blank → General Contact)* | Leave blank to use the General Contact email from `Stores → Configuration → General → Store Email Addresses`. |
| **Email sender** | General | One of Magento's configured store-email identities. |
| **Email template** | `byte8_stock_radar_admin_alert_email_template` | Override under `Marketing → Email Templates` if you want different copy. |

## When the alert fires

Only on the **freshly-crossed event** — once per crossing, not once per subscriber.

| Pending count before | New subscriber arrives | Pending count after | Alert? |
|---|---|---|---|
| 48 | ➕ | 49 | No |
| 49 | ➕ | 50 | **Yes** ← crosses threshold of 50 |
| 50 | ➕ | 51 | No (already above) |
| 51 | restock dispatched, all 51 notified | 0 | No |
| 0 | ➕ ten times | 10 | No |
| 49 | ➕ | 50 | **Yes** ← fresh crossing again |

This means the merchandiser sees one alert per "outbreak" of demand, not one per subscriber after the threshold's met. If the threshold is reset (count drops to zero, e.g. after a restock dispatch) the next time it crosses you'll get another alert.

## What the alert looks like

### Bell-icon notification (major severity)

- **Title:** *"Stock Radar: 50 customers waiting for `{Product name}`"*
- **Body:** *"SKU `{SKU}` has just crossed the high-demand threshold of 50 pending subscribers on store `{ID}`. Review the Demand Heatmap: `{link}`"*

Click through opens `Byte8 → Stock Radar → Demand Heatmap`.

### Email

Same data, friendlier layout. Subject: *"Stock Radar: 50 customers waiting for `{Product name}`"*. Body lists product, SKU, store, current count vs threshold, and a button to open the Demand Heatmap directly. Built from `view/frontend/email/admin_alert.html` — override in your theme or under `Marketing → Email Templates` if you want different copy or branding.

## Double opt-in interaction

When double opt-in is enabled, the alert threshold considers only `PENDING` rows — `UNCONFIRMED` subscribers don't count toward demand because they may never confirm. The check re-runs after a successful `confirmByToken` so confirmation-driven crossings still fire the alert.

## Why this isn't Pingbell

The original concept doc proposed an integration with `byte8/module-pingbell` for these alerts. After implementation review we dropped that plan:

- Pingbell pushes notifications to the [pingbell.io](https://pingbell.io) SaaS for phone / desktop / smartwatch delivery, gated on transactional events (order placed, invoice, shipment, credit memo, new customer registration).
- Stock-Radar threshold alerts are a different shape — they're admin-attention nudges, not transactional events, and don't need to leave the Magento admin.
- Magento's `NotifierInterface` already covers the use case in zero lines of external dependency.

For merchants who want phone-push notifications when a threshold is crossed (the "page me when 50 people want SKU XYZ" scenario), a separate transport-agnostic option (ntfy.sh / Pushover / generic webhook) is on the roadmap.

## Disabling

To turn the whole feature off, set the threshold to `0`:

```bash
bin/magento config:set byte8_stock_radar/admin_alert/threshold 0 --scope=websites --scope-code=base
bin/magento cache:flush config
```

To keep the threshold but silence one transport:

```bash
# email only, no bell
bin/magento config:set byte8_stock_radar/admin_alert/bell_enabled 0 --scope=websites --scope-code=base

# bell only, no email
bin/magento config:set byte8_stock_radar/admin_alert/email_enabled 0 --scope=websites --scope-code=base
```

## Debugging

Failed posts are logged at `WARNING` level to `var/log/system.log` with the prefixes `Byte8 StockRadar admin bell alert failed` and `Byte8 StockRadar admin email alert failed`. A failure in one transport does not block the other, and neither failure ever breaks the subscribe path — alerts are best-effort.
