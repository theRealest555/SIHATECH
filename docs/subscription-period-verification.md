# Subscription period safety and audit

The legacy queued `CheckSubscriptionRenewals` class now runs a read-only local audit. It no longer expires records using a local date or sends renewal emails. Keeping the class allows existing queued payloads to execute safely after worker restart. It remains unscheduled. Paid-invoice reconciliation and verified provider events retain their existing authority over recorded subscription statuses and paid periods.

Subscription access uses a half-open interval: active status with `starts_at <= now < ends_at`. The model helper and active query scope now agree at the exact period-end boundary. The expired-period helper/scope include equality; they describe local time, not provider cancellation. The current-subscription API only labels access as expiring soon when the subscription actually grants access. Pending, future and ended periods no longer receive that label.

The retained period-notice template links to the real frontend `/my-subscription` route. It describes the recorded period and asks the user to check billing status. It no longer claims a renewal amount or asks for a new purchase based on the local end date. No automatic notice delivery was added.

## Read-only operator command

From the backend directory run:

```sh
php artisan subscriptions:audit-periods
php artisan subscriptions:audit-periods --json
```

The command returns aggregate counts only and exits 1 if any count is nonzero, otherwise 0. It does not contact Stripe, change records, grant/revoke access or send notifications.

| Count | Review needed |
| --- | --- |
| `ended_active_periods` | Active local status with a period ending at or before the audit time |
| `invalid_periods` | End time at or before start time, across all statuses |
| `active_without_completed_payment` | Active record with no associated completed local payment |

Counts can overlap. Queries run in one read transaction at the same captured time. Database isolation and concurrency behavior still require testing on the production engine.

An ended active period may reflect delayed provider events or genuine expiry; the audit cannot distinguish them. A completed historical payment does not prove entitlement for the current period, and a missing local payment may reflect historical data gaps. Compare subscription mappings, invoices, provider statuses and webhook history before resolving anomalies. Do not use this command to automate cancellation, expiry or a new charge.

The legacy job logs these aggregate counts at warning level when anomalies exist. Its repeated runs do not modify data or notify users. External log alerts or command execution must be configured through the hosting platform; no new schedule or permanent monitoring service was installed.

## Rollout and verification

No migration is required for this batch. Deploy the code and restart queue workers so old renewal payloads use the safe implementation. Earlier batches' migrations still apply, including the reminder ledger before enabling appointment reminders. Rehearse provider reconciliation using test-mode Stripe and production-like database/cookie settings.

Five new regression tests cover exact period-end access across model/scopes/API, future/pending periods, read-only anomaly counts, repeated legacy-job execution without notifications, renewed dates and the corrected template. Full backend suite: **273 tests, 1532 assertions passed** on PHP 8.5.1 / SQLite. PHP formatting and Git whitespace checks passed. No frontend files changed; previous 96 frontend tests, build and lint results remain applicable. No real provider requests, payments or emails were made.
