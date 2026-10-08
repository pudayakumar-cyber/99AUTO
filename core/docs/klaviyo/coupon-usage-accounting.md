# Coupon usage accounting

## Problem and change

Checkout previously reduced `promo_codes.no_of_times`, and admin status changes
could reduce it again for the same paid order. Six payment paths also contained
a second deduction reading the misspelled session key `copon`.

All 14 checkout handlers and the admin handler now use `CouponUsageService`.
It reads the order's persisted discount, locks that order, decrements the coupon
with a guarded SQL update, and records a timestamp in the same transaction.
Repeated calls for the same order do nothing. The balance cannot go below zero.
Missing/deleted coupons do not throw after a payment has already been accepted.
The service does not invoke order observers or change the order's payment status.

## Deployment: staging before production

This branch is independent of `fix/klaviyo-readiness-resume` and is based on
main at `4a2ccf6` (PR #211).

The new nullable `orders.coupon_usage_recorded_at` column must exist before the
new PHP handlers run. The migration checks that orders and promo_codes use
InnoDB on MySQL; it stops before changing schema if they do not. It does not
automatically convert tables.

The migration marks existing `ORD-` orders as already accounted for, without
changing any coupon balances. This deliberately preserves historical balances;
it cannot reconstruct missed or duplicate deductions by legacy code. Review
pending historical orders and coupon balances separately. Temporary checkout
drafts are not marked.

On the isolated staging database, run only this migration:

```bash
php artisan migrate --path=database/migrations/2026_10_08_000000_add_coupon_usage_marker_to_orders.php --force
```

Verify the database is `99_auto_parts_staging` before running it. Do not run all
outstanding migrations or change production payment settings. Test an accepted
coupon order, repeated admin updates, missing coupons and exhausted balances.
Test each enabled gateway in sandbox, including callbacks, before production.

For production, prepare an additive schema-first release with the existing code
still active, and coordinate/drain in-flight checkouts/callbacks before switching
handlers. Mixing old and new handlers can still duplicate accounting. Confirm
MySQL ALTER TABLE behavior and locking on staging first. This PR does not promise
a zero-downtime database migration or authorize one.

## Validation

Six isolated SQLite tests / 27 assertions pass: repeated checkout/admin handling,
stale models, depleted balances, malformed/deleted coupon references, rollback on
marker-write failure, and historical migration behavior. PHP syntax and Git
whitespace checks pass. The existing PHPUnit XML deprecation remains.

SQLite verifies transaction rollback and repeat handling, not InnoDB's concurrent
row-lock behavior. Concurrent MySQL checkout validation and real gateway sandbox
tests remain staging requirements; no gateway charges were made in development.

## Remaining work

This is usage accounting after order acceptance, not a single-use reservation
before charging. Two checkouts can still accept the same last-use coupon unless
we add pre-payment reservations and expiry/eligibility checks. It does not dedupe
separate order rows created for the same gateway payment, issue campaign codes,
enforce first-order eligibility, or handle referral rewards. Discount-dependent
Klaviyo flows must remain in Draft until those rules and tests are complete.

## Rollback

Revert the code first. Keep the marker column and its data while assessing the
rollback; old code ignores it. Dropping the column loses the duplicate-use guard
history. Do not automatically rerun the migration against changed historical
balances, and do not roll back unrelated migrations.
