# Klaviyo Phase 4: Order Events

This phase sends order lifecycle events from authoritative Laravel order state.
It does not depend on the checkout success page, so refreshing a page cannot
create another revenue event.

## Events

- `Placed Order` is queued after the order receives its final `ORD-...` number.
- `Ordered Product` is queued once per cart line with quantity and line value.
- `Shipped Order` is queued when a non-empty tracking number is added or changed.
- `Fulfilled Order` is queued when the order status changes to `Delivered`.
- `Canceled Order` is queued when the order status changes to `Canceled`.
- `Refunded Order` is queued only when payment status changes to `Refunded`.

Every event uses a deterministic Klaviyo `unique_id`. Repeated saves and queue
retries therefore do not create duplicate events. Customer identity comes from
the saved billing/shipping details, with the registered customer as fallback.

## Deployment

No migration is required. Deploy after Phases 1-3, configure the Klaviyo keys,
leave `KLAVIYO_ENABLED=false` until Test Events validation, and ensure the
existing queue worker is running. Refund tracking will remain dormant until the
application records `Refunded` as an actual payment status.
