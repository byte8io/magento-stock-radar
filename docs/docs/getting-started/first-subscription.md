---
title: Your first subscription
description: End-to-end walkthrough — out-of-stock PDP, customer subscribes, restock, throttled email, customer-account management.
---

# Your first subscription

End-to-end walkthrough showing how a single subscription flows from the storefront through the dispatch queue to the customer's inbox.

## 1. Out-of-stock product page

When a simple product is out of stock (`is_in_stock = 0` or `qty = 0`), the storefront renders the "Notify me when back in stock" block above the SKU display:

```
[OUT OF STOCK]

— Notify me when back in stock —
[ you@example.com         ] [ Notify me ]
```

For configurable products, the form appears for the **whole product** but the hidden SKU field is wired up to the variant picker — when the customer selects "Red, M", the resolved simple SKU is what gets saved.

## 2. Customer submits

The form posts to `/stockradar/subscription/save` with `sku`, `email`, and `form_key`. The controller calls `Byte8\StockRadar\Model\SubscriptionService::subscribe()`, which:

1. Validates the email address.
2. Checks the store has Stock Radar enabled.
3. For simple products, confirms the product is actually out of stock.
4. Resolves the parent configurable ID (if applicable).
5. **Atomic upsert** into `byte8_stock_radar_subscription` — repeat submits with the same email + product + store don't fail, they just update the timestamp.

The customer sees an immediate success message:

> We'll email you when this product is back in stock.

## 3. Restock event

When stock comes back — either a manual edit in the admin, an MSI source-item save, or an ERP push — Magento fires `cataloginventory_stock_item_save_after`. Stock Radar's observer picks it up and:

1. Detects the `is_in_stock` 0 → 1 transition (or qty 0 → positive).
2. For each store with Stock Radar enabled, fetches all pending subscription IDs for the product.
3. Calls `enqueueBatch()` which inserts dispatch rows into `byte8_stock_radar_dispatch` with `scheduled_at = NOW() + RAND(0, throttle_window_seconds)`.

The throttle is the key: 800 subscribers don't all get the email at 09:00. Each row gets a randomly-staggered `scheduled_at` within the configured window (default 30 minutes), spreading load naturally.

## 4. Cron drains the queue

Every minute, `Byte8\StockRadar\Cron\DispatchSender::execute()` runs:

1. Fetch up to 200 dispatch rows where `status = 'queued'` and `scheduled_at <= NOW()`.
2. For each row, render the email template (`byte8_stock_radar_email_template`) with the product, store, and unsubscribe URL.
3. Send via Magento's standard `TransportBuilder` — your existing email transport (SMTP, SendGrid, Mailgun, etc.) is used unchanged.
4. On success, mark the dispatch `sent` and the parent subscription `notified`.
5. On failure, increment `attempts`. After 3 failures, the dispatch row stays `failed` for admin inspection.

## 5. Customer receives the email

The default template renders a clean transactional email:

> **Good news — it's back!**
>
> Stylish Sneakers (SKU-12345) is back in stock at Acme Store.
>
> [View product]
>
> *Don't want these alerts? Unsubscribe*

The unsubscribe link is signed with a 48-character random token. Clicking it calls `Byte8\StockRadar\Model\SubscriptionService::unsubscribeByToken()` which flips the subscription to `cancelled` — and **returns the same response whether the token matched or not**, so you can't enumerate valid tokens.

## 6. Logged-in customer self-service

Logged-in customers get a "Stock Notifications" entry in their account navigation. The page lists pending and notified subscriptions with one-click cancel:

| Product | SKU | Status | Subscribed | Actions |
|---|---|---|---|---|
| Stylish Sneakers | SKU-12345 | Waiting for restock | 2026-04-20 | Cancel |
| Acme Hoodie L | SKU-67890 | Notified | 2026-04-15 | Cancel |

Same `unsubscribe?token=...` route is used for the in-account cancel — no separate controller, no extra database column.

## What you've just exercised

- **Atomic subscription upsert** (no duplicate-key errors)
- **Throttled batched dispatch** (no inventory-crash blast)
- **Token-based unsubscribe** (with no enumeration vector)
- **Customer account self-service** (with reuse of the unsubscribe route)

That's the entire happy path. The rest of the docs go deeper into specific knobs and adapters.
