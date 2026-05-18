---
title: General settings
description: The Stores → Configuration → Byte8 → Stock Radar tree, scope-by-scope.
---

# General configuration

All Stock Radar settings live at **Stores → Configuration → Byte8 → Stock Radar**. Most fields are store-scoped — different store views can run different throttle windows, different sender addresses, even different email templates.

## General

| Field | Scope | Default | Notes |
|---|---|---|---|
| **Enable** | Store | No | Master switch. When disabled, no subscriptions are accepted and no notifications are sent — even queued ones stay in the dispatch table. |

The master switch is honoured at three points:

1. **`SubscriptionService::subscribe()`** — refuses to create new rows.
2. **`StockSaveObserver`** — skips enqueueing for disabled stores.
3. **`DispatchSender`** — would send queued items, but if the store is disabled the email won't render properly because store-scoped templates won't resolve.

Set the master switch on a website level when you want the same behaviour across all stores in a multi-store; set it per store view when only some store views should accept subscriptions.

## Where the rest of the settings live

| Section | Page |
|---|---|
| Throttle window, subscription expiry | [Dispatch](/docs/configuration/dispatch) |
| Sender, email template | [Email](/docs/configuration/email) |
| High-demand admin-alert threshold | [Admin alerts](/docs/advanced/admin-alerts) |
