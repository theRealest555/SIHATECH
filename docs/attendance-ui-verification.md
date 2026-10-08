# Doctor attendance controls and administrator history

The doctor appointment screen now offers Mark no-show only on confirmed past visits, matching the hardened API. Unconfirmed past requests explain that they cannot be recorded as missed visits. Confirmed future visits and final statuses have no no-show action.

The confirmation asks the doctor to verify that the patient did not attend and explains that the decision enters audit history. Keep unchanged cancels without a request. Successful decisions reload the current appointment list; conflicts and uncertain responses retain an error and reload server state through the existing reconciliation behavior.

Administrator audit history now includes an attendance action filter, labels appointment targets and displays the known Confirmed → No-show transition. The API projects that transition only for the exact known action, appointment target and expected status pair; arbitrary legacy metadata stays hidden. Page wording covers administrative and attendance actions, since doctors also record decisions.

## Verification

- Full backend suite: **281 tests / 1574 assertions passed** on PHP 8.5.1 / SQLite, including a new audit-projection test.
- Full frontend suite: **99 tests passed**. Three new interactions cover eligible controls and confirmation cancellation, a competing attendance decision with reload, and the administrator filter/transition display.
- Production frontend build, full lint with zero warnings/errors, PHP formatting and Git whitespace checks passed.
- Real local browser, actual API and isolated preview database: doctor login; Past filter; no no-show action on unconfirmed fixture appointment #8; explicit confirmation cancellation left confirmed fixture #7 unchanged; a second confirmation recorded #7 as no-show and removed its action. Administrator login and attendance filter then showed exactly one matching audit entry with the doctor actor, appointment target and transition.
- Proof screenshots are in the workspace parent: `attendance-doctor-verified.png` and `attendance-audit-verified.png`. Only isolated fixture appointments changed. No real email or provider action occurred.

No migration is added in this batch. Deploy API and frontend together for the new audit projection, while retaining earlier migrations. Browser conflicts were covered by UI/backend tests; the real browser journey verified successful delivery and audit persistence. Production-engine simultaneous completion/no-show locking still needs staging verification. No administrator attendance editor or free-text clinical-note storage was introduced.
