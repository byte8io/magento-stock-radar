---
title: Hyvä companion
description: byte8/module-stock-radar-hyva — Alpine.js + Tailwind variant of the Notify me form, plus a Hyvä-styled account page.
---

# Hyvä companion

`byte8/module-stock-radar-hyva` is a thin layout-only module that swaps the Luma RequireJS template for an Alpine.js + Tailwind variant. **Same block class, same Service layer, same database** — only the template path changes.

## Install

```bash
composer require byte8/module-stock-radar-hyva
bin/magento module:enable Byte8_StockRadarHyva
bin/magento setup:upgrade
```

Both `byte8/module-stock-radar` and `hyva-themes/magento2-theme-module` must be installed and enabled.

## What the companion swaps

| File | Purpose |
|---|---|
| `view/frontend/layout/catalog_product_view.xml` | Swaps the PDP block's template to `Byte8_StockRadarHyva::product/view/notify_me.phtml` |
| `view/frontend/templates/product/view/notify_me.phtml` | Alpine + Tailwind subscribe form |
| `view/frontend/layout/stockradar_account_subscriptions.xml` | Swaps the account page template to the Hyvä variant |
| `view/frontend/templates/account/subscriptions.phtml` | Tailwind table with status badges |

## Form behaviour (Alpine)

- **No jQuery, no RequireJS, no Knockout** — pure Alpine.
- **CSRF via `hyva.getFormKey()`** — the standard Hyvä helper.
- **Variant SKU sync via MutationObserver** on `input[name=selected_configurable_option]` — Hyvä's swatch picker sets that hidden input when all options are chosen, which we observe and resolve via the embedded `variantSkuMap`.
- **Custom `byte8:stockradar:variant` event** — themes that don't go through `selected_configurable_option` can dispatch this `CustomEvent` directly.

```javascript
window.dispatchEvent(new CustomEvent('byte8:stockradar:variant', {
    detail: 'SKU-RESOLVED'
}));
```

## What the companion does *not* do

- **No new business logic** — all validation, dispatch, and email sending lives in the parent module. The companion is layout + Alpine glue only.
- **No Hyva email template variant** — the back-in-stock email is HTML and works in both Luma and Hyvä stores unchanged. Hyvä is a frontend theme, not an email theme.
- **No Magewire / Livewire integration** — Stock Radar's flows are simple enough that a single AJAX POST is all the form needs. No round-trip rendering.

## Customising

Override the template in your Hyvä theme the standard way:

```
app/design/frontend/<Vendor>/<theme>/Byte8_StockRadarHyva/templates/product/view/notify_me.phtml
```

The Alpine `x-data` block is small (~60 lines) — easy to fork wholesale if you want different copy, different layout, or extra fields like marketing-consent checkboxes.
