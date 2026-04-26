---
title: Events & extension points
description: Magento events Stock Radar listens on, and the public service surface for extending behaviour.
---

# Events & extension points

Stock Radar is built so that **the entire data flow can be extended via plugins and observers without forking the module**. This page documents the integration surface.

## Magento events Stock Radar listens on

| Event | Observer | What it does |
|---|---|---|
| `cataloginventory_stock_item_save_after` | `Byte8\StockRadar\Observer\StockSaveObserver` | Detects 0 → 1 (or qty 0 → positive) transitions and enqueues dispatch rows for all pending subscriptions |

Stock Radar **never** swallows transitions silently. If you have a custom inventory pipeline that doesn't fire `cataloginventory_stock_item_save_after`, the module won't see it. The [Plenty bridge](/docs/advanced/plenty-bridge) is one example of a module that hooks an additional event source — others follow the same pattern.

## Public service surface

The recommended extension API is `Byte8\StockRadar\Model\SubscriptionService` and the resource models. Direct table access works but bypasses validation.

```php
namespace Byte8\StockRadar\Model;

class SubscriptionService
{
    public function subscribe(
        string $sku,
        string $email,
        int $storeId,
        ?int $customerId = null
    ): array;  // ['created' => bool]

    public function unsubscribeByToken(string $token): bool;
}
```

```php
namespace Byte8\StockRadar\Model\ResourceModel;

class Subscription extends AbstractResource
{
    public function upsertPending(...): bool;
    public function getPendingIdsForProduct(int $productId, int $storeId): array;
    public function markNotified(int $subscriptionId): int;
    public function cancelExpired(string $cutoffTimestamp): int;
}

class Dispatch extends AbstractResource
{
    public function enqueueBatch(array $subscriptionIds, int $windowMinutes): int;
    public function fetchDueDispatchRows(int $limit): array;
    public function markSent(int $dispatchId): void;
    public function recordFailure(int $dispatchId, int $attempts, string $error): void;
}
```

## Plugin examples

### Override the email body

Plugin around `Byte8\StockRadar\Model\Notifier::notify` to inject extra template variables — that's how the Plenty bridge works:

```xml
<type name="Byte8\StockRadar\Model\Notifier">
    <plugin name="my_module_enrich_notify"
            type="MyVendor\MyModule\Plugin\Notifier\EnrichPlugin"
            sortOrder="20"/>
</type>
```

```php
public function beforeNotify(
    Notifier $subject,
    int $productId,
    int $storeId,
    string $email,
    string $unsubscribeToken
): array {
    // Attach data to product so the email template can read it
    $product = $this->productRepository->getById($productId, false, $storeId);
    $product->setData('my_custom_field', 'value');
    return [$productId, $storeId, $email, $unsubscribeToken];
}
```

### Trigger from a non-Magento source

If your custom integration (custom ERP, third-party stock feed, REST callback) doesn't fire `cataloginventory_stock_item_save_after`, call the resource models directly:

```php
$pendingIds = $this->subscriptionResource->getPendingIdsForProduct($productId, $storeId);
$this->dispatchResource->enqueueBatch($pendingIds, $throttleMinutes);
```

The dispatch worker drains the queue from there — same throttle, same retry logic, same email rendering.

### Filter who gets notified

Plugin around `SubscriptionService::subscribe` to add custom validation — VIP-only products, geo-restrictions, marketing-consent gates, etc. Throw `LocalizedException` to refuse cleanly.

## Custom events Stock Radar dispatches

Currently **none**. The Plenty bridge and Pingbell compatibility module hook in via plugins on the Service / Notifier layer rather than custom events, because plugin ordering is more deterministic than event subscriber ordering.

If you need a custom event for an integration, file an issue on GitHub with the use case — non-breaking event additions are easy.

## Frontend events

| Event | Where dispatched | Payload |
|---|---|---|
| `byte8:stockradar:variant` | Storefront (jQuery on body in Luma; CustomEvent on window in Hyvä) | Resolved simple SKU as a string |

Themes that don't go through the standard swatch widget can dispatch this event directly to feed the resolved variant SKU into the subscribe form.
