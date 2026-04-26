---
title: VelaFront / headless
description: GraphQL-first integration for VelaFront, Next.js, Hydrogen, or any headless storefront.
---

# VelaFront / headless

Stock Radar exposes a complete GraphQL surface for headless storefronts — the same Service layer the Luma controller uses, just behind a different transport. No REST adapter needed; everything goes through Magento's standard GraphQL endpoint.

## Mutations

```graphql
mutation Subscribe($sku: String!, $email: String!) {
  byte8StockRadarSubscribe(input: { sku: $sku, email: $email }) {
    success
    created
    message
  }
}
```

Returns `created: true` on first subscribe, `created: false` on duplicate (still success). Use this to show different copy: "subscribed!" vs "you were already subscribed."

```graphql
mutation Unsubscribe($token: String!) {
  byte8StockRadarUnsubscribe(token: $token) {
    success
    message
  }
}
```

`success` is always `true` — the response is identical whether the token matched or not, by design (no enumeration).

## Query

```graphql
query MySubscriptions {
  byte8StockRadarMySubscriptions {
    items {
      sku
      product_name
      product_url
      status
      created_at
      notified_at
      unsubscribe_token
    }
    total_count
  }
}
```

Requires customer authentication — anonymous calls get `GraphQlAuthorizationException`.

## React hook example

```tsx
import { useMutation } from '@apollo/client';
import { gql } from '@apollo/client';

const SUBSCRIBE = gql`
  mutation Subscribe($sku: String!, $email: String!) {
    byte8StockRadarSubscribe(input: { sku: $sku, email: $email }) {
      success
      created
      message
    }
  }
`;

export function useStockRadarSubscribe() {
  const [subscribe, { data, loading, error }] = useMutation(SUBSCRIBE);

  return {
    subscribe: (sku: string, email: string) =>
      subscribe({ variables: { sku, email } }),
    result: data?.byte8StockRadarSubscribe,
    loading,
    error,
  };
}
```

## Drop-in `<NotifyMe />` component sketch

```tsx
import { useState } from 'react';
import { useStockRadarSubscribe } from './useStockRadarSubscribe';

export function NotifyMe({ sku }: { sku: string }) {
  const [email, setEmail] = useState('');
  const { subscribe, result, loading } = useStockRadarSubscribe();

  return (
    <form onSubmit={(e) => { e.preventDefault(); subscribe(sku, email); }}>
      <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
      <button type="submit" disabled={loading}>
        {loading ? 'Subscribing…' : 'Notify me'}
      </button>
      {result?.success && (
        <p style={{ color: 'green' }}>{result.message}</p>
      )}
    </form>
  );
}
```

## Variant SKU resolution in headless

In a headless storefront you have direct control over option selection — no swatch widget magic needed. When the user picks "Red, M":

1. Use Magento's standard `configurable_options` query to map `option_id → product_id`.
2. Look up the variant's SKU via the `products` query.
3. Pass that simple SKU into `<NotifyMe sku={variantSku} />`.

The Stock Radar mutation just needs the resolved simple SKU; the parent/child relationship is handled server-side via `Configurable::getUsedProducts()`.

## Account subscription management

The `byte8StockRadarMySubscriptions` query returns each subscription's `unsubscribe_token`, so a "My Stock Notifications" page in your headless app can render a Cancel button that calls the unsubscribe mutation directly:

```tsx
async function cancel(token: string) {
  await client.mutate({
    mutation: UNSUBSCRIBE,
    variables: { token },
    refetchQueries: ['MySubscriptions'],
  });
}
```

## Authentication

The customer auth token from Magento's standard `generateCustomerToken` mutation works unchanged — Stock Radar respects `Magento\Framework\GraphQl\Query\ContextInterface::getUserId()` and reads the `Authorization: Bearer <token>` header per Magento's normal flow.

## Rate limiting

The subscribe mutation has no built-in rate limit — if you're worried about abuse (a script subscribing thousands of fake emails to drown your dispatch queue), put your storefront's existing rate limiter (Vercel Edge, CloudFlare, etc.) in front of it. The server-side `EmailAddress` validator catches obvious malformed input.
