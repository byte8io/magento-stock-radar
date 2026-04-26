---
title: Email settings
description: Sender, template, and how to switch to the PlentyONE-enriched variant.
---

# Email

## Sender

| Field | Scope | Default |
|---|---|---|
| `byte8_stock_radar/email/sender` | Store | `sales` |

Picks one of Magento's standard sender identities (configured at **Stores → Configuration → General → Store Email Addresses**). The default `sales` is fine for most stores; switch to `support` if you'd rather replies route to support.

## Template

| Field | Scope | Default |
|---|---|---|
| `byte8_stock_radar/email/template` | Store | `byte8_stock_radar_email_template` |

The default template ships with the module — minimal HTML with `{{template config_path="design/email/header_template"}}` so it inherits your store's existing email header / footer / branding.

### Variables exposed

```
{{var product_name}}      — the product's display name
{{var product_sku}}       — SKU (variant SKU for configurables)
{{var product_url}}       — full storefront URL
{{var store_name}}        — the storefront name
{{var unsubscribe_url}}   — signed unsubscribe link
{{var product}}           — the full Product object (for advanced templates)
```

### Customising

The cleanest path is to **clone the template in admin** rather than editing the file:

1. **Marketing → Communications → Email Templates → Add New Template**.
2. **Load Default Template**: pick "Stock Radar — back in stock".
3. Edit the HTML to taste.
4. Save and select your new template in **Stores → Configuration → Byte8 → Stock Radar → Email → Template**.

This way upgrades don't clobber your customisation.

### Switching to the Plenty-enriched template

If you've installed the [Plenty bridge](/docs/advanced/plenty-bridge), it registers an additional template called `byte8_stock_radar_email_template_with_inbound`. Pick it in the dropdown to render a "Live warehouse data" callout with physical, net, and inbound (PO) quantities pulled from `plenty_stock_entity`.

The enriched template falls back gracefully when Plenty data isn't available — a product not tracked in Plenty silently omits the callout.

## Pingbell integration

| Field | Scope | Default |
|---|---|---|
| `byte8_stock_radar/pingbell/threshold` | Website | 50 |

When pending subscriber count for any single product exceeds this number, post an admin notification via `Byte8\Pingbell` so merchandisers see "100+ subscribers waiting on SKU-12345" without having to open the demand heatmap.

:::note Compatibility module pending
Pingbell wiring lives in a future compatibility module (`byte8/module-stock-radar-pingbell`). Until that ships, the threshold field is present but inert. The free Pingbell module on its own doesn't know about Stock Radar's tables.
:::
