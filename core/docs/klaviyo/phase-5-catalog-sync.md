# Klaviyo Phase 5: Catalog Sync

Phase 5 exposes a token-protected, read-only JSON product feed that Klaviyo can
poll as a custom catalog source. It does not change product data and does not
require the Klaviyo private API key.

## Server configuration

Generate a dedicated random token:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Add these values to `core/.env`:

```dotenv
KLAVIYO_CATALOG_FEED_TOKEN=generated_64_character_token
KLAVIYO_CATALOG_CURRENCY=CAD
```

Then clear cached configuration:

```bash
cd /var/www/html/core
php artisan optimize:clear
```

The protected feed URL appears in **Admin > Product Feed Exports** after the
token is configured. Keep the URL private because it contains the token.

## Klaviyo custom catalog mapping

The endpoint returns a top-level JSON array. Map these fields:

| Klaviyo value | Feed field |
| --- | --- |
| External ID / unique ID | `id` |
| Product name | `title` |
| Description | `description` |
| Product URL | `link` |
| Image URL | `image_link` |
| SKU | `sku` |
| Brand | `brand` |
| Categories | `categories` |
| Price | `price` |
| Compare-at price | `compare_at_price` |
| Currency | `currency` |
| Inventory | `inventory_quantity` |
| Inventory policy | `inventory_policy` |
| Published | `published` |

`id` is the application's item ID. It intentionally matches the
`ProductID` sent by Klaviyo storefront and order events, which enables product
recommendations and event-to-catalog attribution. Product URLs also include
`?item_id=` so products with duplicate historical slugs remain unique.

## Launch checks

1. Open the protected URL and confirm it returns a top-level JSON array.
2. Confirm an active product has the correct title, URL, image, CAD price, and stock.
3. Configure the URL as a custom catalog feed in Klaviyo and map the fields above.
4. Confirm Klaviyo reports a successful sync and the item count is reasonable.
5. Compare an event `ProductID` with the matching catalog `external_id`.
6. Keep `KLAVIYO_ENABLED=false` until the remaining integration phases pass QA.
