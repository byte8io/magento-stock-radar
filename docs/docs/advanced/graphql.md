---
title: GraphQL schema
description: Full reference for the byte8StockRadar* mutations and queries.
---

# GraphQL schema

Defined in `etc/schema.graphqls`. All resolvers go through `Byte8\StockRadar\Model\SubscriptionService` — the same Service layer the Luma controller uses, so validation and side-effects stay in one place.

## Schema

```graphql
type Mutation {
    byte8StockRadarSubscribe(input: Byte8StockRadarSubscribeInput!): Byte8StockRadarSubscribeOutput
    byte8StockRadarUnsubscribe(token: String!): Byte8StockRadarUnsubscribeOutput
}

type Query {
    byte8StockRadarMySubscriptions: Byte8StockRadarSubscriptionList
}

input Byte8StockRadarSubscribeInput {
    sku: String!
    email: String!
}

type Byte8StockRadarSubscribeOutput {
    success: Boolean!
    created: Boolean!
    message: String!
}

type Byte8StockRadarUnsubscribeOutput {
    success: Boolean!
    message: String!
}

type Byte8StockRadarSubscriptionList {
    items: [Byte8StockRadarSubscription!]!
    total_count: Int!
}

type Byte8StockRadarSubscription {
    sku: String!
    product_name: String
    product_url: String
    status: String!
    created_at: String!
    notified_at: String
    unsubscribe_token: String!
}
```

## `byte8StockRadarSubscribe`

| Argument | Type | Notes |
|---|---|---|
| `input.sku` | `String!` | For configurables, pass the **simple variant SKU**. Subscribing to a parent SKU is allowed; the bridge resolves which simples to associate. |
| `input.email` | `String!` | Validated server-side via `Magento\Framework\Validator\EmailAddress`. |

Returns:

| Field | Notes |
|---|---|
| `success` | Always `true` on a non-error response |
| `created` | `true` for first subscribe; `false` if the email already had a pending subscription for this product+store (idempotent) |
| `message` | Human-readable confirmation, suitable for direct UI display |

Errors:

| Condition | Error |
|---|---|
| Empty SKU or email | `GraphQlInputException` — "SKU and email are required." |
| Invalid email format | `GraphQlInputException` — "Please provide a valid email address." |
| Stock Radar disabled for the store | `GraphQlInputException` — "Stock notifications are not enabled for this store." |
| Simple product currently in stock | `GraphQlInputException` — "This product is currently in stock." |

## `byte8StockRadarUnsubscribe`

| Argument | Type | Notes |
|---|---|---|
| `token` | `String!` | The 48-character unsubscribe token from the email link or the customer-account list |

Returns:

| Field | Notes |
|---|---|
| `success` | **Always `true`** — the response is identical whether the token matched or not. This is intentional: any difference in response would let an attacker enumerate which tokens (and thereby which subscriptions) exist. |
| `message` | Always "You have been unsubscribed." |

## `byte8StockRadarMySubscriptions`

Requires customer authentication (`Authorization: Bearer <token>`).

Returns:

| Field | Notes |
|---|---|
| `items[].sku` | Subscribed product's SKU (simple SKU for variants) |
| `items[].product_name` | Storefront-display product name; null if product was deleted |
| `items[].product_url` | Full storefront URL; null if product was deleted |
| `items[].status` | One of `pending`, `notified`, `cancelled`, `bounced` |
| `items[].created_at` | ISO 8601 timestamp |
| `items[].notified_at` | ISO 8601 timestamp; null if not yet sent |
| `items[].unsubscribe_token` | For use with `byte8StockRadarUnsubscribe` |

Filtered to `status IN ('pending', 'notified')` — cancelled and bounced are not exposed (no value to the customer).

Errors:

| Condition | Error |
|---|---|
| Anonymous request | `GraphQlAuthorizationException` — "Customer authentication required." |

## Future extensions (non-breaking)

| Field | Status |
|---|---|
| `byte8StockRadarMySubscriptions(filter: ...)` — paging + status filter | Considered, deferred until customer ask |
| `Byte8StockRadarSubscription.product { ... }` — full Product object via existing GraphQL type | Considered, deferred — current `product_name` + `product_url` covers 90% of UI needs |
| `byte8StockRadarUnsubscribeAll` — admin-only mutation for GDPR delete by email | Planned |
