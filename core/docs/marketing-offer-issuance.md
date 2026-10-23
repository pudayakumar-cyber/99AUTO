# Controlled marketing offer issuance

This command creates customer-bound offers without subscribing anyone, contacting Klaviyo or sending messages. It is a foundation for flow automation, not an activated flow integration.

Prerequisites: migrations `2026_10_23_010000_add_marketing_rules_to_promo_codes.php` and `2026_10_23_020000_add_offer_key_to_promo_codes.php` must be applied to the intended site.

From that site's `core` directory, inspect the interface with `php artisan marketing:issue-offer --help`. Parameters:

- `user`: existing registered customer ID.
- `offer`: `welcome` (10%, first-order only), `cart` (10%) or `winback` (15%).
- `--reference`: stable signup/cart/campaign identifier. Reuse the same reference for retries. A different reference creates a different offer.
- `--expires-at`: exact expiry, for example ISO format `YYYY-MM-DDTHH:MM:SS+00:00`. Use the approved campaign deadline; do not invent or reset it on retry.
- `--issue`: opt-in to database writes. Without this flag only the argument format is checked; customer eligibility is NOT checked.

Each issued code is random, bound to the account email, and has one remaining use. The shopper must sign in. A retry returns the original code and does not extend its expiry, replenish uses or reactivate it. The unique issuance key is not editable through the normal coupon form. Welcome codes are personalized, not the shared literal WELCOME10; future template integration must render the actual issued code.

New win-back codes must expire within seven days of issuance. Longer validity is rejected rather than silently shortened; shorter campaign deadlines are permitted. Retries still return the original code unchanged, including expired or redeemed codes. Existing codes are not retroactively edited. Format-only command checks do not verify this business rule; issuance does. Email delivery delays do not extend the deadline, so templates must show the actual expiry rather than promising seven days from receipt.

Do not enable discount campaigns yet. One remaining use is not an atomic reservation: simultaneous payment attempts can still pass eligibility before an order consumes the code. Reservation handling and Klaviyo issuance/delivery integration remain separate work. Do not reuse a reference after a recipient email change; investigate the existing offer rather than granting another automatically.

Deployment creates no offers. Test with controlled accounts on staging. Retain the issuance-key column on rollback if any offers exist, otherwise retries may mint replacements.
