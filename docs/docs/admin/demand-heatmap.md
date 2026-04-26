---
title: Demand heatmap
description: The merchandiser's reorder report — products ranked by pending subscriber count.
---

# Demand heatmap

Path: **Byte8 → Stock Radar → Demand Heatmap**.

This is the headline differentiator. Most back-in-stock modules stop at "we'll send them an email when stock returns." The demand heatmap inverts the question: **"what are people waiting for, ranked by how many people are waiting?"**

Open it on Monday morning and you've got your reorder priority list before the buyer even logs in.

## What you see

| Pending subscribers | SKU | Product | Product ID | Parent ID | Store | First subscribed | Latest subscribed | Actions |
|---|---|---|---|---|---|---|---|---|
| **243** | SNK-RED-M | Stylish Sneakers Red M | 4521 | 4500 | UK | 2026-04-08 | 2026-04-26 | Edit product |
| **187** | HOO-BLK-L | Acme Hoodie Black L | 4612 | 4600 | UK | 2026-04-12 | 2026-04-25 | Edit product |
| **94** | TEE-WHT-S | Logo Tee White S | 4710 | 4700 | UK | 2026-03-30 | 2026-04-22 | Edit product |
| ... | | | | | | | | |

Sorted DESC by `subscriber_count` by default. Click "Edit product" to jump straight to the catalog admin — for configurables, the link goes to the **parent** so the merchandiser is editing where inventory actually lives.

## How the underlying SQL works

The grid is backed by a custom `Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection` that does this in a single query:

```sql
SELECT
    s.product_id,
    s.parent_product_id,
    s.store_id,
    COUNT(*) AS subscriber_count,
    MIN(s.created_at) AS first_subscribed,
    MAX(s.created_at) AS latest_subscribed,
    p.sku,
    name.value AS product_name
FROM byte8_stock_radar_subscription s
LEFT JOIN catalog_product_entity p ON p.entity_id = s.product_id
LEFT JOIN catalog_product_entity_varchar name
    ON name.entity_id = s.product_id
   AND name.attribute_id = <name_attr_id>
   AND name.store_id IN (0, s.store_id)
WHERE s.status = 'pending'
GROUP BY s.product_id, s.store_id
ORDER BY subscriber_count DESC;
```

Performance notes:

- **Indexed on `(status, product_id)`** — the `WHERE status = 'pending'` filter hits an index range scan.
- **EAV `name` join** picks store-scoped name first, falls back to admin scope (`store_id = 0`).
- **`primaryFieldName = product_id`** in the listing config — there's no synthetic `entity_id` for an aggregate view.

On a healthy database with 100k pending subscriptions across 5k products, this query runs in under 200ms.

## With the Plenty bridge installed

The paid [Plenty bridge](/docs/advanced/plenty-bridge) extends the grid with four additional columns sourced from `plenty_stock_entity`:

| Pending subscribers | SKU | Product | **Plenty physical** | **Plenty net** | **Inbound (PO)** | **Last Plenty sync** | Actions |
|---|---|---|---|---|---|---|---|
| 243 | SNK-RED-M | Stylish Sneakers Red M | 0 | 0 | 240 | 2026-04-26 03:45 | Edit |
| 187 | HOO-BLK-L | Acme Hoodie Black L | 12 | 4 | 0 | 2026-04-26 03:45 | Edit |

Now the merchandiser sees not just "243 people waiting" but **"243 people waiting AND 240 units on order arriving from supplier"** — which usually means the answer is "wait, don't reorder, the PO is fine." That's the kind of decision the heatmap unlocks.

## ACL

Permission resource: `Byte8_StockRadar::demand` — separate from the general subscription grid so you can grant heatmap access to merchandisers without exposing per-customer email data.
