# Operator trade review

This implements an explicit server-operator decision for an existing lifecycle profile. It does not replace business verification, add a public application form, grant pricing/credit terms or subscribe anyone to marketing.

Apply only migration `database/migrations/2026_10_24_000000_create_trade_review_decisions.php` after deploying this PR. Do not run all pending migrations: draft payment PR #231 is unrelated and not ready.

From core, use `php artisan marketing:review-trade --help`. Supply the lifecycle profile ID (not the customer ID) and `approved` or `rejected`. Without --apply the command only previews. After reviewing business evidence externally, supply --apply, --reviewer, --reference and --expected matching the status you reviewed. Store a case reference, not tax documents or secrets. Server access authorizes the operation; reviewer is an operator-entered audit label, not separately authenticated identity.

Approval sets buyer_type=Trade. Rejection sets buyer_type=DIY. Each new decision records its prior status and reference atomically. Reusing the same reference and reviewer retries synchronization without overwriting a later decision. A conflicting reference or stale expected status is rejected. An approved profile can be revoked with a new rejected decision and --expected=approved.

The existing profile sync job updates Klaviyo when enabled. It does not subscribe a customer or directly send a message, but live property-triggered flows may react to the changed audience. Test with a controlled profile and keep trade flows Draft until their acceptance checks pass. No real profiles were changed during development.

Do not drop the decision table after using it: retain the audit history during rollback. The customer-facing application and trade email flow still need configuration/acceptance; this PR supplies the manual review operation only.
