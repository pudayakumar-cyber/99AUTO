# Personal offer reservation service

Depends on the offer issuance change (PR #223). Apply migration `2026_10_23_030000_create_marketing_coupon_reservations.php` after the marketing-rule and issuance-key migrations.

This is a service foundation, not checkout activation. No storefront route calls `MarketingCouponReservation::reserve` in this change. Existing checkout behavior is retained; discount campaigns must remain disabled until payment integration and reconciliation are complete.

`reserve($coupon, $user)` locks and rechecks the persisted coupon, creates a unique reservation, and sets its remaining uses to zero in one transaction. A stale second attempt, changed discount, wrong recipient, expired offer, or existing reservation is rejected. The returned random token must be kept in the server-owned payment/order snapshot as `discount.reservation_token`. Only its SHA-256 hash is stored in the reservation ledger.

`CouponUsageService::record($order)` finalizes the reservation only for its customer and one order, without decrementing again. Existing coupons without a reservation token retain their previous accounting. Late acceptance can finalize an already reserved offer after its original expiry. A timeout alone never restores a use: the provider may still accept a delayed payment.

## Remaining integration gates

- Reserve after checkout validation, immediately before the provider request. Do not reserve in early request middleware: invalid payment details would consume a use.
- Persist the token and immutable amounts with a durable payment attempt. Repeated requests must reuse that attempt rather than create another charge.
- Carry that snapshot through every enabled gateway callback. Current session-only callbacks and cart coupon recalculation require review before enabling reservations.
- Reconcile failed attempts against provider status before offering recovery. There is deliberately no automatic timeout release or administrative release command yet.
- Verify simultaneous attempts on MySQL/InnoDB and complete sandbox gateway success, decline, timeout, replay and delayed-callback tests. The isolated SQLite unit tests do not prove MySQL concurrency or provider behavior.

Deployment sends no messages and creates no offers or reservations. If reservations ever exist, retain the ledger during rollback; dropping it removes redemption evidence. Do not roll back the table while code still handles reservation tokens.
