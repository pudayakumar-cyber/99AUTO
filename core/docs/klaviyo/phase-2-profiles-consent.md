# Klaviyo Phase 2: Profiles and Consent

This phase adds explicit, independently managed email and SMS marketing consent.
Checkout contact details and transactional order messages do not create marketing
consent.

## Deployment configuration

Add these values to the production environment before enabling Klaviyo:

```dotenv
KLAVIYO_ENABLED=false
KLAVIYO_PRIVATE_API_KEY=
KLAVIYO_EMAIL_LIST_ID=
KLAVIYO_SMS_LIST_ID=
KLAVIYO_QUEUE=default
```

The private API key needs profile, subscription, and list write scopes. Keep the
integration disabled until both channel list IDs and a queue worker are configured.
List-scoped withdrawal is intentional: it avoids an email preference change
globally suppressing unrelated channels or lists.

## Deployment commands

```bash
cd /var/www/html/core
php artisan migrate --force
php artisan optimize:clear
sudo supervisorctl restart laravel-worker:*
```

Then enable `KLAVIYO_ENABLED=true`, clear configuration again, and test one email
opt-in, one SMS opt-in, and one withdrawal from the account preferences page.

## Behavior

- Registration has separate, unchecked email and SMS options.
- Newsletter forms are explicit email opt-ins and support re-subscription.
- Account preferences independently grant or withdraw each channel.
- Every real state transition is stored in an append-only audit table.
- Queue jobs contain only a local consent ID and reload the latest state before
  contacting Klaviyo.
- Existing `subscribers` rows remain supported for legacy email preference display.
- Rollback drops only the two new consent tables; it does not modify users, orders,
  subscribers, or checkout data.
