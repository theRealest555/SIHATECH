# Appointment reminders

The registered `appointments:send-reminders` command scans every five minutes when `APPOINTMENT_REMINDERS_ENABLED=true`. It creates one durable reminder per appointment ID and scheduled time for confirmed visits more than 30 minutes and at most 24 hours away. Pending requests and cancelled, completed or missed visits are excluded. This includes late confirmations inside the window and catches up after a missed scheduler tick while the appointment is still eligible.

A new `appointment_reminders` table has a unique appointment/time constraint, delivery-state indexes and an appointment foreign key. The migration also adds an appointment status/time index for scans. The scan uses appointment locks and bounded chunks. Pending rows survive queue dispatch failures and are dispatched again on the next scan; duplicate jobs cannot claim the same reminder twice.

The worker rechecks the current appointment status, scheduled time and eligibility window, the patient's active/verified account and the doctor's active account. Changed or ineligible records are marked `skipped`. A changed scheduled time can create a new reminder; returning to a previously recorded time does not create another one.

The database notification and the mail claim commit together under the appointment/ledger locks. The actual mail attempt happens after commit using Laravel's [immediate notification channel delivery](https://laravel.com/framework/docs/12.x/notifications#using-the-notification-facade), inside the already queued reminder job. The template now links to the frontend's real `/patient/appointments` page and displays the configured application timezone.

## Delivery guarantees and investigation

The ledger states are:

| State | Meaning |
| --- | --- |
| `pending` | Available for a worker to claim; no mail attempt recorded |
| `sending` | Durable database notification committed and mail attempt claimed |
| `sent` | Mail channel returned successfully; not proof of inbox delivery |
| `failed` | An exception occurred during mail or recording its outcome; acceptance may be uncertain |
| `skipped` | Appointment/account was ineligible when the worker checked it |

Retries before claim can recover transaction failures. Once claimed, retries do not repeat the email attempt or database notification. A crash after claim can leave `sending` indefinitely and can result in an unsent email. A transport exception may happen after a provider accepted the message. These are deliberately preserved for investigation instead of automatically risking duplicates. Provider delivery is not exactly once, and this change cannot guarantee inbox receipt.

Monitor counts of `failed` rows and `sending` rows whose `attempted_at` is older than the worker timeout plus a suitable operational margin (for example ten minutes). They require checking application logs and the mail provider before any manual resend. Queue retries may subsequently return successfully after detecting an already claimed ledger row, so `queue:failed` alone is insufficient monitoring. Do not reset ledger states blindly. Cancelling or changing the appointment after the final eligibility check can still race with external mail delivery; no database lock is held across SMTP.

Database notifications are stored using the existing notification table. This batch does not add a notification inbox UI. Skipped account reminders are not automatically reopened if the account later becomes eligible.

## Rollout

1. Back up and rehearse migration `2026_10_07_000005_appointment_reminders.php` on staging. Apply it before enabling reminders or dispatching their jobs. Existing reminder data is not backfilled; currently eligible confirmed visits are scanned after enablement.
2. Provision the scheduler, persistent default queue and supervised worker described in [operations-verification.md](operations-verification.md). Verify `FRONTEND_URL`, `APP_TIMEZONE`, mail credentials, sender identity and delivery using preview patients and a staging mailbox.
3. Set `APPOINTMENT_REMINDERS_ENABLED=true` in staging, refresh cached configuration and restart workers. Verify both notification persistence and actual mail receipt. Then rehearse duplicate jobs, cancellation, a stopped worker, recovery and failure monitoring.
4. Disable the feature to stop new scans and worker deliveries. Already claimed mail can finish. Rolling back the migration deletes reminder/deduplication history; prevent reminder execution before rollback, and do not re-enable after rebuilding the table without assessing duplicate delivery risk.

The flag defaults to false. No real mail was sent, no existing deployment was migrated, and no appointment status was changed by this batch. No-show and provider-renewal jobs remain unscheduled.

## Local verification

Seven regression tests cover disabled scans/delivery, eligibility windows and statuses, repeated scans/jobs, cancellation/rescheduling and inactive/unverified accounts, uncertain mail failures, previously claimed attempts and the frontend template link. Tests use the actual database notification channel and a controlled mail channel; they do not prove SMTP delivery or concurrent MySQL locking.

Full backend suite: **268 tests / 1499 assertions passed**. PHP formatting and Git whitespace checks passed. `schedule:list` lists the minute operations heartbeat and five-minute reminder command; the reminder's runtime feature condition remains disabled by default. No frontend files changed; previous 96 frontend tests, build and lint results remain applicable.
