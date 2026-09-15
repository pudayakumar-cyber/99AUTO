# Email coupon readiness

The website now caps fixed discounts at the cart subtotal and percentage discounts at 100%. Negative or malformed legacy discount values cannot increase the total. Admin creation/editing requires a recognized discount type, nonnegative discount, percentage at most 100, and a nonnegative integer usage count.

Validation: 43 focused tests / 151 assertions passed, including oversized fixed discounts, malformed legacy data, zero/negative subtotals, decimal percentages and actual admin validation rules. One existing PHPUnit XML deprecation remains. No migration is needed for these validation changes.

## Remaining before playbook discount emails can go live

- Welcome: enforce first-order eligibility and the agreed expiry of WELCOME10.
- Cart: generate and assign unique single-use 10% codes for the final recovery message.
- Win-back: issue a 15% code with seven-day validity.
- Referral: implement eligibility and tracked $15 rewards for both parties.
- Enforce redemption once across payment methods and repeated callbacks. Current payment handlers decrement counts separately; admin order status handling can decrement an already-paid order again. A usage count of one is not sufficient evidence of safe single-use enforcement under concurrent checkout.
- Add expiry/eligibility revalidation before payment; the current coupon model contains no expiry or recipient fields.

These changes fix calculation and input validation only. They do not activate flows, create coupons, alter live orders, or establish single-use redemption. Complete redemption integration and payment regression tests before sending discount-dependent emails.

Rollback: revert the coupon validation commit and redeploy; there are no schema changes in this follow-up.
