# Klaviyo Phase 6: Lifecycle Data

Phase 6 prepares the profile properties required by the maintenance, segmentation,
referral, and trade-review flows in the approved playbook.

## Properties synchronized

- `primary_vehicle_year`, `primary_vehicle_make`, `primary_vehicle_model`
- `buyer_type` (`DIY` until a trade account is manually approved)
- `lifecycle_status` (`prospect`, `customer`, `repeat_customer`, or `lapsed`)
- `last_purchase_category`, `last_purchase_at`, `total_orders`
- `next_maintenance_due_date`
- `referral_code`, `referral_status`
- `trade_review_status`

## Maintenance intervals

- Oil, fluids, lubricants, grease, and coolant: 90 days
- Filters: 180 days
- Brakes, shocks, and struts: 365 days
- Other product categories: 180 days

The most recent confirmed order controls the current maintenance due date. Order
processing is idempotent because each source order can appear only once in the
lifecycle order ledger.

## Buyer and referral safeguards

- A second order or business-like company name moves `trade_review_status` to
  `pending`; it does not grant trade pricing or change `buyer_type` automatically.
- `buyer_type` changes to `Trade` only after `trade_review_status` is manually set
  to `approved` by a future trade-account workflow.
- A referral code is reserved for each profile, but redemption and rewards are not
  activated in this phase.
- Referral status becomes `eligible` only after a delivered order is recorded.

## Historical backfill

Preview the backfill:

```bash
php artisan klaviyo:sync-lifecycle --dry-run
```

Build lifecycle records and queue one final profile sync per identity:

```bash
php artisan klaviyo:sync-lifecycle
```

Limit the operation when troubleshooting:

```bash
php artisan klaviyo:sync-lifecycle --user=123 --chunk=100
```

Run the migration before the command. Keep `KLAVIYO_ENABLED=false` until the
Phase 7 test plan is ready; the data will still build locally while outbound jobs
remain disabled.
