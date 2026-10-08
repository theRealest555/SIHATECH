# Recovery links after email changes

Patient and doctor profile email changes now discard password-broker tokens for both the previous email and the destination email. This prevents a link issued for the previous owner from resetting credentials after an address is reused. The current-password and stale-profile checks run before token deletion; unchanged email addresses keep their existing recovery links.

Both token deletions run inside the existing locked profile transaction. A profile-save failure or token-deletion failure rolls back the email, verification state and token changes. Email verification notification remains after commit. No frontend, API-payload or migration change is required.

## Verification

- Six new cases cover patient and doctor address reuse, stale destination links, fresh recovery after an email change, unchanged addresses, incorrect current passwords, profile-save rollback and destination-token-deletion rollback.
- Focused email/password recovery suite: **21 tests, 139 assertions passed**.
- Full backend suite: **344 tests, 1971 assertions passed** on PHP 8.5.1 / SQLite.
- Credentials and recovery tokens were exercised only in disposable automated test databases. No preview or external account was changed and no real mail was sent.
- PHP formatting and Git whitespace checks passed. Frontend code is unchanged; its latest recorded suite has 130 passing tests.

## Rollout and next step

Keep users and the database-backed password broker on the same database connection for rollback to remain atomic. SQLite verifies sequential replay and rollback; it does not establish production concurrency behavior or mail delivery.

Next fix: serialize recovery-token issuance with email/password changes, so an overlapping recovery request cannot leave a token based on stale account details. Rehearse these races on the production database engine before release.
