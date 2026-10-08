# Recovery-token issuance and credential changes

The forgot-password endpoint now locks the matching account inside a database transaction before asking Laravel's broker to create a token. The broker lookup includes the locked account ID and email. Email changes, password changes and token-based resets already use the same account lock, so token creation is ordered with their invalidation work.

The broker callback captures the notification recipient and token without sending mail inside the transaction. After the token commits, the controller sends the standard password-reset notification and dispatches the standard PasswordResetLinkSent event. Broker throttling still preserves the existing token. Unknown addresses, throttled requests and failures keep the same generic, no-store public response and sanitized warning behavior.

If token creation fails after replacing a prior token, the database transaction restores that prior token and no notification is sent. If mail fails after commit, the token remains subject to the existing expiry and broker throttle; the endpoint does not claim successful delivery. A password or email change during delivery invalidates the committed link. No durable mail retry or delivery guarantee is introduced.

## Verification

Five new cases cover notification/event ordering, token-write rollback, mail failure, password change during delivery and email reuse during delivery. Existing privacy and API recovery cases verify unchanged public responses, broker throttling and usable fresh links.

- Focused recovery suite: **16 tests, 84 assertions passed**.
- Full backend suite: **349 tests, 1997 assertions passed** on PHP 8.5.1 / SQLite.
- Changed PHP formatting and Git whitespace checks passed. The latest frontend suite remains 130 passing tests; no frontend code changed in this batch.

Tests use disposable SQLite databases and fake notifications. They exercise deterministic transaction boundaries and interleavings; SQLite does not prove simultaneous row-lock behavior on MySQL. No preview or external credentials were changed. Frontend code, migrations and request payloads are unchanged.

## Rollout and next step

Users and the database-backed password broker must share the default database connection. Rehearse simultaneous recovery issuance, password resets and email changes on the production database engine. Email delivery can arrive after a credential change with an already invalidated link; a fresh request remains the recovery path.

Next fix: recheck administrator authorization under lock for account-status and administrator-approval changes, including concurrent suspension/demotion of the acting administrator.
