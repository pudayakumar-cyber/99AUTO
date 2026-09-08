# Klaviyo Phase 7: Production Validation

Phase 7 validates production readiness before outbound Klaviyo delivery is
enabled. The readiness command does not create profiles, subscribe contacts,
send events, or send messages. Its optional API request is read-only.

## Required configuration

```dotenv
KLAVIYO_ENABLED=false
KLAVIYO_PUBLIC_KEY=
KLAVIYO_PRIVATE_API_KEY=
KLAVIYO_EMAIL_LIST_ID=
KLAVIYO_SMS_LIST_ID=
KLAVIYO_CATALOG_FEED_TOKEN=
KLAVIYO_QUEUE=default
```

The private key requires Events, Profiles, and Subscriptions write access and
Profiles and Lists read access. Use separate Klaviyo email and SMS consent lists.

## Readiness check

Run on production while Klaviyo remains disabled:

```bash
php artisan klaviyo:validate
```

Use `--skip-api` only for local/offline diagnostics. Do not enable outbound
delivery until every required check reports `PASS` and the API check succeeds.

## Controlled activation

1. Create dedicated email and SMS consent lists in Klaviyo and configure IDs.
2. Configure a random catalog feed token of at least 32 characters.
3. Run `php artisan klaviyo:validate` with `KLAVIYO_ENABLED=false`.
4. Use one internal test email address and one authorized test phone number.
5. Set `KLAVIYO_ENABLED=true`, clear config, and restart the queue worker.
6. Test email opt-in, email withdrawal, SMS opt-in, and SMS withdrawal.
7. Verify Viewed Product, Added to Cart, Started Checkout, and Placed Order once
   each in Klaviyo. Confirm unique IDs prevent duplicate order events.
8. Verify the catalog feed returns active products with valid URLs and images.
9. Check failed jobs and Laravel logs before enabling Klaviyo flows.

## Production commands

```bash
php artisan optimize:clear
sudo supervisorctl restart laravel-worker:*
php artisan klaviyo:validate
php artisan queue:failed
```

## Emergency stop

Set `KLAVIYO_ENABLED=false`, then run:

```bash
php artisan optimize:clear
sudo supervisorctl restart laravel-worker:*
```

Disabling the integration stops outbound Klaviyo jobs without affecting normal
storefront, checkout, order, or existing Laravel email behavior.
