# Email readiness audit - 99 Auto Parts

Audit date: 2026-09-15. Reference: 99AutoParts_Email_SMS_Playbook.pdf, sections 2, 4 and 5.

## Result

Not ready for full playbook activation. Integration prerequisites have been repaired and tested, but no Klaviyo flow status or delivered message was verified in this audit. No customer messages were sent, no production settings changed, and no discounts or flows activated.

Project: `D:\laragon\www\omini_org`, Laravel: `core`. Repository: `pudayakumar-cyber/99AUTO`. Branch starts from current `origin/main` (`e01a8eb`) and contains only email-readiness changes. The existing cache changes, screenshots and untracked migrations are excluded.

## Changes in this branch

- Use the same numeric registered-user external ID in browser tracking and backend events. Existing profiles with a `user_` prefix may need account-side reconciliation; this change does not merge historical profiles.
- Generate catalog and queued-order product URLs from the public storefront root even when `APP_URL` ends in `/core`. Local image URLs retain exactly one `/core/public/storage/images/` prefix. External image URLs are preserved and audited, not silently rewritten.
- Classify oil filters as Filters (180 days), rather than Oil / Fluids (90 days). Recognize plural oils/fluids/lubricants/coolants. Existing lifecycle dates are not changed automatically.
- Add `klaviyo:validate --email-only --full-catalog`: validates every generated catalog record, required fields, HTTPS URLs, duplicate IDs, JSON encoding and total feed bytes. Reports stock-based recommendation exclusions separately from invalid records. It does not download the public endpoint or confirm image accessibility.
- Clarify that a passing integration check does not verify API write scopes, worker health, catalog ingestion, flow configuration or inbox delivery.

## Verification results

- 25 focused PHPUnit tests, 62 assertions passed. Existing PHPUnit configuration deprecation remains.
- Full local database scan executed: 2,924 records, 2,232,780 JSON bytes. Local URLs failed HTTPS validation, as this checkout uses a local HTTP address. This is not evidence of a production feed failure.
- Local email list ID and catalog token are missing; local marketing consent tables are missing. Outbound Klaviyo delivery is disabled locally. Do not copy this local environment to production.
- Previous task recorded passing production prerequisites and a working newsletter subscription. Those historical results are not a current production check.
- Live read-only API requests with the local key returned HTTP 403. The Flows endpoint explicitly reported missing `flows:read`. Metrics and catalog reads also returned 403; their individual permission errors require verification. No key values were printed or committed.

## Playbook flow matrix

| Flow | Required configuration | Code/data status | Remaining launch work |
| --- | --- | --- | --- |
| Welcome (4) | Email list signup; immediate, day 2, day 4, day 7; exit after cart/checkout/purchase | Explicit newsletter and registration email consent paths exist | Verify list/opt-in, build or inspect all four messages, first-order coupon restrictions/expiry, actual DIY/Trade links and SMS signup |
| Cart (3) | Added to Cart; total elapsed 1h, 24h, 72h; exit after checkout/purchase | Browser event carries Items, image/price and CheckoutURL | Configure delays as 1h +23h +48h, dynamic event item block, real single-use coupon and production verification of the new expiring cart recovery links (see cart-recovery.md) |
| Browse (1) | Same product viewed 2+ times in 24h; 2h after last view; no cart/purchase | Viewed Product is emitted | Verify same-product counting/last-view timing is supported by chosen configuration; related products must match vehicle; no discount |
| Post-purchase (3) | Delivered +3d installation help, +14d review; category due date reminder | Fulfilled Order means admin marked Delivered; maintenance profile date exists | Configure +3d then +11d; separate date-property reminder, category help/review URLs, exact-SKU reorder; no working recurring subscription established |
| Referral (2) | Verified delivery or positive review; reminder +14d if unused | Code and eligibility reserved only | Implement tracked referral link, first-order $15 reward, referrer $15 reward, single-use redemption and unused-state tracking; positive review event is not established |
| Win-back (3) | Last purchase +60d, +90d, +120d; stop after new purchase | last_purchase_at and order metrics exist | Configure date/event timing and purchase exclusions, 15% coupon with 7-day validity, re-permission/sunset handling; do not rely on stale lifecycle_status alone |
| Trade/VIP (1) | Second qualifying order or manual flag | Pending review suggestion exists; no automatic trade pricing | Application form, manual approval, actual pricing/contact/terms; consumer repeat orders need review before commercial offers |

