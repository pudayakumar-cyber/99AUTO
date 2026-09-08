# Klaviyo Phase 3: Storefront Events

This phase adds Klaviyo's browser SDK to the shared storefront layout and routes
three existing ecommerce actions to Klaviyo without changing Meta or Google:

- `Viewed Product` on an in-stock product detail page
- `Added to Cart` after the cart endpoint successfully adds an item
- `Started Checkout` when the billing step loads

Known logged-in customers and guests with checkout details are identified using
the existing profile data. Identification does not grant marketing consent.
Email and SMS subscriptions continue to use the Phase 2 consent workflow.

## Local configuration

```dotenv
KLAVIYO_ENABLED=true
KLAVIYO_PUBLIC_KEY=your_public_company_id
```

The private API key is not used by browser tracking and is never rendered into
the page. Run `php artisan optimize:clear` after changing environment values.

## Local verification

1. Open a product detail page and confirm the Klaviyo onsite script loads once.
2. In Klaviyo's event activity, confirm one `Viewed Product` event with product
   ID, SKU, name, category, price, URL, and image URL.
3. Add the product to the cart and confirm one `Added to Cart` event only after
   the cart request succeeds.
4. Open checkout billing and confirm one `Started Checkout` event with cart
   value and item details.
5. Confirm Meta Pixel Helper and Google Tag Assistant still show their existing
   events. Klaviyo failures must not block any storefront action.

Klaviyo can associate anonymous browser activity with a profile after the
visitor is identified. This phase does not create flows, templates, discounts,
or order events; those remain in later roadmap phases.
