---
title: Dispatch settings
description: Throttle window, subscription expiry, and how the dispatch worker drains the queue.
---

# Dispatch

Dispatch settings control how — and how fast — Stock Radar sends notifications when stock returns.

## Throttle window (minutes)

| Field | Scope | Default |
|---|---|---|
| `byte8_stock_radar/dispatch/throttle_minutes` | Store | 30 |

When stock returns for a product, **every pending subscription gets enqueued at a randomly-staggered `scheduled_at` within `[now, now + throttle_minutes)`**. This is the headline feature.

### Why throttling matters

A popular hero SKU might have 800 pending subscriptions. Without throttling:

- All 800 emails fire in one cron tick.
- Inventory drops faster than fulfilment can keep up — half your subscribers click through to find the product back at "out of stock."
- Mail providers (SendGrid, Mailgun, SES) often rate-limit bursts; you may see deferrals or blocks.
- Spam filters (Gmail, Outlook) pattern-match identical subjects sent in identical timestamps and downrank the lot.

With throttling at the default 30 minutes:

- Sends spread randomly over half an hour.
- Customers arrive in waves, not a stampede.
- Mail volume per minute stays well within transactional rate limits.

### Tuning

| Window | When to use |
|---|---|
| `0` | Tests / dev. Sends as fast as cron drains. |
| `5` | Very small stores (`<50` subscribers per restock). Window irrelevant. |
| `30` (default) | Mainstream — fits most stores cleanly. |
| `60–120` | High-volume hero SKUs (1000+ subscribers). |
| `240+` | Limited-edition drops where you genuinely want a slow burn. |

## Subscription expiry (days)

| Field | Scope | Default |
|---|---|---|
| `byte8_stock_radar/dispatch/expiry_days` | Website | 90 |

Pending subscriptions older than this are auto-cancelled by the nightly `byte8_stock_radar_expire` cron (runs at 03:15 daily).

The reasoning: if a customer subscribed 4 months ago and the product still hasn't restocked, the email will feel like a stale ghost from a campaign they've forgotten about. Better to cancel cleanly. Set to `0` to disable expiry entirely.

## How the dispatch queue drains

`Byte8\StockRadar\Cron\DispatchSender::execute()` runs every minute (`* * * * *` in `etc/crontab.xml`).

Per tick:

1. Fetch up to **200** rows where `status = 'queued'` and `scheduled_at <= NOW()`.
2. For each row, call `Byte8\StockRadar\Model\Notifier::notify()`.
3. On success: mark dispatch `sent`, mark subscription `notified`.
4. On failure: increment `attempts`. After `3` attempts, mark `failed` (stays in the table for admin inspection).

The 200/minute batch limit is hardcoded — it keeps a single tick bounded so a 50,000-row backlog can't starve other crons. If you need a higher burst rate, run multiple Magento cron consumers in parallel.

## Manual drain

For testing or backlog recovery:

```bash
bin/magento cron:run --group=default
```

Or invoke the worker directly:

```bash
bin/magento dev:tests:run --filter Byte8_StockRadar  # if/when tests ship
```

## Failure inspection

Failed dispatches stay in `byte8_stock_radar_dispatch` with `status = 'failed'` and `last_error` populated:

```sql
SELECT subscription_id, attempts, last_error
FROM byte8_stock_radar_dispatch
WHERE status = 'failed'
ORDER BY updated_at DESC LIMIT 50;
```

Common causes: invalid sender address, bounced email (recipient hard-bounce), template rendering errors. After fixing the underlying issue, you can manually re-queue:

```sql
UPDATE byte8_stock_radar_dispatch
SET status = 'queued', attempts = 0, last_error = NULL
WHERE id IN (...);
```