Event-supplied cart content can render without catalog lookup. The catalog is required for catalog blocks/recommendations, not every email. Cart contents must not be replaced with generic catalog best sellers.

## Other email gaps to resolve before their dependent flows

- Shipped Order contains TrackingNumber but no verified carrier/tracking URL/ETA contract. No carrier-driven Out for Delivery event was found. Existing website tracking/status emails can duplicate new Klaviyo messages.
- Vehicle update endpoint exists, but capture and segmentation links must be exercised end-to-end. Matching recommendations to fitment is not established by merely importing products.
- Maintenance currently stores one date/category per profile from the newest order. Multiple-category maintenance and exact-SKU reorder need explicit implementation/verification. Historical backfill can rewrite delivered_at; do not schedule repeated whole-history backfills as a win-back timer.
- Referral rewards, first-order coupon enforcement, single-use cart discounts, review incentives and Subscribe & Save are not proven by the presence of profile fields or email copy. Keep dependent messages in Draft until the corresponding checkout behavior is verified.
- Use real testimonials and verified policy/offer claims. Account creation alone does not imply marketing consent.

## Deployment and production verification

After review/merge and pulling main on the VPS, run:

```bash
cd /var/www/html/core
php artisan optimize:clear
sudo supervisorctl restart laravel-worker:*
php artisan klaviyo:validate --email-only --full-catalog
php artisan queue:failed
```

The cart recovery follow-up adds one migration; see cart-recovery.md for the targeted deployment command. Do not run missing local migrations against production blindly. Before recalculating existing maintenance dates, review `klaviyo:sync-lifecycle --dry-run` and keep dependent flows controlled: backfill queues profile updates and may affect date-triggered flows.

Full-catalog validation reads the database and serializes records; it does not test public HTTP retrieval. On the VPS, test the generated protected feed URL privately for HTTP 200, complete valid JSON, download time and size. Keep the token out of screenshots and reports. Confirm the actual imported item count in Klaviyo.

For custom catalog finalization, ask Klaviyo Support to inspect ingestion and configure `Ordered Product` and `Viewed Product` with `ProductID` matching catalog `id` mapped to `$id`. Mapping-valid status does not prove import or recommendations are ready.

## Controlled account checks

Use a test profile restricted to test flows, with shortened delays in test copies only. Preview actual event data, then check event arrival, flow entry, message activity, delivery and links. A preview send alone does not prove triggering or exclusions. Do not send to customers during QA.

1. Confirm email opt-in and unsubscribe behavior, including double opt-in if configured.
2. Verify one Viewed Product, Added to Cart and Started Checkout against the same profile.
3. Verify the four Welcome messages and exit after cart/checkout/purchase.
4. Verify the three cart messages, cross-session cart link, and exit after purchase.
5. Verify one browse message only under the playbook's repeat-view condition.
6. Exercise an authorized test order through shipping and delivered; verify installation/review timing and maintenance date.
7. Activate later referral/win-back/trade messages only after their outstanding website and offer dependencies are tested.

To inspect account state, use an authenticated Klaviyo session or a locally stored key with appropriate read access. Do not paste private keys into chat. This audit could not inspect or create account-side flows with the available permissions.

## References

- https://developers.klaviyo.com/en/docs/guide_to_syncing_a_custom_catalog_feed_to_klaviyo
- https://help.klaviyo.com/hc/en-us/articles/115002775132
- https://help.klaviyo.com/hc/en-us/articles/360003165732

## Rollback

Revert this branch's commit, deploy, clear configuration/views and restart the queue worker. The readiness fixes need no schema rollback; the cart recovery follow-up has a separate targeted migration rollback described in cart-recovery.md. Already delivered messages or already synchronized profiles cannot be undone by a code rollback.

## Cart recovery follow-up

Implemented expiring, token-protected cart recovery with explicit confirmation and live price/stock checks. The new `cart_recovery_links` table is required. This is website-side readiness, not proof that the corresponding Klaviyo flows are created or activated. See [cart-recovery.md](cart-recovery.md).
