# Delivered-product maintenance events

Delivered Product emits one event per saved order line after a final order is marked Delivered. The existing lifecycle delivery timestamp anchors the event. Missing delivery records are skipped rather than using the current date. Existing Fulfilled Order events remain unchanged.

Properties include ProductID, ProductName, ProductURL when the saved slug exists, Quantity, ImageURL when available, DeliveredAt, MaintenanceCategory, MaintenanceIntervalDays and MaintenanceDueDate. Intervals use the existing name classifier: oil/fluids 90 days, filters 180, brakes/shocks 365, other 180. These are marketing check-in intervals, not mechanical service advice. Order and line IDs provide stable deduplication; later purchases have distinct IDs.

## Remaining Klaviyo configuration and acceptance

Keep maintenance flows Draft. Trigger on Delivered Product and split by MaintenanceIntervalDays (90/180/365). Use event ProductURL to review the purchased product; this is not automatic cart restoration or variant selection. Do not promise Subscribe & Save: recurring billing is not implemented.

Before activation, verify consent, refunded/canceled-order exits, repeat-purchase suppression for the same ProductID, send frequency across mixed orders, and current product availability. A later repurchase emits a new event but does not itself cancel an older Klaviyo flow entry. Configure and test that behavior before sending reminders. Avoid also running the legacy profile-date reminder, which can duplicate sends.

No migration or historical backfill. Deployment requires normal worker restart. Test a controlled delivered order after deployment; do not change real customer order statuses for QA. Validate event ingestion, profile identity and timing in Klaviyo before declaring end-to-end completion.
