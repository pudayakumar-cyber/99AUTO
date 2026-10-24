# Stripe checkout recovery

Only a provider-retrieved `succeeded` intent with the saved amount, full amount received, currency and checkout metadata can finalize a new checkout. Processing and capture-required payments remain pending and never trigger Paid orders or purchase messages.

## Persistence and replay

The encrypted checkout snapshot is saved before the provider request. A stable Stripe idempotency key recovers a lost create response. A bound intent is retrieved rather than recreated. Unbound attempts older than 23 hours require support reconciliation; they are never recreated after Stripe's idempotency retention window. Keep APP_KEY backed up and preserve both recovery tables.

An active-user unique constraint prevents a registered customer starting another Stripe attempt across sessions. Payment-initiation middleware also blocks switching gateways while that attempt is unresolved. Retrying Stripe must match the original snapshot. Guest attempts have session-level protection only: a guest in another browser cannot safely be identified as the same customer.

Personal offers are reserved in the same transaction as the attempt, before any provider call. The encrypted snapshot retains the reservation token. Retry reuses it; order completion consumes it once. Cancellation does not automatically reissue the offer.

## Completion and notifications

The attempt row is locked while order creation, transaction, inventory, coupon accounting, tracking and notification records commit together. Replay returns the same order. Product/option rows are locked before stock changes. A database failure rolls everything back for reconciliation to retry. Product deletion, partial capture, or corrupted saved data requires support review; never ask the customer to pay again merely because local finalization failed.

Klaviyo/lifecycle, order email, existing transactional SMS, and Facebook work is recorded in `stripe_checkout_effects` in that transaction and processed after commit. Each effect is claimed once. `done` means its handler finished (possibly enqueuing work), not inbox/SMS delivery. `review` means the handler threw; `claimed` can indicate an interrupted handler. Inspect provider and queue logs before manually retrying either state, since an external provider may have accepted delivery. No automatic resend of uncertain outcomes. This is not an exactly-once email-delivery guarantee.

Existing payment sessions created before this release retain the legacy confirmation path. The new atomic recovery guarantee applies to attempts created after this migration/release.

## Recovery

An authenticated registered owner can confirm a saved intent after session expiry. Another customer or a guest cannot recover a snapshot using only an intent ID. Background reconciliation handles saved guest attempts without exposing personal data.

`php artisan stripe:reconcile-checkouts` is a local read-only summary. Adding `--apply` retrieves/replays saved provider attempts, finalizes successes and processes pending effects. It does not charge, capture, cancel, or refund an intent. Replaying a lost create response can create an unconfirmed intent using the original idempotency key. The Laravel scheduler runs this command every minute without overlapping runs.

For `requires_capture`, support must inspect and capture or cancel in Stripe. For an abandoned attempt, support may cancel it in Stripe after checking its status. Reconciliation records only provider-confirmed cancellation and releases the active-account guard. There is no timeout-based release. Old unbound attempts need provider-side investigation using `checkout_attempt_id`; do not delete them to bypass the guard.

## Deployment and acceptance

1. Deploy and run only `php artisan migrate --path=database/migrations/2026_10_23_040000_create_stripe_checkout_attempts.php --force` from `core`. This migration has not previously been deployed. Do not enable checkout traffic against the new code before the migration is complete.
2. Restart queue workers gracefully with `php artisan queue:restart`.
3. Verify the existing Laravel scheduler/cron actually runs `schedule:run` every minute. Queue workers alone do not run scheduled commands. Avoid duplicate cron entries.
4. Run the preview command and inspect its counts; retain logs for failed reconciliations and review/claimed effects.
5. Before production rollout, use staging with Stripe test keys to validate immediate success, 3DS return, processing-to-success with browser closed, capture/cancel, repeated confirmations, and notification processing. Validate concurrent callbacks against MySQL/InnoDB, not just SQLite.

Automated tests use isolated SQLite and mocked provider responses; they do not charge cards, send messages, prove real provider delivery, or exercise MySQL row-lock timing. No live payment tests were performed. Rollback must preserve attempts/effects and APP_KEY; dropping the tables loses recovery evidence. Finish/reconcile in-flight new attempts before reverting their handling code.
