# Stripe pending-payment guard — not deployment ready

The confirmation endpoint must not create a Paid order for processing or requires_capture. It returns a pending response, retains the checkout snapshot in session, and blocks another Stripe intent in that session. Only succeeded proceeds to the existing paid-order path. The browser leaves submission disabled and shows a support reference. Redirect status is not trusted as payment evidence; the server retrieves the intent.

This change is intentionally a draft. Session retention is not durable payment recovery. Before deployment:

- Persist immutable checkout, expected amount/currency, owner and provider intent with idempotency before the provider request. Preserve encrypted personal details and do not store payment credentials.
- Reconcile provider success/cancellation after session expiry and handle duplicate callbacks atomically. Capture-required payments need an explicit operational capture workflow.
- Make order creation and local effects recoverable without double stock/coupon consumption or duplicate customer notifications.
- Attach coupon reservations to the durable attempt; never release a reservation solely on timeout.
- Block new attempts across sessions where an unresolved payment exists, and provide a recovery path. The current guard only covers the same session and does not block other gateways.
- Test asynchronous success/failure, capture, browser closure, retry and concurrency in a Stripe sandbox and MySQL.

Do not merge/deploy this guard alone: pending customers currently require support, and expired-session recovery is not implemented. No live payment tests were performed.
