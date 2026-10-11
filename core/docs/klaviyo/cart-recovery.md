# Cart recovery for email flows

## Website behavior

Successful AJAX cart additions and checkout billing visits now supply `CartRecoveryAvailable`, `CheckoutURL`, `Items`, `ItemNames` and full-cart `$value` to Klaviyo. Google and Meta retain their existing event payloads. The product-level Added to Cart fields still describe the item just added; `Items` describes the complete saved cart.

Snapshots contain product IDs, quantities and option IDs only. The database stores the SHA-256 token hash, never the bearer token. Links expire after seven days. A matching unchanged cart reuses its existing unexpired link. A failure to create a link does not block normal shopping; the event reports `CartRecoveryAvailable=false`.

GET displays a standalone, private confirmation page without analytics or external resources. Email link scanners cannot replace a cart by following a link. POST requires the visitor's session CSRF token, reloads active physical products and their selected options, checks aggregate stock, and builds a replacement cart using current prices. Authentication, contact and payment information are never restored from the snapshot. Old coupon, shipping and payment-session data are cleared before checkout. The customer reviews the cart before payment. The confirmation explicitly says an existing cart is replaced.

Unavailable lines are skipped with a notice. If every saved line is unavailable, the visitor's current cart is preserved. Repeated restores set the saved quantities, not add to them. Digital/license items and carts larger than 100 lines do not receive recovery links. Stock is checked again on each restore; the link does not reserve stock.

## Deployment

After review/merge and pulling main, deploy the new table before relying on recovery events:

```bash
cd /var/www/html/core
php artisan migrate --path=database/migrations/2026_09_15_000000_create_cart_recovery_links_table.php --force
php artisan optimize:clear
sudo supervisorctl restart laravel-worker:*
php artisan klaviyo:validate --email-only --full-catalog
```

Run `php artisan klaviyo:prune-cart-links` periodically to remove expired rows. Expiry is enforced independently of pruning. No automatic scheduler change is included.

## Klaviyo configuration still required

- Restrict new recovery flows to events where `CartRecoveryAvailable` is true. Older events lack the property and cannot reconstruct historical carts.
- Use `event.CheckoutURL` for the recovery CTA. Use `event.Items` for the dynamic table, with `item.ProductName`, `item.ImageURL`, `item.Price`, `item.Quantity` and `item.URL` inside the content repeat.
- The playbook cart delays are total elapsed 1h, 24h, 72h: use successive delays of 1h, 23h, 48h. Exclude Started Checkout and Placed Order after entry. Configure checkout recovery separately to avoid overlaps.
- Recovery remains an optional customer action; suppress remaining messages after purchase. A link can be reopened until expiry but does not charge, log in, or expose an order.
- Single-use coupons and their enforcement are separate unfinished work. Do not claim the third cart email's discount is ready merely because its recovery link works.

## QA

The isolated SQLite tests use the existing installed PDO SQLite extension for this process only:

```powershell
php -d extension=php_pdo_sqlite.dll vendor/bin/phpunit --do-not-cache-result --filter 'CartRecovery|Klaviyo|MarketingIdentity|MaintenanceSchedule|SendKlaviyo'
```

On Linux with PDO SQLite enabled, omit `-d extension=php_pdo_sqlite.dll`. Tests use an in-memory database and never contact Klaviyo or send emails.

Before activation, confirm a two-product cart creates one recovery URL and both Items in Klaviyo. Open that URL in a separate browser: GET must not change a cart; pressing Restore must use current prices and the correct options. Verify expired/deleted links, disabled products, insufficient stock, and repeat clicks. Test a normal purchase separately; this PR does not change checkout payment handling.

## Rollback

Revert the code commit and redeploy before removing the table. Existing links then stop working. If no retained links are needed, roll back only this migration; it does not touch orders, users or products. Never roll back unrelated migrations to remove this table.
