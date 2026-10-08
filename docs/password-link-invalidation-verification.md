# Recovery links after credential changes

The shared `AccountCredentials::changePassword` operation now deletes the account's outstanding password-broker token inside the same transaction as its password update, authentication-version increment, remember-token rotation and API-token revocation. Patient/doctor profile changes, administrator resets and email-token resets all use this operation.

An older recovery link cannot overwrite a password changed through another flow. New recovery requests can still create usable links after the change. Incorrect current-password checks make no changes to the outstanding link. Recovery-token deletion failure rolls back the password and all access revocation; a later administrator audit failure also restores the outstanding link along with credentials.

The email-token reset controller still deletes its consumed token through Laravel's broker after the callback; the additional shared-service deletion is idempotent on the current database token repository. The common transaction requires users, API tokens and recovery tokens to remain on the same database connection.

## Verification

- Six new test cases cover patient and doctor old-link replay, administrator reset invalidation without affecting the actor's link, rejected current-password checks, token-deletion rollback and fresh-link recovery after a password change.
- The existing administrator audit-failure test now also verifies that the original recovery token remains usable after rollback.
- Full backend suite: **338 tests, 1930 assertions passed** on PHP 8.5.1 / SQLite.
- Changed PHP formatting and Git whitespace checks passed. Frontend code is unchanged; its most recent suite remains **130 tests passed**, with build and lint passing in the user-management batch.
- All credential changes and token creation occurred in disposable automated test databases. No preview/external password or email was changed.

## Rollout and next step

No migration or API-payload change is required. Preserve the database-backed password broker on the application's default connection; switching to a cache or separate database requires revisiting atomicity. Rehearse concurrent password changes and recovery-link issuance on the production database engine. SQLite tests prove sequential replay and rollback, not production lock behavior or real mail delivery.

Next fix: invalidate outstanding recovery tokens when an email address changes, including the old address and any pre-existing token at the new address, so recovery links cannot follow an address reused by another account.
