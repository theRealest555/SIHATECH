# Attendance decisions and no-show safety

Elapsed time does not establish whether a patient attended. The legacy queued `MarkNoShowAppointments` class now performs a read-only attendance audit. It does not change statuses, recalculate ratings or send missed-visit notices. The class is retained for existing queue payloads and remains unscheduled.

## Human decision endpoint

The existing doctor/admin no-show endpoints now accept only a confirmed appointment whose start time is at or before the decision time. Unconfirmed requests, completed visits, cancellations and already recorded no-shows return HTTP 409; future confirmed visits return HTTP 400. The owning doctor or an authorized active administrator must make the decision; patient access and other-doctor access remain forbidden.

The endpoint locks doctor then appointment rows, matching the other appointment status writes. It rereads the current state under the lock, changes it to `no_show`, and creates an actor/target/transition audit row in the same transaction. Audit failure rolls back the decision. A repeated decision creates no second audit row. The subsequent UI batch aligns doctor controls and adds an administrator attendance filter and readable transition; see [attendance-ui-verification.md](attendance-ui-verification.md).

The optional request `reason` remains validated for compatibility but is no longer written into general logs. Audit metadata records only the transition; this batch does not create a clinical-note store or persist the free-text reason. The manual endpoint does not automatically email the patient. The retained notice template now links to the actual frontend doctor page, describes a recorded decision and omits the invented 24-hour cancellation deadline.

## Read-only operator audit

```sh
php artisan appointments:audit-attendance
php artisan appointments:audit-attendance --json
```

The audit reports aggregate counts of confirmed appointments needing review and unconfirmed past requests whose start times are more than 30 minutes ago. Exactly 30 minutes, future visits and final statuses are excluded. The threshold selects review candidates; it is not a no-show rule or a manual-decision grace period. No identities, attendance conclusions or automatic decisions are output.

Exit status is 1 when review candidates exist, otherwise 0. The legacy job logs only the same aggregate counts. Counts can require ordinary staff work rather than indicate a system failure. Configure any operational monitoring deliberately; no permanent service or new schedule was installed.

## Rollout and verification

No migration is required for this batch. Restart queue workers after deploying the code so existing legacy jobs use the read-only behavior. Do not reactivate an older scheduler/job implementation. Audit historical no-show records created by previous automatic processing separately; their true attendance cannot be inferred from timestamps and they were not rewritten here.

Seven new regression tests cover read-only repeated jobs, review-window boundaries, doctor decisions and one audit row, pending/final conflicts, future visits and role/ownership restrictions, rollback on audit failure, clean audit output and notice links. Existing doctor/admin endpoint tests also pass.

Full backend suite: **280 tests, 1570 assertions passed** on PHP 8.5.1 / SQLite. PHP formatting and Git whitespace checks passed. SQLite/sequential tests do not prove simultaneous locking on MySQL; rehearse competing completion/no-show decisions on the production engine. No real attendance records or mail were changed. No frontend files changed; previous 96 frontend tests, build and lint results remain applicable.
