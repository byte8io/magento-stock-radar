---
title: Subscription grid
description: Browse, filter, and inspect subscriptions in the admin.
---

# Subscription grid

Path: **Byte8 → Stock Radar → Subscriptions**.

A flat listing of every row in `byte8_stock_radar_subscription` — for support staff investigating "why didn't I get an email?" tickets, and for compliance / GDPR responses.

## Columns

| Column | Source | Filter |
|---|---|---|
| **ID** | `entity_id` | Yes (text) |
| **Product ID** | `product_id` | Yes (text) |
| **Parent ID** | `parent_product_id` | Yes (text) |
| **Store** | `store_id` | Yes (text) |
| **Email** | `email` | Yes (text) |
| **Customer ID** | `customer_id` | Yes (text) |
| **Status** | `status` | Yes (select: pending / notified / cancelled / bounced) |
| **Created** | `created_at` | Yes (date range) |
| **Notified** | `notified_at` | Yes (date range) |

The grid is read-only by design — admins shouldn't be editing subscriber emails. To cancel a subscription on the customer's behalf, use the customer account "Stock Notifications" page (impersonate via standard admin → customer login), or run an SQL update against `byte8_stock_radar_subscription.status = 'cancelled'`.

## Common queries

**"Why didn't this customer get notified?"**

Filter by **Email** and **Status = pending**. If you see a row, the dispatch may be queued but not yet sent — check the dispatch table:

```sql
SELECT d.*, s.email
FROM byte8_stock_radar_dispatch d
JOIN byte8_stock_radar_subscription s ON s.entity_id = d.subscription_id
WHERE s.email = 'customer@example.com' AND d.status != 'sent'
ORDER BY d.scheduled_at DESC;
```

**"How many people are waiting on SKU-12345?"**

For an aggregate view, use the [Demand heatmap](/docs/admin/demand-heatmap) instead — that's exactly what it's for.

**"Cancel all subscriptions for this email" (GDPR delete by email)**

```sql
UPDATE byte8_stock_radar_subscription
SET status = 'cancelled'
WHERE email_hash = SHA2(LOWER(TRIM('customer@example.com')), 256);
```

The lookup uses `email_hash` (indexed) — see the [GDPR page](/docs/advanced/gdpr) for the full deletion playbook.

## ACL

Permission resource: `Byte8_StockRadar::subscription` — under **System → Permissions → User Roles**, you can grant view access to a support team without giving them the full **Stock Radar configuration** permission (`Byte8_StockRadar::config`).
