---
title: GDPR & privacy
description: How Stock Radar stores subscriber data, lawful basis, and how to respond to data subject deletion requests.
---

# GDPR & privacy

Stock Radar stores personal data (email addresses) and that means GDPR. The module is designed so compliance is the default, not an afterthought.

## What data is stored

Two tables hold subscriber data:

### `byte8_stock_radar_subscription`

| Column | Personal data? | Notes |
|---|---|---|
| `email` | ✅ | Plaintext email — needed to send the notification |
| `email_hash` | ✅ (pseudonymous) | SHA-256 of the lowercased trimmed email — used for indexed delete-by-email lookups |
| `customer_id` | ✅ | Foreign key to `customer_entity`; null for guest subscriptions |
| `unsubscribe_token` | ⚠️ | 48-char random token; combined with the row, identifies one subscription |

### `byte8_stock_radar_dispatch`

No direct PII — only `subscription_id` foreign key. When a `byte8_stock_radar_subscription` row is deleted, dispatch rows cascade-delete via the `ON DELETE CASCADE` foreign key.

## Lawful basis

**Art. 6(1)(b) — performance of a contract / pre-contractual measures at the request of the data subject.**

The customer actively requests the notification by submitting the form. We process their email address solely to deliver that one transactional notification. This is the same lawful basis used by checkout-status emails or password-reset emails.

It is **not** consent (Art. 6(1)(a)) — we don't ask "do you consent to marketing?" because this isn't marketing, it's a single transactional message the customer asked for.

It is **not** legitimate interest (Art. 6(1)(f)) — the customer's request is the trigger.

## Suggested privacy policy text

> When you ask us to notify you that a product is back in stock, we store
> your email address and a reference to the product so we can send you that
> one notification. We use Art. 6(1)(b) GDPR (performance of pre-contractual
> measures at your request) as the lawful basis. The notification is sent
> once, and your email is not added to any marketing list. You can cancel
> the notification at any time using the link in the email or in your
> account dashboard.

Adapt the wording to your tone — but don't claim "consent" or "legitimate interest." Both are wrong here.

## Retention

| Status | Default retention |
|---|---|
| `pending` | 90 days, then auto-cancelled by nightly cron (`byte8_stock_radar_expire`) |
| `notified` | Indefinite — needed if the customer asks "did you really send me an email and when?" |
| `cancelled` / `bounced` | Indefinite — minimal data, useful for support investigations |

If your privacy policy commits to shorter retention, set `byte8_stock_radar/dispatch/expiry_days` lower for `pending`, and set up a custom cron / SQL job for the longer-lived statuses.

## Responding to a data subject deletion request

The fastest way is by `email_hash` (indexed):

```sql
DELETE FROM byte8_stock_radar_subscription
WHERE email_hash = SHA2(LOWER(TRIM('customer@example.com')), 256);
```

This cascade-deletes related dispatch rows. **No partial state** — the customer is wholly removed from Stock Radar.

If the customer also has a `customer_entity` record, the standard Magento "Delete Customer" workflow does **not** automatically include Stock Radar rows — Magento's customer-delete cascade only covers core tables. You'll need a custom data-erasure plugin or a manual SQL pass.

A `byte8StockRadarDeleteByEmail` admin GraphQL mutation is on the roadmap to make this one API call instead of raw SQL — see the [GraphQL roadmap](/docs/advanced/graphql#future-extensions-non-breaking).

## What you can hand to your DPO

- **Inventory of data**: this page lists the two tables, every column, and which fields contain PII.
- **Lawful basis**: documented above (Art. 6(1)(b)).
- **Retention schedule**: 90 days for pending, indefinite for completed records (configurable).
- **Erasure procedure**: SQL by `email_hash` cascades both tables.
- **Data flow**: storefront subscribe → DB → cron → email transport (your existing one — SendGrid, Mailgun, SMTP). No third-party service receives subscriber data from Stock Radar specifically.

For the [Plenty bridge](/docs/advanced/plenty-bridge): no additional PII is stored. The bridge reads from `plenty_stock_entity` (anonymous inventory data) and joins on `product_id`. No subscriber data leaves the Stock Radar tables.
