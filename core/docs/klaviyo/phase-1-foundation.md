# Klaviyo Phase 1: Integration Foundation

This phase adds the shared Klaviyo transport only. It does not attach events to
the storefront, create profiles, subscribe contacts, or enable live messages.

## Environment configuration

```dotenv
KLAVIYO_ENABLED=false
KLAVIYO_PUBLIC_KEY=
KLAVIYO_PRIVATE_API_KEY=
KLAVIYO_API_REVISION=2026-07-15
KLAVIYO_API_URL=https://a.klaviyo.com
KLAVIYO_TIMEOUT=10
KLAVIYO_CONNECT_TIMEOUT=5
KLAVIYO_QUEUE=default
```

Keep `KLAVIYO_ENABLED=false` until a test account, API scopes, consent handling,
and the Phase 2 event wiring have been approved. The private key requires event
and profile write permissions and must only be stored in the server `.env`.

The default queue works with the existing Laravel worker. A dedicated
`marketing` queue can be configured later, but only after Supervisor is updated
to process it. API failures are retried after 60, 300, and 900 seconds and must
never block checkout or order creation.

## Event contract

All server-side events must include at least one real profile identifier:
`email`, `phone_number`, or `external_id`. Revenue events must include a numeric
value, ISO-8601 occurrence time, and a stable `unique_id` to prevent duplicates.

Planned ecommerce metric names follow Klaviyo's custom ecommerce guidance:

- Viewed Product
- Added to Cart
- Started Checkout
- Placed Order
- Ordered Product
- Fulfilled Order
- Canceled Order
- Refunded Order

Marketing consent is not inferred from an order, account, or phone number.
Subscription status will be implemented separately with evidence of channel-
specific consent.

## Branch sequence

1. `feature/klaviyo-phase-1-foundation`: configuration, API client, queue job,
   event contract, and transport tests.
2. `feature/klaviyo-phase-2-profiles-consent`: profile identity, newsletter
   migration, separate email/SMS consent capture, and consent audit records.
3. `feature/klaviyo-phase-3-storefront-events`: onsite script, Viewed Product,
   Added to Cart, and Started Checkout.
4. `feature/klaviyo-phase-4-order-events`: Placed Order, Ordered Product,
   fulfillment, cancellation, refund, and shipping/tracking events.
5. `feature/klaviyo-phase-5-catalog-sync`: Klaviyo-compatible catalog feed and
   product recommendation mapping.
6. `feature/klaviyo-phase-6-lifecycle-data`: maintenance dates, buyer type,
   vehicle properties, referral state, trade approval, and historical sync.
7. `feature/klaviyo-phase-7-launch-qa`: end-to-end flow validation, monitoring,
   operational documentation, and staged production enablement.

Email/SMS templates and flows are configured in Klaviyo after their required
events and profile properties pass test-account verification.
