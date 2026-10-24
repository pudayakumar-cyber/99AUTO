# Stripe pending-payment guard — not deployment ready

The confirmation endpoint must not create a Paid order for processing or requires_capture. It returns a pending response, retains the checkout snapshot in session, and blocks another Stripe intent in that session. Only succeeded proceeds to the existing paid-order path. The browser leaves submission disabled and shows a support reference. Redirect status is not trusted as payment evidence; the server retrieves the intent.

This change is intentionally a draft. Session retention is not durable payment recovery. Before deployment:

The draft now includes `StripeCheckoutAttemptStore` and migration `2026_10_23_040000_create_stripe_checkout_attempts.php`. Intent creation saves the encrypted checkout before the provider call, uses a stable idempotency key, and retrieves an already bound intent rather than creating another. Changed snapshots are rejected. Provider amount, currency and attempt metadata are verified before binding. An unbound attempt older than 23 hours requires reconciliation because Stripe may prune idempotency keys after 24 hours. This timeout does not release coupons or prove a payment failed.

Recovery storage returns data only for a matching registered user, but the confirmation controller is not yet wired to that recovery operation. Guest recovery is deliberately unavailable. Keep the application key backed up; key changes can make saved snapshots unreadable. Do not drop the table after real attempts have been written.

- Persist immutable checkout, expected amount/currency, owner and provider intent with idempotency before the provider request. Preserve encrypted personal details and do not store payment credentials.
- Reconcile provider success/cancellation after session expiry and handle duplicate callbacks atomically. Capture-required payments need an explicit operational capture workflow.
- Make order creation and local effects recoverable without double stock/coupon consumption or duplicate customer notifications.
- Attach coupon reservations to the durable attempt; never release a reservation solely on timeout.
- Block new attempts across sessions where an unresolved payment exists, and provide a recovery path. The current guard only covers the same session and does not block other gateways.
- Test asynchronous success/failure, capture, browser closure, retry and concurrency in a Stripe sandbox and MySQL.

Do not merge/deploy this guard alone: pending customers currently require support, and expired-session recovery is not implemented. No live payment tests were performed.
