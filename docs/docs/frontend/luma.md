---
title: Luma integration
description: How the "Notify me" form drops into the standard product page, and the customer account "Stock Notifications" page.
---

# Luma integration

The Luma front-end is provided by the main `byte8/module-stock-radar` package — no companion module required. Layout XML places the form into a standard Magento container and the JS module handles AJAX submission and variant SKU sync.

## Where the form appears

`view/frontend/layout/catalog_product_view.xml` injects the block into `product.info.stock.sku`:

```xml
<referenceContainer name="product.info.stock.sku">
    <block class="Byte8\StockRadar\Block\Product\View\NotifyMe"
           name="byte8.stockradar.notify_me"
           template="Byte8_StockRadar::product/view/notify_me.phtml"
           after="-"/>
</referenceContainer>
```

That container sits above the SKU display on the standard PDP — visually obvious, doesn't fight with the product gallery or add-to-cart button.

## When the form shows / hides

`Block\Product\View\NotifyMe::isAvailable()` controls visibility:

- **Simple product** — shown when `is_in_stock = 0` or `qty <= 0`.
- **Configurable product** — always shown, because configurables can have OOS variants even when the parent shows in-stock. The variant picker drives the actual subscribe-or-not decision via the JS bridge.
- **Other types** (bundle, grouped, virtual) — hidden by default. To enable, override the block class.

## How variant SKU sync works

Configurable products expose a server-emitted variant SKU map in `data-mage-init`:

```html
<div data-mage-init='{
    "Byte8_StockRadar/js/notify-me": {
        "url": "/stockradar/subscription/save",
        "defaultSku": "PARENT-SKU",
        "variantSkuMap": { "4521": "SNK-RED-M", "4522": "SNK-RED-L", ... }
    }
}'>
```

The map is built by walking `Configurable::getUsedProducts()` server-side — no extra AJAX, no race condition with the swatch widget loading.

Client-side, the JS listens for Magento's standard `updateProductSummary` jQuery event (fired by `Magento_Swatches/js/swatch-renderer` after the customer picks options). It looks up the resolved simple product ID in `variantSkuMap` and updates the hidden SKU field.

It also listens for a custom `byte8:stockradar:variant` event — themes that don't go through the standard swatch widget can dispatch this directly:

```javascript
$('body').trigger('byte8:stockradar:variant', 'SKU-RESOLVED');
```

## Customer account "Stock Notifications" page

URL: `/stockradar/account/subscriptions`

Linked from the customer account left navigation (sortOrder 220 — sits below My Orders but above Address Book). Lists pending and notified subscriptions with one-click cancel.

The Cancel link uses the same `/unsubscribe?token=...` route as email links — no separate in-account cancel controller. Same security model: same response whether the token matched or not.

## Customising the template

The PHTML lives at `view/frontend/templates/product/view/notify_me.phtml`. Override it in your theme the standard way:

```
app/design/frontend/<Vendor>/<theme>/Byte8_StockRadar/templates/product/view/notify_me.phtml
```

The block class exposes everything the template needs:

- `$block->getProductSku()` — current product's SKU
- `$block->getCustomerEmail()` — pre-filled email when logged in
- `$block->getSubscribeUrl()` — POST target
- `$block->getVariantSkuMapJson()` — JSON map for `data-mage-init`

## What you can ignore

- **No service worker registration** — the form doesn't need it.
- **No customer-data sections** — subscriptions are fetched server-side on the account page, no Magento private content involved.
- **No checkout integration** — Stock Radar is PDP-only. Checkout flow is untouched.
