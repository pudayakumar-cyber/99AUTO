# Email/SMS resumption — 2026-10-08

## Repository finding

Remote main was `4a2ccf6` (PR #211). The previous email-readiness commits
`0196917`, `b85615c`, `a655c2a`, and `d527247` were not ancestors of main.
This branch reapplies those changes on top of main. The route conflict was
resolved by retaining both the sitemap routes and the cart-recovery routes.
No SEO commits were reverted. This is repository verification, not a check of
the code or migration state currently running on the VPS.

## Review and staging first

This change restores the previously prepared website prerequisites: expiring
cart recovery, consistent profile IDs, public catalog/order URLs, maintenance
category intervals, full-catalog validation and coupon calculation validation.
It does not implement coupon eligibility, expiry or atomic single-use redemption.
See `email-readiness.md` and `coupon-readiness.md` for the remaining flow work.

Deploy the reviewed branch to staging first. Verify the application is connected
to `99_auto_parts_staging` before running the targeted migration described in
`cart-recovery.md`. Keep live payment, email, SMS and shipping credentials disabled.
Do not copy production's environment into staging or run all outstanding migrations.

The current staging Apache restrictions permit public GET/HEAD only. Recovery's
confirmation POST and normal cart/checkout requests therefore need a deliberately
scoped staging test configuration before browser QA. A staging 405 under that
configuration is not evidence that Laravel's recovery route is broken. Retain
Basic Auth and noindex protection. The staging media aliases point to production
media; do not modify those files during QA.

Use isolated fixtures/test customers to verify cart restoration in a second
browser, current prices/options/stock, expiry, repeated restores and ordinary
checkout. Confirm sitemap and canonical product URLs continue to work.

## External checks still outstanding

- Confirm the actual deployed revision and cart-recovery table on the VPS.
- Confirm Klaviyo's imported catalog count; mapping-valid alone is insufficient.
- Inspect account flows using authorized read access. Prior access lacked
  `flows:read`; no current account access was verified in this resumption.
- Verify event arrival, flow entry, exclusions and inbox delivery with test-only
  flows before activating Welcome or Abandoned Cart for customers.
- Complete discount redemption requirements before discount-dependent messages.
- Complete SMS sender/consent configuration and controlled delivery tests.

No customer messages, database migrations, remote settings or production changes
were performed during this resumption.

## Local validation

The recovered email/cart/coupon/middleware tests passed: 42 tests, 155 assertions,
PHP 8.2.26 with in-memory SQLite. PHP route syntax, JavaScript syntax and Git
whitespace checks passed. PHPUnit reports an existing XML-schema deprecation.
Four existing ProductMetaTitle tests could not boot the application because its
service provider queries the MySQL settings table; this isolated checkout has no
application database or live environment. They are not recorded as passing.
Full application/checkout browser testing remains a staging requirement.
